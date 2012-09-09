<?php
/*
Plugin Name: wp-publications
Description: Creates publication lists from Bibtex files by integrating bibtexbrowser into wordpress
Plugin URI: http://www.monperrus.net/martin/wp-publications
Author: Martin Monperrus
Author URI: http://www.monperrus.net/martin/
*/

// While bibtexbrowser is mature, this plugin is somewhat experimental

define('__WP_PLUGIN__','wp-publications');

/** returns a permalink string of the $entry (BibtexEntry).
  * This an extension of bibtexbrowser, not wordpress
  */
function wp_bibtexbrowser_url_builder( &$entry ) {

  // gets the posts corresponding to this entry
  $my_posts = get_posts(
    array(
      'name' =>  $entry->getKey(),
      'post_type' => __WP_PLUGIN__,
    )
  );
  
  // returns the permalink
  return get_permalink($my_posts[0]);
}

/** Encapsulates the logic of wp-publications */
class WP_BibtexBrowser {
  
  // Constructor
  function __construct() {
    add_action( 'init', array( &$this, 'load_bibtexbrowser' ) );
    add_action( 'init', array( &$this, 'register_post_type' ) );
    add_action( 'init', array( &$this, 'register_css' ) );
    add_shortcode( __WP_PLUGIN__, array( &$this, 'wp_publications_shortcode_handler') );        
    add_action('the_post', array( &$this, 'update_bibtex' ));
    register_activation_hook( __FILE__,  array( &$this, 'activation_action'));
  }

  // loads bibtexbrowser using appropriate context variables
  function load_bibtexbrowser() {
    $_GET['library']=1;
    define('PAGE_SIZE',10000);
    define('BIBTEXBROWSER_URL_BUILDER','wp_bibtexbrowser_url_builder');
    
    is_readable(dirname(__FILE__).'/'.'bibtexbrowser.php') or die('bibtexbrowser.php is not readable');

    file_exists(dirname(__FILE__).'/'.'bibtexbrowser.php') or die('the plugin wp-publications requires bibtexbrowser.<br/> Please download it at http://www.monperrus.net/martin/bibtexbrowser.php.txt and copy it into wp-content/plugins/wp-publications. Thanks :)');
    require(dirname(__FILE__).'/'.'bibtexbrowser.php');
  }

  // register an additional CSS
  function register_css() {
    $myStyleUrl = plugins_url(__WP_PLUGIN__.'.css', __FILE__); 
    wp_register_style(__WP_PLUGIN__, $myStyleUrl);
    wp_enqueue_style(__WP_PLUGIN__);
  }

  /** updates the post if and only if
   *   - it is a publication post
   *   - the underlying bibtex file has changed
   * it may overwrite your own changes in the admin interface
   */
  function update_bibtex(&$post) {
  
    if ($post->post_type == __WP_PLUGIN__) {
    
      // get the bibtex file associated with the post
      $bibtex = array_pop(get_post_meta($post->ID,'bibtex'));

      // updating the post
      // we need one refresh to get this into account
      $database = zetDB($this->resolve($bibtex));
      // slugs are case-insensitives
      $bibdb = $database->bibdb;
      $entry = NULL;
      if (isset($bibdb[$post->post_name])) {
        $entry = $bibdb[$post->post_name];
      } else {
        foreach ($bibdb as $k=>$v) {
          if (strtolower(preg_replace('/[:+]/','',$k)) == $post->post_name) {
            $entry = $v;
          }
        }    
      }
      
      if ($entry) { $this->add_or_update_publication_entry($entry,$bibtex,$post->ID); }
    } // end if  ($post->post_type == __WP_PLUGIN__)
  } // end function

  function register_post_type() {    
    register_post_type( __WP_PLUGIN__,
        array(
          'labels' => array(
          'name' => __( 'Publications' ),
//          'singular_name' => __( 'URL' ),
//          'add_new' => __( 'Add New' ),
//          'add_new_item' => __( 'Add New URL' ),
//          'edit' => __( 'Edit' ),
//          'edit_item' => __( 'Edit URL' ),
//          'new_item' => __( 'New URL' ),
//          'view' => __( 'View URL' ),
//          'view_item' => __( 'View URL' ),
//          'search_items' => __( 'Search URL' ),
//          'not_found' => __( 'No URLs found' ),
//          'not_found_in_trash' => __( 'No URLs found in Trash' )
          ),
        'public' => true,
//      'show_ui' => false,
//      'query_var' => true,
//      'menu_position' => 20,
//      'supports' => array( 'title' ),
//      'with_front' - allowing permalinks to be prepended with front base (example: if your permalink structure is /blog/, 
//      'rewrite' => array( 'slug' => 'publications', 'with_front' => false )
      )
    );
    // see http://wordpress.org/support/topic/permalink-to-custom-post-type-gives-me-404-error#post-1893539
    flush_rewrite_rules();        
  }

  /** adds a test post for wp-publications and create a sample bibtex file*/
  function activation_action() {  
      // add a sample.bib
      //@file_put_contents(plugin_dir_path(__FILE__).'/sample.bib', 
      //   "@article{doe2000,title={An article},author={Jane Doe},journal={The Wordpress Journal},year=2000}\n".
      //   "@book{doo2001,title={An bok},author={Jane Doe},year=2001}");
      
      //add_fake_post
      $post = array(
        'post_content' => "&#91;wp-publications bib=sample.bib all=1&#93; gives:\n[wp-publications bib=sample.bib all=1]", //The full text of the post.
        'post_status' => 'publish',
        'post_title' => 'wp-publications example', //The title of your post.
    );  
    $post_id = wp_insert_post( $post );    
  }
  
  /** adds a new publication entry if $ID=NULL based on the BibtexEntry $entry 
   * from the bibtex file $bibtex_file
   * updates it otherwise
   */
  function add_or_update_publication_entry(&$entry, $bibtex_file, $ID=NULL) {
    $post = array(
        'ID' => $ID, //Are you updating an existing post?
        'post_content' => $this->createContent($entry), //The full text of the post.
        'post_name' => $entry->getKey(), // The name (slug) for your post
        'post_status' => 'publish',
        'post_title' => $entry->getTitle(), //The title of your post.
        'post_type' => __WP_PLUGIN__, //You may want to insert a regular post, page, link, a menu item or some custom post type
    );  
    $post_id = wp_insert_post( $post );    
    update_post_meta($post_id, 'bibtex', $bibtex_file);
  }

  /** returns the string contents of the BibtexEntry $entry */
  function createContent(&$entry) {
    $bibdisplay = new BibEntryDisplay($entry);
    return $bibdisplay->displayOnSteroids();
  }

  /** returns a valid path of the $bibtex */
  function resolve($bibtex) {
    $resolvedbibtexfiles = array();
    
    foreach (explode(';',$bibtex) as $bibtexfile) {
      array_push($resolvedbibtexfiles,$this->resolveOneBibTexFile($bibtexfile));
    }
    
    return implode(';',$resolvedbibtexfiles);
  }
  
  function resolveOneBibTexFile($bibtex) {
    // in the plugin directory wp-content/plugins/wp-publications
    $file = dirname(__FILE__).'/'.$bibtex;
    if (is_file($file)) {
      return $file;      
    }

    // in the wordpress directory
    $file = dirname($_SERVER['SCRIPT_FILENAME']).'/'.$bibtex;
    if (is_file($file)) {
        return $file;      
    }

    return $bibtex;
  }


  /** returns the string associated with a publication list shortcode
   * e.g. [wp-publications bib="publications.bib" all=true]
   */
  function wp_publications_shortcode_handler( $atts, $content=null, $code="" ) {
    // first we simulate a standard call to bibtexbrowser
    $_GET = array_merge($_GET, $atts);
    
    $_GET['bib'] = $this->resolve($_GET['bib']);
    $database = zetDB($this->resolve($_GET['bib']));
    
    foreach($database->getEntries() as $entry) {
      $bibdisplay = new BibEntryDisplay($entry);
      
      $key =$entry->getKey(); 
      $args=array(
        'name' =>  $key,
        'post_type' => __WP_PLUGIN__,
      );
      $my_posts = get_posts($args);
      if( $my_posts ) {
        // already added in the database
        // echo 'ID on the first post found '.$my_posts[0]->ID;
      } else {
        $this->add_or_update_publication_entry($entry,$atts['bib']);
      }
    }
    ob_start();
    new Dispatcher();
    return ob_get_clean();   
  } // end function
  
  // PHP4 Constructor
  function WP_BibtexBrowser() {
    $this->__construct();
  }
    

} // end class WP_BibtexBrowser

$WP_BibtexBrowser = new WP_BibtexBrowser();
