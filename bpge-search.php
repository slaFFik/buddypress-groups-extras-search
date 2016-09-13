<?php
/*
Plugin Name: BuddyPress Groups Extras - Search
Plugin URI: https://github.com/slaFFik/BP-Groups-Extras-Search
Description: Now custom content created by BPGE is searchable.
Version: 1.3
Author: slaFFik
Author URI: https://ovirium.com/
*/

if ( ! defined( 'BPGE_PRO' ) ) {
	define( 'BPGE_PRO', true );
}

define( 'BPGE_PRO_SEARCH', true );
define( 'BPGE_PRO_SEARCH_VER', '1.3' );

/**
 * Options for admin area.
 *
 * @param array $tabs
 *
 * @return array
 */
function bpges_admin_init( $tabs ) {
	$tabs[] = include( dirname( __FILE__ ) . '/bpge-search-admin.php' );

	return $tabs;
}

add_filter( 'bpge_admin_tabs', 'bpges_admin_init', 999 );

/**
 * Intrude into get_groups SQL.
 */
function bpges_init() {
	add_filter( 'bp_groups_get_paged_groups_sql', 'bpges_search_add_paged', 1, 3 );
	add_filter( 'bp_groups_get_total_groups_sql', 'bpges_search_add_total', 1, 3 );
}

add_action( 'plugins_loaded', 'bpges_init', 999 );

/**
 * In groups lists that are search results display where we found data.
 */
function bpges_display_extra_results() {
	$cur_group_id = bp_get_group_id();
	$group_link   = trim( bp_get_group_permalink(), '/' );
	$results      = bpges_search_get_groups();
	$display      = '';

	// Prepare pages
	if ( ! empty( $results['map']['pages'] ) ) {

		foreach ( $results['map']['pages'] as $group_id => $data ) {
			// display for the current group only
			if ( $group_id != $cur_group_id ) {
				continue;
			}
			foreach ( $data as $d ) {
				$link    = $group_link . '/' . BPGE_GPAGES . '/' . $d->post_name . '/';
				$pages[] = '<a href="' . $link . '" target="_blank">' . stripslashes( $d->post_title ) . '</a>';
			}
		}
		if ( ! empty( $pages ) ) {
			$display .= __( 'Found in Group Pages:', 'buddypress-groups-extras' ) . '&nbsp' . implode( ', ', $pages );
		}
	}

	if ( ! empty( $display ) ) {
		$display .= '<br/>';
	}

	// Prepare fields
	if ( ! empty( $results['map']['fields'] ) ) {
		foreach ( $results['map']['fields'] as $group_id => $data ) {
			// display for the current group only
			if ( $group_id != $cur_group_id ) {
				continue;
			}
			foreach ( $data as $d ) {
				$fields[] = '<a href="' . $group_link . '/extras/" target="_blank">' . stripslashes( $d->post_title ) . '</a>';
			}
		}
		if ( ! empty( $fields ) ) {
			$display .= __( 'Found in Group Fields:', 'buddypress-groups-extras' ) . '&nbsp' . implode( ', ', $fields );
		}
	}

	if ( ! empty( $display ) ) {
		echo '<div class="item-desc bpges-results">';
		echo $display;
		echo '</div>';
	}
}

add_action( 'bp_directory_groups_item', 'bpges_display_extra_results' );

/**
 * Search in posts and fields for group IDs.
 *
 * @param null|string $search_terms
 *
 * @return mixed
 */
function bpges_search_get_groups( $search_terms = null ) {
	/** @var $wpdb WPDB */
	global $wpdb, $bpge;
	static $cached;

	// Get the search terms
	if ( null === $search_terms ) {
		$query_arg = bp_core_get_component_search_query_arg( 'groups' );
		if ( ! empty( $_REQUEST['search_terms'] ) ) {
			$search_terms = wp_strip_all_tags( trim( wp_unslash( $_REQUEST['search_terms'] ) ) );
		} elseif ( ! empty( $_REQUEST['s'] ) ) {
			$search_terms = wp_strip_all_tags( trim( wp_unslash( $_REQUEST['s'] ) ) );
		} elseif ( ! empty( $_REQUEST[ $query_arg ] ) ) {
			$search_terms = wp_strip_all_tags( trim( wp_unslash( $_REQUEST[ $query_arg ] ) ) );
		} else {
			return false;
		}
	}

	// Don't query for the same results twice.
	if ( isset( $cached[ $search_terms ] ) ) {
		return $cached[ $search_terms ];
	}

	$search_terms_sql = '%' . $wpdb->esc_like( $search_terms ) . '%';

	$pages            = $fields = array();
	$pages_groups_ids = $fields_groups_ids = array();
	$pages_map        = $fields_map = array();

	// Get group_ids from gpages that are relevant to this search
	if ( isset( $bpge['search_pages'] ) && $bpge['search_pages'] === 'on' ) {
		$type  = BPGE_GPAGES;
		$pages = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value AS group_id, p.post_name, p.post_title
	        FROM {$wpdb->postmeta} AS pm
	        LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
	        WHERE pm.meta_key = 'group_id'
	          AND p.post_status = 'publish'
	          AND p.post_type = '%s'
	          AND p.post_parent > 0
	          AND (p.post_title LIKE %s
	                OR p.post_content LIKE %s
	              )", $type, $search_terms_sql, $search_terms_sql ) );
	}
	foreach ( $pages as $data ) {
		$pages_groups_ids[]             = $data->group_id;
		$pages_map[ $data->group_id ][] = $data;
	}

	// Get groups_ids from fields that are relevant to this search
	if ( isset( $bpge['search_fields'] ) && $bpge['search_fields'] === 'on' ) {
		$type   = BPGE_GFIELDS;
		$fields = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT (post_parent) AS group_id, post_name, post_title
			FROM wp_posts
			WHERE post_type = '%s'
			  AND post_status = 'publish'
			  AND post_parent > 0
			  AND post_content LIKE %s;", $type, $search_terms_sql ) );
	}
	foreach ( $fields as $data ) {
		$fields_groups_ids[]             = $data->group_id;
		$fields_map[ $data->group_id ][] = $data;
	}

	// Merge groups from 2 types of searches
	$results['groups_ids']    = array_merge( $pages_groups_ids, $fields_groups_ids );
	$results['map']['pages']  = $pages_map;
	$results['map']['fields'] = $fields_map;

	$cached[ $search_terms ] = $results;

	return $results;
}

/**
 * Modify pages search results.
 *
 * @param string $sql_str
 * @param array $sql_arr
 * @param array $query_args
 *
 * @return string
 */
function bpges_search_add_paged( $sql_str, $sql_arr, $query_args ) {
	if ( empty( $query_args['search_terms'] ) ) {
		return $sql_str;
	}

	// Get all groups that have pages/fields that are good for this search
	$results = bpges_search_get_groups( $query_args['search_terms'] );

	if ( ! empty( $results['groups_ids'] ) ) {
		$include = 'g.ID IN (' . implode( ',', $results['groups_ids'] ) . ')';

		// Modify the query to get search working with groups pages
		$sql_arr['search'] = str_replace( 'g.name LIKE', $include . ' OR g.name LIKE', $sql_arr['search'] );
	}

	return implode( ' ', (array) $sql_arr );
}

/**
 * Modify total search results (for pagination and counters).
 *
 * @param string $sql_str
 * @param array $sql_arr
 * @param array $query_args
 *
 * @return mixed
 */
function bpges_search_add_total( $sql_str, $sql_arr, $query_args ) {
	if ( empty( $query_args['search_terms'] ) ) {
		return $sql_str;
	}

	// Get all groups that have pages/fields that are good for this search
	$results = bpges_search_get_groups( $query_args['search_terms'] );

	if ( ! empty( $results['groups_ids'] ) ) {
		$include = 'g.ID IN (' . implode( ',', $results['groups_ids'] ) . ')';

		// insert it into search
		$sql_str = str_replace( 'g.name LIKE', $include . ' OR g.name LIKE', $sql_str );
	}

	return $sql_str;
}
