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


?>
