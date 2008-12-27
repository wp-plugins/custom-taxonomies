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

/**
 * These functions/classes should already exist, since their category-specific
 * counterparts exist, but they don't, so here they are.
 **/

if(!class_exists('Walker_TermDropdown')) {
class Walker_TermDropdown extends Walker {
        /**
         * @see Walker::$tree_type
         * @since 2.1.0
         * @var string
         */
        var $tree_type;

        /**
         * @see Walker::$db_fields
         * @since 2.1.0
         * @todo Decouple this
         * @var array
         */
        var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');

	function Walker_TermDropdown($taxonomy) {
		$this->tree_type = $taxonomy;
	}

        /**
         * @see Walker::start_el()
         * @since 2.1.0
         *
         * @param string $output Passed by reference. Used to append additional content.
         * @param object $category Category data object.
         * @param int $depth Depth of category. Used for padding.
         * @param array $args Uses 'selected', 'show_count', and 'show_last_update' keys, if they exist.
         */
        function start_el(&$output, $term, $depth, $args) {
                $pad = str_repeat('&nbsp;', $depth * 3);

                $cat_name = apply_filters('list_cats', $term->name, $term);
                $output .= "\t<option class=\"level-$depth\" value=\"".$term->term_id."\"";
                if ( $term->term_id == $args['selected'] )
                        $output .= ' selected="selected"';
                $output .= '>';
                $output .= $pad.$cat_name;
                if ( $args['show_count'] )
                        $output .= '&nbsp;&nbsp;('. $term->count .')';
                if ( $args['show_last_update'] ) {
                        $format = 'Y-m-d';
                        $output .= '&nbsp;&nbsp;' . gmdate($format, $term->last_update_timestamp);
                }
                $output .= "</option>\n";
        }
}
}

if(!class_exists('Walker_Term_Checklist')) {
class Walker_Term_Checklist extends Walker {
        var $tree_type;
        var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function Walker_Term_Checklist($taxonomy) {
		$this->tree_type = $taxonomy;
	}

        function start_lvl(&$output, $depth, $args) {
                $indent = str_repeat("\t", $depth);
                $output .= "$indent<ul class='children'>\n";
        }

        function end_lvl(&$output, $depth, $args) {
                $indent = str_repeat("\t", $depth);
                $output .= "$indent</ul>\n";
        }

        function start_el(&$output, $term, $depth, $args) {
                extract($args);

                $class = in_array( $term->term_id, $popular_terms ) ? ' class="popular-'.$this->tree_type.'"' : '';
                $output .= "\n<li id='{$this->tree_type}-{$term->term_id}'{$class}>" . '<label class="selectit"><input value="' . $term->term_id . '" type="checkbox" name="post_'.$this->tree_type.'[]" id="in-' . $this->tree_type . '-' . $term->term_id . '"' . (in_array( $term->term_id, $selected_terms ) ? ' checked="checked"' : "" ) . '/> ' . wp_specialchars( apply_filters('the_term', $term->name )) . '</label>';
        }

        function end_el(&$output, $term, $depth, $args) {
                $output .= "</li>\n";
        }
}
}

/* Does exist but is tied to categories despite function name */
function custax_wp_popular_terms_checklist( $taxonomy, $default = 0, $number = 10, $echo = true ) {
        global $post_ID;
        if ( $post_ID )
                $checked_terms = wp_get_object_terms($post_ID, $taxonomy);
        else
                $checked_terms = array();
        $terms = get_terms( $taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => $number, 'hierarchical' => false ) );

        $popular_ids = array();
        foreach ( (array) $terms as $term ) {
                $popular_ids[] = $term->term_id;
                if ( !$echo ) // hack for AJAX use
                        continue;
                $id = "popular-$taxonomy-$term->term_id";
                ?>

                <li id="<?php echo $id; ?>" class="popular-<?php echo $taxonomy; ?>">
                        <label class="selectit">
                        <input id="in-<?php echo $id; ?>" type="checkbox" value="<?php echo (int) $term->term_id; ?>" />
                                <?php echo wp_specialchars( apply_filters( 'the_'.$taxonomy, $term->name ) ); ?>
                        </label>
                </li>

                <?php
        }
        return $popular_ids;
}

function custax_wp_dropdown_terms( $taxonomy, $args = '' ) {
        $defaults = array(
                'show_option_all' => '', 'show_option_none' => '',
                'orderby' => 'ID', 'order' => 'ASC',
                'show_last_update' => 0, 'show_count' => 0,
                'hide_empty' => 1, 'child_of' => 0,
                'exclude' => '', 'echo' => 1,
                'selected' => 0, 'hierarchical' => 0,
                'name' => $taxonomy, 'class' => 'postform',
                'depth' => 0, 'tab_index' => 0
        );

        $defaults['selected'] = get_query_var( $taxonomy );

        $r = wp_parse_args( $args, $defaults );
        $r['include_last_update_time'] = $r['show_last_update'];
        extract( $r );

        $tab_index_attribute = '';
        if ( (int) $tab_index > 0 )
                $tab_index_attribute = " tabindex=\"$tab_index\"";

        $terms = get_terms( $taxonomy, $r );

        $output = "<select name='$name' id='$name' class='$class' $tab_index_attribute>\n";

	if ( $show_option_all && ! empty( $terms )) {
		$show_option_all = apply_filters( 'list_terms', $show_option_all );
		$output .= "\t<option value='0'>$show_option_all</option>\n";
	}

	if ( $show_option_none ) {
		$show_option_none = apply_filters( 'list_terms', $show_option_none );
		$output .= "\t<option value='-1'>$show_option_none</option>\n";
	}


        if ( ! empty( $terms ) ) {
                if ( $hierarchical )
                        $depth = $r['depth'];  // Walk the full depth.
                else
                        $depth = -1; // Flat.

                $output .= custax_walk_term_dropdown_tree( $taxonomy, $terms, $depth, $r );
        }
        $output .= "</select>\n";

	if( empty( $terms ) && $hide_empty )
		$output = '';

        $output = apply_filters( 'wp_dropdown_terms', $output );

        if ( $echo )
                echo $output;
        return $output;
}

function custax_walk_term_dropdown_tree() {
        $args = func_get_args();
        $walker = new Walker_TermDropdown(array_shift($args));
        return call_user_func_array(array( &$walker, 'walk' ), $args );
}

function custax_dropdown_terms( $taxonomy, $default = 0, $parent = 0, $popular_ids = array() ) {
        global $post_ID;
        custax_wp_term_checklist($taxonomy, $post_ID);
}

function custax_wp_term_checklist( $taxonomy, $post_id = 0, $descendants_and_self = 0, $selected_terms = false, $popular_terms = false ) {
        $walker = new Walker_Term_Checklist($taxonomy);
        $descendants_and_self = (int) $descendants_and_self;
        $args = array();

        if ( is_array( $selected_terms ) )
                $args['selected_terms'] = $selected_terms;
        elseif ( $post_id )
                $args['selected_terms'] = wp_get_object_terms($post_id, $taxonomy, array( 'fields' => 'ids' ) );
        else
                $args['selected_terms'] = array();

        if ( is_array( $popular_terms ) )
                $args['popular_terms'] = $popular_terms;
        else
                $args['popular_terms'] = get_terms( $taxonomy, array( 'fields' => 'ids', 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );

        if ( $descendants_and_self ) {
		if(is_taxonomy_hierarchical($taxonomy)) {
	                $terms = get_terms( $taxonomy, array( 'child_of' => $descendants_and_self, 'hierarchical' => 0, 'hide_empty' => 0 ) );
        	        $self = get_term( $descendants_and_self, $taxonomy );
                	array_unshift( $terms, $self );
		}
		else {
        	        $self = get_term( $descendants_and_self, $taxonomy );
			$terms = array($self);
		}
        } else {
                $terms = get_terms( $taxonomy, array( 'get' => 'all' ) );
        }

        // Post process $terms rather than adding an exclude to the get_terms() query to keep the query the same across all posts (for any query cache)
        $checked_terms = array();
        for ( $i = 0; isset($terms[$i]); $i++ ) {
                if ( in_array($terms[$i]->term_id, $args['selected_terms']) ) {
                        $checked_terms[] = $terms[$i];
                        unset($terms[$i]);
                }
        }

        // Put checked cats on top
        echo call_user_func_array(array(&$walker, 'walk'), array($checked_terms, 0, $args));
        // Then the rest of them
        echo call_user_func_array(array(&$walker, 'walk'), array($terms, 0, $args));
}

