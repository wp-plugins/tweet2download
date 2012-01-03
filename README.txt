=== Plugin Name ===
Contributors: popra
Tags: social, viral, social marketing, viral marketing, twitter, download, pay with a tweet, tweet to download, tweet and get it, auto tweet, auto follow, auto followers
Requires at least: 3.0
Tested up to: 3.3.0
Stable tag: 1.4.1

Tweet2Download allows a wordpress blog to require a tweet and a follow in exchange for the downloads or article parts available on the blog.

== Description ==

If you have a blog that provides downloads, this plugin is the perfect twitter marketing tool for you. [Tweet2Download](http://inspiredcore.com/tweet2download-wordpress-plugin) allows
you to require a tweet and a follow in exchange for a download or a content snippet on your blog. It effectively asks your users to ["pay" with a tweet](http://inspiredcore.com/tweet2download-wordpress-plugin) for free content on your blog, making it a very powerfull viral marketing tool. 

The plugin is well suited for blogs that offer music, videos, tips & tricks, ezines, software, code, design creatives, tutorials, torrent files, download links or any kind of downloads/content for that matter. 

**Requires:**
PHP 5.2 or newer, cURL, tested with Wordpress 3.0 and newer

**Usage:**

1. Upload a file to wordpress (wp-admin -> Media -> Add New), make sure to tick "These files will be downloaded using Tweet2Download"
2. When editing a post, click the "Add Tweet2Download Button" icon (next to the "Upload/Insert" media icons)
3. Select the file you previously uploaded, and click "Insert Tweet2Download button into Post"
4. Change the shortcode to contain the tweet and follow you want, Publish!

For more information and to get an idea about how the plugin works, go to the [Tweet2Download home page](http://inspiredcore.com/tweet2download-wordpress-plugin) 
and give it a try!

== Screenshots ==

1. Uploading a file
2. Inserting a file into the post
3. The Tweet2Download button in action

== Installation ==

Same as the Akismet plugin, Tweet2Download requires an API key from Twitter to work. You can get the required 
Twitter API key, consumer secret and consumer key from [here](https://dev.twitter.com/apps/new), just make sure to 
select *Default Access type:* *Read & Write* in order for the plugin to work properly. For more information on how to properly
get the [Twitter API key, consumer secret and consumer key for Tweet2Download](http://inspiredcore.com/twitter-app-setup-for-tweet2download)
see our documentation page on the subject. 
  
To install the Tweet2Download Wordpress Plugin, follow these steps:

1. Extract and upload the `tweet2download` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create a Twitter App for your blog [here](https://dev.twitter.com/apps/new), for more information on how to do this see the [documentation](http://inspiredcore.com/twitter-app-setup-for-tweet2download)
4. Go to the plugin setup page (wp-admin -> Settings -> Tweet2Download Settings) and fill in the Twitter App info

== Changelog ==

= 1.4.0 =
* fixed compatibility issues with Wordpress 3.3
* adjusted settings page to reflect Twitter APP changes

= 1.3.0 =
* plugin is now able to reveal hidden content on the page in exchange for a tweet and a follow
* added ability to customize the Tweet2Download button with the [tweet2downloadhtml] shortcode
* some bugfixes 

= 1.2.1 =
* First public release

== Upgrade Notice ==

= 1.3 =
You can customize the Tweet2Download button, additionally, instead of downloads you can now also provide post/page parts in exchange for a tweet and a download.
