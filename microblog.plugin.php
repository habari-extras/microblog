<?php

class Microblog extends Plugin
{
	
	public function action_update_check()
	{
		Update::add( $this->info->name, 'fd3dbaf7-c91a-440b-92ad-c73b04e329b6', $this->info->version );
	}
	
	public function filter_post_type_display($type, $foruse) 
	{ 
		$names = array( 
			'micropost' => array(
		 		'singular' => _t('Micropost'),
		 		'plural' => _t('Microposts'),
				)
		); 
		return isset($names[$type][$foruse]) ? $names[$type][$foruse] : $type; 
	}
	
	/**
	 * Register content type
	 **/
	public function action_plugin_activation( $plugin_file )
	{
		// add the content type.
		Post::add_new_type( 'micropost' );

		// Give anonymous users access
		$group = UserGroup::get_by_name( 'anonymous' );
		$group->grant( 'post_micropost', 'read');
	}

	public function action_plugin_deactivation( $plugin_file )
	{
		if( !Post::delete_post_type( 'micropost' ) )
		{
			Post::deactivate_post_type( 'micropost' );
		}
	}
	
	/**
	 * Modify publish form. We're going to add the custom 'address' field, as we like
	 * to hold our events at addresses.
	 */
	public function action_form_publish($form, $post, $context)
	{
		// only edit the form if it's an event
		if ($form->content_type->value == Post::type('micropost')) {
			
			$form->title->caption = _t( 'Title (optional)' );
			
			// // just want to add a text field
			// 			$form->insert('tags', 'text', 'address', 'null:null', _t('Event Address'), 'admincontrol_textArea');
			// 			$form->address->value = $post->info->address;
			// 			$form->address->template = 'admincontrol_text';
		}
	}

	/**
	 * Save our data to the database
	 */
	public function action_publish_post( $post, $form )
	{
		if ($post->content_type == Post::type('micropost')) {
			
			if( $post->title == '' )
			{
				$post->title = Format::summarize( strip_tags( $post->content ), 5 );
			}
			
		}
	}
	
}

?>