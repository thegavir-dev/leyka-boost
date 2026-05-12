# Leyka Boost

Leyka Boost is a WordPress plugin that adds extra tools for the [Leyka](https://wordpress.org/plugins/leyka/) donation plugin: UTM tracking, donation form improvements, campaign controls, and a shared event log.

## Requirements

- WordPress 6.4 or higher
- PHP 7.4 or higher
- Leyka 3.20 or higher installed and active

## Modules

### UTM Tracker

Captures UTM marks for Leyka donations, stores them in the `{prefix}leyka_utm_tracker` table, shows analytics, supports CSV export, and includes a UTM link generator.

### Toolkit

Adds donation form improvements, including newsletter subscription and recurring donation consent controls.

### Close Campaign

Adds a frontend control for closing a campaign by the remaining amount and stores module statistics in `leyka_close_settings` / `leyka_close_stats`.

## Admin Pages

- `Leyka Boost > Settings`: module toggles and global event log settings.
- `Leyka Boost > Event log`: shared event log with filters, statistics, pagination, clear, and download actions.
- `UTM Tracker`: analytics, donation table, settings, and link generator.
- `Toolkit`: form improvement settings.
- `Close Campaign`: campaign control settings.

## Localization

Text Domain: `leyka-boost`

Translations are stored in `languages/`:

- `leyka-boost-ru_RU.po` / `leyka-boost-ru_RU.mo`
- `leyka-boost-en_US.po` / `leyka-boost-en_US.mo`

## Installation

1. Upload the `leyka-boost` folder to `/wp-content/plugins/`.
2. Make sure Leyka is installed and active.
3. Activate Leyka Boost in WordPress.
4. Open `Leyka Boost` in the admin menu and configure modules.

## License

GPLv2 or later. See `license.txt`.
