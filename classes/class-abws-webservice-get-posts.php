<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ABWS_Webservice_get_posts {

	private static $instance = null;

	/**
	 * Get singleton instance of class
	 *
	 * @return null|ABWS_Webservice_get_posts
	 */
	public static function get() {

		if ( self::$instance == null ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->hooks();
	}

	/**
	 * Setup hooks
	 */
	private function hooks() {
		add_action( 'wpsws_webservice_get_posts', array( $this, 'get_posts' ) );
		add_action( 'wpsws_webservice_get_post', array( $this, 'get_post' ) );
		add_action( 'wpsws_webservice_app_configuration', array( $this, 'app_configuration' ) );
		add_action( 'wpsws_webservice_article_card', array( $this, 'article_card' ) );
		add_action( 'wpsws_webservice_app_update', array( $this, 'app_update' ) );
		add_action( 'wpsws_webservice_card_layout', array( $this, 'card_layout' ) );
		add_action( 'wpsws_webservice_auth_login', array( $this, 'auth_login' ) );
		add_action( 'wpsws_webservice_register_webhook', array( $this, 'register_webhook' ) );
		
	}
   
	/**
	 * Function to get the default settings
	 *
	 * @return array
	 */
	 
	public function get_default_settings() {
		return array( 'enabled' => 'false', 'fields' => array(), 'custom' => array() );
	}

	/**
	 * This is the default included 'get_posts' webservice
	 * This webservice will fetch all posts of set post type
	 *
	 * @todo
	 * - All sorts of security checks
	 * - Allow custom query variables in webservice (e.g. custom sorting, posts_per_page, etc.)
	 */
	public function get_posts() {
        global $wpdb;
		$post_type = 'post';

		// Global options
		$options = APP_Browzer_Web_Service::get()->get_options();

		// Get 'get_posts' options
		$gp_options = array();
		if ( isset( $options['app_config'] ) ) {
			$gp_options = $options['app_config'];
		}
        
         
		$dbwhere = "wpost.post_type = 'post' AND (wpost.post_status = 'publish' OR wpost.post_status = 'private') AND $wpdb->terms.term_status=0 "; 
		
		$page_url = get_site_url() . '/api/get_posts?';
		
		if ( isset( $_GET['search'] ) ) {			
			$like = '%' . $wpdb->esc_like( $_GET['search'] ) . '%';			
			$dbwhere  .= $wpdb->prepare( " AND ((wpost.post_title LIKE %s) OR (wpost.post_content LIKE %s))", $like, $like );
			
			$page_url = get_site_url() . '/api/get_posts?search='.urlencode($_GET['search']).'&';
		}
		
		if ( isset( $_GET['category'] ) ) {			
			$dbwhere  .= $wpdb->prepare(" AND wp_terms.name LIKE %s",$_GET['category']);
			$page_url = get_site_url() . '/api/get_posts?category='.urlencode($_GET['category']).'&';
		}

		// Get posts
		
		if(isset($gp_options['post_per_page']) && $gp_options['post_per_page'] >0){
		  $posts_per_page =$gp_options['post_per_page'];
		}else{
		  $posts_per_page = 10;
		}
		if (isset($_GET["page"]) && is_numeric($_GET["page"]) ) { $page  = $_GET["page"]; } else { $page=1; }; 
		
		if($page >0 )
		  $start_from = ($page-1) * $posts_per_page; 
		else
		  $start_from = 0;  
		
		$querystr = "SELECT SQL_CALC_FOUND_ROWS DISTINCT wpost.*
						FROM $wpdb->posts as wpost
						INNER JOIN $wpdb->term_relationships
						ON (wpost.ID = $wpdb->term_relationships.object_id)
						INNER JOIN $wpdb->term_taxonomy
						ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)
						INNER JOIN $wpdb->terms
						ON ($wpdb->terms.term_id = $wpdb->term_taxonomy.term_id)
						AND $wpdb->term_taxonomy.taxonomy = 'category' 
						WHERE $dbwhere ORDER BY $wpdb->terms.term_order ASC LIMIT $start_from, $posts_per_page
					 ";
        $posts = $wpdb->get_results($querystr, OBJECT);
	    $post_count = $wpdb->get_row( "SELECT FOUND_ROWS() as total;" );
		$meta_arr = array();
        if($post_count->total > 0){
		  $previous_page = '';
		  $next_page   = '';
		  
	      $total_pages = ceil($post_count->total / $posts_per_page); 
		  if($start_from > 0){		   
		   $previous_page =  $page_url.'page='.($page-1);
		  }
		  if($total_pages!=$page){
		   $next_page =  $page_url.'page='.($page+1);
		  }
		  
		  $meta_arr['count'] =  $post_count->total;
		  $meta_arr['previous'] = $previous_page;
		  $meta_arr['current'] =  $page_url.'page='.$page;
		  $meta_arr['next'] =  $next_page;
		  
		  
		}
		
		// Data array
		$return_data = array();
		$response_data = array();
        
        $js_file_url = plugin_dir_url( ABWS_PLUGIN_FILE ) . 'assets/js/abws.js';
		$css_file_url = plugin_dir_url( ABWS_PLUGIN_FILE ) . 'assets/css/app_style.css';
        
		// Loop through posts
		foreach ( $posts as $post ) {
		   $data = array();
		   
		   $images = array();
		   
		   $videos = array();
		   
           $data['post_id'] =$post->ID;
		   $data['comments-count'] =$post->comment_count;
		   
		   $data['permalink'] =get_permalink($post->ID);
		   
		   $data['featured_image'] =wp_get_attachment_url( get_post_thumbnail_id($post->ID) );
		   
		   $author_name = get_the_author_meta('user_nicename', $post->post_author);
		   $data['author'] = array('name' =>$author_name,'author_id'=>$post->post_author);
		   
		   $data['post_type'] =$post->post_type;
		   
		    $post_categories = wp_get_post_categories( $post->ID);
			$cats = array();
				
			foreach($post_categories as $c){
				$cat = get_category( $c );
				$cats[] = array( 'cat_id'=>$c,'name' => $cat->name, 'slug' => $cat->slug );
			}
		   $data['categories'] =$cats;
		   $data['title'] =$post->post_title;
		   $data['date']  =$post->post_date;
		   $data['formatted_date'] =date('d M Y', strtotime($post->post_date));
		   
		   $media_images = get_attached_media( 'image',$post->ID ); 
			if ( $media_images ) {
				foreach ( $media_images as $media_image ) {
					$images[] = array('url'=>wp_get_attachment_url( $media_image->ID));
				}
			   
			}
		   $data['images'] =$images;
		   
		   $media_videos = get_attached_media( 'video',$post->ID ); 
			if ( $media_videos ) {
				foreach ( $media_videos as $media_video ) {
					$videos[] = array('url'=>wp_get_attachment_url( $media_video->ID));
				}
			   
			}
			$data['videos'] =$videos;
			$data['lazy']  = false;		 
			$data['content_url'] = get_site_url() . '/api/get_post?url='.get_permalink($post->ID);

			$post_content = apply_filters('the_content', $post->post_content);
			$content_head = '<!DOCTYPE html><html dir="ltr"><head><meta charset="utf-8"><meta name="description" content=""><meta name="keywords" content=""><meta name="language" content="en"/><meta name="viewport" content="width=device-width, minimum-scale=1, maximum-scale=1, user-scalable=no"> <link href="'.$css_file_url.'" rel="stylesheet" media="all" /><script type="text/javascript" src="'.$js_file_url.'"></script> </head>';

			$content_body = '<body class="ab_body"> <article class="ab_article">  <h1 class="gamma ab_post_title">'.$post->post_title.'</h1> <div class="thin-border"></div></div> <div class="ab_post_meta ab_post_author"><span>By </span>'.$author_name.'</div><div class="ab_post_meta ab_post_date"><time title="'.$post->post_date.'">'.mysql2date('F j, Y', $post->post_date).'</time></div><div class="ab_clear"></div>'.$post_content.'</article> </body></html>';

		   $content = $content_head . $content_body;
		   $data['content'] = $content;
		   
		   $data['sticky']  = is_sticky($post->ID);	   
		   
		   setup_postdata($post);
		   					   
		   $data['summary'] =  html_entity_decode(strip_tags(get_the_excerpt()));

		  $return_data[] = $data;

		}
		if(!empty($data))
          $response_data['meta'] = $meta_arr ;
          
        $response_data['posts'] = $return_data;  
		ABWS_Output::get()->output( $response_data );
	}

   public function get_post(){      
	 
	 if ( ! isset( $_GET['url'] ) ) {
			APP_Browzer_Web_Service::get()->throw_error( 'No url type set.' );
		}

		// Set post type
		$url = esc_sql( $_GET['url'] );
		$post_slug = basename($url);
		$post_type = 'post';

		// Global options
		$options = APP_Browzer_Web_Service::get()->get_options();

		// Get 'get_posts' options
		$gp_options = array();
		if ( isset( $options['get_posts'] ) ) {
			$gp_options = $options['get_posts'];
		}

		// Fix scenario where there are no settings for given post type
		if ( ! isset( $gp_options[$post_type] ) ) {
			$gp_options[$post_type] = array();
		}

		// Setup options
 		$pt_options = wp_parse_args( $gp_options[$post_type], $this->get_default_settings() );

		// Setup default query vars
		$default_query_arguments = array(
		    'name'   => $post_slug,
			'posts_per_page' => 1,
			'order'          => 'ASC',
			'orderby'        => 'title',
		);

		// Get query vars
		$query_vars = array();
		if ( isset( $_GET['qv'] ) ) {
			$query_vars = $_GET['qv'];
		}

		// Merge query vars
		$query_vars = wp_parse_args( $query_vars, $default_query_arguments );

		// Set post type
		$query_vars['post_type'] = $post_type;
        $js_file_url = plugin_dir_url( ABWS_PLUGIN_FILE ) . 'assets/js/abws.js';
		$css_file_url = plugin_dir_url( ABWS_PLUGIN_FILE ) . 'assets/css/app_style.css';
		// Get posts
		$posts = get_posts( $query_vars );
		
		if(! $posts ) {
          throw new Exception("NoSuchPostBySpecifiedURL");
       }
		
		// Data array
		$return_data = array();
        
		if(!empty($posts)){		   
		   $post = $posts[0];
		   
		   $return_data['post_id'] =$post->ID;
		   $return_data['comments-count'] =$post->comment_count;
		   $return_data['permalink'] =get_permalink($post->ID);
		   
		   $return_data['featured_image'] =wp_get_attachment_url( get_post_thumbnail_id($post->ID) );
		   
		   $author_name = get_the_author_meta('user_nicename', $post->post_author);
		   $return_data['author'] = array('name' =>$author_name,'author_id'=>$post->post_author);
		   
		   $return_data['post_type'] =$post->post_type;
		   
		    $post_categories = wp_get_post_categories( $post->ID);
			$cats = array();
				
			foreach($post_categories as $c){
				$cat = get_category( $c );
				$cats[] = array( 'cat_id'=>$c,'name' => $cat->name, 'slug' => $cat->slug );
			}
		   $return_data['categories'] =$cats;
		   $return_data['title'] =$post->post_title;		   
		   $return_data['date']  =$post->post_date;
		   $return_data['formatted_date'] =date('d M Y', strtotime($post->post_date));
		   
		   $media_images = get_attached_media( 'image',$post->ID ); 
		   
		   
			if ( $media_images ) {
				foreach ( $media_images as $media_image ) {
					$images[] = array('url'=>wp_get_attachment_url( $media_image->ID));
				}
			   
			}
		   $return_data['images'] =$images;
		   
		   $media_videos = get_attached_media( 'video',$post->ID ); 
			if ( $media_videos ) {
				foreach ( $media_videos as $media_video ) {
					$videos[] = array('url'=>wp_get_attachment_url( $media_video->ID));
				}
			   
			}
		   $return_data['videos'] =$videos;
		   $return_data['lazy']  = false;
		   
		   $post_content = apply_filters('the_content', $post->post_content);
		   $content_head = '<!DOCTYPE html><html dir="ltr"><head><meta charset="utf-8"><meta name="description" content=""><meta name="keywords" content=""><meta name="language" content="en"/><meta name="viewport" content="width=device-width, minimum-scale=1, maximum-scale=1, user-scalable=no"> <link href="'.$css_file_url.'" rel="stylesheet" media="all" /><script type="text/javascript" src="'.$js_file_url.'"></script> </head>';
		   
		   $content_body = '<body class="ab_body"> <article class="ab_article">  <h1 class="gamma ab_post_title">'.$post->post_title.'</h1> <div class="thin-border"></div></div> <div class="ab_post_meta ab_post_author"><span>By </span>'.$author_name.'</div><div class="ab_post_meta ab_post_date"><time title="'.$post->post_date.'">'.mysql2date('F j, Y', $post->post_date).'</time></div><div class="ab_clear"></div>'.$post_content.'</article> </body></html>';

		   $content = $content_head . $content_body;

		   $return_data['content'] = $content;
		   
		   $return_data['sticky']  = is_sticky($post->ID);		   
		   
		   setup_postdata($post);
					   
		   $return_data['summary'] =  html_entity_decode(strip_tags(get_the_excerpt()));
		   
		}
		
		ABWS_Output::get()->output( $return_data );
	 
	 
   }
  
  public function app_configuration(){
  	
		$return_data = $this->get_configuration_data();										
		ABWS_Output::get()->output( $return_data );											
  }  
  
  public function get_configuration_data(){
  
    // Global options
		$options = APP_Browzer_Web_Service::get()->get_options();
        $return_data = array();   
		// Get 'app_config' options
		$gp_options = array();
		if ( isset( $options['app_config'] ) ) {
			$gp_options = $options['app_config'];
		}
		
       $return_data['general_configuration'] = array(
	                                                 'name' =>$gp_options['app_name'],
													 'logo' => $gp_options['app_logo'],
													 'banner' => $gp_options['app_banner'],
	                                                 /*'content_url' =>get_site_url() . '/api/get_posts/',*/
	                                                 'dynamic_ui_url'=>get_site_url() . '/api/article_card/',
		                                             'content'=>array('type'=>"array", 'root_key'=>"posts",'data_url'=> get_site_url() ."/api/get_posts",
		                                              'search_url'=>get_site_url() ."/api/get_posts?search=#[app.search_term]")
	                                                );
		
		
		
		$args = array(
					'orderby'       =>  'term_order',
					'depth'         =>  0,
					'child_of'      => 0,
					'hide_empty'    =>  0,
					'taxonomy'      => 'category',
		);
		$categories = get_categories( $args ); 
		
		if(!empty($categories)){
		   $category = array();
		   foreach($categories as $terms){		     
			 $visibility = ($terms->term_status ==0)?true:false;
		     $category[] = array(
			                     'id'=>$terms->cat_ID,
								 'name'=>$terms->cat_name,
								 'url'=>get_site_url() . '/api/get_posts?category='.urlencode($terms->name),
								 'visibility'=>$visibility,
								 'position'=>$terms->term_order); 
		   
		   }
		   
		   $return_data['navigation_configuration']['categories'] =  $category;
		  
		}											
		return $return_data;									
  }
  
  
  public function article_card(){
	    
        $options = APP_Browzer_Web_Service::get()->get_options();         
		// Get 'app_config' options
		$gp_options = array();
		if ( isset( $options['app_config'] ) ) {
			$gp_options = $options['app_config'];
		}		     
	   header('Content-Type: application/json; charset=utf-8');	  
       echo stripslashes($gp_options['article_card']);	  
     
  }
  
  
  public function app_update(){	  
	 global $wpdb; 	
	 $json = file_get_contents('php://input');
     $postData = json_decode($json,true);    
	 ABWS_Catch_Request::get()->check_auth_key(); 
	        
	if(!empty($postData)){
		
		$optionsArr = APP_Browzer_Web_Service::get()->get_options();
		$file_url = '';		
		if(isset($postData['logo']) && $postData['logo']!=''){			
			
			$filteredData=substr($postData['logo'], strpos($postData['logo'], ",")+1);
			$unencodedData=base64_decode($filteredData);			
			$f = finfo_open();
            $mime_type = finfo_buffer($f, $unencodedData, FILEINFO_MIME_TYPE);
            $split = explode( '/', $mime_type );
            $type  = $split[1]; 						
			$filename = uniqid().".{$type}";
			
			$wp_upload_dir = wp_upload_dir();
			
			$file          = $wp_upload_dir['path'] . '/' .$filename;
			$file_url      = $wp_upload_dir['url'] . '/' .$filename;				
			$fp            = fopen( $file, 'wb' );
			fwrite( $fp, $unencodedData);
			fclose( $fp );
			
		}
		/// For Banner Image
		$banner_url = '';
		if(isset($postData['banner']) && $postData['banner']!=''){		
			
			$filteredData=substr($postData['banner'], strpos($postData['banner'], ",")+1);
			$unencodedData=base64_decode($filteredData);			
			$f = finfo_open();
            $mime_type = finfo_buffer($f, $unencodedData, FILEINFO_MIME_TYPE);
            $split = explode( '/', $mime_type );
            $type  = $split[1]; 						
			$filename = uniqid().".{$type}";
			
			$wp_upload_dir = wp_upload_dir();
			
			$file          = $wp_upload_dir['path'] . '/' .$filename;
			$banner_url    = $wp_upload_dir['url'] . '/' .$filename;				
			$fp            = fopen( $file, 'wb' );
			fwrite( $fp, $unencodedData);
			fclose( $fp );			
		}
	  	
	  $app_name = ($postData['app_name']!='')?$postData['app_name']:$optionsArr['app_config']['app_name'];
	  $article_card = ($postData['card_layout']!='')?json_encode($postData['card_layout']):$optionsArr['app_config']['article_card'];
	  $file_url    = ($file_url!='')?$file_url:$optionsArr['app_config']['app_logo'];
	  $banner_url  = ($banner_url!='')?$banner_url:$optionsArr['app_config']['app_banner'];
	  
	  $optionsArr['app_config'] = array('app_name'=>wp_unslash($app_name),'app_banner'=>$banner_url,'app_logo'=>$file_url,'article_card'=>wp_unslash($article_card),'theme_color'=>$postData['theme_color'],'post_per_page'=>$optionsArr['app_config']['post_per_page']); 
	  	  	  
	  APP_Browzer_Web_Service::get()->save_options( $optionsArr );
	  
	  if(isset($postData['navigation']) && $postData['navigation']!=''){
		   foreach($postData['navigation'] as $naviData){
			   $term    = get_term_by('name', $naviData['name'], 'category');
			   $status  = ($naviData['visibility'])?0:1;
			   if($term->term_id!=''){
				 $wpdb->update( $wpdb->terms, array('term_order' => $naviData['position'],'term_status'=>$status), array('term_id' => $term->term_id) ); 
				   
				}
			   
		    }
		  
	    } 
	   
	    $return_data = $this->get_configuration_data();										
		ABWS_Output::get()->output( $return_data );	
	   
	 }else{
	   ABWS_Output::get()->output( array('error'=>'Empty json raw data') ); 
	 }
	
   }
   
   public function card_layout(){	  
	global $wpdb;  
		
	$json = file_get_contents('php://input');
    $postData = json_decode($json,true);
    
    ABWS_Catch_Request::get()->check_auth_key(); 
    
	if(!empty($postData)){	 
	  
	  $optionsArr = APP_Browzer_Web_Service::get()->get_options();		
	  	
	  $app_name = $optionsArr['app_config']['app_name'];
	  $article_card = ($postData['card_layout']!='')?json_encode($postData['card_layout']):$optionsArr['app_config']['article_card'];
	  $file_url  = $optionsArr['app_config']['app_logo'];	
	  
	  $banner_url  = $optionsArr['app_config']['app_banner'];
	    
	  
	  $optionsArr['app_config'] = array('app_name'=>wp_unslash($app_name),'app_banner'=>$banner_url,'app_logo'=>$file_url,'article_card'=>wp_unslash($article_card),'post_per_page'=>$optionsArr['app_config']['post_per_page']); 
	  
	   APP_Browzer_Web_Service::get()->save_options( $optionsArr );
	   
	   $return_data = array('status'=>'success');
	   ABWS_Output::get()->output( $return_data );
	   
	 }else{
	   ABWS_Output::get()->output( array('error'=>'Empty json raw data') ); 
	 }
	
   }
   
   public function auth_login(){
	   global $wpdb;
	   $redirect_url = isset($_GET['redirect_uri'])?$_GET['redirect_uri']:'';
	   $state = isset($_GET['state'])?$_GET['state']:'';
	   if(empty($redirect_url)){
			 echo '<div id="login_error">' . apply_filters( 'login_errors', 'Return url not defined.' ) . "</div>\n";
			 exit;	
		}
	   
	   if(is_user_logged_in() ){
		   
		   $sec_key = wp_generate_password( 48, false );
		   $optionsArr = APP_Browzer_Web_Service::get()->get_options();	    
		   $optionsArr['ABWS_auth_key'] = $sec_key;
		   APP_Browzer_Web_Service::get()->save_options( $optionsArr );
		   $redirect_url.='?auth_key='.$sec_key.'&state='.$state;  
		   wp_redirect($redirect_url);		 
		   
	   }else{
		   $sec_key = wp_generate_password( 48, false );
		   $optionsArr = APP_Browzer_Web_Service::get()->get_options();	    
		   $optionsArr['ABWS_auth_key'] = $sec_key;
		   $optionsArr['redirect_uri']  = $redirect_url;
		   $optionsArr['ABWS_state']  = $state;
		   APP_Browzer_Web_Service::get()->save_options( $optionsArr );
		   
		  $login_url = site_url( 'wp-login.php')."?redirect_to=$redirect_url" ; 
	      wp_redirect($login_url);
	   }
	   
	   
	}    
    
    public function register_webhook(){
		global $wpdb; 
		$json = file_get_contents('php://input');
		$postData = json_decode($json,true);
		
		ABWS_Catch_Request::get()->check_auth_key();
		
		if(!empty($postData)){ 
		  $optionsArr = APP_Browzer_Web_Service::get()->get_options();
		  $optionsArr['webhook_url']  = $postData['webhook_url'];
		  APP_Browzer_Web_Service::get()->save_options( $optionsArr );
		  
		}else{
	      ABWS_Output::get()->output( array('error'=>'Empty json raw data') ); 
	    }
		
		
	}      	

}
