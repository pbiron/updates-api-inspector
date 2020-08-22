=== Updates API Inspector ===

Contributors: pbiron, afragen
Tags: updates, api, auto-updates, updates-api
Requires at least: 4.6
Requires PHP: 5.6.0
Tested up to: 5.5.0
Stable tag: 0.2.0-beta-1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Z6D97FA595WSU

Inspect various aspects of the WordPress Updates API

== Description ==

**Note:** This is a `Beta` pre-release.  No guarantee that anything close to this version will actually be released.
 
The WordPress Updates API is pretty much a mystery to many developers for many reasons, not least of which is the fact that there is *no documentation* for it.

For plugins hosted in the [WordPress Plugin Directory](https://wordpress.org/plugins/) and themes hosted in the [WordPress Theme Directory](https://wordpress.org/themes/) (and core itself), the API "just works".  

Plugins and themes hosted externally (such as premium plugins/themes) need to hook into the API and ensure the proper information is populated in the proper site transients so that core can offer updates for those externally hosted plugins/themes.  What site transients are those:

* `update_plugins`
* `update_themes`

(and of course, `update_core` for core updates).

This plugin attempts to demystify the Updates API by allowing you to inspect:

* how the API is queried by core
* what the API returns in respose to a query
* what's in the site transients core uses when offering updates to admin users (whether manual or auto-updates)

At this point, this plugin is *very preliminary* (it is version 0.1.1 after all), but I'm releasing it in it's current state because of the new [Auto-updates UI in WordPress 5.5.0](https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/).  While many externally hosted plugins/themes have been hooking into API for years, the new auto-updates UI has certain requirements for how the site transients are populated and not all externally hosted plugins/themes have populated them such that the new UI will work properly (see [Recommended usage of the Updates API to support the auto-updates UI for Plugins and Themes in WordPress 5.5](https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/)).

My hope is that by providing an easy way for developers to inspect the API queries/responses and the site transients core populates from them, they will more easily be able to update their code so that the new UI works properly for them.

In future versions, I plan to add many other features, as well as more extensive on-screen help of an "educational" nature.

== Installation ==

From your WordPress dashboard

1. Go to _Plugins > Add New_ and click on _Upload Plugin_
2. Upload the zip file
3. Activate the plugin


== Screenshots ==

1. The `Request` for plugin updates
2. The `API Response` for plugin updates
3. The `Transient Value as Set` for plugin updates
4. The `Transient Value as Read` for plugin updates
5. Multisite

== Frequently Asked Questions ==

= Why isn't the Updates API documented? =

That's a good question, and I honestly don't know the answer.

= What's the best hook to use for injecting information about by externally hosted plugin or theme into the site transients? =

There is no *best hook*!  

The most common hooks used are probably:

* For plugins:
    * [pre_set_site_transient_update_plugins](https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/) (fires when the transient is set)
    * [site_transient_update_plugins](https://developer.wordpress.org/reference/hooks/site_transient_transient/) (fires when the transient is "got")
* For themes:
    * [pre_set_site_transient_update_themes](https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/) (fires when the transient is set)
    * [site_transient_update_themes](https://developer.wordpress.org/reference/hooks/site_transient_transient/) (fires when the transient is "got")

A number of other hooks can be used, but except in *very special** cases I wouldn't recommend them...so I'm not even going to list what they are :-)

Many considerations go into deciding which hook to use and I couldn't possibly give those considerations their due here...so I won't even try.

= Will this plugin work in versions of WordPress prior to 5.5.0? =

It should!  My main motivation for releasing it _now_ is to help developers of externally hosted plugins/themes prepare for the release of 5.5.0, this plugin should work just fine with previous versions (although I have only tested it with 5.5.0).

= Does this plugin work in multisite? =

Yes it does!

In non-multisite, the Updates API Inspector menu item appers in `Tools`.

In multisite, this plugin can only be network activated (since that's where updates happen) and the menu item appears at the top-level of `Network Admin`.

= What capability does a user need to have to view the inspector? =

Any user that has been granted the `view_updates_api_inspector` capability can view the inspector.  Additionally, users with `manage_options` (or `manage_network_options` in multisite) are also able to view the inspector.

= Can I contribute to this plugin? =

Yes you can!  Development happens on [GitHub](https://github.com/pbiron/updates-api-inspector).  If you find a bug or have other suggestions, please open an issue there.  Pull requests accepted.  Please read [CONTRIBUTING](https://github.com/pbiron/updates-api-inspector/CONTRIBUTING.md).

== Upgrade Notice ==

This version contains bug fixes, including corrections several of the messages displayed that give information about certain aspects of the Updates API.

== Changelog ==

= 0.2.0 (not yet released) =

* Complete rewrite
    * Version 0.1.1 was rushed to release.  Now that WP 5.5 is out the door and things have calmed down, I've had time to think about how the code should be structured.
    * Minimum PHP version is now 5.6

* Enhancements
    * new capability, `view_updates_api_inspector`, for accessing the inspector.  By default, this cap maps to `manage_options` (or `manage_network_options` in multisite), but can be granted to any user/role with tools like User Role Editor
	* API Request/Response is now also captured on [pre_http_request](https://developer.wordpress.org/reference/hooks/pre_http_request) and [http_response](https://developer.wordpress.org/reference/hooks/http_response)

* Bug Fixes
    * now correctly pretty prints stdClass in PHP < 7.3 and any first class objects in all PHP versions  
    * string corrections

= 0.1.1 (2020-08-05) =

* Scrap the use of AJAX: run the update check before the tool page is rendered and output just what we need to.
* Also adds a minimal help screen and other various code/string cleanup.

= 0.1.0 (2020-08-01) =

* init commit.
