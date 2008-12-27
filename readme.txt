=== Plugin Name ===
Contributors: bkrausz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=2105034
Tags: admin, administration, taxonomy, taxonomies
Requires at least: 2.5
Tested up to: 2.7
Stable tag: trunk

Custom Taxonomies provides a full administrative interface for creating 
and using taxonomies beyond the standard Tags and Categories.

== Description ==

Wordpress provides a lot of functionality geared towards the use of 
generic "taxonomies", such as Tags and Categories, but no way to manage 
the taxonomies themselves.  This plugin allows you to:

*   Create custom taxonomies with admin-set names and slugs for posts, 
    pages, or links
*   Define whether or not to accept hierarchies, descriptions, multiple 
    selections, and/or tag-style entry
*   Manage each taxonomy's terms in a full AJAX-interface
*   Assign terms to posts, pages, and links just as you would with 
    categories and tags

== Installation ==

1. Upload `custom-taxonomies` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use the `Taxonomies` page in `Settings` to create and manage taxonomies
1. Use the following functions to use taxonomies in your themes:
*  [get_term](http://codex.wordpress.org/Function_Reference/get_term)
*  [get_term_by](http://codex.wordpress.org/Function_Reference/get_term_by)
*  [get_term_children](http://codex.wordpress.org/Function_Reference/get_term_children)
*  [get_terms](http://codex.wordpress.org/Function_Reference/get_terms)
*  [is_term](http://codex.wordpress.org/Function_Reference/is_term)
*  [wp_get_object_terms](http://codex.wordpress.org/Function_Reference/wp_get_object_terms)

= Example Use =

Here are some examples of how you can implement the taxonomies in your themes:

* Display a comma-seperated list of taxonomy 'thing' in the format "Things: Thing 1, Thing 2":
`<?php if(function_exists('custax_the_terms')) custax_the_terms('thing', 'Things: ', ', ', '<br />'); ?>`

* More examples coming soon, especially once a workaround is found for (this bug)[http://trac.wordpress.org/ticket/8731].

= Known Issues =

There seems to be a bug in Wordpress that makes listing objects in a taxonomy show page drafts.  There exist workaround, and they are being investigated.  
See (this bug)[http://trac.wordpress.org/ticket/8731].

== Frequently Asked Questions ==

= Isn't Automattic going to develop this into Wordpress? =

In theory yes, the functionality is all there, all Automattic has to do 
is build the functionality into the administrative interface.  The 
problem is, there seems to be relatively little demand in terms of 
quantity for the functionaliy (though those that do have a need seem to 
have a strong need for it).  Similar to the advanced roles 
functionality, they may end up putting it on the backburner and letting 
plugins provide the functionality, making the core code less 
complicated.

== Screenshots ==

1. A dynamically generated term edit screen. [Full size](http://wordpress.org/extend/plugins/custom-taxonomies/screenshot-1.png)
