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
 * These functions/classes should already exist (or do exist with issues), 
 * since their category-specific counterparts exist, but they don't, so here 
 * they are.
 **/

function custax_the_terms( $taxonomy, $before = '', $sep = '', $after = '' ) {
	$terms = get_the_terms( 0, $taxonomy );

	if ( is_wp_error( $terms ) )
		return false;

	if ( empty( $terms ) )
		return false;

	foreach ( $terms as $term ) {
		/** TODO: links do not work, see http://trac.wordpress.org/ticket/8731
		$link = get_term_link( $term, $taxonomy );
		if ( is_wp_error( $link ) )
			return false;
		$term_links[] = '<a href="' . $link . '" rel="tag">' . $term->name . '</a>';
		**/

		$term_links[] = $term->name;
	}

	$term_links = apply_filters( "term_links-$taxonomy", $term_links );

	echo $before . join( $sep, $term_links ) . $after;
}

function custax_list_terms( $taxonomy, $args = '' ) {
        $defaults = array(
                'show_option_all' => '', 'orderby' => 'name',
                'order' => 'ASC', 'show_last_update' => 0,
                'style' => 'list', 'show_count' => 0,
                'hide_empty' => 1, 'use_desc_for_title' => 1,
                'child_of' => 0, 'feed' => '', 'feed_type' => '',
                'feed_image' => '', 'exclude' => '', 'current_category' => 0,
                'hierarchical' => true, 'title_li' => '',
                'echo' => 1, 'depth' => 0
        );

        $r = wp_parse_args( $args, $defaults );

        if ( !isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
                $r['pad_counts'] = true;
        }

        if ( isset( $r['show_date'] ) ) {
                $r['include_last_update_time'] = $r['show_date'];
        }

	if ( $r['hierarchcial'] && !is_taxonomy_hierarchical($taxonomy) ) {
		$r['hierarchical'] = false;
	}

        extract( $r );

        $terms = get_terms( $taxonomy, $r );
        $output = '';
        if ( $title_li && 'list' == $style )
                        $output = '<li class="terms">' . $r['title_li'] . '<ul>';

        if ( empty( $terms ) ) {
                if ( 'list' == $style )
                        $output .= '<li>' . __( "No terms" ) . '</li>';
                else
                        $output .= __( "No terms" );
        } else {
                global $wp_query;

                if( !empty( $show_option_all ) )
                        if ( 'list' == $style )
                                $output .= '<li><a href="' .  get_bloginfo( 'url' )  . '">' . $show_option_all . '</a></li>';
                        else
                                $output .= '<a href="' .  get_bloginfo( 'url' )  . '">' . $show_option_all . '</a>';

                if ( empty( $r['current_term'] ) && is_tax() )
                        $r['current_term'] = $wp_query->get_queried_object_id();
                if ( $hierarchical )
                        $depth = $r['depth'];
                else
                        $depth = -1; // Flat.

                $output .= custax_walk_term_tree( $taxonomy, $terms, $depth, $r );
        }

        if ( $title_li && 'list' == $style )
                $output .= '</ul></li>';

        $output = apply_filters( 'wp_list_terms', $output );

        if ( $echo )
                echo $output;
        else
                return $output;
}



?>
