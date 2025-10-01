=== My Private Site with AI Defense ===
Contributors: dgewirtz
Donate link: http://zatzlabs.com/lab-notes/
Tags: login, visibility, private, security, bots
Requires at least: 4.4
Requires PHP: 5.4
Tested up to: 6.8
Stable tag: 4.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lock down your site with one click. Privacy for family, projects, or teams.

== Description ==

**My Private Site** is the easiest way to make your entire WordPress site private. With one setting, you can restrict all pages and posts so they are visible only to logged-in users. Visitors who are not signed in are automatically redirected to the WordPress login screen.  

Unlike full membership plugins, My Private Site does not require the overhead of subscriptions, payments, or profile management. Instead, it focuses on strong, lightweight privacy, perfect for projects where you just need to keep your site limited to a trusted audience.

### Ideal Use Cases
- **Family sites and school projects**: Share personal updates, photos, or assignments only with family members, classmates, or teachers you choose.
- **Development and staging sites**: Safely show work-in-progress to clients or teammates without exposing unfinished content or letting it be indexed by search engines.
- **Clubs, groups, and internal blogs**: Create a private online space for members or staff without the overhead of a complex membership system.

### Key Features
* One-click option to make your entire WordPress site private.
* Optional setting to keep the homepage public while locking down the rest of the site.
* Automatic login prompts whenever non-logged-in users try to access content.
* Flexible landing page control—send users back to the page they requested, to your homepage, dashboard, or a custom URL after login.
* Hide the WordPress admin bar on the front end for a cleaner look.
* REST API Guardian: block REST API access for logged-out users.
* Works with custom login pages, Multisite, BuddyPress, and Theme My Login.
* Privacy shortcode lets you selectively show or hide content within a page or post.

### Built-in AI Defense
The internet is rapidly changing, with AI crawlers and bots harvesting content without consent. My Private Site helps you defend your work with integrated **AI Defense** features:
* **NoAI and NoImageAI tags**: Automatically add meta tags and headers that signal compliant AI systems not to use your text or images for training.
* **Block GPTBot**: Add a robots.txt rule to prevent OpenAI’s crawler from accessing your site.
* **Really Simple Licensing (RSL)**: Publish a machine-readable license that explicitly prohibits AI training on your content.

These protections are included free in the core plugin, easy to enable with a checkbox, and designed to safeguard your site without affecting normal visitors or search engines. You can use them even if you're not using any other site privacy features.

### Privacy Made Simple for Teams, Projects, and Families
In short, My Private Site turns your WordPress installation into a secure, invite-only space with just a few clicks. Whether you’re running a private blog, staging a project, or safeguarding your family site, it provides peace of mind, modern AI defenses, and effortless setup—all while staying lightweight and easy to manage.

### Watch the Video Overview and Demo

https://youtu.be/jry3DHD-OB8

### Premium Add-ons
Premium add-ons turn My Private Site into a comprehensive privacy suite, giving you enterprise-style layered security defenses, smarter oversight, and flexible access, without the complexity or cost.

Advanced AI Defense, Visitor Intelligence, and Block IP provide protections regardless of whether you're using any site privacy features.

https://youtu.be/B6s8O9VZLc0

* [Public Pages 2.0](https://zatzlabs.com/project/my-private-site-public-pages/): Allows site operators to designate certain specific pages, or pages with specified prefix, to be available to the public without login. Now also allows public site, private pages. [Watch the video](https://youtu.be/u7BuYtzS_pI)
* [Advanced AI Defense](https://zatzlabs.com/project/my-private-site-advanced-ai-defense/): Protect WordPress content from AI crawlers using licensing, opt-out tags, selective bot blocking, and firewall defenses to control and safeguard your data. [Watch the video](https://youtu.be/Eb4qQDafaRk)
* [Visitor Intelligence](https://zatzlabs.com/project/my-private-site-visitor-intelligence/): Track logins, logouts, failed attempts, and bot activity with a unified log, anomaly detection, and export tools for stronger site oversight and security. [Watch the video](https://youtu.be/TTK8bGVD8pM)
* [Guest Access](https://zatzlabs.com/project/my-private-site-guest-access/): Grant temporary, secure access to private WordPress content using unique shareable links with expiration, one-time use, and full admin-controlled invite management. [Watch the video](https://youtu.be/j1vYV8lhqcc)
* [Block IP](https://zatzlabs.com/project/my-private-site-block-ip/): Block unwanted visitors by IP address or range with full IPv4/IPv6 support, configurable scope, and fast enforcement to secure your WordPress site. [Watch the video](https://youtu.be/vsxLqYXWITs)
* [Tags & Categories](https://zatzlabs.com/project/my-private-site-tags-and-categories/): Allows you to make pages public or (with Public Pages 2.0) private based on tags and categories. [Watch the video](https://youtu.be/dEv7lXxU5lo)
* [Selective Content](https://zatzlabs.com/project/my-private-site-selective-content/): Allows hiding, showing, and obscurifying page content through the use of shortcodes. Can also selectively hide widgets and sidebars. [Watch the video](https://youtu.be/exgJrJJSCNY)
* [Digital Fortress Bundle](https://zatzlabs.com/project/my-private-site-pricing/): All add-ons are available in bundle form.  [Watch the video](https://youtu.be/B6s8O9VZLc0)

## Limits

This plugin does not hide non-WordPress web pages, such as .html and .php files. It also won’t restrict images and other media and text files directly accessed by their URL. If your hosting provider’s filesystem protections haven’t been set up correctly, files may also be accessed by directory listing.

## Support Note

Support has moved to the ZATZLabs site and is no longer provided on the WordPress.org forums. If you need a timely reply from the developer, please [open a ticket](http://zatzlabs.com/submit-ticket/).

## Mailing List
If you'd like to keep up with the latest updates to this plugin, please visit [David's Lab Notes](http://zatzlabs.com/lab-notes/) and add yourself to the mailing list.

== Installation ==

**IMPORTANT: Support has moved to the ZATZLabs site and is no longer provided on the WordPress.org forums. If you need a timely reply from the developer, please [open a ticket](http://zatzlabs.com/submit-ticket/).**

This section describes how to install the *My Private Site* plugin and get it working.

* Use Add Plugin within the WordPress Admin panel to download and install this My Private Site plugin from the WordPress.org plugin repository.
* Activate the My Private Site plugin through the Installed Plugins admin panel in WordPress. If you have a WordPress Network ("Multisite"), you can either Network Activate this plugin, or Activate it individually on the sites where you wish to use it.
* To enable privacy, go to the My Private Site, go to the Site Privacy tab and check Enable Privacy. If you use Elementor or find that privacy doesn't seem to work, switch the Compatibility Mode setting to Theme Fix.

== Frequently Asked Questions ==

**IMPORTANT: Support has moved to the ZATZLabs site and is no longer provided on the WordPress.org forums. If you need a timely reply from the developer, please [open a ticket](http://zatzlabs.com/submit-ticket/).**

= How do I fix Redirect Loops (browser cycles for a long time then gives up)? =

By far, the most common way to create a Redirect Loop on your browser with this plugin is to specify both Custom Login page and Landing Location on the plugin's Settings page.  Simply setting "Where to after Login?" in the Landing Location section to "Omit ?redirect_to= from URL" should correct the problem.

This problem has been observed when the URL of the Custom Login page is a WordPress Page.  It occurs because, for Page URLs, WordPress uses the ?redirect_to= Query keyword for purposes other than a Landing Location.

= What happened? I changed my Permalinks and now some things don't work. =

Whenever you change your WordPress Permalinks (Settings:Permalinks in admin panels), this My Private Site plugin does **not** automatically change any URLs you have entered in the plugin's Settings. You will therefore want to make changes to URLs in the plugin's settings whenever you change Permalinks.

== Screenshots ==

1.AI Defense settings active
2.Site privacy mode enabled
3.REST API privacy protection
4.Plugin welcome and overview
5.Landing page redirect options
6.Public homepage visibility settings
7.Shortcodes for content privacy
8.Membership and registration options

== Changelog ==

= 4.0.3 =
* Added AI Defense subsystem
* Added built-in video tutorials for all sections
* Added Manage Settings subtab to Advanced to save, restore, and reset settings
* Substantially optimized plugin startup operations
* Added subtabs engine for easier grouping of functions
* Updated main My Private Site page to make it more readable
* Fixed bug in mail list sign up experience
* Refactored some of the source code for more maintainable files
* Removed direct serialization of settings array for increased security
* Updated EDD licensing library
* Upgraded CMB2 library from 2.10.1 to 2.11.0

= 3.2.1 =
* Safeguarded selective content processing with nonce and capability checks
* Hardened core admin handlers with current_user_can for membership, site privacy, landing, advanced, and more

= 3.2 = 
* Added feature to hide the admin bar from the front-end for logged-in users
* This feature works whether the site is set to private or not

= 3.1.1 =
* Minor UI tweak to provide better clarity for theme option

= 3.1.0 =
* Added REST API Guardian protection to core plugin
* Increased size of heading font in dashboard
* Replaced tags set (login, pages, private, security, visibility, plugin, page, posts, post) with (registered only, privacy, protected, restricted, password protect)

= 3.0.14 =
* Minor compatibility update for WordPress 6.4.

= 3.0.13 =
* Minor update to better support my_private_site_public_check_access_by_page filter.

= 3.0.12 =
* Slight improvement to HTML support pages

= 3.0.11 =
* Fixed a CMB2 type error that popped up from time to time (Thanks, Michael!)

= 3.0.10 =
* Fixed a bunch of over-eager security checks

= 3.0.9 =
* Fixed compatibility switch bug

= 3.0.8 =
* Fixed some security bugs

= 3.0.7 =
* Added more hooks for increased granular control of access
* Fixed some minor bugs

= 3.0.6 =
* Moved compatibility mode option to Site Privacy tab and added additional theme compatibility fixes

= 3.0.5 =
* Added a compatibility mode option on the Advanced tab which will hopefully finally fix the Elementor issues

= 3.0.4 =
* Possible fix for Elementor incompatibility issues
* Fixed some small bugs

= 3.0.3 =
* Added Advanced feature allowing users to specify custom password reset page

= 3.0.2 =
* Fixed duplicate header bug found on some systems

= 3.0.1 =
* Minor bug fixes
* Added uninstall telemetry

= 3.0.0 =
* Complete rewrite with an entirely new, streamlined UI
* Added the selective content subsystem

= 2.14.2 =
* Minor fix for password reset. Thanks to user o2Xav.

= 2.14.1 =
* Minor support update

= 2.14 =
* Force login at 'get_header' instead of 'template_redirect' Action to be compatible with wpengine.com hosting
* Allow Custom Login page that is NOT on the current WordPress site, and clean up Settings page validation of related fields
* Fix double display of Error Messages on Settings page

= 2.13 =
* Remove Plugin's entry on Users submenu on WordPress Admin panels
* Remove associated Setting, which determined whether Users submenu entry existed

= 2.12 =
* Wait until Pretty Permalinks applied before deciding whether to force a login
* Add an Override Advanced Setting with Warnings on Usage, to allow Landing Location and Custom Login to both be specified.
* Correct coding error that allowed Landing Location with Custom Login, a potential Redirection error
* Fix Error Log warning on [mb]strpos Offset parameter

= 2.11.4 =
* Use Custom Login setting, if present, to redirect failed login attempts with blank username and/or password

= 2.11.3 =
* Use Custom Login setting, if present, to redirect failed login attempts

= 2.11.2 =
* Provide a Setting to disable User with No Role behaviour introduced in 2.11

= 2.11.1 =
* Remove forced logout when User with No Role attempts to access a Site (Network/Multisite install), to fix repeated messages when wp_logout is hooked by other plugins

= 2.11 =
* In a WordPress Network ("Multisite"), block Users with No Role on the current Site
* Make Landing Location work when free Paid Membership Pro plugin is activated 

= 2.10.1 =
* Add setting to obey Landing Location for users who login via a URL of wp-login.php without a &redirect_to= following

= 2.10 =
* Add setting to not display a Users submenu option for the plugin's settings
* Conditional logic for Settings Saved update message in Validate function

= 2.9 =
* Set Landing Location for logins via Meta Widget link, as well as automatic Login prompts

= 2.8 =
* Add Prefix option to Always Visible URLs
* Automatically use mb_ Multi-Byte string functions, if available

= 2.7 =
* Add Custom Login URL setting

= 2.6.1 =
* Older versions of WordPress require a parameter be passed to get_post()

= 2.6 =
* Detect and make visible Login-associated Pages created by the Theme My Login plugin

= 2.5 =
* Allow other URLs to be Always Visible with new Setting

= 2.4.2 =
* Reveal BuddyPress /activate/ Activation page when Reveal Registration selected

= 2.4.1 =
* Fix bug in URL matching for Root, where one URL has a trailing slash and the other does not

= 2.4 =
* Handle BuddyPress' redirection of Register URL in Reveal Registration

= 2.3 =
* Add Setting to Reveal Home Page on a Private Site
* Fixed Problems with wp_registration_url function in WordPress prior to Version 3.6

= 2.2 =
* Add the WordPress User Self-Registration field to the plugin's Settings page
* Add the Settings page to the User submenu of Admin panel, too

= 2.1 =
* Add a settings checkbox to reveal the Register page for User Self-Registration

= 2.0 =
* Add Settings page, specifying Landing Location and turning Private Site off and on
* Warning for new default of OFF for Private Site until Settings are first viewed
* Add Networking Settings information page
* Track plugin version number in internal settings
* Replace WordPress Activation/Deactivation hooks with Version checking code from jonradio Multiple Themes
* Add Plugin entry on individual sites when plugin is Network Activated, and Settings link on all Plugin entries

= 1.1 =
* Change Action Hook to 'wp' from 'wp_head' to avoid Modify Header errors when certain other plugins are present

= 1.0 =
* Add readme.txt and screenshots
* Add in-line documentation for php functions

== Upgrade Notice ==

= 2.14 =
Support wpengine.com hosting and off-site Login pages

= 2.13 =
Reduce WordPress Admin panels Menu clutter by removing plugin Settings from Users submenu

= 2.12 =
Correct handling of www. in a URL by waiting until Pretty Permalinks applied before deciding whether to force Login

= 2.11.3 =
Correct Failed Logins when Custom Login selected

= 2.11.2 =
Add Setting to control User with No Role

= 2.11.1 =
Fix User with No Role errors on Network/Multisite

= 2.11 =
Improves Multisite security and supports Paid Membership Pro

= 2.10.1 =
Landing Location obeyed for direct access with wp-login.php URL

= 2.10 =
Allow deletion of Users submenu entry for plugin settings

= 2.9 =
Meta Widget logins now go to Landing Location

= 2.8 =
Support Prefix URL for Always Visible pages

= 2.7 =
Support Custom Login page

= 2.6.1 =
Support Theme My Login plugin with older versions of WordPress

= 2.6 =
Support Theme My Login plugin

= 2.5 =
Allow many Always Visible pages

= 2.4.2 =
Reveal BuddyPress Activation page

= 2.4.1 =
Home Page better URL matching for Root Home Pages

= 2.4 =
Support BuddyPress

= 2.3 =
New Setting to display Home Page on a Private Site.

= 2.2 =
Display WordPress Self-Registration field on plugin Settings page.

= 2.1 =
Allow User Self-Registration by "revealing" the Register page to those not logged in.

= 2.0 =
Create a Settings page that defines where the user ends up after logging in

= 1.1 =
Should eliminate Modify Header errors due to conflict with other plugins

= 1.0 =
Production version, updated to meet WordPress Repository standards