<?php

/**
 * @package Dropbox_Autopost
 * @version 1.0
 */

/*
Plugin Name: Dropbox Autopost
Plugin URI: https://github.com/matiasinsaurralde/dropbox-autopost
Description: This plugin allows you to post files/directories from your Dropbox.
Author: Matias Insaurralde
Version: 1.0
Author URI: https://matias.insaurral.de/
*/

require_once __DIR__.'/dropbox-sdk/Dropbox/autoload.php';
use \Dropbox as dbx;

add_action( 'admin_init', 'dropbox_init' );

function dropbox_init() {

  register_setting( 'dropbox-settings', 'dropbox-settings' );
  add_settings_section( 'dropbox-main', 'API Settings', 'dropbox_main_callback', 'dropbox' );
  add_settings_field( 'app-key', 'Application Key', 'dropbox_key_callback', 'dropbox', 'dropbox-main' );
  add_settings_field( 'app-secret', 'Application Secret', 'dropbox_secret_callback', 'dropbox', 'dropbox-main' );
  add_settings_section( 'dropbox-access', 'Access', 'dropbox_access_callback', 'dropbox');
  add_settings_field( 'dropbox-access-token', 'Token', 'dropbox_access_token_callback', 'dropbox', 'dropbox-access' );
  add_settings_field( 'dropbox-access-userid', 'User ID', 'dropbox_access_userid_callback', 'dropbox', 'dropbox-access' );

  $options = get_option( "dropbox-settings" );

  if( isset( $options[ 'dropbox-access-token'] ) ) {

    add_settings_section( 'dropbox-functionality', 'Functionality', 'dropbox_functionality_callback', 'dropbox' );
    add_settings_field( 'dropbox-root', 'Root directory', 'dropbox_root_callback', 'dropbox', 'dropbox-functionality' );
    add_settings_field( 'dropbox-thumb-size', 'Thumb size', 'dropbox_thumb_size_callback', 'dropbox', 'dropbox-functionality' );
    add_settings_field( 'dropbox-ext', 'Hide file extension?', 'dropbox_fileext_callback', 'dropbox', 'dropbox-functionality' );

  };
};

function register_session() {
  if( !session_id())
    session_start();
};

add_action('init','register_session');

function dropbox_root_callback() {
  $options = get_option( "dropbox-settings" );
  if( $options['dropbox-root'] == '' ) {
    $options['dropbox-root'] = '/';
  };
  echo "<input id='dropbox_root' name='dropbox-settings[dropbox-root]' size='40' type='text' value='{$options['dropbox-root']}' />";
};
function dropbox_thumb_size_callback() {
  $options = get_option( "dropbox-settings" );
?>
<select id="thsize" name="dropbox-settings[dropbox-thumb-size]">
  <option value="xs">XS - 32x32</option>
  <option value="s">S - 64x64</option>
  <option value="m">M - 128x128</option>
  <option value="l">L - 640x480</option>
  <option value="xl">XL - 1024x768</option>
</select>
<script>
var th = document.getElementById('thsize');
th.value = '<?php echo $options['dropbox-thumb-size']; ?>';
</script>
<?php
};

function dropbox_fileext_callback() {
  $options = get_option( "dropbox-settings" );
?>
<input type="checkbox" name="dropbox-settings[dropbox-ext]" value="1"<?php checked( 1 == $options['dropbox-ext'] ); ?> />
<?php
};

function dropbox_key_callback() {
  $options = get_option( "dropbox-settings" );
  echo "<input id='dropbox_key' name='dropbox-settings[app-key]' size='40' type='text' value='{$options['app-key']}' />";
};

function dropbox_secret_callback() {
  $options = get_option( "dropbox-settings" );
  echo "<input id='dropbox_secret' name='dropbox-settings[app-secret]' size='40' type='text' value='{$options['app-secret']}' />";
};

function dropbox_access_callback() {
};

function dropbox_access_token_callback() {
  $options = get_option( "dropbox-settings" );
  if ( isset( $options['dropbox-access-token'] ) ) {
    echo "<input id='dropbox_access' name='dropbox-settings[dropbox-access-token]' size='40' type='text' value='{$options['dropbox-access-token']}' readonly='true' />";
  } else {
    if( isset( $options['app-key'] ) && isset( $options['app-secret'] ) &&  !empty( $options['app-key'] ) && !empty( $options['app-secret'] ) ) {
      echo "<p>Add the following redirect URL to your <a target=\"_blank\" href=\"https://www.dropbox.com/developers/apps/info/".$options['app-key']."\">Dropbox app settings</a>:</p>";
      echo "<p><code>".admin_url('plugins.php?page=dropbox-settings&auth=true')."</code></p><br />";
      echo "<p>Then <a href=\"" . admin_url( 'plugins.php?page=dropbox-settings&auth=true' ) . "\">click here to enable Dropbox access.</a></p>";
    } else {
      echo "Please set your application key/secret.";
    };
  };
};

function dropbox_access_userid_callback() {
  $options = get_option( "dropbox-settings" );
  if ( isset( $options['dropbox-access-token'] ) ) {
    echo "<input id='dropbox_access_userid' name='dropbox-settings[dropbox-access-userid]' size='40' type='text' value='{$options['dropbox-access-userid']}' readonly='true' />";
    echo "<br /><br />";
    echo "<a href=\"". admin_url( 'plugins.php?page=dropbox-settings&clear=true' ) ."\">Clear credentials</a><br />";
    echo "<a href=\"". admin_url( 'plugins.php?page=dropbox-settings&msync=true' ) ."\">Sync now</a><br />";
    echo "<a href=\"". admin_url( 'plugins.php?page=dropbox-settings&mcleanup=true' ) ."\">Cleanup</a><br />";
  };
};


function dropbox_plugin_action_links( $links, $file ) {

        if ( $file == plugin_basename( dirname(__FILE__).'/dropbox-autopost.php' ) ) {
                $links[] = '<a href="' . admin_url( 'plugins.php?page=dropbox-settings' ) . '">'.__( 'Settings' ).'</a>';
        };

        return $links;
}

add_filter( 'plugin_action_links', 'dropbox_plugin_action_links', 10, 2 );

add_action('admin_menu', 'register_dropbox_settings');

function register_dropbox_settings() {
  add_submenu_page( 'plugins.php', 'Dropbox', 'Dropbox', 'manage_options', 'dropbox-settings', 'dropbox_settings_callback' );
}

function dropbox_sync() {

  $options = get_option( "dropbox-settings" );

  $dbxClient = new dbx\Client( $options['dropbox-access-token'], CLIENT_NAME );
  $dbxCategory = wp_create_category( "Dropbox", 0 );
  $accountInfo = $dbxClient->getAccountInfo();

  $root_path = $options['dropbox-root'];

  $root = $dbxClient->getMetadataWithChildren( $root_path );

  foreach( $root[contents] as $item ) {

    if( $item[ is_dir ] == 1 ) {

      $folder_name = str_replace( $root_path, "", $item[ path ] );
      $folder_name = str_replace( "/", "", $folder_name );
      $category_id = wp_create_category($folder_name, $dbxCategory);

      $files = $dbxClient->getMetadatawithChildren( $item[path] );

      foreach( $files[contents] as $file ) {
        if( $file[ is_dir ] != 1 ) {

          $file_link = $dbxClient->createShareableLink( $file[ path ] );
          $file_thumbnail = $dbxClient->getThumbnail( $file[ path ], "jpeg", $options['dropbox-thumb-size'] );
          $file_thumbnail_b64 = base64_encode( $file_thumbnail[1] );

          $file_path_info = pathinfo( $file[ path ] );

          if( $options['dropbox-ext'] == 1 ) {
            $file_name = $file_path_info[ 'basename' ];
          } else {
            $file_name = $file_path_info[ 'filename' ];
          };

          $post_content = "<img src=\"data:image/jpeg;base64,".$file_thumbnail_b64."\" />";

          $post = array(
                    'post_title'    => $folder_name,
                    'post_content'  => $post_content,
                    'post_status'   => 'publish',
                    'post_author'   => 1,
                    'post_category' => array( $category_id )
                  );

           $existingPost = get_page_by_title( $file_name, 'object', 'post' );

           if ( $existingPost == NULL ) {
             wp_insert_post( $post );
           };
        };

      };
    };
  };
  echo "<script>location.href = '".admin_url( 'plugins.php?page=dropbox-settings' )."';</script>";
};

function dropbox_cleanup() {

  query_posts('category=dropbox');
  if ( have_posts() ) : while ( have_posts() ) : the_post();
    wp_trash_post( the_id());
    endwhile;
    endif;
  echo "<script>location.href = '".admin_url( 'plugins.php?page=dropbox-settings' )."';</script>";
};


function dropbox_settings_callback() {

  $options = get_option( "dropbox-settings" );

  if( $_GET['msync'] == true ) {
    dropbox_sync();
    break;
  };

  if( $_GET['mcleanup'] == true ) {
    dropbox_cleanup();
    break;
  };

  if( $_GET['clear'] == true ) {
    $emptyOptions = array();
    update_option( "dropbox-settings", $emptyOptions );
    echo "<p>Clearing credentials...</p>";
    echo "<script>location.href = '".admin_url( 'plugins.php?page=dropbox-settings' )."';</script>";
  };

  if( $_GET['auth'] == true ) {

    $appInfo = new dbx\AppInfo( $options['app-key'], $options['app-secret'] );
    $redirectUrl = admin_url( 'plugins.php?page=dropbox-settings&auth=true' );
    $csrfTokenStore = new dbx\ArrayEntryStore( $_SESSION, 'dropbox-auth-csrf-token' );


    if( isset( $_GET['code'] ) ) {

      $params = array( 'code' => $_GET['code'], 'state' => $_GET['state'] );
      $webAuth = new dbx\WebAuth( $appInfo, CLIENT_NAME, $redirectUrl, $csrfTokenStore, "en" );

      list($accessToken, $dropboxUserId) = $webAuth->finish($params);

      $newOptions = array( 'dropbox-access-token' => $accessToken, 'dropbox-access-userid' => $dropboxUserId );
      update_option( "dropbox-settings", array_merge( $options, $newOptions ) );

      echo "<p>Storing access token...</p>";
      echo "<script>location.href = '".admin_url( 'plugins.php?page=dropbox-settings' )."';</script>";

    } else {

      $webAuth = new dbx\WebAuth( $appInfo, CLIENT_NAME, $redirectUrl, $csrfTokenStore, "en" );
      echo "<p>Redirecting you to Dropbox...</p>";
      echo "<script>location.href = '".$webAuth->start()."';</script>";
    };


  } else {
?>
<div class="wrap"><div id="icon-tools" class="icon32"></div>
<h2>Dropbox settings</h2>
  <form action="options.php" method="POST">
      <?php settings_fields( 'dropbox-settings' ); ?>
      <?php do_settings_sections( 'dropbox' ); ?>

      <?php submit_button(); ?>
  </form>
</div>

<script>
  window.onload = function() {
    var dbx_key_field = document.getElementById('dropbox_key');
    if( dbx_key_field.value.length == 0 ) {
      dbx_key_field.focus();
    };
  };
</script>

<?php
  };
};
?>
