<?php
/*
  Tweet2Download Wordpress Plugin
  Copyright (C) 2010  Razvan Pop

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

  You may contact the author of this program via electronic mail at razvan.pop@gmail.com

  == Credits ==

  This program uses the following 3rd party libraries:

  Twitter-async - Copyright (c) 2007, Jaisen Mathai. See the libraries own license for more details.

  == Plugin Header ==

  Plugin Name: Tweet2Download
  Description: Tweet2Download allows a wordpress blog to require <strong>a tweet and a follow in exchange for a download</strong>. To get started: 1) Click the "Activate" link to the left of this description, 2) <a href="https://dev.twitter.com/apps/new" target="_blank">Sign up for a Twitter consumer key and secret</a>, 3) Enter the Twitter API key, consumer key and secret in your <a href="options-general.php?page=tweet2download">Tweet2Download configuration</a> page
  Version: 1.4.1
  Author: Razvan Pop
  Plugin URI: http://inspiredcore.com/tweet2download-wordpress-plugin
  Author URI: http://twitter.com/popra
 */

global $t2d_shortcodes, $t2d_html;
$t2d_uploads_dir = wp_upload_dir();

define('T2D_LIBS_FOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'libs');
define('T2D_LIB_TWITTER_ASYNC_FOLDER', T2D_LIBS_FOLDER . DIRECTORY_SEPARATOR . 'jmathai-twitter-async');
define('T2D_UPLOADS_FOLDER', $t2d_uploads_dir['basedir'] . DIRECTORY_SEPARATOR . 'social2download');
define('T2D_UPLOADS_URL', get_bloginfo('url') . '/wp-content/plugins/tweet2download/download.php?f=');
define('T2D_IMAGES_URL', get_bloginfo('url') . '/wp-content/plugins/tweet2download/img/');

register_activation_hook(__FILE__, 't2d_activate');
add_action('admin_menu', 't2d_create_menu');
add_action('admin_init', 't2d_register_settings');

add_action('admin_notices', 't2d_admin_notices');

if ((trim(get_option('t2d_twitter_consumersecret', '')) != '')
        && (trim(get_option('t2d_twitter_consumerkey', '')) != '')
        && t2d_requirements_ok()) {

    // add hooks and filters only when we're set

    add_action('wp_print_scripts', 't2d_scripts');
    add_action('post-flash-upload-ui', 't2d_post_flash_upload_ui');
    add_action('post-html-upload-ui', 't2d_post_html_upload_ui');
    add_action('post-plupload-upload-ui', 't2d_post_plupload_upload_ui');

    add_filter('upload_post_params', 't2d_upload_post_params');

    add_filter('wp_handle_upload', 't2d_handle_upload', 0);
    add_filter('wp_get_attachment_url', 't2d_get_attachment_url', 20, 2);
    add_filter('upload_dir', 't2d_upload_dir', 20);
    add_filter('wp_generate_attachment_metadata', 't2d_generate_attachment_metadata', 20, 2);
    add_filter('get_attached_file', 't2d_get_attached_file', 20, 2);
    add_filter('post_mime_types', 't2d_post_mime_types');

    add_filter('media_buttons_context', 't2d_media_buttons_context');

    add_filter('attachment_fields_to_edit', 't2d_attachment_fields_to_edit', 10, 2);

    add_action('wp_insert_post', 't2d_wp_insert_post', 10, 2);

    add_shortcode('tweet2download', 't2d_shortcode');
}

function t2d_requirements_ok() {
    list($major, $mid, ) = explode('.', phpversion());

    if (($mid < 2) || ($major < 5)) {
        return false;
    }

    if (!function_exists("curl_init")) {
        return false;
    }
    return true;
}

function t2d_scripts() {
    wp_enqueue_script('tweet2download_script', get_bloginfo('url') . '/wp-content/plugins/tweet2download/js/tweet2download.js', array('jquery'));
}

/*
 * save 
 */

function t2d_wp_insert_post($post_id, $data) {
    //$data['post_content'];$data['post_content_filtered'];
    global $t2d_shortcodes;

    $t2d_shortcodes = array();

    //remove_all_shortcodes();
    remove_shortcode('tweet2download');
    add_shortcode('tweet2download', 't2d_shortcode_insert');
    do_shortcode($data->post_content);
    remove_shortcode('tweet2download');
    update_post_meta($post_id, 'tweet2download_post_shortcodes', $t2d_shortcodes);
}

function t2d_shortcode_insert($attrs, $content=null) {
    global $t2d_shortcodes;

    if ($content != null) {
        remove_shortcode('tweet2downloadhtml');
        add_shortcode('tweet2downloadhtml', 't2d_shortcode_html_insert');
        $content = do_shortcode($content);
        remove_shortcode('tweet2downloadhtml');
    }

    $t2d_shortcodes[] = array('attrs' => $attrs, 'content' => $content);
    return '';
}

function t2d_shortcode_html_insert($attrs, $content=null) {
    return '';
}

function t2d_admin_url($url, $path, $blog_id) {
    if ($path == '[tweet2download-media-icon]') {
        remove_filter('admin_url', 't2d_admin_url');
        return T2D_IMAGES_URL . 'media-icon.png';
    }
    return $url;
}

function t2d_media_buttons_context($html) {
    add_filter('admin_url', 't2d_admin_url', 10, 3);
    return $html . _media_button(__('Add Tweet2Download Button'), '[tweet2download-media-icon]', 'media', 't2d');
}

function t2d_admin_notices() {

    if (!t2d_requirements_ok()) {
        echo '<div class="error fade" style="background-color:red;"><p><strong>Tweet2Download requires PHP 5.2 or newer and cURL. Most web hosting services should have these, you might want to consider changing web hosting providers. You can find a cool one <a href="http://www.dreamhost.com/r.cgi?139477">here</a>, and we\'ll even give you a <a href="http://inspiredcore.com/freebie-massive-dreamhost-web-hosting-discount-coupon">discount code</a>.</strong></p></div>';
        return;
    }

    if ((trim(get_option('t2d_twitter_consumersecret', '')) == '')
            || (trim(get_option('t2d_twitter_consumerkey', '')) == '')) {
        echo '<div class="error fade" style="background-color:yellow;"><p><strong>' . ('Tweet2Download needs your attention.') . '</strong> Go to the <a href="options-general.php?page=tweet2download">Tweet2Download Settings</a> page to complete the install. </p></div>';
    }

    if (!is_dir(T2D_UPLOADS_FOLDER)) {
        $uploads_dir = wp_upload_dir();
        echo '<div class="error fade" style="background-color:red;"><p><strong>' . ('An upload folder for Tweet2Download could not be created. Please manualy create a writeable folder called "social2download" (without the quotes) under your uploads folder which is ' . $uploads_dir . '') . '</strong></p></div>';
    } else if (!is_writeable(T2D_UPLOADS_FOLDER)) {
        echo '<div class="error fade" style="background-color:red;"><p><strong>' . ('Your Tweet2Download upload folder is not writable (the folder is: ' . T2D_UPLOADS_FOLDER . '). Please manualy make it writeable.') . '</strong></p></div>';
    }
    if (!is_file(T2D_UPLOADS_FOLDER . '/.htaccess')) {
        echo '<div class="error fade" style="background-color:red;"><p><strong>' . ('Your Tweet2Download uploads are not protected. This could allow users to download the files without tweeting') . '</strong></p>
       	<p>To solve this, put a file called ".htaccess" (without the quotes) into the Tweet2Download uploads folder (which is: ' . T2D_UPLOADS_FOLDER . ') containing the text "deny from all" (without the quotes)</p></div>';
    }
}

function t2d_activate() {



    mt_srand();
    add_option('t2d_secret', md5(microtime() . mt_rand()));
    //add_option('t2d_twitter_apikey', '');
    add_option('t2d_twitter_consumerkey', '');
    add_option('t2d_twitter_consumersecret', '');

    @mkdir(T2D_UPLOADS_FOLDER, 0777, true);
    @file_put_contents(T2D_UPLOADS_FOLDER . '/.htaccess', "deny from all\n");
    @file_put_contents(T2D_UPLOADS_FOLDER . '/index.php', "");
}

function t2d_create_menu() {

    add_options_page('Tweet2Download Plugin Settings', 'Tweet2Download Settings', 'administrator', 'tweet2download', 't2d_settings_page');
}

function t2d_register_settings() {
    //register our settings
    //register_setting('t2d-settings-group', 't2d_twitter_apikey');
    register_setting('t2d-settings-group', 't2d_twitter_consumerkey');
    register_setting('t2d-settings-group', 't2d_twitter_consumersecret');

    add_settings_section('twitter_section', null, 't2d_twittersection', __FILE__);
    //$args = array('name' => 't2d_twitter_apikey');
    //add_settings_field('t2d_twitter_apikey', 'Twitter API key:', 't2d_inputtext', __FILE__, 'twitter_section', $args);
    $args = array('name' => 't2d_twitter_consumerkey');
    add_settings_field('t2d_twitter_consumerkey', 'Twitter Consumer key:', 't2d_inputtext', __FILE__, 'twitter_section', $args);
    $args = array('name' => 't2d_twitter_consumersecret');
    add_settings_field('t2d_twitter_consumersecret', 'Twitter Consumer secret:', 't2d_inputtext', __FILE__, 'twitter_section', $args);
}

function t2d_twittersection() {
    echo '<p>You can obtain a Twitter API key, Consumer key and Consumer secret from <a href="https://dev.twitter.com/apps/new" target="_blank">here</a>. If you need help obtaining these, please check out our <a href="http://inspiredcore.com/twitter-app-setup-for-tweet2download">documentation</a>.</p>';
}

function t2d_inputtext($args) {
    if (isset($args['desc'])) {
        echo '<p>' . $args['desc'] . '</p>';
    }
    echo '<input type="text" id="' . $args['name'] . '" class="regular-text" name="' . $args['name'] . '" value="' . get_option($args['name'], '') . '"/>';
}

function t2d_settings_page() {
    ?>
    <div class="wrap">
        <div class="icon32" id="icon-options-general"><br></div>
        <h2>Tweet2Download Options</h2>
        
        <div id="poststuff" class="metabox-holder has-right-sidebar">
            <div class="postbox">
                <div class="inside">Need a <strong>better hosting</strong> provider? We are proud supporters of <a target="_blank" href="http://www.dreamhost.com/r.cgi?139477">Dreamhost</a>, a host optimized for Wordpress with a lot of awesome features. <a target="_blank" href="http://www.dreamhost.com/r.cgi?139477">Give them a try!</a></div>
            </div>
            <!--  Some optional text here explaining the overall purpose of the options and what they relate to etc. -->
            <div class="postbox">
                <h3 class="hndle"><span>Twitter API</span></h3>
                <div class="inside">        
                    <form action="options.php" method="post">
                <?php settings_fields('t2d-settings-group'); ?>
                <?php do_settings_sections(__FILE__); ?>
                        <p class="submit">
                            <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function t2d_upload_post_params($post_params) {

    return $post_params;
}

function t2d_post_flash_upload_ui() {
    echo '
		<script type="text/javascript">
			function t2d_toggle_flash(e) {
				if (e.checked) {
					swfu.addPostParam("is_t2d_upload", "true");
				} else {
					swfu.removePostParam("is_t2d_upload");
				}
			}
		</script>
		<label><input type="checkbox" onclick="t2d_toggle_flash(this)"> These files will be downloaded using Tweet2Download</label>
	';
}

function t2d_post_html_upload_ui() {
    echo '
		<label><input type="checkbox" name="is_t2d_upload" value="true" /> This file will be downloaded using Tweet2Download</label>
	';
}

function t2d_post_plupload_upload_ui() {
    echo '
		<script type="text/javascript">
			function t2d_toggle_plupload(e) {
				if (e.checked) {
					uploader.settings["multipart_params"]["is_t2d_upload"] = "true";
				} else {
					uploader.settings["multipart_params"]["is_t2d_upload"] = "false";
				}
			}
		</script>
		<label><input type="checkbox" onclick="t2d_toggle_plupload(this)"> These files will be downloaded using Tweet2Download</label>
	';
}

function t2d_preety_filename($filename) {
    if (strlen($filename) <= 27) {
        return $filename;
    }
    return substr($filename, 0, 25) . ' ..';
}

function t2d_attachment_fields_to_edit($fields, $post) {

    if (is_tweet2download_attachment($post->ID)) {
        $meta = wp_get_attachment_metadata($post->ID);

        $file = basename(get_attached_file($post->ID));
        unset($fields[url]);

        $fields['t2d_button_script'] = array(
            'label' => '',
            'input' => 'html',
            'html' => '
				<script type="text/javascript">
					jQuery(document).ready(function(){
							e = jQuery(".savesend :input[id=\'send[' . $post->ID . ']\']");
							newEl = jQuery("<input type=\"button\" value=\"Insert Tweet2Download button into Post\" class=\"button\" />");
							newEl.click(
								function () {
									(window.dialogArguments||opener||parent||top).send_to_editor(\'[tweet2download file="' . $file . '" tweet="Add your tweet here. You can use %%post-url%% as a place holder for the post\\\'s url." follow="@inspiredcore" /]\');
								}
							);
							e.after(newEl);
							e.hide();
					});
				</script>
			'
        );
    }

    return $fields;
}

function is_tweet2download_file($file) {
    if (strpos(realpath($file), realpath(T2D_UPLOADS_FOLDER)) === 0) {
        return true;
    }

    return false;
}

function is_tweet2download_upload() {
    if (isset($_POST['is_t2d_upload'])
            && ($_POST['is_t2d_upload'] == "true")) {
        return true;
    }
    return false;
}

function is_tweet2download_attachment($id) {
    $meta = wp_get_attachment_metadata($id);
    if (isset($meta['is_tweet2download_upload']) && $meta['is_tweet2download_upload'] == true) {
        return true;
    }

    return false;
}

function t2d_shortcode_html($attrs, $content = null) {
    global $t2d_html;
    $t2d_html = $content;
    return '';
}

function t2d_shortcode($attrs, $content = null) {
    global $t2d_shortcodes, $t2d_html;

    $post_id = get_the_ID();
    $t2d_shortcodes[$post_id][] = $attrs;
    $shortcode_index = count($t2d_shortcodes[$post_id]) - 1;
    $tmp = get_post_meta($post_id, 'tweet2download_post_shortcodes', true);
    $attrs = $tmp[$shortcode_index];
    $attrs = $attrs['attrs'];

    if (!isset($attrs['tweet'])) {
        return 'tweet2download error: "tweet" attribute missing. eg. [tweet2download file="my-file.zip" tweet="I\'ve downloaded a file from %%post-url%%" follow="@batman"]';
    }

    if (!isset($attrs['follow'])) {
        return 'tweet2download error: "follow" attribute missing. eg. [tweet2download file="my-file.zip" tweet="I\'ve downloaded a file from %%post-url%%" follow="@batman"]';
    }

    $file = null;
    if (isset($attrs['file'])) {
        if (!is_tweet2download_attachment($attrs['file']) && !is_tweet2download_file(T2D_UPLOADS_FOLDER . '/' . $attrs['file'])) {
            return '[tweet2download error: file "' . htmlentities($attrs['file']) . '" is not a Tweet2Download file.]';
        }

        if (is_numeric($attrs['file'])) {
            $file = get_attached_file($attrs['file']);
        } else {
            $file = $attrs['file'];
        }
    }

    $t2d_html = null;
    if ($content != null) {
        add_shortcode("tweet2downloadhtml", "t2d_shortcode_html");
        do_shortcode($content);
        remove_shortcode("tweet2downloadhtml");
    }

    $script = "w=800;h=560;l=parseInt((screen.availWidth/2)-(w/2));t=parseInt((screen.availHeight/2)-(h/2));window.open('" . t2d_download_url($post_id, $shortcode_index) . "', 'open_window', 'location, directories, status, scrollbars, resizable, dependent, width='+w+', height='+h+', left='+l+', top='+t+'');return false;";

    $hidden = '<p id="tweet2download-' . $post_id . '-' . $shortcode_index . '" style="display:none;"></p>';
    if ($t2d_html != null) {
        // $t2d_html can be modified in do_shortcode() by t2d_shortcode_html();
        $t2d_html = str_replace('%%tweet2download-href%%', htmlentities(t2d_download_url($post_id, $shortcode_index), ENT_COMPAT, 'UTF-8'), $t2d_html);
        $t2d_html = str_replace('%%tweet2download-onclick%%', $script, $t2d_html);
        $t2d_html = str_replace('%%tweet2download-filename%%', htmlentities(basename($file), ENT_COMPAT, 'UTF-8'), $t2d_html);
        $t2d_html = str_replace('%%tweet2download-preetyfilename%%', htmlentities(t2d_preety_filename(basename($file)), ENT_COMPAT, 'UTF-8'), $t2d_html);
        return $t2d_html . $hidden;
    }

    if (!is_null($file)) {
        return "<a style=\"background-image:url(" . T2D_IMAGES_URL . "t2d.png);display:block;height:26px;width:160px;padding-top:36px;padding-left:48px;font-size:11px;color:#80b4f1;font-family:Arial,Helvetica,sans-serif;\" onclick=\"" . $script . "\" target=\"_blank\" href=\"" . t2d_download_url($post_id, $shortcode_index) . "\" title=\"Download " . basename($file) . " using Tweet2Download\">" . t2d_preety_filename(basename($file)) . "</a>";
    } else {
        return "<a style=\"background-image:url(" . T2D_IMAGES_URL . "t2d.png);display:block;height:26px;width:160px;padding-top:36px;padding-left:48px;font-size:11px;color:#80b4f1;font-family:Arial,Helvetica,sans-serif;\" onclick=\"" . $script . "\" target=\"_blank\" href=\"" . t2d_download_url($post_id, $shortcode_index) . "\" title=\"View hidden content using Tweet2Download\">Show me the hidden content!</a>" . $hidden;
    }
}

function t2d_download_url($post_id, $shortcode_index) {
    return get_bloginfo('url') . '/wp-content/plugins/tweet2download/download.php?f=' . urlencode($post_id) . '-' . urlencode($shortcode_index);
}

function t2d_post_mime_types($mime_types) {
    //'video' => array(__('Video'), __('Manage Video'), _n_noop('Video <span class="count">(%s)</span>', 'Video <span class="count">(%s)</span>')),
    $mime_types['tweet2download'] = array(
        __('Tweet2Download'),
        __('Manage Tweet2Download'),
        _n_noop('Tweet2Download <span class="count">(%s)</span>', 'Tweet2Downloads <span class="count">(%s)</span>')
    );
    return $mime_types;
}

function t2d_get_attached_file($file, $id) {
    if (is_tweet2download_attachment($id)) {
        return T2D_UPLOADS_FOLDER . '/' . basename($file);
    }
    return $file;
}

function t2d_upload_dir($upload) {
    if (is_tweet2download_upload()) {
        $upload['path'] = T2D_UPLOADS_FOLDER;
        $upload['url'] = T2D_UPLOADS_URL;
        $upload['subdir'] = '/';
        $upload['basedir'] = T2D_UPLOADS_FOLDER;
        $upload['baseurl'] = T2D_UPLOADS_URL;
    }
    return $upload;
}

function t2d_generate_attachment_metadata($metadata, $id) {
    if (is_tweet2download_upload()) {
        $metadata['is_tweet2download_upload'] = true;
    }
    return $metadata;
}

function t2d_handle_upload($file) {
    if (is_tweet2download_upload() && is_file($file['file']) && is_writable($file['file'])) {
        $file['type'] = 'tweet2download/octet-stream';
    }
    return $file;
}

function t2d_get_attachment_url($url, $post_id) {
    if (is_tweet2download_upload()) {
        return T2D_UPLOADS_URL . '/' . urlencode(basename($url));
    }
    if (is_tweet2download_attachment($post_id)) {
        return T2D_UPLOADS_URL . '/' . urlencode(basename($url));
    }
    return $url;
}

function t2d_relative_upload_path($new_path, $path) {

    if (is_tweet2download_upload()) {
        return basename($new_path);
    }

    return $new_path;
}

function t2d_send_download($file) {
    if (file_exists($file)) {

        ob_end_clean();

        header('Content-Description: Download');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: private');
        header('Content-Length: ' . filesize($file));

        if ((isset($_ENV['XSendFile'])) && ($_ENV['XSendFile'] == 'on')) {
            header('X-Sendfile: ' . $file);
            flush();
        } else {
            readfile($file);
            flush();
        }

        die();
    }
}

function t2d_get_tweet($shortcode) {
    return substr(str_replace('%%post-url%%', get_permalink($shortcode['post']->ID), $shortcode['attrs']['tweet']), 0, 140);
}

function t2d_get_follow($shortcode) {
    $followers = explode(',', $shortcode['attrs']['follow']);

    $ret = array();
    foreach ($followers as $f) {
        $tmp = trim(str_replace("@", '', $f));
        if (!empty($tmp)) {
            $ret[] = $tmp;
        }
    }
    return $ret;
}

function t2d_get_shortcode($shortcode_id) {

    list($post_id, $shortcode_index) = explode('-', $shortcode_id);
    if (!is_numeric($shortcode_index) || !is_numeric($post_id)) {
        return false;
    }

    $meta = get_post_meta($post_id, 'tweet2download_post_shortcodes', true);
    if (empty($meta) || !is_array($meta) || !isset($meta[$shortcode_index])) {
        return false;
    }

    $shortcode = $meta[$shortcode_index];

    if (isset($shortcode['attrs']['file'])) {

        if (is_numeric($shortcode['attrs']['file'])
                && is_tweet2download_attachment($shortcode['attrs']['file'])) {

            $attachment = get_post($shortcode['attrs']['file']);
            $attachment->file_path = get_attached_file($shortcode['attrs']['file']);
        } else if (is_string($shortcode['attrs']['file'])) {
            $attachment_path = realpath(T2D_UPLOADS_FOLDER . '/' . $shortcode['attrs']['file']);
            if (!is_tweet2download_file($attachment_path)) {
                return false;
            }
            $attachment = new stdClass();
            $attachment->file_path = $attachment_path;
        } else {
            return false;
        }
    }

    $shortcode['post'] = get_post($post_id);
    $shortcode['attachment'] = $attachment;

    return $shortcode;
}

function t2d_sign($params) {
    $params['rip'] = $_SERVER['REMOTE_ADDR'];
    $params['now'] = time();
    $params['ttl'] = 5 * 60;

    ksort($params);
    $str = '';
    foreach ($params as $key => $value) {
        $str.= urlencode($key) . '=' . urlencode($value) . '&';
    }
    $str .= 'sign=' . md5($str . get_option('t2d_secret', md5(mt_rand())));
    return $str;
}

function t2d_checksign($params, $sign) {
    if ($_SERVER['REMOTE_ADDR'] != $params['rip']) {
        return false;
    }

    if (time() > ($params['now'] + $params['ttl'])) {
        return false;
    }

    ksort($params);
    $str = '';
    foreach ($params as $key => $value) {
        $str.= urlencode($key) . '=' . urlencode($value) . '&';
    }

    if ($sign != md5($str . get_option('t2d_secret', md5(mt_rand())))) {
        return false;
    }

    return true;
}