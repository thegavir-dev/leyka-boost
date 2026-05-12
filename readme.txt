=== Leyka Boost ===
Contributors: studioavp
Tags: leyka, donations, fundraising, utm, analytics
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Additional tools for the Leyka donation plugin: UTM tracking, form improvements, campaign controls.

== Description ==

Leyka Boost is an add-on for the Leyka donation plugin. It requires Leyka to be installed and active.

The plugin combines three admin and frontend modules for donation workflows:

* UTM Tracker: captures UTM marks for Leyka donations, shows donation analytics, supports CSV export, and includes a UTM link generator.
* Toolkit: adds form improvements such as newsletter subscription and recurring donation consent controls.
* Close Campaign: adds controls for closing a campaign by the remaining amount and records usage statistics.

Leyka Boost also includes shared settings, a unified event log, Russian and English translation files, and cleanup on uninstall.

== Requirements ==

* WordPress 6.4 or higher
* PHP 7.4 or higher
* Leyka 3.20 or higher installed and active

== Installation ==

1. Upload the `leyka-boost` folder to the `/wp-content/plugins/` directory, or install the plugin ZIP through the WordPress admin.
2. Make sure the Leyka plugin is installed and active.
3. Activate Leyka Boost through the Plugins screen in WordPress.
4. Open `Leyka Boost` in the WordPress admin menu and configure the modules.

== Frequently Asked Questions ==

= Does Leyka Boost work without Leyka? =

No. Leyka Boost is an add-on and requires the Leyka donation plugin to be installed and active.

= Which languages are included? =

The plugin includes Russian (`ru_RU`) and English (`en_US`) translation files. WordPress selects the language from the site locale.

= Where are module settings stored? =

Leyka Boost keeps legacy-compatible option names for its modules: UTM Tracker uses `leyka_utm_tracker_*`, Toolkit uses `leyka_toolkit_settings`, and Close Campaign uses `leyka_close_settings`.

= Where is the event log stored? =

The shared event log is stored under `wp-content/uploads/leyka-boost/logs/`.

== Screenshots ==

1. Leyka Boost settings.
2. Unified event log.
3. UTM Tracker analytics and donation table.

== Changelog ==

= 1.0.0 =
* Initial public release of Leyka Boost.
* Added UTM Tracker, Toolkit, and Close Campaign modules.
* Added shared settings and unified event log.
* Added Russian and English localization files.

== Upgrade Notice ==

= 1.0.0 =
Initial public release. Requires Leyka 3.20 or higher.
