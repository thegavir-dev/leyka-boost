<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerAnalytics')) {
    class LeykaUTMTrackerAnalytics {

        /**
         * Build WHERE conditions as array of literal strings with placeholders.
         * Column names are internal constants, not user input.
         * Returns array('conditions' => array(...), 'values' => array(...)).
         */
        private static function build_where_parts($filters = array()) {
            $conditions = array();
            $values     = array();
            $touch      = self::get_touch_preference();

            if (!empty($filters['utm_source'])) {
                $conditions[] = $touch === 'last' ? '`utm_last_source` = %s' : '`utm_first_source` = %s';
                $values[]     = sanitize_text_field($filters['utm_source']);
            }

            if (!empty($filters['utm_medium'])) {
                $conditions[] = $touch === 'last' ? '`utm_last_medium` = %s' : '`utm_first_medium` = %s';
                $values[]     = sanitize_text_field($filters['utm_medium']);
            }

            if (!empty($filters['utm_campaign'])) {
                $conditions[] = $touch === 'last' ? '`utm_last_campaign` = %s' : '`utm_first_campaign` = %s';
                $values[]     = sanitize_text_field($filters['utm_campaign']);
            }

            if (!empty($filters['status'])) {
                $conditions[] = '`status` = %s';
                $values[]     = sanitize_text_field($filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $conditions[] = '`created_at` >= %s';
                $values[]     = sanitize_text_field($filters['date_from']) . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $conditions[] = '`created_at` <= %s';
                $values[]     = sanitize_text_field($filters['date_to']) . ' 23:59:59';
            }

            return array(
                'conditions' => $conditions,
                'values'     => $values,
            );
        }

        /**
         * Build WHERE SQL string from array of literal condition strings.
         */
        private static function build_where_sql(array $conditions) {
            if (empty($conditions)) {
                return '';
            }

            return ' WHERE ' . implode(' AND ', $conditions);
        }

        /**
         * Get the active touch preference (first or last).
         */
        public static function get_touch_preference() {
            return get_option('leyka_utm_touch_preference', 'first');
        }

        /**
         * Return source/medium/campaign column prefix based on touch preference.
         */
        public static function get_touch_prefix() {
            return self::get_touch_preference() === 'last' ? 'utm_last' : 'utm_first';
        }

        /**
         * Counts: total, success, pending, fail, sum_success.
         */
        public static function get_counts($filters = array()) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();
            $parts = self::build_where_parts($filters);
            $where = self::build_where_sql($parts['conditions']);

            if (!empty($parts['values'])) {
                $values = array_merge(array($table), $parts['values']);
                $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    $wpdb->prepare(
                        "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                        SUM(CASE WHEN status = 'fail' THEN 1 ELSE 0 END) AS fail_count,
                        COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS sum_success
                        FROM %i" . $where, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                        ...$values
                    )
                );
            } else {
                $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, table name from wpdb prefix.
                    $wpdb->prepare(
                        "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                        SUM(CASE WHEN status = 'fail' THEN 1 ELSE 0 END) AS fail_count,
                        COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS sum_success
                        FROM %i",
                        $table
                    )
                );
            }

            return array(
                'total'         => (int)   ($row->total ?? 0),
                'success_count' => (int)   ($row->success_count ?? 0),
                'pending_count' => (int)   ($row->pending_count ?? 0),
                'fail_count'    => (int)   ($row->fail_count ?? 0),
                'sum_success'   => (float) ($row->sum_success ?? 0),
            );
        }

        /**
         * Conversion rate: success / (success + fail). Pending excluded.
         */
        public static function get_conversion($counts) {
            $completed = $counts['success_count'] + $counts['fail_count'];
            if ($completed === 0) {
                return 0.0;
            }
            return round(($counts['success_count'] / $completed) * 100, 1);
        }

        /**
         * Top sources by donation count and sum (success only).
         */
        public static function get_top_sources($filters = array(), $limit = 10) {
            global $wpdb;
            $table  = LeykaUTMTrackerDB::get_table_name();
            $prefix = self::get_touch_prefix();
            $col    = $prefix . '_source';
            $parts  = self::build_where_parts($filters);
            $where  = self::build_where_sql($parts['conditions']);

            if (!empty($parts['values'])) {
                $values = array_merge(array($col, $table), $parts['values'], array($col, $limit));
                return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    "SELECT %i AS source_name, COUNT(*) AS donations, COALESCE(SUM(amount), 0) AS total_amount FROM %i" . $where . " AND status = 'success' GROUP BY %i ORDER BY total_amount DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                    ...$values
                ));
            } else {
                return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    "SELECT %i AS source_name,
                    COUNT(*) AS donations,
                    COALESCE(SUM(amount), 0) AS total_amount
                    FROM %i
                    WHERE status = 'success'
                    GROUP BY %i ORDER BY total_amount DESC LIMIT %d",
                    $col,
                    $table,
                    $col,
                    $limit
                ));
            }
        }

        /**
         * Top campaigns by donation count and sum (success only).
         */
        public static function get_top_campaigns($filters = array(), $limit = 10) {
            global $wpdb;
            $table  = LeykaUTMTrackerDB::get_table_name();
            $prefix = self::get_touch_prefix();
            $col    = $prefix . '_campaign';
            $parts  = self::build_where_parts($filters);
            $where  = self::build_where_sql($parts['conditions']);

            if (!empty($parts['values'])) {
                $values = array_merge(array($col, $table), $parts['values'], array($col, $limit));
                return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    "SELECT %i AS campaign_name, COUNT(*) AS donations, COALESCE(SUM(amount), 0) AS total_amount FROM %i" . $where . " AND status = 'success' GROUP BY %i ORDER BY total_amount DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                    ...$values
                ));
            } else {
                return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    "SELECT %i AS campaign_name,
                    COUNT(*) AS donations,
                    COALESCE(SUM(amount), 0) AS total_amount
                    FROM %i
                    WHERE status = 'success'
                    GROUP BY %i ORDER BY total_amount DESC LIMIT %d",
                    $col,
                    $table,
                    $col,
                    $limit
                ));
            }
        }

        /**
         * First → Last attribution paths, TOP N by sum (success only).
         */
        public static function get_first_last_paths($filters = array(), $limit = 10) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();
            $parts = self::build_where_parts($filters);
            $where = self::build_where_sql($parts['conditions']);

            if (!empty($parts['values'])) {
                $values = array_merge(array($table), $parts['values'], array($limit));
                return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    "SELECT utm_first_source AS first_source, utm_last_source AS last_source, COUNT(*) AS donations, COALESCE(SUM(amount), 0) AS total_amount FROM %i" . $where . " AND status = 'success' GROUP BY utm_first_source, utm_last_source ORDER BY total_amount DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                    ...$values
                ));
            } else {
                return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    "SELECT
                    utm_first_source AS first_source,
                    utm_last_source AS last_source,
                    COUNT(*) AS donations,
                    COALESCE(SUM(amount), 0) AS total_amount
                    FROM %i
                    WHERE status = 'success'
                    GROUP BY utm_first_source, utm_last_source ORDER BY total_amount DESC LIMIT %d",
                    $table,
                    $limit
                ));
            }
        }

        /**
         * Get distinct values for filter dropdowns.
         */
        public static function get_filter_options() {
            global $wpdb;
            $table  = LeykaUTMTrackerDB::get_table_name();
            $prefix = self::get_touch_prefix();

            $sources   = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT {$prefix}_source FROM %i WHERE {$prefix}_source != '' ORDER BY {$prefix}_source", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Column prefix and table name are internal values from wpdb prefix, safe.
            $mediums   = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT {$prefix}_medium FROM %i WHERE {$prefix}_medium != '' ORDER BY {$prefix}_medium", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Column prefix and table name are internal values from wpdb prefix, safe.
            $campaigns = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT {$prefix}_campaign FROM %i WHERE {$prefix}_campaign != '' ORDER BY {$prefix}_campaign", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Column prefix and table name are internal values from wpdb prefix, safe.

            return array(
                'sources'   => is_array($sources) ? $sources : array(),
                'mediums'   => is_array($mediums) ? $mediums : array(),
                'campaigns' => is_array($campaigns) ? $campaigns : array(),
            );
        }

        /**
         * Paginated rows with filters.
         */
        public static function get_rows($filters = array(), $per_page = 50, $current_page = 1) {
            global $wpdb;
            $table  = LeykaUTMTrackerDB::get_table_name();
            $parts  = self::build_where_parts($filters);
            $where  = self::build_where_sql($parts['conditions']);
            $offset = ($current_page - 1) * $per_page;

            if (!empty($parts['values'])) {
                $count_values = array_merge(array($table), $parts['values']);
                $total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM %i" . $where, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                        ...$count_values
                    )
                );
                $values = array_merge(array($table), $parts['values'], array($per_page, $offset));
                $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    $wpdb->prepare(
                        "SELECT * FROM %i" . $where . " ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                        ...$values
                    )
                );
            } else {
                $total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, table name from wpdb prefix.
                    $wpdb->prepare("SELECT COUNT(*) FROM %i", $table)
                );
                $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    $wpdb->prepare("SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d", $table, $per_page, $offset)
                );
            }

            return array(
                'rows'  => $rows,
                'total' => $total,
            );
        }

        /**
         * CSV export — all rows matching filters.
         */
        public static function get_export_rows($filters = array()) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();
            $parts = self::build_where_parts($filters);
            $where = self::build_where_sql($parts['conditions']);

            if (!empty($parts['values'])) {
                $values = array_merge(array($table), $parts['values']);
                return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, SQL built via wpdb->prepare(), table name from wpdb prefix.
                    $wpdb->prepare(
                        "SELECT * FROM %i" . $where . " ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from literal condition strings defined in build_where_parts(), no user input.
                        ...$values
                    ),
                    ARRAY_A
                );
            }

            return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, table name from wpdb prefix.
                $wpdb->prepare("SELECT * FROM %i ORDER BY id DESC", $table),
                ARRAY_A
            );
        }
    }
}
