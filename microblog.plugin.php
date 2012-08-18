<?php

class Microblog extends Plugin
{
	
	static $default_characterlimit = 140;
	static $send_services = array();
	static $link_services = array();
	static $copy_services = array();
	
	public function action_init()
	{		
		self::$send_services = Plugins::filter( 'microblog__send_services', self::$send_services );
		self::$link_services = Plugins::filter( 'microblog__link_services', self::$link_services );
		self::$copy_services = Plugins::filter( 'microblog__copy_services', self::$copy_services );
		
		// enable send services
		$enabled_send_services = Options::get( 'microblog__send_services', array() );
		foreach( self::$send_services as $service => $status )
		{
			if( in_array( $service, $enabled_send_services ) )
			{
				self::$send_services[ $service ] = true;
			}
		}
		
		// enable link services
		$enabled_link_services = Options::get( 'microblog__link_services', array() );
		foreach( self::$link_services as $service => $status )
		{
			if( in_array( $service, $enabled_link_services ) )
			{
				self::$link_services[ $service ] = true;
			}
		}
		
		// enable copy services
		$enabled_copy_services = Options::get( 'microblog__copy_services', array() );
		foreach( self::$copy_services as $service => $status )
		{
			if( in_array( $service, $enabled_copy_services ) )
			{
				self::$copy_services[ $service ] = true;
			}
		}
				
		$this->try_copy();
		
	}
	
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
		
		$users = Users::get_all();
		
		// Add options of services to send microblogs to
		if( count( self::$send_services ) > 0 )
		{
			$send = $ui->append( 'fieldset', 'send_services_container', _t( 'Post microposts' ) );
			$send->append( 'select', 'send_user', 'option:microblog__senduser' , _t('Post as:') );
			foreach( $users as $user )
			{
				$send->send_user->options[ $user->id ] = $user->displayname;
			}
			
			$send_services = $send->append( 'checkboxes', 'send_services', 'option:microblog__send_services' );
			foreach( self::$send_services as $service => $state )
			{
				$send_services->options[ $service ] = $this->service( $service, 'name' );
				// Utils::debug( )
			}
		}
		
		// Add options of services to use for linking
		if( count( self::$link_services ) > 0 )
		{
			$link_services = $ui->append( 'fieldset', 'link_services_container', _t( 'Link microposts to' ) )->append( 'checkboxes', 'link_services', 'option:microblog__link_services' );
			foreach( self::$link_services as $service => $state )
			{
				$link_services->options[ $service ] = $this->service( $service, 'name' );
				// Utils::debug( )
			}
		}
		
		// Add options of services to use for copying
		if( count( self::$copy_services ) > 0 )
		{
			$copy = $ui->append( 'fieldset', 'copy_services_container', _t( 'Copy microposts' ) );
			$copy->append( 'select', 'copy_user', 'option:microblog__copyuser' , _t('Copy as:') );
			foreach( $users as $user )
			{
				$copy->copy_user->options[ $user->id ] = $user->displayname;
			}
			
			$copy_services = $copy->append( 'checkboxes', 'copy_services', 'option:microblog__copy_services' );
			foreach( self::$copy_services as $service => $state )
			{
				$copy_services->options[ $service ] = $this->service( $service, 'name' );
				// Utils::debug( )
			}
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
	 * Create a summarized title, if necessary
	 **/
	public function action_post_update_title( $post )
	{
		if ($post->content_type == Post::type('micropost') && $post->title == '' ) {

			$post->title = Format::summarize( strip_tags( $post->content ), 5 );
			
		}
	}

	/**
	 * Save our data to the database
	 */
	public function action_post_insert_after( $post )
	{
		if ($post->content_type == Post::type('micropost')) {
			
			foreach( self::$send_services as $service => $active )
			{
				if( $active )
				{
					$this->service( $service, 'send', array( 'post' => $post, 'user' => User::get_by_id( Options::get( 'microblog__senduser' ) ) ) );
				}
			}
			
		}
	}
	
	/**
	 * Copy posts from copy services 
	 **/
	public function try_copy ()
	{
		$user = User::get_by_id( Options::get( 'microblog__copyuser' ) );
				
		$posts = array();
		foreach( self::$copy_services as $service => $active )
		{
			if( $active )
			{
				$posts = $this->service( $service, 'copy', array( 'user' => $user ) );
			}
		}
		
		$posts = Plugins::filter( 'microblog__copyposts', $posts );
		
		foreach( $posts as $post )
		{			
			if( Posts::get( array( 'content' => $post->text, 'pubdate' => HabariDateTime::date_create( $post->time ) ) )->count() == 0 )
			{
				$micropost = new Post( array( 'content_type' => Post::type( 'micropost' ) ) );

				$micropost->content = $post->text;
				$micropost->pubdate = HabariDateTime::date_create( $post->time );
				
				$micropost->info->source_id = $post->id;
				$micropost->info->source_link = $post->permalink;
				
				$micropost->user_id = $user->id;
				
				$micropost->insert();

				Session::notice( _t( 'Micropost successfully copied' ) );
				
			}
			
		}
	}
	
	/**
	 * Handle a service action
	 **/
	public function service( $service, $action, $params = array() )
	{
		$service_handlers = array();
		$service_handlers = Plugins::filter( 'microblog__servicehandlers', $service_handlers );
		
		$params = Plugins::filter( 'microblog__servicehandler_params', $params, $service, $action );
		
		Plugins::act( 'microblog__pre_servicehandle', $service, $action, $params );
		$return = call_user_func_array( $service_handlers[ $service ][ $action ], $params );
		Plugins::act( 'microblog__post_servicehandle', $service, $action, $params, $return );
		
		return $return;
		
	}
	
}

?>