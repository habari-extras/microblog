<?php

class Microblog extends Plugin
{
	
	static $default_characterlimit = 140;
	
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
	 * Add links to users and hashtags
	 **/
	public function filter_post_content_out( $content, $post )
	{
		if( $post->content_type == Post::type('micropost') )
		{
			$user_regex = '/(^|\s)(@([a-z0-9_\.]+))/i';
	
			if( preg_match_all( $user_regex, $content, $matches ) )
			{
				foreach( $matches[3] as $username )
				{
					$link = Plugins::filter( 'microblog_userlink', array( false, $username ), $post );
					if( $link[0] )
					{
						$content = str_replace( '@' . $username, '<a href="' . $link[1] . '" class="username">@' . $username . '</a>', $content );
					}
				}
			}
		}
		
		return $content;
		
	}
	
	/**
	 * Build the configuration settings
	 */
	public function configure()
	{
		$ui = new FormUI( 'microblog_config' );

		// Add a text control for the address you want the email sent to
		$limit = $ui->append( 'text', 'characterlimit', 'option:microblog__characterlimit', _t( 'Character limit for microposts:' ) );
		$limit->helptext = _t( 'For none, enter 0.' );
		if( $limit->value == '' )
		{
			$limit->value = self::$default_characterlimit;
		}
		
		$ui->append( 'submit', 'save', 'Save' );
		return $ui;
	}
	
	/**
	 * Validate that the given text is under a specific character limit
	 */
	function validate_length( $text, $control, $form, $max_length ) {
		
		$length = strlen( $text );
		
		if( $length > $max_length )
		{			
			return array( sprintf( _t( 'Text is too long by <strong>%d</strong> characters.' ), ( $length - $max_length ) ) );
		}
		
	    return array();
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
			
			if( Options::get('microblog__characterlimit', self::$default_characterlimit ) > 0 )
			{
				$form->content->add_validator( array( $this, 'validate_length' ), Options::get('microblog__characterlimit', self::$default_characterlimit ) );
			}
			
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