=== Taggy ===
Contributors: shanerounce
Tags: tags, movies, omdb, taxonomy
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.421.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find movie titles with OMDb and add them as tags in the WordPress block editor.

== Description ==

Taggy adds a movie search panel to the post editor. Search for a title, choose a result and save the post to keep its new tag. Existing tags are reused where possible.

Searches run through your WordPress server, so editors do not receive your OMDb key. Results are cached for five minutes and requests are limited per user. Taggy supports post types that use the standard post tags taxonomy in the block editor. It does not add a Classic Editor interface.

Part of the Ace Media suite: https://acemedia.ninja/ . Hosting: https://acemedia.host/ .

== External services ==

An OMDb API key is required. The plugin sends your search text and API key from your server to https://www.omdbapi.com/ after you enter three characters in the movie search panel. OMDb receives your server's IP address. No request is made until an editor searches. Your service plan's quota and terms apply.

Service and terms information: https://www.omdbapi.com/
API keys and plans: https://www.omdbapi.com/apikey.aspx

== Installation ==

1. Install and activate the release ZIP.
2. Add your own OMDb API key in Settings > Taggy.
3. Open a post in the block editor and use the Taggy movie tags panel.
4. Select a title, then save the post.

== Screenshots ==

1. The Taggy movie tags panel in the post editor, searching a fictional film title.

== Frequently Asked Questions ==

= Does it support multisite? =

Each site uses its own key and cache. This release stops copying keys to the network options table. It leaves historical network options untouched; these are not used by Taggy's editor or lookup helper.

= Can contributors submit changes? =

Public issues are welcome at https://github.com/AceMedia/Taggy/issues. Official development is maintained by Ace Media; please discuss contributions before opening a pull request. GPL rights remain unchanged.

== Changelog ==

= 0.421.0 =
* Keep OMDb keys server-side and check search permissions and nonces.
* Render titles as text and remove third-party HTML insertion.
* Use a native editor panel rather than polling a generated input ID.
* Cancel stale searches, cache responses and limit remote requests.
* Keep API settings local to each site in multisite.

= 0.420.0 =
* Previous release.
