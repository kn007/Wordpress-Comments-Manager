=== Wordpress Comments Manager ===
Contributors: kn007
Donate Link: https://kn007.net/donate/
Tags: comments, view, spam, control, ajax, search, management, manager, comment search, comment view, comment edit, comment reply, comment approval, comment moderation, comment spam, comment trash, comment delete
Requires at least: 3.6
Tested up to: 4.7.2
Stable tag: 1.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Manage your comments faster, handle data more quickly.

== Description ==

Wordpress Comments Manager help you to quickly find comments and manage comments.

It can be very convenient to review selected comments, open the comment in a new window, reply comment, edit comment and delete comments.

See the screenshots for more details.

== Installation ==

1. Upload the entire `wp-comments-manager` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

No technical skills needed.

== Screenshots ==

1. Main Page.
2. Select item.
3. Multi-select items.
4. Search field select.
5. Advanced search fields.
6. Open select comment in new tab.
7. Edit Comment(Popup way).
8. Set selected comments status.
9. Delete comments dialog.
10. Search word like 'cctv'.
11. Advanced search like author contain 'World', published date between 2016-08-01 and 2016-08-31.
12. Screenshot 11 result.
13. Select which columns show.
14. Only show author and content columns.
15. Main page with search field select on mobile.
16. Main page with set comment status menu on mobile.
17. Main page with delete comment dialog menu on mobile.

== Changelog ==

= 1.6 =
* Add `Unanswered` tab, could show the comments that have not received a reply by internal user yet.
* Experimental feature: public comments search shortcode support(need enable `WPCM_ENABLE_EXPERIMENTAL_FEATURES` first).

= 1.5.1 =
* Fix some bugs.

= 1.5 =
* Query optimization.
* Fix some bugs.

= 1.4 =
* Using back Wordpress API to edit comment.
* Change: double-click a row to call reply dialog form.
* Add a hotkey to the form(`Ctrl+Enter` to submit).

= 1.3 =
* Feature: reply comment.
* Review POST request.

= 1.2 =
* Code normalization.
* Make the code compatible with `JQuery 1`.
* Lessen the possibility of XSS vulnerabilities.
* Using nonce to prevent unauthorized access.

= 1.1 =
* Make `Site` column to a hyperlink.
* Double-click a row to popup display the `Content` contents.

= 1.0 =
* Initial release.

== Upgrade Notice ==

Update through the automatic WordPress updater, all Wordpress Comments Manager content will remain in place.

== Requirements ==

PHP 5.3+, PHP7 recommended for better performance, WordPress 3.6+, WordPress 4.7+ recommended for better experience.