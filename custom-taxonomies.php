<?php
/*
Plugin Name: Custom Taxonomies
Plugin URI: http://nerdlife.net/
Description: Custom Taxonomies provides a full administrative interface for 
creating and using taxonomies beyond the standard Tags and Categories offered 
in the default Wordpress installation.
Author: Brian Krausz
Version: 1.0
Author URI: http://nerdlife.net/
*/

/** Copyright 2008 Brian Krausz (email : brian@nerdlife.net)
 *
 *  This file is part of Custom Taxonomies.

 *  Custom Taxonomies is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by the
 *  Free Software Foundation, either version 3 of the License, or (at your 
 *  option) any later version.
 *
 *  Custom Taxonomies is distributed in the hope that it will be useful, but 
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
 *  or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License 
 *  for more details.
 *
 *  You should have received a copy of the GNU General Public License along 
 *  with Custom Taxonomies.  If not, see <http://www.gnu.org/licenses/>.
 **/

//the plugin directory
$custax_dir = dirname(__FILE__);
require_once($custax_dir . '/custax.class.php');
require_once($custax_dir . '/edit-taxonomies.php');
require_once($custax_dir . '/taxonomy_functions.php');
require_once($custax_dir . '/taxonomy_template.php');

//make sure we can use $wpdb: only needed so the activate check doesn't fail
global $wpdb;

//the version of the DB
define('CUSTAX_DB_VERSION', '1.0');

//the translation domain
define('CUSTAX_DOMAIN', 'custom_taxonomies');

//the URL of our JS directory
$custax_js_url = WP_PLUGIN_URL.'/'.basename($custax_dir).'/js';

//anything that could conflict with ids and such in edit pages
$custax_reserved_slugs = array('post_tag', 'category', 'link_category', 'cat', 'status', 'author', 'type', 'id', 'slug', 'template', 'title', 'name', 'author_override', 'private', 'url', 'description', 'target', 'rel', 'image', 'rss', 'notes', 'rating');

//anything that could show a select box for a taxonomy and therefore needs our style definitions
$custax_style_pages = array(
	'post-new.php', 'post.php', 
	'page-new.php', 'page.php', 
	'link-add.php', 'link.php');

//build the list of custax objects
$custax_taxonomies = array();
$wpdb->custom_taxonomies = $wpdb->prefix . 'custom_taxonomies';
$taxes = $wpdb->get_results('SELECT * FROM '.$wpdb->custom_taxonomies);
if($taxes) {
	foreach($taxes AS $tax) {
		$custax_taxonomies[$tax->slug] = new custax($tax);
	}
}

register_activation_hook(__FILE__, 'custax_install');

wp_register_script( 'admin-terms', $custax_js_url.'/terms.js', array('wp-lists'), '20081223' );
wp_register_script( 'inline-edit-custax', $custax_js_url.'/inline-edit.js', array( 'jquery', 'jquery-form' ), '20081223' );
wp_localize_script( 'inline-edit-custax', 'inlineEditL10n', array(
	'error' => __('Error while saving the changes.'),
	'l10n_print_after' => 'try{convertEntities(inlineEditL10n);}catch(e){};'
) );

add_action('admin_menu', 'custax_menu');
add_action('wp_ajax_inline-save-custax', 'custax_inline_edit');

foreach($custax_style_pages AS $page)
	add_action('admin_head-'.$page, 'custax_styles');

function custax_menu() {
	add_options_page('Taxonomies', 'Taxonomies', 9, 'custax_edit', 'custax_edit');
}

function custax_style_implode($front, $back, $between = false) {
	global $custax_taxonomies;
	static $keys;
	if(!$keys)
		$keys = array_keys($custax_taxonomies);
	if($between) {
		$first = true;
		foreach($keys AS $key) {
			if($first)
				$first = false;
			else
				echo ', ';
			echo $front . $key . $between . $key . $back;
		}
	}
	else {
		echo $front;
		echo implode($back.', '.$front, $keys);
		echo $back;
	}
}

function custax_styles() {
	//TODO: use classes so we don't need style_implode
	?>
	<style type="text/css">
	<?php custax_style_implode('#', 'div div.ui-tabs-panel'); ?> {
		height: 150px;
		overflow: auto;
		padding: 0.5em 0.9em;
	}

	<?php custax_style_implode('#', 'div ul'); ?> {
		list-style-image: none;
		list-style-position: outside;
		list-style-type: none;
		margin: 0;
		padding: 0;
	}

	<?php custax_style_implode('ul.', 'checklist li'); ?> {
		line-height: 19px;
		margin: 0;
		padding: 0;
	}

	<?php custax_style_implode('#', 'checklist ul', 'div ul.'); ?> {
		margin-left: 18px;
	}

	<?php custax_style_implode('#side-info-column #', '-tabs'); ?> {
		margin-bottom: 3px;
	}

	<?php custax_style_implode('#side-info-column #', '-tabs li'); ?> {
		display: inline;
		padding-right: 8px;
	}

	<?php custax_style_implode('#', '-tabs li.ui-tabs-selected'); ?> {
		background-color: #F1F1F1;
	}

	#side-info-column .term-add input {
		width: 94%;
	}

	#side-info-column .term-add select {
		width: 100%;
	}

	#side-info-column .term-add input {
		width: 94%;
	}

	#side-info-column .term-add .term-add-submit {
		width: auto;
	}

	<?php custax_style_implode('#side-info-column #', '-tabs .ui-tabs-selected a'); ?> {
		color: #333333;
	}

	<?php custax_style_implode('#side-info-column #', '-tabs a'); ?> {
		text-decoration: none;
	}
	</style>
	<?php
}

function custax_inline_edit() {
	global $custax_taxonomies;

        check_ajax_referer( 'taxinlineeditnonce', '_inline_edit' );

        if ( ! current_user_can('manage_categories') )
                die( __('Cheatin&#8217; uh?') );

        if ( ! isset($_POST['tax_ID']) || ! ( $id = (int) $_POST['tax_ID'] ) )
                die(-1);

	$type = $_POST['tax_type'];

	if ( ! array_key_exists( $type, $custax_taxonomies ) )
		die(-2);

        $ret = wp_update_term($id, $type, $_POST);

        if ( $ret && !is_wp_error($ret) )
		echo $custax_taxonomies[$type]->_term_row($ret['term_id'], max( (int) $_POST['level'], 0 ));
        else
                die( __('Term not updated.', CUSTAX_DOMAIN) );

        exit;
}

function custax_install() {
	global $wpdb;

	if($wpdb->get_var("show tables like '$wpdb->custom_taxonomies'") != $wpdb->custom_taxonomies) {
		$sql = "CREATE TABLE `{$wpdb->custom_taxonomies}` (
			`id` int(8) unsigned NOT NULL auto_increment,
			`slug` varchar(32) NOT NULL,
			`name` varchar(32) NOT NULL,
			`plural` varchar(32) NOT NULL,
			`object_type` varchar(32) NOT NULL,
			`hierarchical` tinyint(1) unsigned NOT NULL,
			`multiple` tinyint(1) unsigned NOT NULL,
			`tag_style` tinyint(1) unsigned NOT NULL,
			`descriptions` tinyint(1) unsigned NOT NULL,
			`show_column` tinyint(1) unsigned NOT NULL,
			PRIMARY KEY  (`id`),
			UNIQUE KEY `slug` (`slug`)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		add_option('custax_db_version', CUSTAX_DB_VERSION);
	}

	//TODO: update DB check (not needed until we actually change our DB)
}
?>
