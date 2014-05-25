=== Formatting correcter ===

Author: sedLex
Contributors: sedLex
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/plugins/formatting-correcter/
Tags: tag
Requires at least: 3.0
Tested up to: 3.9
Stable tag: trunk

The plugin detects any formatting issues in your posts such as "double space" or any other issues that you may configure and proposes to correct them accordingly. 

== Description ==

The plugin detects any formatting issues in your posts such as "double space" or any other issues that you may configure and proposes to correct them accordingly. 

= Multisite - Wordpress MU =

This plugins works on MU installations

= Localization =

* English (United States), default language

= Features of the framework =

This plugin uses the SL framework. This framework eases the creation of new plugins by providing tools and frames (see dev-toolbox plugin for more info).

You may easily translate the text of the plugin and submit it to the developer, send a feedback, or choose the location of the plugin in the admin panel.

Have fun !

== Installation ==

1. Upload this folder formatting-correcter to your plugin directory (for instance '/wp-content/plugins/')
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'SL plugins' box
4. All plugins developed with the SL core will be listed in this box
5. Enjoy !

== Screenshots ==

1. Results page
2. Modification page
3. Parameters page

== Changelog ==

= 1.1.4 = 
* BUG : Avoid duplicate entries

= 1.1.3 = 
* NEW : Simplify the h1, h2, h3, etc. header tags to remove unneeded data

= 1.1.2 = 
* NEW : Show that the link is an attachment if so 
* NEW : add the Edit link in the tabs

= 1.1.1 = 
* BUG : Object expected while it was an ID

= 1.1.0 = 
* NEW : Now attachment may be anaysed 
* NEW : title and excerpt may also be anaysed

= 1.0.0 -&gt; 1.0.12 = 
* BUG: the tinyMCE may be broken in certain situations
* NEW: Update of the framework
* BUG: Finally, modifiying ... into, &-helip; is not a good idea
* NEW: The ... will be modified into &-helip;
* NEW: improve the look of the plugin
* BUG: the  non breaking space should not be in a html tag
* BUG: global improvement of the regex
* NEW: add a blank after a comma 
* NEW: add a blank after a double quote 
* NEW: add a option to check EPC rules/articles
* NEW: display the number of articles to be checked / checked
* NEW: add a option to check EPC guidelines
* BUG: The force analysis were not able to get all id of the post, page
* NEW : Remove incorrect non breaking space
* NEW : Three dots may be change into ellipses
* NEW : A force analysis of all articles are available
* NEW : Space may be removed from inside HTML tag
* NEW : improve the leading space removal
* NEW : you may delete the DIV tag in the text of posts
* NEW : you may now approve all propositions at the same time
* NEW : you may edit manually the text if their is an issue
* First release on the WP directory

== Frequently Asked Questions ==

 
InfoVersion:1decc85676b77adddea80ca329275b3afbdaccd7