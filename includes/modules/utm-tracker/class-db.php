<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerDB')) {
    class LeykaUTMTrackerDB {

        const SCHEMA_VERSION = '1.5.1';

        public static function get_table_name() {
            global $wpdb;
            return $wpdb->prefix . 'leyka_utm_tracker';
        }

        public static function activate() {
            $old = get_option('leyka_utm_tracker_schema_version', '');
            self::create_or_update_table($old);
            update_option('leyka_utm_tracker_schema_version', self::SCHEMA_VERSION);
        }

        public static function maybe_upgrade_schema() {
            $saved = get_option('leyka_utm_tracker_schema_version', '');
            $table = self::get_table_name();

            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table existence check, no WP_Query alternative.

            if ($exists !== $table || $saved !== self::SCHEMA_VERSION) {
                self::create_or_update_table($saved);
                update_option('leyka_utm_tracker_schema_version', self::SCHEMA_VERSION);
            }
        }

        public static function create_or_update_table($old_version = '') {
            global $wpdb;

            $table = self::get_table_name();
            $charset = $wpdb->get_charset_collate();

            $sql = $wpdb->prepare("CREATE TABLE %i (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                donation_id BIGINT UNSIGNED NOT NULL,
                utm_source VARCHAR(255) NOT NULL DEFAULT '',
                utm_medium VARCHAR(255) NOT NULL DEFAULT '',
                utm_campaign VARCHAR(255) NOT NULL DEFAULT '',
                utm_first_source VARCHAR(255) NOT NULL DEFAULT '',
                utm_first_medium VARCHAR(255) NOT NULL DEFAULT '',
                utm_first_campaign VARCHAR(255) NOT NULL DEFAULT '',
                utm_last_source VARCHAR(255) NOT NULL DEFAULT '',
                utm_last_medium VARCHAR(255) NOT NULL DEFAULT '',
                utm_last_campaign VARCHAR(255) NOT NULL DEFAULT '',
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY donation_id (donation_id),
                KEY status (status),
                KEY utm_source (utm_source(191)),
                KEY utm_campaign (utm_campaign(191)),
                KEY utm_first_source (utm_first_source(191)),
                KEY utm_first_campaign (utm_first_campaign(191)),
                KEY utm_last_source (utm_last_source(191)),
                KEY utm_last_campaign (utm_last_campaign(191))
            ) {$charset};", $table); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $charset is $wpdb->get_charset_collate(), a WordPress internal value, not user input.

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            self::ensure_column('updated_at', $wpdb->prepare("ALTER TABLE %i ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP", $table));
            self::ensure_column('amount', $wpdb->prepare("ALTER TABLE %i ADD COLUMN amount DECIMAL(15,2) NOT NULL DEFAULT 0.00", $table));
            self::ensure_column('status', $wpdb->prepare("ALTER TABLE %i ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending'", $table));
            self::ensure_column('utm_source', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_source VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_medium', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_medium VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_campaign', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_campaign VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_first_source', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_first_source VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_first_medium', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_first_medium VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_first_campaign', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_first_campaign VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_last_source', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_last_source VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_last_medium', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_last_medium VARCHAR(255) NOT NULL DEFAULT ''", $table));
            self::ensure_column('utm_last_campaign', $wpdb->prepare("ALTER TABLE %i ADD COLUMN utm_last_campaign VARCHAR(255) NOT NULL DEFAULT ''", $table));

            // Backfill old rows so older installs still show sensible data.
            // Only run when upgrading from a version before first/last touch columns existed.
            if ($old_version !== '' && version_compare($old_version, '1.4.2', '<')) {
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from wpdb prefix, safe. Migration query.
                    $wpdb->prepare(
                        "UPDATE %i
                     SET
                        utm_first_source = CASE WHEN utm_first_source = '' THEN utm_source ELSE utm_first_source END,
                        utm_first_medium = CASE WHEN utm_first_medium = '' THEN utm_medium ELSE utm_first_medium END,
                        utm_first_campaign = CASE WHEN utm_first_campaign = '' THEN utm_campaign ELSE utm_first_campaign END,
                        utm_last_source = CASE WHEN utm_last_source = '' THEN utm_source ELSE utm_last_source END,
                        utm_last_medium = CASE WHEN utm_last_medium = '' THEN utm_medium ELSE utm_last_medium END,
                        utm_last_campaign = CASE WHEN utm_last_campaign = '' THEN utm_campaign ELSE utm_last_campaign END",
                        $table
                    )
                );
            }
        }

        protected static function ensure_column($column, $sql) {
            global $wpdb;
            $table = self::get_table_name();

            $exists = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from wpdb prefix, safe.
                "SHOW COLUMNS FROM %i LIKE %s",
                $table,
                $column
            ));

            if ($exists !== $column) {
                $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema migration, SQL built with wpdb->prepare(), table name from wpdb prefix.
            }
        }
    }
}
