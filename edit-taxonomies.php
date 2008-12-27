<?php
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

//TODO: organize this more
//TODO: us wpnonce and wp_check_referrer

function custax_get_tax($id) {
	global $wpdb;

	$query = "SELECT * FROM {$wpdb->custom_taxonomies} WHERE id = %d";

	$row = $wpdb->get_row( $wpdb->prepare($query, $id) );

	return $row;
}

function custax_get_taxes() {
	global $wpdb;

	$query = "SELECT ct.*, COUNT(tt.term_id) AS count FROM {$wpdb->custom_taxonomies} ct LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.taxonomy = ct.slug 
		GROUP BY ct.id ORDER BY ct.name ASC";

	$results = $wpdb->get_results($query);

	return $results;
}

/**
 * custax_update_tax is not used right now without being called by 
 * custax_insert_tax since editing gets complicated and can break things
 **/
function custax_update_tax($id, $row) {
	global $wpdb;

	$h = $row['hierarchical']?1:0;
	$m = $row['multiple']?1:0;
	$t = $row['tag_style']?1:0;
	$d = $row['descriptions']?1:0;
	$s = $row['show_column']?1:0;

	$args = array($row['name'], $row['plural'], $row['object_type'], $row['slug'], $h, $m, $t, $d, $s);

	$query = '';
	if($id)
		$query .= 'UPDATE ';
	else
		$query .= 'INSERT INTO ';

	$query .= $wpdb->custom_taxonomies.' SET name = %s, plural = %s, object_type = %s, slug = %s, hierarchical = %d, multiple = %d, tag_style = %d, descriptions = %d, show_column = %d';

	if($id) {
		$query .= ' WHERE id = %d';
		$args[] = $id;
	}

	array_unshift($args, $query);

	$query = call_user_func_array(array($wpdb, 'prepare'), $args);
	return $wpdb->query( $query );
}

function custax_insert_tax($row) {
	return custax_update_tax( 0, $row );
}

/**
 * custax_delete_tax
 *
 * $delete_terms right now is always true, since the logistics of leaving 
 * terms get wonky.  Perhaps make an advanced setting for experiences 
 * taxonomists?
 **/
function custax_delete_tax($id, $delete_terms = true) {
	global $wpdb;

	$slug = $wpdb->get_var( $wpdb->prepare('SELECT slug FROM '.$wpdb->custom_taxonomies.' WHERE id = %d', $id) );

	$delete = $wpdb->query( $wpdb->prepare('DELETE FROM '.$wpdb->custom_taxonomies.' WHERE id = %d', $id) );
	if(!$delete || is_wp_error($delete))
		return $delete;

	if($delete_terms) {
		$terms = get_terms($slug, array('fields'=>'ids', 'hide_empty'=>false));
		foreach($terms AS $term) {
			wp_delete_term((int)$term, $slug);
		}
	}
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

	$out .= '<td class="name column-name><strong><a class="row-title" href="' . $edit_link . '" title="' . attribute_escape(sprintf(__('Edit "%s"'), $name)) . '">' . $name . '</a></strong><br />';
	$actions = array();
//	$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
	$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url($delete_link, 'delete-tax_' . $tax->id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this taxonomy '%s' along with ALL terms associated with this taxonomy.  Please be SURE you want to do this.\n 'Cancel' to stop, 'OK' to delete.", CUSTAX_DOMAINS), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
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

function custax_tax_rows( ) {
        // Get a page worth of tags
        $start = ($page - 1) * $pagesize;

        $args = array('offset' => $start, 'number' => $pagesize, 'hide_empty' => 0);

        $taxes = custax_get_taxes();

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
	global $action, $custax_reserved_slugs;

        $cols = array(
            'name' => __('Name'),
            'slug' => __('Slug'),
            'terms' => __('Terms'),
        );

        register_column_headers('edit-taxonomies', $cols);

	$self = $_SERVER['REQUEST_URI'];

	$title = __('Taxonomies', CUSTAX_DOMAIN);

	$subtitle = __('Add a New Taxonomy', CUSTAX_DOMAIN);
	$submit = __('Add Taxonomy', CUSTAX_DOMAIN);
	$new_action = 'addtax';

	wp_reset_vars( array('action', 'tax') );

	switch($action) {
	case 'addtax':
		if(!$_POST['name'] || !$_POST['plural']) {
			$message = 6;
			break;
		}
	        if(empty($_POST['slug']))
			$_POST['slug'] = sanitize_title_with_dashes($_POST['name']);
		if(in_array($_POST['slug'], $custax_reserved_slugs)) {
			$message = 7;
			break;
		}
		$ret = custax_insert_tax($_POST);
		if ( $ret && !is_wp_error( $ret ) ) {
			$message = 1;
		} else {
			$message = 4;
		}
	break;

	/** 
	 * TODO: allow some limited form of editing (perhaps only things like 
	 * show_columns and descriptions
	 **/
	/*
	case 'edittax':
		$tax_ID = (int) $_GET['tax_ID'];

		$ret = custax_update_tax($tax_ID, $_POST);
		if ( $ret && !is_wp_error( $ret ) ) {
			$message = 3;
		} else {
			$message = 5;
		}
	break;
	*/

	case 'delete':
		$tax_ID = (int) $_GET['tax_ID'];
		check_admin_referer('delete-tax_' .  $tax_ID);

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		custax_delete_tax( $tax_ID );

		$message = 2;
	break;

	/*
	case 'edit':
		$tax_ID = (int) $_GET['tax_ID'];

		$tax = custax_get_tax($tax_ID);

		$subtitle = __('Edit Taxonomy', CUSTAX_DOMAIN).' "'.$tax->name.'"';
		$submit = __('Edit Taxonomy', CUSTAX_DOMAIN);
		$new_action = 'edittax';

	break;
	*/
	}

	$messages[1] = __('Taxonomy added.', CUSTAX_DOMAIN);
	$messages[2] = __('Taxonomy deleted.', CUSTAX_DOMAIN);
	$messages[3] = __('Taxonomy updated.', CUSTAX_DOMAIN);
	$messages[4] = __('Taxonomy not added.', CUSTAX_DOMAIN);
	$messages[5] = __('Taxonomy not updated.', CUSTAX_DOMAIN);
	$messages[6] = __('Name and plural are both required.', CUSTAX_DOMAIN);
	$messages[7] = __('You\'ve used a reserved slug: try a different one.', CUSTAX_DOMAIN);
?>
<div class="wrap nosubsub">
<?php screen_icon(); ?>
<h2><?php echo wp_specialchars( $title ); ?></h2>

<?php if ( isset($message) ) { ?>
<div id="message" class="updated fade"><p><?php echo $messages[$message]; ?></p></div>
<?php } ?>

<br class="clear" />

<div id="col-container">

<div id="col-right">
<div class="col-wrap">

<br class="clear" />

<div class="clear"></div>

<table class="widefat tag fixed" cellspacing="0">
	<thead>
	<tr>
        <?php print_column_headers('edit-taxonomies'); ?>
	</tr>
	</thead>

	<tfoot>
	<tr>
        <?php print_column_headers('edit-taxonomies', false); ?>
	</tr>
	</tfoot>

	<tbody id="the-list" class="list:taxonomy">
<?php custax_tax_rows( ); ?>
	</tbody>
</table>
<br class="clear" />
</div>
</div><!-- /col-right -->

<div id="col-left">
<div class="col-wrap">

<div class="form-wrap">
<h3><?php echo $subtitle; ?></h3>
<p>
<strong><?php _e('Note:'); ?></strong><br />
<?php _e('Taxonomies cannot be edited, only created and deleted.  This is because of the logistical complexities of changing settings such as nested items and multiple selections.  If you want to change a setting, please note the terms and taxonomies and recreate them.  In the future we will try to add some limited editing so this is less annoying.', CUSTAX_DOMAIN); ?>
</p>
<form name="addtax" id="addtax" method="post" action="<?php echo $self ?>" class="add:the-list: validate">
<input type="hidden" name="action" value="<?php echo $new_action ?>" />

<div class="form-field form-required">
	<label for="name"><?php _e('Taxonomy name', CUSTAX_DOMAIN) ?></label>
	<input name="name" id="name" type="text" value="<?php echo $tax->name; ?>" size="40" aria-required="true" />
    <p><?php _e('The name is how the taxonomy appears on your site.', CUSTAX_DOMAIN); ?></p>
</div>

<div class="form-field form-required">
	<label for="plural"><?php _e('Taxonomy name plural', CUSTAX_DOMAIN) ?></label>
	<input name="plural" id="plural" type="text" value="<?php echo $tax->plural; ?>" size="40" aria-required="true" />
    <p><?php _e('The plural form of the name.', CUSTAX_DOMAIN); ?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Taxonomy slug', CUSTAX_DOMAIN) ?></label>
	<input name="slug" id="slug" type="text" value="<?php echo $tax->slug; ?>" size="40" />
    <p><?php _e('The &#8220;slug&#8221; is the <b>unique</b> URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Taxonomy object', CUSTAX_DOMAIN) ?></label>
	<select name="object_type" id="object_type" style="width:100px">
		<option value="post" <?php selected($tax->object_type, 'post'); ?>>Post</option>
		<option value="page" <?php selected($tax->object_type, 'page'); ?>>Page</option>
		<option value="link" <?php selected($tax->object_type, 'link'); ?>>Link</option>
	</select>
    <p><?php _e('The object is the type of data the taxonomy will apply to.', CUSTAX_DOMAIN); 
?></p>
</div>

<div class="form-field">
	<label><?php _e('Miscellaneous options', CUSTAX_DOMAIN) ?></label>

	<p><input name="hierarchical" id="hierarchical" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->hierarchical, true)?> />
	<?php _e('Allow nested terms', CUSTAX_DOMAIN) ?></p>

	<p><input name="multiple" id="multiple" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->multiple, true)?> />
	<?php _e('Allow multiple selections for each item', CUSTAX_DOMAIN); ?>
	<?php echo '<br /><strong>'.__('Note:').'</strong> '.__('This has not been implemented yet.', CUSTAX_DOMAIN); ?></p>

	<p><input name="tag_style" id="tag_style" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->tag_style, true)?> />
	<?php _e('Use tag-style selection, encouraging arbitrary term creation (works best with multiple selections, but not required)', CUSTAX_DOMAIN) ?>
	<?php echo '<br /><strong>'.__('Note:').'</strong> '.__('This has not been implemented yet.', CUSTAX_DOMAIN); ?></p>

	<p><input name="descriptions" id="descriptions" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->descriptions, true)?> />
	<?php _e('Allow descriptions', CUSTAX_DOMAIN) ?></p>

	<p><input name="show_column" id="show_column" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->show_column, true)?> />
	<?php _e('Show taxonomy on object\'s manage screen', CUSTAX_DOMAIN) ?></p>
</div>

<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo $submit; ?>" /></p>
</form></div>

</div>
</div><!-- /col-left -->

</div><!-- /col-container -->
</div><!-- /wrap -->

<?php
}
?>
