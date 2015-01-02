<?php
/*
Plugin Name: BuddyPress Groups Extras Pro - Search
Plugin URI: http://ovirium.com/downloads/bp-groups-extras-pro-search/
Description: Now custom content created by BPGE is searchable
Version: 1.3
Author: slaFFik
Author URI: http://ovirium.com/
*/

if ( ! defined( 'BPGE_PRO' ) ) {
	define( 'BPGE_PRO', true );
}

define( 'BPGE_PRO_SEARCH', true );
define( 'BPGE_PRO_SEARCH_VER', '1.3' );

/**
 * Options for admin area
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
 * Remove cron
 */
function bpges_remove_cron() {
	if ( wp_next_scheduled( 'bpge_pro_check_versions_event' ) ) {
		wp_clear_scheduled_hook( 'bpge_pro_check_versions_event' );
	}
}

add_action( 'admin_init', 'bpges_remove_cron' );

/**
 * Intrude into get_groups sql
 */
function bpges_init() {
	add_filter( 'bp_groups_get_paged_groups_sql', 'bpges_search_add_paged', 1, 2 );
	add_filter( 'bp_groups_get_total_groups_sql', 'bpges_search_add_total', 1, 2 );
}

add_action( 'plugins_loaded', 'bpges_init', 999 );

/**
 * In groups lists that are search results display where we found data
 */
function bpges_display_extra_results() {
	$cur_group_id = bp_get_group_id();
	$group_link   = trim( bp_get_group_permalink(), '/' );
	$results      = bpges_search_get_groups();
	$display      = '';

	// prepare pages
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
			$display .= __( 'Found in Group Pages:', 'bpge' ) . '&nbsp' . implode( ', ', $pages );
		}
	}

	if ( ! empty( $display ) ) {
		$display .= '<br/>';
	}

	// prepare fields
	if ( ! empty( $results['map']['fields'] ) ) {
		foreach ( $results['map']['fields'] as $group_id => $data ) {
			// display for the current group only
			if ( $group_id != $cur_group_id ) {
				continue;
			}
			foreach ( $data as $d ) {
				$link     = $group_link . '/extras/';
				$fields[] = '<a href="' . $link . '" target="_blank">' . stripslashes( $d->post_title ) . '</a>';
			}
		}
		if ( ! empty( $fields ) ) {
			$display .= __( 'Found in Group Fields:', 'bpge' ) . '&nbsp' . implode( ', ', $fields );
		}
	}

	if ( ! empty( $display ) ) {
		echo '<div class="item-desc">';
		echo $display;
		echo '</div>';
	}
}

add_action( 'bp_directory_groups_item', 'bpges_display_extra_results' );

/**
 * Search in posts and fields for group IDs
 */
function bpges_search_get_groups() {
	/** @var $wpdb WPDB */
	global $wpdb, $bpge;

	// get the search terms
	if ( isset( $_REQUEST['search_terms'] ) && ! empty( $_REQUEST['search_terms'] ) ) {
		$search_terms = strip_tags( trim( $_REQUEST['search_terms'] ) );
		// ajax
		$search_terms = esc_sql( $wpdb->esc_like( $search_terms ) );
	} elseif ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
		$search_terms = strip_tags( trim( $_REQUEST['s'] ) );
		// url-based
		$search_terms = esc_sql( $wpdb->esc_like( $search_terms ) );
	} else {
		return false;
	}

	$pages            = $fields = array();
	$pages_groups_ids = $fields_groups_ids = array();
	$pages_map        = $fields_map = array();

	// get group_ids from gpages that are relevant to this search
	if ( isset( $bpge['search_pages'] ) && $bpge['search_pages'] == 'on' ) {
		$type  = BPGE_GPAGES;
		$pages = $wpdb->get_results( $wpdb->prepare(
			"SELECT
                            pm.meta_value AS group_id, p.post_name, p.post_title
                        FROM {$wpdb->postmeta} AS pm
                        LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
                        WHERE pm.meta_key = 'group_id'
                          AND p.post_status = 'publish'
                          AND p.post_type = '%s'
                          AND p.post_parent > 0
                          AND (p.post_title LIKE '%%%s%%'
                                OR p.post_content COLLATE UTF8_GENERAL_CI LIKE '%%%s%%'
                              )", $type, $search_terms, $search_terms ) );
	}
	foreach ( $pages as $data ) {
		$pages_groups_ids[]             = $data->group_id;
		$pages_map[ $data->group_id ][] = $data;
	}

	// get groups_ids from fields that are relevant to this search
	if ( isset( $bpge['search_fields'] ) && $bpge['search_fields'] == 'on' ) {
		$type   = BPGE_GFIELDS;
		$fields = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT (post_parent) AS group_id, post_name, post_title
            FROM wp_posts
            WHERE post_type = '%s'
              AND post_status = 'publish'
              AND post_parent > 0
              AND post_content COLLATE UTF8_GENERAL_CI LIKE '%%%s%%';", $type, $search_terms ) );
	}
	foreach ( $fields as $data ) {
		$fields_groups_ids[]             = $data->group_id;
		$fields_map[ $data->group_id ][] = $data;
	}

	// merge groups from 2 types of searches
	$results['groups_ids']    = array_merge( $pages_groups_ids, $fields_groups_ids );
	$results['map']['pages']  = $pages_map;
	$results['map']['fields'] = $fields_map;

	return $results;
}

/**
 * Modify pages search results
 *
 * @param $sql_str
 * @param $sql_arr
 *
 * @return string
 */
function bpges_search_add_paged( $sql_str, $sql_arr ) {
	if ( ! isset( $sql_arr['search'] ) ) {
		return $sql_str;
	}

	// get all groups that have pages/fiels that are good for this search
	$results    = bpges_search_get_groups();
	$groups_ids = $results['groups_ids'];

	if ( ! empty( $groups_ids ) ) {
		$include = 'g.ID IN (' . implode( ',', $groups_ids ) . ')';

		// modify the query to get search working with groups pages
		$sql_arr['search'] = str_replace( 'g.name LIKE', $include . ' OR g.name LIKE', $sql_arr['search'] );
	}

	return join( ' ', (array) $sql_arr );
}

/**
 * Modify total search results (for pagination and counters)
 *
 * @param string $sql_str
 * @param string $sql_arr
 *
 * @return mixed
 */
function bpges_search_add_total(
	$sql_str,
	/** @noinspection PhpUnusedParameterInspection */
	$sql_arr
) {
	// check that we are in a search
	$pos = strpos( $sql_str, 'g.name LIKE' );
	if ( $pos === false ) {
		return $sql_str;
	}

	// get all groups that have pages/fiels that are good for this search
	$results    = bpges_search_get_groups();
	$groups_ids = $results['groups_ids'];

	if ( ! empty( $groups_ids ) ) {
		$include = 'g.ID IN (' . implode( ',', $groups_ids ) . ')';

		// insert it into search
		$sql_str = str_replace( 'g.name LIKE', $include . ' OR g.name LIKE', $sql_str );
	}

	return $sql_str;
}