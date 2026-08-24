=== KO: Truncate Divi Blog Titles ===
Contributors: ko
Tags: divi, blog, titles, truncation
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Truncates rendered Divi Blog titles at a word boundary without changing stored post titles.

== Description ==

KO: Truncate Divi Blog Titles adds class-driven, display-only title limits to Divi Blog modules.

Add a class in this format to a Blog module:

`ko-truncate-divi-blog-titles-30`

The final number is the maximum displayed character count, including the three-dot ellipsis. Limits from 10 through 500 are supported.

The plugin:

* Truncates at a word boundary whenever possible.
* Leaves the WordPress post title unchanged.
* Preserves the full title in the rendered link's `aria-label` and `title` attributes.
* Supports Divi 5 blocks and legacy Divi Blog module output.
* Has no settings screen and adds no front-end assets.

== Installation ==

1. Upload and activate the plugin.
2. Open the Divi Blog module.
3. Add a class such as `ko-truncate-divi-blog-titles-30` to the module's CSS classes.
4. Save the page and clear Divi's generated CSS cache if needed.

== Changelog ==

= 1.0.0 =
* Initial release.
