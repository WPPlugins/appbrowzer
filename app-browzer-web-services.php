<?php
/*
Plugin Name: App Browzer Web Services
Description: App Browzer Rest Web Services. Add JSON REST web services to your WordPress website.
Version: 1.0.7
Author: App Browzer
License: GPL v3

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABWS_PLUGIN_DIR' ) ) {
	define( 'ABWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ABWS_PLUGIN_FILE' ) ) {
	define( 'ABWS_PLUGIN_FILE', __FILE__ );
}

/*
 * @todo
 * - Make it easy for webservice developers to create custom settings
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET'); 
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: x-user-agent,auth-key,Content-Type,Accept,x-csrf-token,accept');  
 
class APP_Browzer_Web_Service {

	const WEBSERVICE_REWRITE = 'api/([a-zA-Z0-9_-]+)$';
	const OPTION_KEY         = 'wpw_options';

	private static $instance = null;

	/**
	 * Get singleton instance of class
	 *
	 */
	public static function get() {

		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Function that runs on install
	 */
	public static function install() {

		// Clear the permalinks
		flush_rewrite_rules();
		
		global $wpdb;
            
            //check if the term_order column exists;
            $query = "SHOW COLUMNS FROM $wpdb->terms 
                        LIKE 'term_order'";
            $result = $wpdb->query($query);
            
            if ($result == 0)
                {
                    $query = "ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'";
                    $result = $wpdb->query($query); 
                }
		
		     //check if the term_status column exists;
            $query = "SHOW COLUMNS FROM $wpdb->terms 
                        LIKE 'term_status'";
            $result2 = $wpdb->query($query);
            
            if ($result2 == 0)
                {
                    $query = "ALTER TABLE $wpdb->terms ADD `term_status` INT( 4 ) NULL DEFAULT '0'";
                    $result2 = $wpdb->query($query); 
                }
			

	}

	/**
	 * Constructor
	 */
	private function __construct() {

		// Load files
		$this->includes();

		// Init
		$this->init();

	}

	/**
	 * Load required files
	 */
	private function includes() {

		require_once( ABWS_PLUGIN_DIR . 'classes/class-abws_rewrite_rules.php' );
		require_once( ABWS_PLUGIN_DIR . 'classes/class-abws-webservice-get-posts.php' );
		
		if ( is_admin() ) {
			// Backend             			
		}
		else {
			// Frondend
			require_once( ABWS_PLUGIN_DIR . 'classes/class-abws-catch-request.php' );
			require_once( ABWS_PLUGIN_DIR . 'classes/class-abws-output.php' );
		}

	}

	/**
	 * Initialize class
	 */
	private function init() {

		// Setup Rewrite Rules
		ABWS_Rewrite_Rules::get();

		// Default webservice
		ABWS_Webservice_get_posts::get();

		if ( is_admin() ) {
			// Backend
		}
		else {
			// Frondend
			// Catch request
			ABWS_Catch_Request::get();			
			
            $pos =  strpos($_SERVER["REQUEST_URI"],'api/');            
			if((isset($_SERVER['HTTP_X_USER_AGENT']) && (strpos($_SERVER['HTTP_X_USER_AGENT'],'appbrowzer') !== false)) && $pos ==false)
			{
			   
			    echo file_get_contents(get_site_url() . '/api/app_configuration');			   
			    exit;
			  
			}
			
		}

	}

	/**
	 * The correct way to throw an error in a webservice
	 *
	 * @param $error_string
	 */
	public function throw_error( $error_string ) {
		wp_die( '<b>Webservice error:</b> ' . $error_string );
	}

	/**
	 * Function to get the plugin options
	 *
	 * @return array
	 */
	public function get_options() {
		return get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Function to save the plugin options
	 *
	 * @param $options
	 */
	public function save_options( $options ) {
		update_option( self::OPTION_KEY, $options );
	}
	
	public function WS_get_terms_orderby($orderby, $args)
        {
            if (isset($args['orderby']) && $args['orderby'] == "term_order" && $orderby != "term_order")
                return "t.term_order";
                
            return $orderby;
        }
		
   public function WS_applyorderfilter($orderby, $args)
        {
	       return 't.term_order';
        }		
	

}

/**
 * Function that returns singleton instance of APP_Browzer_Web_Service class
 *
 * @return null|APP_Browzer_Web_Service
 */
function APP_Browzer_Web_Service() {
	return APP_Browzer_Web_Service::get();
}


function ABW_login() {	
	$redirect_to = $_REQUEST['redirect_to'];
	$optionsArr = APP_Browzer_Web_Service::get()->get_options();
	
	if(isset($optionsArr['redirect_uri']) && ($optionsArr['redirect_uri'] == $redirect_to)){
	      $redirect_to.='?auth_key='.$optionsArr['ABWS_auth_key'].'&state='.$optionsArr['ABWS_state'];  
		  wp_redirect($redirect_to);
		  exit;	
	}
	
}

function post_updated_send_request( $post_id, $post ) {

	// If this is just a revision
	if ($post->post_date != $post->post_modified) return;
    
		$optionsArr = APP_Browzer_Web_Service::get()->get_options();
		if(isset($optionsArr['webhook_url']) && $optionsArr['webhook_url']!=''){
		$post_url = get_site_url() . '/api/get_post?url='.get_permalink($post->ID);	
		$postArr  = array();
		$postArr['dynamic_ui_url'] = get_site_url() . '/api/article_card/';
		$postArr['content'] = array('type'=>'object','root_key'=>'','data_url'=>$post_url);
		$data = array();
		$author_name = get_the_author_meta('user_nicename', $post->post_author);
		$data['featured_image'] = wp_get_attachment_url( get_post_thumbnail_id($post->ID) );
		$data['author'] = array('name' =>$author_name,'author_id'=>$post->post_author);
		$data['title'] =$post->post_title;
		$data['formatted_date'] =date('d M Y', strtotime($post->post_date));
		$data['featured_image'] =wp_get_attachment_url( get_post_thumbnail_id($post->ID) );
		setup_postdata($post);
		$data['summary'] =  html_entity_decode(strip_tags(get_the_excerpt()));
		$postArr['data'] = $data;
	      
	 $post_data   = json_encode($postArr); 
	 $remote_url  = $optionsArr['webhook_url'];
	 
	 $response = wp_remote_post( $remote_url, array (
			'method'      => 'POST',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array('Accept'=>'application/json','Content-type'=>'application/json','AUTH-KEY'=>$optionsArr['ABWS_auth_key']),
			'body'        => $post_data,
			'cookies'     => array ()
		)
	 );
	 
	
	}
    
		
}

// Load plugin
add_action( 'plugins_loaded', create_function( '', 'APP_Browzer_Web_Service::get();' ) );

// Install hook
register_activation_hook( ABWS_PLUGIN_FILE, array( 'APP_Browzer_Web_Service', 'install' ) );

add_filter('get_terms_orderby',  array( 'APP_Browzer_Web_Service', 'WS_applyorderfilter' ), 10, 2);

add_filter('get_terms_orderby',  array( 'APP_Browzer_Web_Service', 'WS_get_terms_orderby' ), 1, 2);

add_action('wp_login', 'ABW_login');

add_action( 'publish_post', 'post_updated_send_request', 10, 2);
