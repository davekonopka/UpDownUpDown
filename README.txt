=== UpDownUpDown ===
Contributors: dkonopka
Tags: voting, votes, posts, comments
Requires at least: 3.0
Tested up to: 3.1.3
Stable tag: trunk

Simple WordPress plugin for up/down voting on posts and comments.

== Description ==

UpDownUpDown provides two template tags for adding up/down voting for any post or comment. Anonymous guest visitors can either be allowed to vote (tracked by ip address) or be denied voting and shown a view-only vote count badge. Votes are registered on the server without refreshing the page.

Fork the Github repo: [https://github.com/davekonopka/updownupdown](https://github.com/davekonopka/updownupdown)

This plugin was initially developed as a project of Wharton Research Data Services.
  
== Installation ==

1. Upload the folder containing the plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place the following function calls in your templates, somewhere inside the post or comment loop:
  1. `<?php if(function_exists('up_down_post_votes')) { up_down_post_votes( get_the_ID() ); } ?>`
  1. `<?php if(function_exists('up_down_comment_votes')) { up_down_comment_votes( get_comment_ID() ); } ?>`
1. Display vote badge display only with no voting by setting the second parameter to false:
  1. `<?php if(function_exists('up_down_post_votes')) { up_down_post_votes( get_the_ID(), false ); } ?>`
  1. `<?php if(function_exists('up_down_comment_votes')) { up_down_comment_votes( get_comment_ID(), false ); } ?>`
1. Visit the plugin settings page to customize it.

== Screenshots ==

1. This shows a badge in a post with no votes yet.
2. This shows a badge in a post with a vote set.
3. This shows a view-only badge if voting is disabled via theme function flag or guest voting is disabled in the admin settings.
4. This shows a badge using the alternate simple style.
5. Admin settings page for the plugin.

== Changelog ==

= 1.1 =
* Release includes major contributions by Martin Scharm
* Added admin page
* Added option to allow guest-votes
* Added option to select from multiple styles, added simple styling
* Choose between up/down counts or a total count
* Fixed some XHTML errors

= 1.0.1 =
* Replaced JavaScript JSON.parse reference with jQuery.parseJSON to accommodate browsers without native JSON support. In IE7 votes were registering on the server but not updating in the browser without a refresh. Now it's fixed in IE7.

= 1.0 =
* Initial release.