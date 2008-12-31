<?php

if(!function_exists('register_column_headers')) {
function register_column_headers($screen, $columns) {
        global $_wp_column_headers;

        if ( !isset($_wp_column_headers) )
                $_wp_column_headers = array();

        $_wp_column_headers[$screen] = $columns;
}
}

if(!function_exists('print_column_headers')) {
function print_column_headers( $type, $id = true ) {
        $type = str_replace('.php', '', $type);
        $columns = get_column_headers( $type );
        $hidden = get_hidden_columns($type);
        $styles = array();

        foreach ( $columns as $column_key => $column_display_name ) {
                $class = ' class="manage-column';

                $class .= " column-$column_key";

                if ( 'cb' == $column_key )
                        $class .= ' check-column';
                elseif ( in_array($column_key, array('posts', 'comments', 'links')) )
                        $class .= ' num';

                $class .= '"';

                $style = '';
                if ( in_array($column_key, $hidden) )
                        $style = 'display:none;';

                if ( isset($styles[$type]) && isset($styles[$type][$column_key]) )
                        $style .= ' ' . $styles[$type][$column_key];
                $style = ' style="' . $style . '"';
?>
        <th scope="col" <?php echo $id ? "id=\"$column_key\"" : ""; echo $class; echo $style; ?>><?php echo $column_display_name; ?></th>
<?php }
}
}

if(!function_exists('get_column_headers')) {
function get_column_headers($page) {
        global $_wp_column_headers;

        if ( !isset($_wp_column_headers) )
                $_wp_column_headers = array();

        // Store in static to avoid running filters on each call
        if ( isset($_wp_column_headers[$page]) )
                return $_wp_column_headers[$page];

	return array();
}
}

if(!function_exists('get_hidden_columns')) {
function get_hidden_columns($page) {
        $page = str_replace('.php', '', $page);
        return (array) get_user_option( 'manage-' . $page . '-columns-hidden', 0, false );
}
}

if(!function_exists('_admin_search_query')) {
function _admin_search_query() {
        echo isset($_GET['s']) ? attribute_escape( stripslashes( $_GET['s'] ) ) : '';
}
}

if(!function_exists('inline_edit_term_row')) {
function inline_edit_term_row($type) {

        if ( ! current_user_can( 'manage_categories' ) )
                return;

        $is_tag = $type == 'edit-tags';
        $columns = get_column_headers($type);
        $hidden = array_intersect( array_keys( $columns ), array_filter( get_hidden_columns($type) ) );
        $col_count = count($columns) - count($hidden);
        ?>

<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
        <tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $col_count; 
?>">

                <fieldset><div class="inline-edit-col">
                        <h4><?php _e( 'Quick Edit' ); ?></h4>

                        <label>
                                <span class="title"><?php _e( 'Name' ); ?></span>
                                <span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" /></span>
                        </label>

                        <label>
                                <span class="title"><?php _e( 'Slug' ); ?></span>
                                <span class="input-text-wrap"><input type="text" name="slug" class="ptitle" value="" /></span>
                        </label>

                </div></fieldset>

<?php

        $core_columns = array( 'cb' => true, 'description' => true, 'name' => true, 'slug' => true, 'posts' => true );

        foreach ( $columns as $column_name => $column_display_name ) {
                if ( isset( $core_columns[$column_name] ) )
                        continue;
                do_action( 'quick_edit_custom_box', $column_name, $type );
        }

?>

        <p class="inline-edit-save submit">
                <a accesskey="c" href="#inline-edit" title="<?php _e('Cancel'); ?>" class="cancel button-secondary alignleft"><?php _e('Cancel'); ?></a>
                <?php $update_text = ( $is_tag ) ? __( 'Update Tag' ) : __( 'Update Category' ); ?>
                <a accesskey="s" href="#inline-edit" title="<?php echo attribute_escape( $update_text ); ?>" class="save button-primary alignright"><?php echo $update_text; ?></a>
                <img class="waiting" style="display:none;" src="images/loading.gif" alt="" />
                <span class="error" style="display:none;"></span>
                <?php wp_nonce_field( 'taxinlineeditnonce', '_inline_edit', false ); ?>
                <br class="clear" />
        </p>
        </td></tr>
        </tbody></table></form>
<?php
}
}
