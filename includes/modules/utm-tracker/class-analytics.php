<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerAnalytics')) {
    class LeykaUTMTrackerAnalytics {

        /**
         * Build a WHERE clause from filter parameters.
         * Returns array('sql' => '...', 'values' => array(...)).
         */
        public static function build_where($filters = array()) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();

            $clauses = array();
            $values  = array();

            $touch = self::get_touch_preference();

            if (!empty($filters['utm_source'])) {
                $col = $touch === 'last' ? 'utm_last_source' : 'utm_first_source';
                $clauses[] = "{$col} = %s";
                $values[]  = sanitize_text_field($filters['utm_source']);
            }

            if (!empty($filters['utm_medium'])) {
                $col = $touch === 'last' ? 'utm_last_medium' : 'utm_first_medium';
                $clauses[] = "{$col} = %s";
                $values[]  = sanitize_text_field($filters['utm_medium']);
            }

            if (!empty($filters['utm_campaign'])) {
                $col = $touch === 'last' ? 'utm_last_campaign' : 'utm_first_campaign';
                $clauses[] = "{$col} = %s";
                $values[]  = sanitize_text_field($filters['utm_campaign']);
            }

            if (!empty($filters['status'])) {
                $clauses[] = 'status = %s';
                $values[]  = sanitize_text_field($filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $clauses[] = 'created_at >= %s';
                $values[]  = sanitize_text_field($filters['date_from']) . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $clauses[] = 'created_at <= %s';
                $values[]  = sanitize_text_field($filters['date_to']) . ' 23:59:59';
            }

            if (empty($clauses)) {
                return array('sql' => '', 'values' => array());
            }

            $sql = ' WHERE ' . implode(' AND ', $clauses);
            return array('sql' => $sql, 'values' => $values);
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
            $where = self::build_where($filters);

            $base_sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'fail' THEN 1 ELSE 0 END) AS fail_count,
                COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) AS sum_success
                FROM {$table}";

            if (!empty($where['values'])) {
                $row = $wpdb->get_row($wpdb->prepare($base_sql . $where['sql'], ...$where['values']));
            } else {
                $row = $wpdb->get_row($base_sql);
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
            $where  = self::build_where($filters);

            $status_clause = " AND status = 'success'";

            $sql = "SELECT
                {$col} AS source_name,
                COUNT(*) AS donations,
                COALESCE(SUM(amount), 0) AS total_amount
                FROM {$table}";

            if (!empty($where['values'])) {
                $sql .= $where['sql'] . $status_clause;
                $sql .= " GROUP BY {$col} ORDER BY total_amount DESC LIMIT %d";
                $values = array_merge($where['values'], array($limit));
                return $wpdb->get_results($wpdb->prepare($sql, ...$values));
            } else {
                $sql .= " WHERE status = 'success'";
                $sql .= " GROUP BY {$col} ORDER BY total_amount DESC LIMIT %d";
                return $wpdb->get_results($wpdb->prepare($sql, $limit));
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
            $where  = self::build_where($filters);

            $status_clause = " AND status = 'success'";

            $sql = "SELECT
                {$col} AS campaign_name,
                COUNT(*) AS donations,
                COALESCE(SUM(amount), 0) AS total_amount
                FROM {$table}";

            if (!empty($where['values'])) {
                $sql .= $where['sql'] . $status_clause;
                $sql .= " GROUP BY {$col} ORDER BY total_amount DESC LIMIT %d";
                $values = array_merge($where['values'], array($limit));
                return $wpdb->get_results($wpdb->prepare($sql, ...$values));
            } else {
                $sql .= " WHERE status = 'success'";
                $sql .= " GROUP BY {$col} ORDER BY total_amount DESC LIMIT %d";
                return $wpdb->get_results($wpdb->prepare($sql, $limit));
            }
        }

        /**
         * First → Last attribution paths, TOP N by sum (success only).
         */
        public static function get_first_last_paths($filters = array(), $limit = 10) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();
            $where = self::build_where($filters);

            $status_clause = " AND status = 'success'";

            $sql = "SELECT
                utm_first_source AS first_source,
                utm_last_source AS last_source,
                COUNT(*) AS donations,
                COALESCE(SUM(amount), 0) AS total_amount
                FROM {$table}";

            if (!empty($where['values'])) {
                $sql .= $where['sql'] . $status_clause;
                $sql .= " GROUP BY utm_first_source, utm_last_source ORDER BY total_amount DESC LIMIT %d";
                $values = array_merge($where['values'], array($limit));
                return $wpdb->get_results($wpdb->prepare($sql, ...$values));
            } else {
                $sql .= " WHERE status = 'success'";
                $sql .= " GROUP BY utm_first_source, utm_last_source ORDER BY total_amount DESC LIMIT %d";
                return $wpdb->get_results($wpdb->prepare($sql, $limit));
            }
        }

        /**
         * Get distinct values for filter dropdowns.
         */
        public static function get_filter_options() {
            global $wpdb;
            $table  = LeykaUTMTrackerDB::get_table_name();
            $prefix = self::get_touch_prefix();

            $sources   = $wpdb->get_col("SELECT DISTINCT {$prefix}_source FROM {$table} WHERE {$prefix}_source != '' ORDER BY {$prefix}_source");
            $mediums   = $wpdb->get_col("SELECT DISTINCT {$prefix}_medium FROM {$table} WHERE {$prefix}_medium != '' ORDER BY {$prefix}_medium");
            $campaigns = $wpdb->get_col("SELECT DISTINCT {$prefix}_campaign FROM {$table} WHERE {$prefix}_campaign != '' ORDER BY {$prefix}_campaign");

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
            $where  = self::build_where($filters);
            $offset = ($current_page - 1) * $per_page;

            $count_sql = "SELECT COUNT(*) FROM {$table}";
            $rows_sql  = "SELECT * FROM {$table}";

            if (!empty($where['values'])) {
                $total = (int) $wpdb->get_var($wpdb->prepare($count_sql . $where['sql'], ...$where['values']));
                $rows_sql .= $where['sql'] . " ORDER BY id DESC LIMIT %d OFFSET %d";
                $values = array_merge($where['values'], array($per_page, $offset));
                $rows = $wpdb->get_results($wpdb->prepare($rows_sql, ...$values));
            } else {
                $total = (int) $wpdb->get_var($count_sql);
                $rows = $wpdb->get_results($wpdb->prepare(
                    $rows_sql . " ORDER BY id DESC LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                ));
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
            $where = self::build_where($filters);

            $sql = "SELECT * FROM {$table}";

            if (!empty($where['values'])) {
                $sql .= $where['sql'] . " ORDER BY id DESC";
                return $wpdb->get_results($wpdb->prepare($sql, ...$where['values']), ARRAY_A);
            }

            return $wpdb->get_results($sql . " ORDER BY id DESC", ARRAY_A);
        }
    }
}
