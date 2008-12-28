=== Plugin Name ===
Contributors: bkrausz
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=2105034
Tags: admin, administration, taxonomy, taxonomies
Requires at least: 2.5
Tested up to: 2.7
Stable tag: 1.1

Custom Taxonomies provides a full administrative interface for creating 
and using taxonomies beyond the standard Tags and Categories.

== Description ==

WordPress provides a lot of functionality geared towards the use of 
generic "taxonomies", such as Tags and Categories, but no way to manage 
the taxonomies themselves.  This plugin allows you to:

*   Create custom taxonomies with admin-set names and slugs for posts, 
    pages, or links
*   Define whether or not to accept hierarchies, descriptions, multiple 
    selections, and/or tag-style entry
*   Manage each taxonomy's terms in a full AJAX-interface
*   Assign terms to posts, pages, and links just as you would with 
    categories and tags
*   __NEW:__ Add widgets for your taxonomies into your site

== Installation ==

1. Upload `custom-taxonomies` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use the `Taxonomies` page in `Settings` to create and manage taxonomies
1. After creating a taxonomy, go to the `Widgets` page in `Appearance` to add a list of terms into your site's sidebar
1. Use the examples below to use taxonomies in your themes:

= Example Use =

Here are some examples of how you can implement the taxonomies in your themes:

* Display a comma-seperated list of a post or page's taxonomy 'thing' in the format "Things: Thing 1, Thing 2":

`\<?php if\(function_exists\('custax\_the\_terms'\)\) custax\_the\_terms\('thing', 'Things: ', ', ', '\<br /\>'\); ?\>`

* Display a list of all terms for the taxonomy 'thing':

`\<?php if\(function_exists\('custax\_list\_terms'\)\) custax_list_terms\('thing'\); ?\>`

Note that neither of these will provide links to a list of all objects with a particular term.  This will be fixed once a workaround is found for [this bug](http://trac.wordpress.org/ticket/8731).

= Advanced Use =

If you'd like to use these taxonomies in a more advanced way, please see the following documentation (note that most of it has yet to be written, I 
will be contributing heavily to this shortly):

*  [get\_term](http://codex.wordpress.org/Function_Reference/get_term)
*  [get\_term\_by](http://codex.wordpress.org/Function_Reference/get_term_by)
*  [get\_term\_children](http://codex.wordpress.org/Function_Reference/get_term_children)
*  [get\_terms](http://codex.wordpress.org/Function_Reference/get_terms)
*  [is\_term](http://codex.wordpress.org/Function_Reference/is_term)
*  [wp\_get\_object\_terms](http://codex.wordpress.org/Function_Reference/wp_get_object_terms)

= Known Issues =

There seems to be a bug in WordPress that makes listing objects in a taxonomy show page drafts.  There exist workaround, and they are being 
investigated.  See [this bug](http://trac.wordpress.org/ticket/8731).

== Frequently Asked Questions ==

= Isn't Automattic going to develop this into WordPress? =

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
