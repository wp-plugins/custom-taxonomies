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

class custax {

	var $id;
	var $slug;
	var $name;
	var $plural;
	var $object_type;
	var $hierarchical;
	var $multiple;
	var $descriptions;
	var $show_column;
	var $rewrite_rules;

	function custax($tax) {
		$this->id = $tax->id;
		$this->slug = $tax->slug;
		$this->name = $tax->name;
		$this->plural = $tax->plural;
		$this->object_type = $tax->object_type;
		$this->hierarchical = $tax->hierarchical;
		$this->multiple = $tax->multiple;
		$this->descriptions = $tax->descriptions;
		$this->show_column = $tax->show_column;
		$this->rewrite_rules = $tax->rewrite_rules;

		$args = array(
			//TODO: this will add the slug as a query var...this may not work if the slug is already a query var.  Add checks for this...
			'query_var' => $this->slug,
			'hierarchical' => $this->hierarchical,
		);

		switch($this->object_type) {
		case 'link':
			$this->manage_page = 'link-manager.php';
			$manage_col_hook = 'manage_link_custom_column';
			$manage_col_filter = 'manage_link-manager_columns';
		break;
		case 'page':
			$this->manage_page = 'edit-pages.php';
			$args['update_count_callback'] = 'custax_update_term_count';
			$manage_col_hook = 'manage_pages_custom_column';
			$manage_col_filter = 'manage_edit-pages_columns';
		break;
		default:
			$this->object_type = 'post';
			$this->manage_page = 'edit.php';
			$args['update_count_callback'] = 'custax_update_term_count';
			$manage_col_hook = 'manage_posts_custom_column';
			$manage_col_filter = 'manage_edit_columns';
		break;
		}

		if(custax_old_version())
			$this->manage_page = 'edit.php';

		register_taxonomy($this->slug, $this->object_type, $args);

		add_action('wp_ajax_inline-save-custax', array(&$this, 'register_column'), 5);
		add_action('wp_ajax_add-quick-' . $this->slug, array(&$this, 'ajax_quick_add'));

		add_action('wp_ajax_add-' . $this->slug, array(&$this, 'register_column'), 5);
		add_action('wp_ajax_add-' . $this->slug, array(&$this, 'ajax_add'));

		add_action('admin_menu', array(&$this, 'register_column'), 5);
		add_action('admin_menu', array(&$this, 'admin_menu'));

		add_action('widgets_init', array(&$this, 'register_widget'));

		if($this->show_column) {
			add_action($manage_col_hook, array(&$this, 'manage_column'), 10, 2);
			add_filter($manage_col_filter, array(&$this, 'manage_column_name'));
		}

		if($this->object_type == 'link') {
			add_action('add_link', array(&$this, 'save'));
			add_action('edit_link', array(&$this, 'save'));
			add_action('delete_link', array(&$this, 'delete'));
			$add_object_page_suffix = 'add';
		}
		else {
			add_action('save_post', array(&$this, 'save'));
			add_action('delete_post', array(&$this, 'delete'));
			$add_object_page_suffix = 'new';
		}

		$edit_term_hook_name = 'admin_print_scripts-' . $this->object_type . 's_page_custax_' . $this->slug;
		add_action( $edit_term_hook_name, array(&$this, 'enqueue_scripts') );

		$add_object_hook_end  = $this->object_type . '-' . $add_object_page_suffix . '.php';
		$edit_object_hook_end = $this->object_type . '.php';
		add_action( 'admin_print_scripts-' . $add_object_hook_end,  array(&$this, 'select_enqueue_scripts') );
		add_action( 'admin_print_scripts-' . $edit_object_hook_end, array(&$this, 'select_enqueue_scripts') );
		add_action( 'admin_head-' . $add_object_hook_end,  array(&$this, 'select_scripts'), 20 );
		add_action( 'admin_head-' . $edit_object_hook_end, array(&$this, 'select_scripts'), 20 );

		add_action( 'init', array(&$this, 'rewrite_rules') );
	}

	function rewrite_rules() {
		global $wp_rewrite;
		//TODO: not sure what the side effects of all of this is...have to be careful
		add_rewrite_tag('%'.$this->slug.'%', '(.+)');
		if($this->rewrite_rules) {
			$wp_rewrite->add_permastruct($this->slug, $this->slug.'/%'.$this->slug.'%');
			custax_rewrite_rules($this->slug, false);
		}
	}

	function register_widget() {
		if ( !$options = get_option( 'widget_terms_'.$this->slug ) )
			$options = array();

		$widget_ops = array('classname' => 'widget_terms_'.$this->slug, 'description' => sprintf(__( 'A list or dropdown of %s', CUSTAX_DOMAIN), $this->plural));
		$id = false;
		foreach ( (array) array_keys($options) as $o ) {
			$id = "terms-$this->slug-$o";

			wp_register_sidebar_widget($id, $this->plural, array(&$this, 'widget'), $widget_ops, array('number' => $o));
			wp_register_widget_control($id, $this->plural, array(&$this, 'widget_control'), array( 'id_base' => 'terms-'.$this->slug ), array( 'number' => $o ) );
		}

		// If there are none, we register the widget's existance with a generic template
		if ( !$id ) {
			$id = "terms-$this->slug-1";

			wp_register_sidebar_widget($id, $this->plural, array(&$this, 'widget'), $widget_ops, array('number' => -1));
			wp_register_widget_control($id, $this->plural, array(&$this, 'widget_control'), array( 'id_base' => 'terms-'.$this->slug ), array( 'number' => -1 ) );
		}
	}

	function widget($args, $widget_args = 1) {
		extract($args, EXTR_SKIP);
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract($widget_args, EXTR_SKIP);

		$options = get_option('widget_terms_'.$this->slug);
		if ( !isset($options[$number]) )
			return;

		$c = $options[$number]['count'] ? '1' : '0';
		$h = ( $options[$number]['hierarchical'] && $this->hierarchical ) ? '1' : '0';
		$d = $options[$number]['dropdown'] ? '1' : '0';

		$title = empty($options[$number]['title']) ? $this->plural : apply_filters('widget_title', $options[$number]['title']);

		echo $before_widget;
		echo $before_title . $title . $after_title;

		$term_args = array('orderby' => 'name', 'show_count' => $c, 'hierarchical' => $h, 'slug_value' => 1);

		if ( $d ) {
			$term_args['show_option_none'] = __('Select ').$this->name;
			custax_wp_dropdown_terms($this->slug, $term_args);
			?>
			<script type='text/javascript'>
			/* <![CDATA[ */
			var dropdown = document.getElementById("<?php echo $this->slug; ?>");
			function on<?php echo ucfirst($this->slug); ?>Change() {
				if ( dropdown.options[dropdown.selectedIndex].value != 0 && dropdown.options[dropdown.selectedIndex].value != -1 ) {
					<?php if($this->rewrite_rules) { ?>
					location.href = "<?php echo get_option('home'); ?>/<?php echo $this->slug; ?>/"+dropdown.options[dropdown.selectedIndex].value;
					<?php } else { ?>
					location.href = "<?php echo get_option('home'); ?>/?<?php echo $this->slug; ?>="+dropdown.options[dropdown.selectedIndex].value;
					<?php } ?>
				}
			}
			dropdown.onchange = on<?php echo ucfirst($this->slug); ?>Change;
			/* ]]> */
			</script>
			<?php
		} else {
			echo '<ul>';
			$term_args['title_li'] = '';
			custax_list_terms($this->slug, $term_args);
			echo '</ul>';
		}
		echo $after_widget;
	}

	function widget_control($widget_args) {
		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract($widget_args, EXTR_SKIP);

		$options = get_option('widget_terms_'.$this->slug);

		if ( !is_array( $options ) )
			$options = array();

		if ( !$updated && !empty($_POST['sidebar']) ) {
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();
			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( (array) $this_sidebar as $_widget_id ) {
				if ( is_array($wp_registered_widgets[$_widget_id]['callback']) &&
					isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "terms-$this->slug-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
				}
			}

			foreach ( (array) $_POST['widget-terms-'.$this->slug] as $widget_number => $widget_term ) {
				if ( !isset($widget_term['title']) && isset($options[$widget_number]) ) // user clicked cancel
					continue;
				$title = trim(strip_tags(stripslashes($widget_term['title'])));
				$count = isset($widget_term['count']);
				$hierarchical = isset($widget_term['hierarchical']);
				$dropdown = isset($widget_term['dropdown']);
				$options[$widget_number] = compact( 'title', 'count', 'hierarchical', 'dropdown' );
			}

			update_option('widget_terms_'.$this->slug, $options);
			$updated = true;
		}

		if ( -1 == $number ) {
			$title = '';
			$count = false;
			$hierarchical = false;
			$dropdown = false;
			$number = '%i%';
		} else {
			$title = attribute_escape( $options[$number]['title'] );
			$count = (bool) $options[$number]['count'];
			$hierarchical = (bool) $options[$number]['hierarchical'];
			$dropdown = (bool) $options[$number]['dropdown'];
		}
		?>
			<p>
			<label for="terms-<?php echo $this->slug; ?>-title-<?php echo $number; ?>">
			<?php _e( 'Title:' ); ?>
			<input class="widefat" id="terms-<?php echo $this->slug; ?>-title-<?php echo $number; ?>" name="widget-terms-<?php echo $this->slug; ?>[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" />
			</label>
			</p>

			<p>
			<label for="terms-<?php echo $this->slug; ?>-dropdown-<?php echo $number; ?>">
			<input type="checkbox" class="checkbox" id="terms-<?php echo $this->slug; ?>-dropdown-<?php echo $number; ?>" name="widget-terms-<?php echo $this->slug; ?>[<?php echo $number; ?>][dropdown]"<?php checked( $dropdown, true ); ?> />
			<?php _e( 'Show as dropdown' ); ?>
			</label>
			<br />
			<label for="terms-<?php echo $this->slug; ?>-count-<?php echo $number; ?>">
			<input type="checkbox" class="checkbox" id="terms-<?php echo $this->slug; ?>-count-<?php echo $number; ?>" name="widget-terms-<?php echo $this->slug; ?>[<?php echo $number; ?>][count]"<?php checked( $count, true ); ?> />
			<?php _e( 'Show '.$this->object_type.' counts' ); ?>
			</label>
		<?php if($this->hierarchical) : ?>
			<br />
			<label for="terms-<?php echo $this->slug; ?>-hierarchical-<?php echo $number; ?>">
			<input type="checkbox" class="checkbox" id="terms-<?php echo $this->slug; ?>-hierarchical-<?php echo $number; ?>" name="widget-terms-<?php echo $this->slug; ?>[<?php echo $number; ?>][hierarchical]"<?php checked( $hierarchical, true ); ?> />
			<?php _e( 'Show hierarchy' ); ?>
			</label>
		<?php endif; ?>
			</p>

			<input type="hidden" name="widget-terms-<?php echo $this->slug; ?>[<?php echo $number; ?>][submit]" value="1" />
		<?php
	}

	function save($id) {
		$terms = $_POST['post_' . $this->slug];
		if(empty($terms) || !is_array($terms))
			return;
		$terms = array_map('intval', $terms);
		$terms = array_unique($terms);

		wp_set_object_terms( (int)$id, $terms, $this->slug );
	}

	function delete($id) {
		wp_delete_object_term_relationships( (int)$id, $this->slug );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'admin-terms' );
		wp_enqueue_script( 'inline-edit-custax' );
	}

	function register_column() {
		$cols = array(
			'cb' => '<input type="checkbox" />',
			'name' => __('Name'),
		);

		if($this->descriptions)
			$cols['description'] = __('Description');

		$cols['slug'] = __('Slug');

		switch($this->object_type) {
		case 'link':
			$cols['links'] = __('Links');
		break;
		case 'page':
			$cols['pages'] = __('Pages');
		break;
		default:
			$cols['posts'] = __('Posts');
		break;
		}

		register_column_headers('edit-'.$this->slug, $cols);
	}

	function manage_column($name, $id) {
		if($name != $this->slug)
			return;
		$terms = wp_get_object_terms($id, $this->slug);
		$first = true;
		foreach($terms AS $term) {
			if($first)
				$first = false;
			else
				echo ', ';
			echo '<a href="' . $this->manage_page . '?' . $this->slug . '=' . $term->slug . '">';
			echo $term->name;
			echo '</a>';
		}
	}

	function manage_column_name($cols) {
		$ends = array('comments', 'date', 'rel', 'visible');
		$end = array();
		foreach($cols AS $k=>$v) {
			if(in_array($k, $ends)) {
				$end[$k] = $v;
				unset($cols[$k]);
			}
		}
		$cols[$this->slug] = $this->plural;
		$cols = array_merge($cols, $end);
		return $cols;
	}

	function admin_menu() {
//TODO: add option for modify permissions level (admin vs. contributor)??
		$capability = 'manage_categories';

		add_meta_box($this->slug.'div', $this->plural, array(&$this, 'select'), $this->object_type, 'side');

		add_submenu_page($this->manage_page, $this->plural, $this->plural, $capability, 'custax_' . $this->slug, array(&$this, 'manage'));
	}

	function ajax_quick_add() {
		check_ajax_referer( 'add-quick-'.$this->slug );
		if ( !current_user_can( 'manage_categories' ) )
			die('-1');

		$names = explode(',', $_POST['new'.$this->slug]);
		if ( 0 > $parent = (int) $_POST['new'.$this->slug.'_parent'] )
			$parent = 0;
		$post_term = isset($_POST['post_'.$this->slug])? (array) $_POST['post_'.$this->slug] : array();
		$checked_terms = array_map( 'absint', (array) $post_term );
		$popular_ids = isset( $_POST['popular_ids'] ) ? array_map( 'absint', explode( ',', $_POST['popular_ids'] ) ) : false;

		$x = new WP_Ajax_Response();

		foreach ( $names as $term_name ) {
			$term_name = trim($term_name);
			$term_nicename = sanitize_title($term_name);
			if ( '' === $term_nicename )
				continue;
			$term = wp_insert_term($term_name, $this->slug, array('parent' => $parent));
			if(!$term || is_wp_error($term))
				die(0);
			$term_id = $term['term_id'];
			$checked_terms[] = $term_id;
			if ( $parent ) // Do these all at once in a second
				continue;
			$term = get_term( $term_id, $this->slug );
			ob_start();
				custax_wp_term_checklist( $this->slug, 0, $term_id, $checked_terms );
			$data = ob_get_contents();
			ob_end_clean();
			$x->add( array(
				'what' => $this->slug,
				'id' => $term_id,
				'data' => $data,
				'position' => -1
			) );
		}
		if ( $parent ) { // Foncy - replace the parent and all its children
			$parent = get_term( $parent, $this->slug );
			ob_start();
				custax_dropdown_terms( $this->slug, 0, $parent );
			$data = ob_get_contents();
			ob_end_clean();
			$x->add( array(
				'what' => $this->slug,
				'id' => $parent->term_id,
				'old_id' => $parent->term_id,
				'data' => $data,
				'position' => -1
			) );
		}
		$x->send();
		die();
	}

	function ajax_add() {
		check_ajax_referer( 'addterm' );
		if ( !current_user_can( 'manage_categories' ) )
			die('-1');

		if ( '' === trim($_POST['name']) ) {
			$x = new WP_Ajax_Response( array(
				'what' => 'cat',
				'id' => new WP_Error( 'name', __('You did not enter a name.') )
			) );
			$x->send();
			die();
		}

		if ( is_term( trim( $_POST['name'] ), $this->slug ) ) {
			$x = new WP_Ajax_Response( array(
				'what' => $this->slug,
				'id' => new WP_Error( 'exists', __('The term you are trying to create already exists.', CUSTAX_DOMAIN), array( 'form-field' => 'name' ) ),
			) );
			$x->send();
			die();
		}

		$term = wp_insert_term( $_POST['name'], $this->slug, $_POST );

		if ( is_wp_error($term) ) {
			$x = new WP_Ajax_Response( array(
				'what' => $this->slug,
				'id' => $term
			) );
			$x->send();
			die();
		}

		if ( !$term || (!$term = get_term( $term['term_id'], $this->slug )) )
			die('0');

		$level = 0;
		$term_full_name = $term->name;
		$_term = $term;
		while ( $_term->parent ) {
			$_term = get_term( $_term->parent, $this->slug );
			$term_full_name = $_term->name . ' &#8212; ' . $term_full_name;
			$level++;
		}
		$term_full_name = attribute_escape($term_full_name);

		$x = new WP_Ajax_Response( array(
			'what' => $this->slug,
			'id' => $term->term_id,
			'position' => -1,
			'data' => $this->_term_row( $term, $level, $term_full_name ),
			'supplemental' => array('name' => $term_full_name, 'show-link' => $this->name.' <a href="'.$this->slug.'-'.$term->term_id.'">'.$term_full_name.'</a> '.__( 'added', CUSTAX_DOMAIN ))
		) );
		$x->send();
		die();
	}

	function select_enqueue_scripts() {
		wp_enqueue_script('jquery-ui-tabs');
	}

	function select_scripts() {
//TODO: allow multiple = 0
//TODO: does this work with multiple taxonomies?
//TODO: can we make this one function for all taxonomies and not dynamically generate it?
	?>
	<script type="text/javascript">
	jQuery(document).ready(function() {
	// Tabs
	var termTabs =jQuery('#<?php echo $this->slug; ?>-tabs').tabs();

	// Ajax
	var newTerm = jQuery('#new<?php echo $this->slug; ?>').one( 'focus', function() { jQuery(this).val( '' ).removeClass( 'form-input-tip' ) } );
	jQuery('#<?php echo $this->slug; ?>-add-sumbit').click( function() { newTerm.focus(); } );
	var newTermParent = false;
	var newTermParentOption = false;
	var noSyncChecks = false; // prophylactic. necessary?
	var syncChecks = function() {
		if ( noSyncChecks )
			return;
		noSyncChecks = true;
		var th = jQuery(this);
		var c = th.is(':checked');
		var id = th.val().toString();
		jQuery('#in-<?php echo $this->slug; ?>-' + id + ', #in-popular-<?php echo $this->slug; ?>-' + id).attr( 'checked', c );
		noSyncChecks = false;
	};
	var popularTerms = jQuery('#<?php echo $this->slug; ?>checklist-pop :checkbox').map( function() { return parseInt(jQuery(this).val(), 10); } ).get().join(',');
	var termAddBefore = function( s ) {
		s.data += '&popular_ids=' + popularTerms + '&' + jQuery( '#<?php echo $this->slug; ?>checklist :checked' ).serialize();
		return s;
	};
	var termAddAfter = function( r, s ) {
		if ( !newTermParent ) newTermParent = jQuery('#new<?php echo $this->slug; ?>parent');
		if ( !newTermParentOption ) newTermParentOption = newTermParent.find( 'option[value=-1]' );
		jQuery(s.what + ' response_data', r).each( function() {
			var t = jQuery(jQuery(this).text());
			t.find( 'label' ).each( function() {
				var th = jQuery(this);
				var val = th.find('input').val();
				var id = th.find('input')[0].id;
				jQuery('#' + id).change( syncChecks ).change();
				if ( newTermParent.find( 'option[value=' + val + ']' ).size() )
					return;
				var name = jQuery.trim( th.text() );
				var o = jQuery( '<option value="' +  parseInt( val, 10 ) + '"></option>' ).text( name );
				newTermParent.prepend( o );
			} );
			newTermParentOption.attr( 'selected', true );
		} );
	};
	jQuery('#<?php echo $this->slug; ?>checklist').wpList( {
		alt: '',
		response: '<?php echo $this->slug; ?>-ajax-response',
		addBefore: termAddBefore,
		addAfter: termAddAfter
	} );
	jQuery('#<?php echo $this->slug; ?>-add-toggle').click( function() {
		jQuery(this).parents('div:first').toggleClass( 'wp-hidden-children' );
		termTabs.find( 'a[href="#<?php echo $this->slug; ?>-all"]' ).click();
		jQuery('#new<?php echo $this->slug; ?>').focus();
		return false;
	} );

	jQuery('a[href="#<?php echo $this->slug; ?>-all"]').click(function(){deleteUserSetting('<?php echo $this->slug; ?>');});
	jQuery('a[href="#<?php echo $this->slug; ?>-pop"]').click(function(){setUserSetting('<?php echo $this->slug; ?>','pop');});
	if ( 'pop' == getUserSetting('<?php echo $this->slug; ?>') )
		jQuery('a[href="#<?php echo $this->slug; ?>-pop"]').click();

	jQuery('.<?php echo $this->slug; ?>checklist .popular-<?php echo $this->slug; ?> :checkbox').change( syncChecks ).filter( ':checked' ).change();
	});
	</script>
	<?php
	}

	function select($object) {
	?>
	<ul id="<?php echo $this->slug; ?>-tabs" class="ui-tabs-nav">
		<li class="ui-tabs-selected"><a tabindex="3" href="#<?php echo $this->slug; ?>-all">All <?php echo $this->plural; ?></a></li>
		<li class=""><a tabindex="3" href="#<?php echo $this->slug; ?>-pop">Most Used</a></li>
	</ul>

	<div id="<?php echo $this->slug; ?>-pop" class="ui-tabs-panel" style="display: none;">
		<ul id="<?php echo $this->slug; ?>checklist-pop" class="<?php echo $this->slug; ?>checklist form-no-clear" >
		<?php $popular_ids = custax_wp_popular_terms_checklist($this->slug); ?>
		</ul>
	</div>

	<div id="<?php echo $this->slug; ?>-all" class="ui-tabs-panel" style="display: block;">
		<ul id="<?php echo $this->slug; ?>checklist" class="list:quick-<?php echo $this->slug.' '.$this->slug; ?>checklist form-no-clear">
		<?php custax_wp_term_checklist($this->slug, $object->ID, false, false, $popular_ids) ?>
		</ul>
	</div>

	<?php if ( current_user_can('manage_categories') ) : ?>
	<div id="<?php echo $this->slug; ?>-adder" class="wp-hidden-children">
		<h4><a id="<?php echo $this->slug; ?>-add-toggle" href="#<?php echo $this->slug; ?>-add" class="hide-if-no-js" tabindex="3"><?php echo __( '+ Add New' ).' '.$this->name; ?></a></h4>
		<p id="<?php echo $this->slug; ?>-add" class="wp-hidden-child term-add">
		<label class="hidden" for="new<?php echo $this->slug; ?>"><?php echo __( 'Add New' ).' '.$this->name; ?></label><input type="text" name="new<?php echo $this->slug; ?>" id="new<?php echo $this->slug; ?>" class="form-required form-input-tip" value="<?php _e( 'New name' ); ?>" tabindex="3" aria-required="true"/>
		<?php if($this->hierarchical) : ?>
                <label class="hidden" for="new<?php echo $this->slug; ?>_parent"><?php _e('Parent'); ?>:</label><?php 
		custax_wp_dropdown_terms( $this->slug, array( 'hide_empty' => 1, 'name' => 'new'.$this->slug.'_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => __('Parent'), 'tab_index' => 3 ) );
		?>
		<?php endif; ?>
                <input type="button" id="<?php echo $this->slug; ?>-add-sumbit" class="add:<?php echo $this->slug; ?>checklist:<?php echo $this->slug; ?>-add button term-add-submit" value="<?php _e( 'Add' ); ?>" tabindex="3" />
                <?php wp_nonce_field( 'add-quick-'.$this->slug, '_ajax_nonce', false ); ?>
                <span id="<?php echo $this->slug; ?>-ajax-response"></span>
	        </p>
	</div>
	<?php
	endif;
	}

	function _term_row( $term, $level, $name_override = false ) {
		$self = $_SERVER['SCRIPT_NAME'].'?page='.$_GET['page'];

		static $row_class = '';

		$term = get_term( $term, $this->slug, OBJECT, 'display' );

		$pad = str_repeat( '&#8212; ', $level );

		$row_class = 'alternate' == $row_class ? '' : 'alternate';

		$count = number_format_i18n( $term->count );
		$count = ( $count > 0 ) ? "<a href='{$this->manage_page}?{$this->slug}={$term->slug}'>$count</a>" : $count;

		$name = ( $name_override ? $name_override : $pad . ' ' . $term->name );
		$name = apply_filters( 'term_name', $name );
		$edit_link = "{$self}&amp;action=edit&amp;term_ID=$term->term_id";

		$out = '';
		$out .= '<tr id="'.$this->slug.'-' . $term->term_id . '" class="iedit ' . $row_class . '">';

		$columns = get_column_headers('edit-'.$this->slug);
		$hidden = get_hidden_columns('edit-'.$this->slug);

		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array($column_name, $hidden) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ($column_name) {
			case 'cb':
				$out .= '<th scope="row" class="check-column"> <input type="checkbox" name="delete_terms[]" value="' . $term->term_id . '" /></th>';
			break;
			case 'name':
				$qe_data = get_term( $term, $this->slug, OBJECT, 'edit' );
				$out .= '<td ' . $attributes . '><strong><a class="row-title" href="' . $edit_link . '" title="' . attribute_escape(sprintf(__('Edit "%s"'), $name)) . '">' . $name . '</a></strong><br />';
				$actions = array();
				$actions['edit'] = '<a href="' . $edit_link . '">' . __('Edit') . '</a>';
				$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __('Quick&nbsp;Edit') . '</a>';
				$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url("{$self}&amp;action=delete&amp;term_ID=$term->term_id", 'delete-term_' . $term->term_id) . "' onclick=\"if ( confirm('" . js_escape(sprintf(__("You are about to delete this term '%s'\n 'Cancel' to stop, 'OK' to delete.", CUSTAX_DOMAIN), $name )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
				$action_count = count($actions);
				$i = 0;
				$out .= '<div class="row-actions">';
				foreach ( $actions as $action => $link ) {
					++$i;
					( $i == $action_count ) ? $sep = '' : $sep = ' | ';
					$out .= "<span class='$action'>$link$sep</span>";
				}
				$out .= '</div>';
				$out .= '<div class="hidden" id="inline_' . $qe_data->term_id . '">';
				$out .= '<div class="name">' . $qe_data->name . '</div>';
				$out .= '<div class="slug">' . $qe_data->slug . '</div>';
				$out .= '<div class="level">' . $level . '</div>';
				$out .= '<div class="parent">' . $qe_data->parent . '</div></div></td>';
			break;
			case 'slug':
				$out .= "<td $attributes>$term->slug</td>";
			break;
			case 'description':
				$out .= "<td $attributes>$term->description</td>";
			break;
			case 'posts':
			case 'pages':
			case 'links':
				$attributes = 'class="'.$column_name.' column-'.$column_name.' num"' . $style;
				$out .= "<td $attributes>$count</td>";
			break;
			}
		}

		$out .= '</tr>';

		return $out;
	}

	function _term_rows( $terms, &$count, $parent = 0, $level = 0, $page = 1, $per_page = 20 ) {
		if ( empty($terms) ) {
			$args = array('hide_empty' => 0);
			if ( !empty($_GET['s']) )
				$args['search'] = $_GET['s'];
			$terms = get_terms( $this->slug, $args );
		}

		if ( !$terms )
			return false;

		$children = _get_term_hierarchy($this->slug);

		$start = ($page - 1) * $per_page;
		$end = $start + $per_page;
		$i = -1;
		ob_start();
		foreach ( $terms as $term ) {
			if ( $count >= $end )
				break;

			$i++;

			if ( $term->parent != $parent )
				continue;

			// If the page starts in a subtree, print the parents.
			if ( $count == $start && $term->parent > 0 ) {
				$my_parents = array();
				while ( $my_parent) {
					$my_parent = get_term($my_parent, $this->slug);
					$my_parents[] = $my_parent;
					if ( !$my_parent->parent )
						break;
					$my_parent = $my_parent->parent;
				}
				$num_parents = count($my_parents);
				while( $my_parent = array_pop($my_parents) ) {
					echo "\t" . $this->_term_row( $my_parent, $level - $num_parents );
					$num_parents--;
				}
			}

			if ( $count >= $start )
				echo "\t" . $this->_term_row( $term, $level );

			unset($terms[$i]); // Prune the working set
			$count++;

			if ( isset($children[$term->term_id]) )
				$this->_term_rows( $terms, $count, $term->term_id, $level + 1, $page, $per_page );

		}

		$output = ob_get_contents();
		ob_end_clean();

		echo $output;
	}

	function term_rows( $parent = 0, $level = 0, $terms = 0, $page = 1, $per_page = 20 ) {
		$count = 0;
		$this->_term_rows($terms, $count, $parent, $level, $page, $per_page);
	}

    function manage() {
	global $action, $term;

	$self = $_SERVER['SCRIPT_NAME'].'?page='.$_GET['page'];

	$title = $this->plural;

	wp_reset_vars( array('action', 'term') );

	if ( isset( $_GET['action'] ) && isset($_GET['delete_terms']) && ( 'delete' == $_GET['action'] || 'delete' == $_GET['action2'] ) )
		$action = 'bulk-delete';

	switch($action) {

	case 'addterm':
		check_admin_referer('addterm');

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		$ret = wp_insert_term($_POST['name'], $this->slug, $_POST);
		if ( $ret && !is_wp_error( $ret ) ) {
			$message = 1;
		} else {
			$message = 4;
		}
	break;

	case 'delete':
		$term_ID = (int) $_GET['term_ID'];
		check_admin_referer('delete-term_' .  $term_ID);

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		wp_delete_term( $term_ID, $this->slug);

		$message = 2;
	break;

	case 'bulk-delete':
		check_admin_referer('bulk-terms');

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		$terms = $_GET['delete_terms'];
		foreach( (array) $terms as $term_ID ) {
			wp_delete_term( $term_ID, $this->slug);
		}

		$message = 6;
	break;

	case 'edit':
		$title = __('Edit').' '.$this->name;

		$term_ID = (int) $_GET['term_ID'];

		$term = get_term($term_ID, $this->slug, OBJECT, 'edit');

		if ( empty($term_ID) ) { ?>
			<div id="message" class="updated fade"><p><strong><?php _e('Nothing was selected for editing.', CUSTAX_DOMAIN); ?></strong></p></div>
			<?php
			return;
		}

		do_action('edit_term_form_pre', $this->slug, $term); ?>

		<div class="wrap">
		<?php if(function_exists('scree_icon')) screen_icon(); ?>
		<h2><?php echo $title ?></h2>
		<div id="ajax-response"></div>
		<form name="editterm" id="editterm" method="post" action="<?php echo $self; ?>" class="validate">
		<input type="hidden" name="action" value="editedterm" />
		<input type="hidden" name="term_ID" value="<?php echo $term->term_id ?>" />
		<?php wp_original_referer_field(true, 'previous'); wp_nonce_field('update-term_' . $term_ID); ?>
			<table class="form-table">
				<tr class="form-field form-required">
					<th scope="row" valign="top"><label for="name"><?php _e('Name') ?></label></th>
					<td><input name="name" id="name" type="text" value="<?php if ( isset( $term->name ) ) echo attribute_escape($term->name); ?>" size="40" aria-required="true" />
		            <p><?php _e('The name is how the term appears on your site.', CUSTAX_DOMAIN); ?></p></td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top"><label for="slug"><?php _e('Slug') ?></label></th>
					<td><input name="slug" id="slug" type="text" value="<?php if ( isset( $term->slug ) ) echo attribute_escape(apply_filters('editable_slug', $term->slug)); ?>" size="40" />
		            <p><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p></td>
				</tr>

				<?php if($this->hierarchical) : ?>
				<tr class="form-field">
					<th scope="row" valign="top"><label for="parent"><?php _e('Parent') ?></label></th>
					<td><?php 
					custax_wp_dropdown_terms($this->slug, array('hide_empty' => 0, 'name' => 'parent', 'orderby' => 'name', 'selected' => $term->parent, 'hierarchical' => true, 'show_option_none' => __('None'))); ?>
			    <p><?php _e('This taxonomy can have a hierarchy. You might have a Jazz term, and under that have children categories for Bebop and Big Band. Totally optional.', CUSTAX_DOMAIN); ?></p></td>
				</tr>
				<?php endif; ?>

				<?php if($this->descriptions) : ?>
				<tr class="form-field">
					<th scope="row" valign="top"><label for="description"><?php _e('Description') ?></label></th>
					<td><textarea name="description" id="description" rows="5" cols="40"><?php if ( isset( $term->slug ) ) echo attribute_escape(apply_filters('editable_slug', $term->slug)); ?></textarea>
			    <p><?php _e('The description is not prominent by default, however some themes may show it.'); ?></p></td>
				</tr>
				<?php endif; ?>

			</table>
		<p class="submit"><input type="submit" class="button-primary" name="submit" value="<?php _e('Update Term', CUSTAX_DOMAIN); ?>" /></p>
		<?php do_action('edit_term_form', $this->slug, $term); ?>
		</form>
		</div>
		<?php
		return;
	break;

	case 'editedterm':
		$term_ID = (int) $_POST['term_ID'];
		check_admin_referer('update-term_' . $term_ID);

		if ( !current_user_can('manage_categories') )
			wp_die(__('Cheatin&#8217; uh?'));

		$ret = wp_update_term($term_ID, $this->slug, $_POST);

		if ( $ret && !is_wp_error( $ret ) )
			$message = 3;
		else
			$message = 5;
	break;

	}

	$can_manage = current_user_can('manage_categories');

	$messages[1] = __('Term added.', CUSTAX_DOMAIN);
	$messages[2] = __('Term deleted.', CUSTAX_DOMAIN);
	$messages[3] = __('Term updated.', CUSTAX_DOMAIN);
	$messages[4] = __('Term not added.', CUSTAX_DOMAIN);
	$messages[5] = __('Term not updated.', CUSTAX_DOMAIN);
	$messages[6] = __('Terms deleted.', CUSTAX_DOMAIN); ?>

	<div class="wrap nosubsub">
	<?php if(function_exists('scree_icon')) screen_icon(); ?>
	<h2><?php echo wp_specialchars( $title );
	if ( isset($_GET['s']) && $_GET['s'] )
		printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', wp_specialchars( stripslashes($_GET['s']) ) ); ?>
	</h2>

	<?php if ( isset($message) && ( $msg = (int) $message ) ) : ?>
	<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
	<?php endif; ?>

	<form class="search-form" action="<?php echo $self; ?>" method="get">
	<p class="search-box">
		<label class="hidden" for="term-search-input"><?php echo __( 'Search' ).' '.$this->plural; ?>:</label>
		<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
		<input type="text" class="search-input" id="term-search-input" name="s" value="<?php _admin_search_query(); ?>" />
		<input type="submit" value="<?php echo __( 'Search' ).' '.$this->plural; ?>" class="button" />
	</p>
	</form>
	<br class="clear" />

	<div id="col-container">

	<div id="col-right">
	<div class="col-wrap">
	<form id="posts-filter" action="<?php echo $self; ?>" method="get">
	<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>" />
	<div class="tablenav">
	<?php
	$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
	if ( empty($pagenum) )
		$pagenum = 1;

	$termsperpage = apply_filters("termsperpage",20);

	$page_links = paginate_links( array(
		'base' => add_query_arg( 'pagenum', '%#%' ),
		'format' => '',
		'prev_text' => __('&laquo;'),
		'next_text' => __('&raquo;'),
		'total' => ceil(wp_count_terms($this->slug) / $termsperpage),
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
	<?php wp_nonce_field('bulk-terms'); ?>
	</div>

	<br class="clear" />
	</div>

	<div class="clear"></div>

	<table class="widefat term fixed" cellspacing="0">
		<thead>
		<tr>
	<?php print_column_headers('edit-'.$this->slug); ?>
		</tr>
		</thead>

		<tfoot>
		<tr>
	<?php print_column_headers('edit-'.$this->slug, false); ?>
		</tr>
		</tfoot>

		<tbody id="the-list" class="list:<?php echo $this->slug; ?>">
	<?php
	$searchterms = isset( $_GET['s'] ) ? trim( $_GET['s'] ) : '';
	$count = $this->term_rows( 0, 0, 0, $pagenum, $termsperpage );
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
		do_action('add_term_form_pre', $this->slug); ?>

	<div class="form-wrap">
	<h3><?php echo __('Add a New', CUSTAX_DOMAIN).' '.$this->name; ?></h3>
	<div id="ajax-response"></div>
	<form name="addterm" id="addterm" method="post" action="<?php echo $self; ?>" class="add:the-list: validate">
	<input type="hidden" name="action" value="addterm" />
	<?php wp_original_referer_field(true, 'previous'); wp_nonce_field('addterm'); ?>

	<div class="form-field form-required">
		<label for="name"><?php _e('Name'); ?></label>
		<input name="name" id="name" type="text" value="" size="40" aria-required="true" />
	    <p><?php echo __('The name is how the term appears on your site.', CUSTAX_DOMAIN); ?></p>
	</div>

	<div class="form-field">
		<label for="slug"><?php _e('Slug') ?></label>
		<input name="slug" id="slug" type="text" value="" size="40" />
	    <p><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
	</div>

	<?php if($this->hierarchical) : ?>
	<div class="form-field">
        	<label for="parent"><?php _e('Parent') ?></label>
	        <?php 
		custax_wp_dropdown_terms($this->slug, array('hide_empty' => 0, 'name' => 'parent', 'orderby' => 'name', 'selected' => $term->parent, 'hierarchical' => true, 'show_option_none' => __('None'))); ?>
	    <p><?php _e('This taxonomy can have a hierarchy. You might have a Jazz term, and under that have children categories for Bebop and Big Band. Totally optional.', CUSTAX_DOMAIN); ?></p>
	</div>
	<?php endif; ?>

	<?php if($this->descriptions) : ?>
	<div class="form-field">
	        <label for="description"><?php _e('Description') ?></label>
        <textarea name="description" id="description" rows="5" cols="40"></textarea>
	    <p><?php _e('The description is not prominent by default, however some themes may show it.'); ?></p>
	</div>
	<?php endif; ?>

	<p class="submit"><input type="submit" class="button" name="submit" value="<?php echo __('Add').' '.$this->name; ?>" /></p>
	<?php do_action('add_term_form', $this->slug); ?>
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
			var m = '<?php echo js_escape(__("You are about to delete the selected terms.\n  'Cancel' to stop, 'OK' to delete.", CUSTAX_DOMAIN)); ?>';
			$('#doaction').click(function(){
				if ( $('select[name^="action"]').val() == 'delete' ) {
					return showNotice.warn(m);
				}
			});
			$('#doaction2').click(function(){
				if ( $('select[name^="action2"]').val() == 'delete' ) {
					return showNotice.warn(m);
				}
			});
		});
	})(jQuery);
	/* ]]> */
	</script>

	<?php inline_edit_term_row('edit-'.$this->slug); ?>

<?php
    }

}

?>
