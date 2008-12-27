<?php
/*
Plugin Name: Custom Taxonomies
Plugin URI: http://nerdlife.net/
Description: Custom Taxonomies provides a full administrative interface for 
creating and using taxonomies beyond the standard Tags and Categories offered 
in the default Wordpress installation.
Author: Brian Krausz
Version: 0.5
Author URI: http://nerdlife.net/
*/

//make sure we can use $wpdb: only needed so the activate check doesn't fail
global $wpdb;

$custax_dir = dirname(__FILE__);
$custax_js_url = WP_PLUGIN_URL.'/'.basename($custax_dir).'/js';

require_once($custax_dir . '/custax.class.php');
require_once($custax_dir . '/edit-taxonomies.php');
require_once($custax_dir . '/taxonomy_functions.php');

//anything that could conflict with ids and such in edit pages
$custax_reserved_slugs = array('post_tag', 'category', 'link_category', 'cat', 'status', 'author', 'type', 'id', 'slug', 'template', 'title', 'name', 'author_override', 'private', 'url', 'description', 'target', 'rel', 'image', 'rss', 'notes', 'rating');

$custax_taxonomies = array();

$wpdb->custom_taxonomies = $wpdb->prefix . 'custom_taxonomies';
$taxes = $wpdb->get_results('SELECT * FROM '.$wpdb->custom_taxonomies);
if($taxes) {
	foreach($taxes AS $tax) {
		$custax_taxonomies[$tax->slug] = new custax($tax);
	}
}

wp_register_script( 'admin-terms', $custax_js_url.'/terms.js', array('wp-lists'), '20081223' );

wp_register_script( 'inline-edit-custax', $custax_js_url.'/inline-edit.js', array( 'jquery', 'jquery-form' ), '20081223' );
wp_localize_script( 'inline-edit-custax', 'inlineEditL10n', array(
	'error' => __('Error while saving the changes.'),
	'l10n_print_after' => 'try{convertEntities(inlineEditL10n);}catch(e){};'
) );

add_action('admin_menu', 'custax_menu');
add_action('wp_ajax_inline-save-custax', 'custax_inline_edit');

$custax_style_pages = array(
	'post-new.php', 'post.php', 
	'page-new.php', 'page.php', 
	'link-add.php', 'link.php');

foreach($custax_style_pages AS $page)
	add_action('admin_head-'.$page, 'custax_styles');

function custax_menu() {
	add_options_page('Taxonomies', 'Taxonomies', 9, __FILE__, 'custax_edit');
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
                die( __('Term not updated.') );

        exit;
}

?>
