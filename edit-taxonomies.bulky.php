<?php
function custax_get_taxes( $args ) {
	global $wpdb;

        $defaults = array('orderby' => 'name', 'order' => 'ASC',
                'number' => 10, 'offset' => 0);
        $args = wp_parse_args( $args, $defaults );

	$query = "SELECT ct.*, COUNT(tt.term_id) AS count FROM {$wpdb->custom_taxonomies} ct LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.taxonomy = ct.slug 
		GROUP BY tt.taxonomy ORDER BY ct.{$args['orderby']} {$args['order']} LIMIT {$args['offset']}, {$args['number']}";

	$results = $wpdb->get_results($query);

	return $results;
}

function custax_tax_row( $tax, $class = '' ) {

	$count = number_format_i18n( $tax->count );
	//$count = ( $count > 0 ) ? "<a href='edit.php?tag=$tag->slug'>$count</a>" : $count;

	$name = apply_filters( 'taxonomy_name', $tax->name );
	$edit_link = $_SERVER['REQUEST_URI'].'&amp;action=edit&amp;tax_ID='.$tax->id;
	$delete_link = $_SERVER['REQUEST_URI'].'&amp;action=delete&amp;tax_ID='.$tax->id;
	$out = '';
	$out .= '<tr id="tax-' . $tax->id . '"' . $class . '>';

                        $class = "class=\"$column_name column-$column_name\"";


	$out .= '<th scope="row" class="check-column"> <input type="checkbox" name="delete_taxes[]" value="' . $tax->id . '" /></th>';

	$out .= '<td class="name column-name><strong><a class="row-title" href="' . $edit_link . '" title="' . attribute_escape(sprintf(__('Edit "%s"'), $name)) . '">' . $name . '</a></strong><br />';
	$actions = array();
	$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
//	$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __('Quick&nbsp;Edit') . '</a>';
	$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url($delete_url, 'delete-tax_' . $tax->id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this taxonomy '%s'\n 'Cancel' to stop, 'OK' to delete."), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
	$action_count = count($actions);
	$i = 0;
	$out .= '<div class="row-actions">';
	foreach ( $actions as $action => $link ) {
		++$i;
		( $i == $action_count ) ? $sep = '' : $sep = ' | ';
		$out .= "<span class='$action'>$link$sep</span>";
	}
	$out .= '</div>';
	$out .= '<div class="hidden" id="inline_' . $tax->id . '">';
	$out .= '<div class="name">' . $tax->name . '</div>';
	$out .= '<div class="slug">' . $tax->slug . '</div></div></td>';

	$out .= "<td class=\"slug column-slug\">$tax->slug</td>";

        $out .= "<td class=\"terms column-terms num\">$count</td>";

	$out .= '</tr>';

	return $out;
}

function custax_tax_rows( $page = 1, $pagesize = 20 ) {
        // Get a page worth of tags
        $start = ($page - 1) * $pagesize;

        $args = array('offset' => $start, 'number' => $pagesize, 'hide_empty' => 0);

        $taxes = custax_get_taxes( 'post_tag', $args );

        // convert it to table rows
        $out = '';
        $count = 0;
        foreach( $taxes as $tax )
                $out .= custax_tax_row( $tax, ++$count % 2 ? ' class="iedit alternate"' : ' class="iedit"' );

        // filter and send to screen
        echo $out;
        return $count;
}

function custax_edit() {
	global $action;

	$self = $_SERVER['REQUEST_URI'];

	$title = __('Taxonomies');

	wp_reset_vars( array('action', 'tax') );

//	if ( isset( $_GET['action'] ) && isset($_GET['delete_taxes']) && ( 'delete' == $_GET['action'] || 'delete' == $_GET['action2'] ) )
//		$action = 'bulk-delete';

	switch($action) {

	case 'addtax':

		check_admin_referer('add-tax');

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		$ret = custax_insert_tax($_POST['name'], $_POST);
		if ( $ret && !is_wp_error( $ret ) ) {
			wp_redirect('edit-tags.php?message=1#addtax');
		} else {
			wp_redirect('edit-tags.php?message=4#addtax');
		}
		exit;
	break;

	case 'delete':
		$tax_ID = (int) $_GET['tax_ID'];
		check_admin_referer('delete-tax_' .  $tax_ID);

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		wp_delete_term( $tax_ID, 'post_tax');

		wp_redirect('edit-tags.php?message=2');
		exit;

	break;

/*
	case 'bulk-delete':
		check_admin_referer('bulk-tags');

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		$tags = $_GET['delete_taxes'];
		foreach( (array) $tags as $tax_ID ) {
			wp_delete_term( $tax_ID, 'post_tax');
		}

		$location = 'edit-tags.php';
		if ( $referer = wp_get_referer() ) {
			if ( false !== strpos($referer, 'edit-tags.php') )
				$location = $referer;
		}

		$location = add_query_arg('message', 6, $location);
		wp_redirect($location);
		exit;
	break;
*/

	case 'edit':
		$title = __('Edit Taxonomy');

		$tax_ID = (int) $_GET['tax_ID'];

		$tag = custax_get_tax($tax_ID, 'post_tax', OBJECT, 'edit');
		include(ABSPATH . 'wp-content/plugins/taxonomies/edit-tax-form.php');

	break;

/*
	case 'editedtax':
		$tax_ID = (int) $_POST['tax_ID'];
		check_admin_referer('update-tax_' . $tax_ID);

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		$ret = wp_update_term($tax_ID, 'post_tax', $_POST);

		$location = 'edit-taxs.php';
		if ( $referer = wp_get_original_referer() ) {
			if ( false !== strpos($referer, 'edit-tags.php') )
				$location = $referer;
		}

		if ( $ret && !is_wp_error( $ret ) )
			$location = add_query_arg('message', 3, $location);
		else
			$location = add_query_arg('message', 5, $location);

		wp_redirect($location);
		exit;
	break;
*/

	default:

	if ( isset($_GET['_wp_http_referer']) && ! empty($_GET['_wp_http_referer']) ) {
		 wp_redirect( remove_query_arg( array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI']) ) );
		 exit;
	}

	$can_manage = current_user_can('manage_categories');

//	wp_enqueue_script('admin-tags');
//	if ( $can_manage )
//		wp_enqueue_script('inline-edit-tax');

	$messages[1] = __('Taxonomy added.');
	$messages[2] = __('Taxonomy deleted.');
	$messages[3] = __('Taxonomy updated.');
	$messages[4] = __('Taxonomy not added.');
	$messages[5] = __('Taxonomy not updated.');
	$messages[6] = __('Taxonomies deleted.'); ?>

<div class="wrap nosubsub">
<?php screen_icon(); ?>
<h2><?php echo wp_specialchars( $title ); ?></h2>

<?php if ( isset($_GET['message']) && ( $msg = (int) $_GET['message'] ) ) : ?>
<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
endif; ?>

<br class="clear" />

<div id="col-container">

<div id="col-right">
<div class="col-wrap">
<form id="posts-filter" action="<?php echo $self ?>" method="get">

<!--
<div class="tablenav">
<?php
$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
if ( empty($pagenum) )
	$pagenum = 1;

$taxesperpage = apply_filters("taxesperpage",20);

$page_links = paginate_links( array(
	'base' => add_query_arg( 'pagenum', '%#%' ),
	'format' => '',
	'prev_text' => __('&laquo;'),
	'next_text' => __('&raquo;'),
	'total' => ceil(wp_count_terms('post_tax') / $taxesperpage),
	'current' => $pagenum
));

if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<select name="action">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction" id="doaction" class="button-secondary action" />
<?php wp_nonce_field('bulk-tags'); ?>
</div>

<br class="clear" />
</div>
-->

<!-- ADDED -->
<br class="clear" />

<div class="clear"></div>

<table class="widefat tag fixed" cellspacing="0">
	<thead>
	<tr>
	<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
	<th scope="col" id="name" class="manage-column column-name" style=""><?php _e('Name'); ?></th>
	<th scope="col" id="slug" class="manage-column column-slug" style=""><?php _e('Slug'); ?></th>
	<th scope="col" id="terms" class="manage-column column-terms num" style=""><?php _e('Terms'); ?></th>
	</tr>
	</thead>

	<tfoot>
	<tr>
	<th scope="col"  class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
	<th scope="col"  class="manage-column column-name" style=""><?php _e('Name'); ?></th>
	<th scope="col"  class="manage-column column-slug" style=""><?php _e('Slug'); ?></th>
	<th scope="col"  class="manage-column column-terms num" style=""><?php _e('Terms'); ?></th>
	</tr>
	</tfoot>

	<tbody id="the-list" class="list:tag">
<?php

$searchterms = isset( $_GET['s'] ) ? trim( $_GET['s'] ) : '';

$count = custax_tax_rows( $pagenum, $taxesperpage, $searchterms );
?>
	</tbody>
</table>

<div class="tablenav">
<?php
if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<select name="action2">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
</div>

<br class="clear" />
</div>

<br class="clear" />
</form>
</div>
</div><!-- /col-right -->

<div id="col-left">
<div class="col-wrap">

<?php if ( $can_manage ) {
	do_action('add_tax_form_pre'); ?>

<div class="form-wrap">
<h3><?php _e('Add a New Taxonomy'); ?></h3>
<div id="ajax-response"></div>
<form name="addtax" id="addtax" method="post" action="<?php echo $self ?>" class="add:the-list: validate">
<input type="hidden" name="action" value="addtax" />
<?php wp_original_referer_field(true, 'previous'); wp_nonce_field('add-tax'); ?>

<div class="form-field form-required">
	<label for="name"><?php _e('Taxonomy name') ?></label>
	<input name="name" id="name" type="text" value="" size="40" aria-required="true" />
    <p><?php _e('The name is how the taxonomy appears on your site.'); ?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Taxonomy slug') ?></label>
	<input name="slug" id="slug" type="text" value="" size="40" />
    <p><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
</div>

<p class="submit"><input type="submit" class="button" name="submit" value="<?php _e('Add Taxonomy'); ?>" /></p>
<?php do_action('add_tax_form'); ?>
</form></div>
<?php } ?>

</div>
</div><!-- /col-left -->

</div><!-- /col-container -->
</div><!-- /wrap -->

<script type="text/javascript">
/* <![CDATA[ */
(function($){
	$(document).ready(function(){
		$('#doaction, #doaction2').click(function(){
			if ( $('select[name^="action"]').val() == 'delete' ) {
				var m = '<?php echo js_escape(__("You are about to delete the selected taxonomies.\n  'Cancel' to stop, 'OK' to delete.")); ?>';
				return showNotice.warn(m);
			}
		});
	});
})(jQuery);
/* ]]> */
</script>

<?php inline_edit_term_row('edit-tags'); ?>

<?php
break;
}

}
?>
