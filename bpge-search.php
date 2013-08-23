<?php
/*
Plugin Name: BuddyPress Groups Extras Pro - Search
Plugin URI: http://ovirium.com/downloads/bp-groups-extras-pro-search/
Description: Adding extra fields and pages, menu sorting and other missing functionality to groups
Version: 1.2
Author: slaFFik
Author URI: http://ovirium.com/
*/

if(!defined('BPGE_PRO')){
    define('BPGE_PRO', true);
}

define('BPGE_PRO_SEARCH', true);
define('BPGE_PRO_SEARCH_VER', '1.2');

/**
 * Options for admin area
 */
add_filter('bpge_admin_tabs', 'bpges_admin_init', 999);
function bpges_admin_init($tabs){
    $tabs[] = include(dirname(__FILE__).'/bpge-search-admin.php');

    return $tabs;
}

add_action('plugins_loaded', 'bpges_init', 999);
function bpges_init(){
    add_filter('bp_groups_get_paged_groups_sql', 'bpges_search_add_paged', 1, 2);
    add_filter('bp_groups_get_total_groups_sql', 'bpges_search_add_total', 1, 2);
}


/**
 * Search in posts and fields for group IDs
 */
function bpges_search_get_groups(){
    global $wpdb, $bpge;

    // get the search terms
    if (isset($_REQUEST['search_terms'])) {
        // ajax
        $search_terms = esc_sql( like_escape( $_REQUEST['search_terms'] ) );
    }elseif (isset($_REQUEST['s'])){
        // url-based
        $search_terms = esc_sql( like_escape( $_REQUEST['s'] ) );
    }else{
        return false;
    }

    $group_ids = $pages_group_ids = $fields_group_ids = array();

    // get group_ids from gpages that are relevant to this search
    if( isset($bpge['search_pages']) && $bpge['search_pages'] == 'on' ) {
        $type = BPGE_GPAGES;
        $pages_group_ids = $wpdb->get_col($wpdb->prepare("SELECT pm.meta_value AS group_id
                                            FROM {$wpdb->postmeta} AS pm
                                            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
                                            WHERE pm.meta_key = 'group_id'
                                              AND p.post_status = 'publish'
                                              AND p.post_type = '%s'
                                              AND p.post_parent > 0
                                              AND (p.post_title LIKE '%%%s%%'
                                                OR p.post_content LIKE '%%%s%%'
                                              )", $type, $search_terms, $search_terms ) );
    }

    // get groups_ids from fields that are relevant to this search
    if( isset($bpge['search_fields']) && $bpge['search_fields'] == 'on' ) {
        $type = BPGE_GFIELDS;
        $fields_group_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT (post_parent) AS group_id
                                    FROM wp_posts
                                    WHERE post_type = '%s'
                                      AND post_status = 'publish'
                                      AND post_parent > 0
                                      AND post_content LIKE '%%%s%%';", $type, $search_terms));
    }

    // merge groups from 2 types of searches
    $group_ids = array_merge($pages_group_ids, $fields_group_ids);

    return $group_ids;
}

/**
 * Modify pages search results
 */
// add_filter('bp_groups_get_paged_groups_sql', 'bpges_search_add_paged', 1, 2);
function bpges_search_add_paged($sql_str, $sql_arr){
    if( !isset($sql_arr['search']) )
        return $sql_str;

    // get all groups that have pages/fiels that are good for this search
    $group_ids = bpges_search_get_groups();

    if(!empty($group_ids)){
        $include = 'g.ID IN ('. implode(',', $group_ids) .')';

        // modify the query to get search working with groups pages
        $sql_arr['search']  = str_replace('g.name LIKE', $include . ' OR g.name LIKE', $sql_arr['search']);
    }

    return join( ' ', (array) $sql_arr );
}

/**
 * Modify total search results (for pagination and counters)
 */
// add_filter('bp_groups_get_total_groups_sql', 'bpges_search_add_total', 1, 2);
function bpges_search_add_total($sql_str, $sql_arr){
    // check that we are in a search
    $pos = strpos($sql_str, 'g.name LIKE');
    if ($pos === false)
        return $sql_str;

    // get all groups that have pages/fiels that are good for this search
    $group_ids = bpges_search_get_groups();

    if(!empty($group_ids)){
        $include = 'g.ID IN ('. implode(',', $group_ids) .')';

        // insert it into search
        $sql_str = str_replace('g.name LIKE', $include . ' OR g.name LIKE', $sql_str);
    }

    return $sql_str;
}