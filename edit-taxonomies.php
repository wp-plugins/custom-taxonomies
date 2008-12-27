<?php
//TODO: organize this more
//TODO: more validation (plural, reserved names)
//TODO: limit editing
//TODO: inquire on deleting (remove term links?)
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

function custax_update_tax($id, $row) {
	global $wpdb;

        if(empty($row['slug']))
		$row['slug'] = sanitize_title_with_dashes($row['name']);

	$h = $row['hierarchical']?1:0;
	$m = $row['multiple']?1:0;
	$t = $row['tag_style']?1:0;
	$d = $row['descriptions']?1:0;

	$args = array($row['name'], $row['plural'], $row['object_type'], $row['slug'], $h, $m, $t, $d);

	$query = '';
	if($id)
		$query .= 'UPDATE ';
	else
		$query .= 'INSERT INTO ';

	$query .= $wpdb->custom_taxonomies.' SET name = %s, plural = %s, object_type = %s, slug = %s, hierarchical = %d, multiple = %d, tag_style = %d, descriptions = %d';

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

function custax_delete_tax($id) {
	global $wpdb;

	return $wpdb->query( $wpdb->prepare('DELETE FROM '.$wpdb->custom_taxonomies.' WHERE id = %d', $id) );
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
	$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
	$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url($delete_link, 'delete-tax_' . $tax->id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this taxonomy '%s'\n 'Cancel' to stop, 'OK' to delete."), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
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
	global $action;

        $cols = array(
//            'cb' => '<input type="checkbox" />',
            'name' => __('Name'),
            'slug' => __('Slug'),
            'terms' => __('Terms'),
        );

        register_column_headers('edit-taxonomies', $cols);

	$self = $_SERVER['REQUEST_URI'];

	$title = __('Taxonomies');

	$subtitle = __('Add a New Taxonomy');
	$submit = __('Add Taxonomy');
	$new_action = 'addtax';

	wp_reset_vars( array('action', 'tax') );

	switch($action) {
//TODO: input validation
	case 'addtax':
		$ret = custax_insert_tax($_POST);
		if ( $ret && !is_wp_error( $ret ) ) {
			$message = 1;
		} else {
			$message = 4;
		}
	break;

	case 'edittax':
		$tax_ID = (int) $_GET['tax_ID'];

		$ret = custax_update_tax($tax_ID, $_POST);
		if ( $ret && !is_wp_error( $ret ) ) {
			$message = 3;
		} else {
			$message = 5;
		}
	break;

	case 'delete':
		$tax_ID = (int) $_GET['tax_ID'];
		check_admin_referer('delete-tax_' .  $tax_ID);

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		custax_delete_tax( $tax_ID );

		$message = 2;
	break;

	case 'edit':
		$tax_ID = (int) $_GET['tax_ID'];

		$tax = custax_get_tax($tax_ID);

		$subtitle = __('Edit Taxonomy').' "'.$tax->name.'"';
		$submit = __('Edit Taxonomy');
		$new_action = 'edittax';

	break;

}
	$messages[1] = __('Taxonomy added.');
	$messages[2] = __('Taxonomy deleted.');
	$messages[3] = __('Taxonomy updated.');
	$messages[4] = __('Taxonomy not added.');
	$messages[5] = __('Taxonomy not updated.');
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
<!-- //TODO: put this in intelligent place -->
<script type="text/javascript">
jQuery(function($) {
	var options = false
	if ( document.forms['addtax'].taxonomy_parent )
		options = document.forms['addtax'].taxonomy_parent.options;

	var addAfter = function( r, settings ) {
		var name = $("<span>" + $('name', r).text() + "</span>").html();
		var id = $('taxonomy', r).attr('id');
		options[options.length] = new Option(name, id);

		addAfter2( r, settings );
	}

	var addAfter2 = function( x, r ) {
		var t = $(r.parsed.responses[0].data);
		if ( t.length == 1 )
			inlineEditTax.addEvents($(t.id));
	}

	var delAfter = function( r, settings ) {
		var id = $('tax', r).attr('id');
		for ( var o = 0; o < options.length; o++ )
			if ( id == options[o].value )
				options[o] = null;
	}

	if ( options )
		$('#the-list').wpList( { addAfter: addAfter, delAfter: delAfter } );
	else
		$('#the-list').wpList({ addAfter: addAfter2 });

	if ( jQuery('#link-taxonomy-search-input').size() ) {
		columns.init('edit-link-taxonomies');
	} else {
		columns.init('taxonomies');
	}
});
</script>
<br class="clear" />
</div>
</div><!-- /col-right -->

<div id="col-left">
<div class="col-wrap">

<div class="form-wrap">
<h3><?php echo $subtitle; ?></h3>
<form name="addtax" id="addtax" method="post" action="<?php echo $self ?>" class="add:the-list: validate">
<input type="hidden" name="action" value="<?php echo $new_action ?>" />

<div class="form-field form-required">
	<label for="name"><?php _e('Taxonomy name') ?></label>
	<input name="name" id="name" type="text" value="<?php echo $tax->name; ?>" size="40" aria-required="true" />
    <p><?php _e('The name is how the taxonomy appears on your site.'); ?></p>
</div>

<div class="form-field form-required">
	<label for="plural"><?php _e('Taxonomy name plural') ?></label>
	<input name="plural" id="plural" type="text" value="<?php echo $tax->plural; ?>" size="40" aria-required="true" />
    <p><?php _e('The plural form of the name.'); ?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Taxonomy slug') ?></label>
	<input name="slug" id="slug" type="text" value="<?php echo $tax->slug; ?>" size="40" />
    <p><?php _e('The &#8220;slug&#8221; is the <b>unique</b> URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Taxonomy object') ?></label>
	<select name="object_type" id="object_type" style="width:100px">
		<option value="post" <?php selected($tax->object_type, 'post'); ?>>Post</option>
		<option value="page" <?php selected($tax->object_type, 'page'); ?>>Page</option>
		<option value="link" <?php selected($tax->object_type, 'link'); ?>>Link</option>
	</select>
    <p><?php _e('The object is the type of data the taxonomy will apply to.'); 
?></p>
</div>

<div class="form-field">
	<label><?php _e('Miscellaneous options') ?></label>

	<p><input name="hierarchical" id="hierarchical" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->hierarchical, true)?> />
	<?php _e('Allow nested terms') ?></p>

	<p><input name="multiple" id="multiple" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->multiple, true)?> />
	<?php _e('Allow multiple selections for each item') ?></p>

	<p><input name="tag_style" id="tag_style" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->tag_style, true)?> />
	<?php _e('Use tag-style selection, encouraging arbitrary term creation (works best with multiple selections, but not required)') ?></p>

	<p><input name="descriptions" id="descriptions" type="checkbox" value="1" style="width:20px;margin-top:0;" <?php checked($tax->descriptions, true)?> />
	<?php _e('Allow descriptions') ?></p>
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
