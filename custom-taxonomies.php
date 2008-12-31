<?php
/*
Plugin Name: Custom Taxonomies
Plugin URI: http://nerdlife.net/custom-taxonomies/
Description: Custom Taxonomies provides a full administrative interface for creating and using taxonomies beyond the standard Tags and Categories offered in the default WordPress installation.
Author: Brian Krausz
Version: 1.2
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
require_once($custax_dir . '/backwards_compatibility.php');
require_once($custax_dir . '/custax.class.php');
require_once($custax_dir . '/edit-taxonomies.php');
require_once($custax_dir . '/taxonomy_functions.php');
require_once($custax_dir . '/taxonomy_template.php');

//make sure we can use $wpdb: only needed so the activate check doesn't fail
global $wpdb;

if(!defined('CUSTAX_SETUP')) {
//prevent multiple includes (done right after activation for some reason)
define('CUSTAX_SETUP', true);

//the version of the DB
define('CUSTAX_DB_VERSION', '1.2');

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
$custax_taxes = $wpdb->get_results('SELECT * FROM '.$wpdb->custom_taxonomies);
if($custax_taxes) {
	foreach($custax_taxes AS $custax_tax) {
		$custax_taxonomies[$custax_tax->slug] = new custax($custax_tax);
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

add_filter('posts_results', 'custax_posts_results');

//TODO: hack, either because of a glitch in WP_Query or my lack of understanding of it
add_filter('mod_rewrite_rules', 'custax_mod_rewrite_rules');

foreach($custax_style_pages AS $custax_page)
	add_action('admin_head-'.$custax_page, 'custax_styles');

function custax_menu() {
	add_options_page('Taxonomies', 'Taxonomies', 9, 'custax_edit', 'custax_edit');
}

function custax_update_term_count( $terms ) {
	global $wpdb;

	foreach ( (array) $terms as $term ) {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND term_taxonomy_id = %d", $term ) );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
	}
}


function custax_rewrite_rules($slug, $flush = false, $disable = false) {
	global $wp_rewrite;

	$rules = array(
		$slug . '/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$' => 
			'index.php?'.$slug.'=$matches[1]&feed=$matches[2]',
		$slug . '/(.+?)/(feed|rdf|rss|rss2|atom)/?$' => 
			'index.php?'.$slug.'=$matches[1]&feed=$matches[2]',
		$slug . '/(.+?)/page/?([0-9]{1,})/?$' => 
			'index.php?'.$slug.'=$matches[1]&paged=$matches[2]',
		$slug . '/(.+?)/?$' => 
			'index.php?'.$slug.'=$matches[1]',
	);

	foreach($rules AS $regex=>$redirect) {
		if($disable)
			unset($wp_rewrite->extra_rules_top[$regex]);
		else
			add_rewrite_rule($regex, $redirect, 'top');
	}

	if($flush)
		$wp_rewrite->flush_rules();
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

	$charset_collate = '';

	$can_collate = false;
	if ( method_exists( $wpdb, 'supports_collation' ) )
		$can_collate = $wpdb->supports_collation();
	elseif ( method_exists( $wpdb, 'has_cap' ) )
		$can_collate = $wpdb->has_cap( 'collation' );

	if ( $can_collate ) {
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}

	$sql = "CREATE TABLE {$wpdb->custom_taxonomies} (
		id int(8) unsigned NOT NULL auto_increment,
		slug varchar(32) NOT NULL,
		name varchar(32) NOT NULL,
		plural varchar(32) NOT NULL,
		object_type varchar(32) NOT NULL,
		hierarchical tinyint(1) unsigned NOT NULL,
		multiple tinyint(1) unsigned NOT NULL,
		tag_style tinyint(1) unsigned NOT NULL,
		descriptions tinyint(1) unsigned NOT NULL,
		show_column tinyint(1) unsigned NOT NULL,
		rewrite_rules tinyint(1) unsigned NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY slug (slug)
	) $charset_collate;";

	$installed_ver = get_option( 'custax_db_version' );

	if($wpdb->get_var("show tables like '$wpdb->custom_taxonomies'") != $wpdb->custom_taxonomies || 
		$installed_ver != CUSTAX_DB_VERSION) {

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		if(isset($installed_ver))
			update_option('custax_db_version', CUSTAX_DB_VERSION);
		else
			add_option('custax_db_version', CUSTAX_DB_VERSION);
	}
}

//TODO: Hack to work around http://trac.wordpress.org/ticket/8731
function custax_posts_results($results) {
	if(!is_tax())
		return $results;

	$new_results = array();
	foreach((array)$results AS $row) {
		if($row->post_status != 'inherit') {
			$new_results[] = $row;
		}
	}
	return $new_results;
}

//TODO: ugly, ugly hack, but luckily hardcoded to not matter
function custax_mod_rewrite_rules($rules) {
	global $wp_rewrite;
	if(!$wp_rewrite->use_verbose_rules)
		return $rules;

	$rules_array = explode("\n", $rules);
	$count_row = -1;
	$new_rules_array = array();
	$skipped = 0;
	$i = 0;
	foreach($rules_array AS $rule) {
		if($count_row == -1 && strpos($rule, '[S=') !== false)
			$count_row = $i;
		if(strpos($rule, '$matches[') !== false) {
			$skipped++;
			continue;
		}
		$new_rules_array[$i++] = $rule;
	}
	$rewrite = $wp_rewrite->rewrite_rules();
	$num_rules = count($rewrite) - $skipped;

	if($count_row != -1)
		$new_rules_array[$count_row] = "RewriteRule ^.*$ - [S=$num_rules]";

	return implode("\n", $new_rules_array);
}
}

//needs to be seperate
if(!function_exists('custax_old_version')) {
function custax_old_version() {
        global $wp_version;
        $v = explode('.', $wp_version);
        return ($v[0] < 2 || $v[1] < 7);
}
}
?>
