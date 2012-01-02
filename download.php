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
 */

define('WP_USE_THEMES', false);
require('../../../wp-blog-header.php');
status_header(200);

$shortcode_id = isset($_GET['f']) ? trim($_GET['f']) : null;

if (is_null($shortcode_id)) {
    wp_die('No file', 'No file provided', array('response' => 404));
}

$shortcode = t2d_get_shortcode($shortcode_id);

if ($shortcode === false) {
    wp_die('Shortcode error', 'Shortcode Error', array('response' => 404));
}

$meta = $shortcode['attrs'];

require_once(T2D_LIB_TWITTER_ASYNC_FOLDER . '/EpiCurl.php');
require_once(T2D_LIB_TWITTER_ASYNC_FOLDER . '/EpiOAuth.php');
require_once(T2D_LIB_TWITTER_ASYNC_FOLDER . '/EpiTwitter.php');

$twitterObj = new EpiTwitter(get_option('t2d_twitter_consumerkey', ''), get_option('t2d_twitter_consumersecret', ''));

if (isset($_GET['denied'])) {
    // user denied access to twitter
    wp_die('Seems you denied the tweet. You\'ll not be directed to the download.', 'No tweet?');
} else if (isset($_GET['sign'])) {
    // final step
    if (t2d_checksign(array(
                'f' => $_GET['f'],
                'ttl' => $_GET['ttl'],
                'rip' => $_GET['rip'],
                'now' => $_GET['now']
                    ), $_GET['sign'])) {
        t2d_send_download($shortcode['attachment']->file_path);
    } else {
        wp_die('Failed to validate the download url.', 'Signing issue');
    }
} else if (!isset($_GET['oauth_token']) || !isset($_GET['oauth_verifier'])) {
    // entry point
    try {
        $url = $twitterObj->getAuthorizeUrl(null, array('oauth_callback' => T2D_UPLOADS_URL . $shortcode_id));
    } catch (Exception $e) {
        wp_die('There was an error connecting to twitter, probably a consumer key/secret issue.');
    }
    echo t2d_intro_page($shortcode, $url, get_option('t2d_twitter_consumerkey'));
} else {
    // user granted access to twitter
    $ok = false;

    try {
        $twitterObj->setToken($_GET['oauth_token']);
        $token = $twitterObj->getAccessToken(array('oauth_verifier' => $_GET['oauth_verifier']));
        $twitterObj->setToken($token->oauth_token, $token->oauth_token_secret);
        $twitterInfo = $twitterObj->get('/account/verify_credentials.json');

        $tweet = t2d_get_tweet($shortcode);
        try {
            $twitterObj->post_statusesUpdate(array('status' => $tweet));
        } catch (EpiTwitterForbiddenException $e) {
            if ($msg->error != 'Status is a duplicate.') {
                throw $e;
            }
        }
        $followers = t2d_get_follow($shortcode);
        foreach ($followers as $follow) {
            try {
                $twitterObj->post_friendshipsCreate(array('screen_name' => $follow));
            } catch (Exception $e) {
                
            }
        }

        $ok = true;
    } catch (EpiTwitterForbiddenException $e) {
        $msg = json_decode($e->getMessage());
        if ($msg->error == 'Status is a duplicate.') {
            $ok = true;
        }
    } catch (Exception $e) {
        wp_die('Could not tweet, please try again later. (' . $e->getCode() . ':' . $e->getMessage() . ')', 'Could not tweet.');
    }

    if ($ok) {

        if (isset($shortcode['attachment']->file_path)) {
            $sign_str = t2d_sign(array(
                'f' => $_GET['f']
                    ));

            $url = str_replace('f=', '', T2D_UPLOADS_URL) . $sign_str;
            echo t2d_download_page($shortcode, $url);
        } else {
            echo t2d_unhide_page($shortcode, 'tweet2download-' . $_GET['f']);
        }
    }
}

function t2d_intro_page($shortcode, $twitter_url, $twitter_apikey) {
    $meta = $shortcode['attrs'];
    $template = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<script src="http://platform.twitter.com/anywhere.js?id=[twitter-apikey]&v=1" type="text/javascript"></script>
<!--STYLE-->
<style type="text/css">
body {background-color: #68bfe1; font-family:Arial, Helvetica, sans-serif;}
#logo {background-image:url(\'img/logo.jpg\'); width: 770px; height: 104px;  }
#content { background-color:#FFF; width: 770px; hddeight: 334px; }
.title { color: #1c1c1c; padding-top: 20px; padding-left: 20px; margin-bottom: 20px; font-weight: bold; margin-top: 0px; }
.tweet { padding: 7px 100px; font-size:13px; line-height:19px; margin-left: 20px; margin-right: 20px; text-align:left; color: #343434; background-color: #eeeeee; border: 1px solid #e0e0e0; }
.link { font-style:italic; font-size:13px; line-height:19px; color: #197da4; }
.link2 { font-size:11px; text-decoration:underline; line-height:19px; color: #c2eeff; }
.account { font-weight: bold; font-size:14px; line-height:19px;  margin: 0 20px 10px 20px; padding-top: 7px; padding-bottom: 7px; text-align:center; color: #3e3e3e; background-color: #eeeeee; border: 1px solid #e0e0e0; }
.button { background-image:url(\'img/button.png\'); background-repeat:no-repeat;  color:#FFF; font-size:16px; text-decoration:none;  padding: 5px 33px 5px 33px; font-weight: bold; width: 284px; }
.ads { font-size:11px; text-align:center; color:#FFF; margin-top: 25px; }
.p1 { font-size: 13px; color: #7f7f7f; text-align:center; margin: 0px; padding-top: 10px; line-height: 18px; }
.line-separator{height:1px; border-bottom:1px solid #c8dbe3; width: 730px; margin-left: 20px; margin-top: 10px; }
</style>
<!--END STYLE-->
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>[title]</title>
</head>

<body>
<script type="text/javascript">
	twttr.anywhere(function (T) {
		T("#tweet").linkifyUsers();
		T("#account").linkifyUsers();
		T.hovercards();
	});
</script>

<div id="logo"></div>
<div id="content">
		<p class="p1">[message]</p>
		<p class="p1">Please connect to Twitter using the button below to begin.</p>
		<div class="line-separator"></div>
		<p class="title">You\'ll send this tweet:</p>
    	<p class="tweet" id="tweet">[tweet-message]</p>
		<p class="title">and you will follow:</p>
    	<p class="account" id="account">[follow-user]</p>
        <center><a href="[twitter-authorization-url]" title="Click to start the download"><img style="border:none;" src="img/button.png" width="249" height="49" /></a></center>
</div>
<p class="ads">Feature provided by <a href="http://inspiredcore.com/tweet2download-wordpress-plugin" class="link2">Tweet2Download</a> a Wordpress plugin created by <a href="http://inspiredcore.com/" class="link2">InspiredCore</a></<p>    
</body>
</html>';

    $template = str_replace('[twitter-authorization-url]', $twitter_url, $template);
    $template = str_replace('[tweet-message]', htmlentities(t2d_get_tweet($shortcode), ENT_COMPAT, 'UTF-8'), $template);
    $template = str_replace('[follow-user]', htmlentities('@' . implode(',@', t2d_get_follow($shortcode)), ENT_COMPAT, 'UTF-8'), $template);

    if (isset($shortcode['attachment']->file_path)) {
        // [download-filename]
        $template = str_replace('[title]', htmlentities('Downloading ' . basename($shortcode['attachment']->file_path) . ' | Tweet2Download', ENT_COMPAT, 'UTF-8'), $template);
    } else {
        $template = str_replace('[title]', htmlentities('Viewing hidden content | Tweet2Download', ENT_COMPAT, 'UTF-8'), $template);
    }
    if (isset($shortcode['attachment']->file_path)) {
        $template = str_replace('[message]', 'You are about to download <strong>' . htmlentities('' . basename($shortcode['attachment']->file_path) . '', ENT_COMPAT, 'UTF-8') . '</strong>', $template);
    } else {
        $template = str_replace('[message]', htmlentities('You are about to view hidden content on this page.', ENT_COMPAT, 'UTF-8'), $template);
    }

    $template = str_replace('[twitter-apikey]', htmlentities($twitter_apikey, ENT_COMPAT, 'UTF-8'), $template);

    return $template;
}

function t2d_unhide_page($shortcode, $element_id) {

    if (!$guessurl = site_url())
        $guessurl = wp_guess_url();

    $template = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<!--STYLE-->
<style type="text/css">
body {background-color: #68bfe1; font-family:Arial, Helvetica, sans-serif;}
#logo {background-image:url(\'img/logo.jpg\'); width: 770px; height: 104px;  }
#content { background-color:#FFF; width: 770px; hei//ght: 130px; }
.title { color: #1c1c1c; padding-top: 20px; padding-left: 20px; margin-bottom: 20px; font-weight: bold; margin-top: 0px; }
.tweet { font-style:italic; font-size:13px; line-height:19px; margin-left: 20px; margin-right: 20px; text-align:center; color: #343434; background-color: #eeeeee; border: 1px solid #e0e0e0; }
.link { font-style:italic; font-size:13px; line-height:19px; color: #197da4; }
.link2 { font-size:11px; text-decoration:underline; line-height:19px; color: #c2eeff; }
.account { font-weight: bold; font-size:14px; line-height:19px;  margin: 0 20px 10px 20px; padding-top: 7px; padding-bottom: 7px; text-align:center; color: #3e3e3e; background-color: #eeeeee; border: 1px solid #e0e0e0; }
.button { background-image:url(\'img/buttonthx.png\'); background-repeat:no-repeat;  color:#FFF; font-size:16px; text-decoration:none;  padding: 5px 33px 5px 33px; font-weight: bold; width: 137px; height: 37px; border:none; margin-bottom: 10px; }
.ads { font-size:11px; text-align:center; color:#FFF; margin-top: 25px; }
.p1 { font-size: 13px; color: #7f7f7f; text-align:center; margin: 0px; padding-top: 10px; line-height: 18px; }
.line-separator{height:1px; border-bottom:1px solid #c8dbe3; width: 730px; margin-left: 20px; margin-top: 10px; }
</style>
<!--END STYLE-->
<script type="text/javascript" src="' . $guessurl . '/wp-admin/load-scripts.php?c=1&load=jquery"></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Download complete! | Tweet2Download</title>
</head>
<body>
	<div id="logo"></div>
	<div id="content"> 
		<p class="p1">Thanks for the tweet and follow, we really appreciate your support!</p>
		<br />
		<center><input class="button" type="button" onclick="self.close();"/></center>
		<div id="hiddencontent">
		<h3>Here\'s the content:</h3>
		' . $shortcode['content'] . '
		</div>
	</div>
	<p class="ads">Feature provided by <a href="http://inspiredcore.com/tweet2download-wordpress-plugin" class="link2">Tweet2Download</a> a Wordpress plugin created by <a href="http://inspiredcore.com/" class="link2">InspiredCore</a></<p>    
	<script type="text/javascript">
		(window.dialogArguments||opener||parent||top).tweet2download_show_content("[element-id]", ' . json_encode($shortcode['content']) . ');
		if (window.focus) {
			(window.dialogArguments||opener||parent||top).focus();
		}
		el = document.getElementById("hiddencontent");
		el.style.display = "none"; 
	</script>
</body>
</html>
	';
    $template = str_replace('[element-id]', $element_id, $template);
    return $template;
}

function t2d_download_page($shortcode, $download_url) {

    $template = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta HTTP-EQUIV="REFRESH" content="0; url=[download-url]">
<!--STYLE-->
<style type="text/css">
body {background-color: #68bfe1; font-family:Arial, Helvetica, sans-serif;}
#logo {background-image:url(\'img/logo.jpg\'); width: 770px; height: 104px;  }
#content { background-color:#FFF; width: 770px; height: 130px; }
.title { color: #1c1c1c; padding-top: 20px; padding-left: 20px; margin-bottom: 20px; font-weight: bold; margin-top: 0px; }
.tweet { font-style:italic; font-size:13px; line-height:19px; margin-left: 20px; margin-right: 20px; text-align:center; color: #343434; background-color: #eeeeee; border: 1px solid #e0e0e0; }
.link { font-style:italic; font-size:13px; line-height:19px; color: #197da4; }
.link2 { font-size:11px; text-decoration:underline; line-height:19px; color: #c2eeff; }
.account { font-weight: bold; font-size:14px; line-height:19px;  margin: 0 20px 10px 20px; padding-top: 7px; padding-bottom: 7px; text-align:center; color: #3e3e3e; background-color: #eeeeee; border: 1px solid #e0e0e0; }
.button { background-image:url(\'img/buttonthx.png\'); background-repeat:no-repeat;  color:#FFF; font-size:16px; text-decoration:none;  padding: 5px 33px 5px 33px; font-weight: bold; width: 137px; height: 37px; border:none; }
.ads { font-size:11px; text-align:center; color:#FFF; margin-top: 25px; }
.p1 { font-size: 13px; color: #7f7f7f; text-align:center; margin: 0px; padding-top: 10px; line-height: 18px; }
.line-separator{height:1px; border-bottom:1px solid #c8dbe3; width: 730px; margin-left: 20px; margin-top: 10px; }
</style>
<!--END STYLE-->
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Download complete! | Tweet2Download</title>
</head>
<body>
<script>
</script>
	<div id="logo"></div>
	<div id="content"> 
		<p class="p1">Thanks for the tweet and follow, we really appreciate your support!</p>
		<p class="p1">Your download should begin shortly. If the download doesn\'t start, click <a href="[download-url]">here</a> to download your file.</p>
		<br />
		<center><input class="button" type="button" onclick="self.close();"/></center>
	</div>
	<p class="ads">Feature provided by <a href="http://inspiredcore.com/tweet2download-wordpress-plugin" class="link2">Tweet2Download</a> a Wordpress plugin created by <a href="http://inspiredcore.com/" class="link2">InspiredCore</a></<p>    
</body>
</html>
	';

    $template = str_replace('[download-url]', $download_url, $template);
    return $template;
}