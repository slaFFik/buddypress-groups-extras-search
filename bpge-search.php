<?php
/*
Plugin Name: BuddyPress Groups Extras Pro - Search
Plugin URI: http://ovirium.com/portfolio/bp-groups-extras/
Description: Adding extra fields and pages, menu sorting and other missing functionality to groups
Version: 1.1
Author: slaFFik
Author URI: http://ovirium.com/
*/

add_action('admin_init', 'bpges_admin_init');
function bpges_admin(){
    include(dirname(__FILE__).'/bpge-search-admin.php');
}