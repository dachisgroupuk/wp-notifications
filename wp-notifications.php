<?php
/*
Plugin Name: WP Notifications
Plugin URI: http://headshift.com/
Description: Allows plugins to sign up on notifications and customize them
Version: 1.0
Author: Pavlos Syngelakis
Author URI: http://headshift.com/
*/

foreach( glob(__DIR__ . '/notifications_api/*.php') as $file ){
	require_once $file;
}

$WP_Notification = new WP_Notifications();

Class WP_Notifications{
	
	protected $_plugins = array();
	
	function __construct(){
		add_action( 'wp_notifications_subscribe', array($this, 'add_subscriber'), 10, 1 );
		add_action( 'transition_post_status', array($this, 'post_status_notification'), 10, 3);
		add_action( 'delete_post', array($this, 'post_delete_notification'), 10, 1);
		add_action( 'post_updated', array($this, 'post_update_notification'), 10, 3 );
		add_action( 'wp_notifications_add_sender', array($this, 'add_subscriber'), 10, 1 );
	}
	
	/**
	 * Callback function for when a subcription request has been caught.
	 *
	 * @param string $plugin 
	 * @return void
	 * @author Pavlos Syngelakis
	 */
	function add_subscriber($plugin){
		if ( !in_array( $plugin, $this->_plugins ) ){
			$this->_plugins[] = $plugin;
		}
	}
		
	function post_delete_notification( $post_ID ){		
		if( !wp_is_post_revision($post_ID) ){
			$post = get_post($post_ID);
			$this->post_status_notification( 'delete', $post->post_status, $post);
		}		
	}
	
	function post_update_notification( $post_ID, $post_after, $post_before ){
		if( $post_before->post_status == $post_after->post_status ){
			$post = get_post($post_ID);
			$this->post_status_notification( 'update', $post->post_status, $post);
		}		
	}
	
	function post_status_notification( $new_status, $old_status, $post ){
		if($new_status != $old_status){
			$this->notify_subscribers( $post->post_type, $new_status, $post );
		}		
	}
	
	/**
	 * Generates every notification factory and fires the specific action for each plugin.
	 *
	 * @param string $type_payload 
	 * @param string $op_payload 
	 * @param string $payload 
	 * @return array
	 * @author Pavlos Syngelakis
	 */
	function generate_notifications( $type_payload, $op_payload, $payload ){
		$factories = array();
		foreach( $this->_plugins as $plugin){
			$factory = new Notifications_Api_Factory_Queue($plugin, $type_payload, $op_payload, $payload);
			do_action( $plugin.'_notification_factory', $factory );
			$factories[] = $factory;
		}
		return $factories;
	}
	
	/**
	 * Initiates the notification process.
	 * 
	 * @param string $type_payload 
	 * @param string $op_payload 
	 * @param Object $payload 
	 * @return void
	 * @author Pavlos Syngelakis
	 */
	protected function notify_subscribers( $type_payload, $op_payload, $payload ){
		$factories = $this->generate_notifications($type_payload, $op_payload, $payload);
		$this->alter_notifications($factories);
		$this->send_notifications($factories);
	}
	
	/**
	 * Fires an action that allows plugings to alter specific notifications within specific factories.
	 *
	 * @param array $factories 
	 * @return void
	 * @author Pavlos Syngelakis
	 */
	protected function alter_notifications( $factories ){
		do_action_ref_array('wp_notifications_alter', $factories);
	}
	
	/**
	 * Sends all the notifications contained in the current factories.
	 *
	 * @param array $factories 
	 * @return void
	 * @author Pavlos Syngelakis
	 */
	protected function send_notifications( $factories ){
		foreach( $factories as $factory ){
			$factory->send();
		}
	}
}