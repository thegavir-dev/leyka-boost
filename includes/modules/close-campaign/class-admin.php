<?php
/**
 * Класс админ-панели плагина Leyka Close Campaign
 * Версия: 1.0.6
 *
 * Отвечает за:
 * - Меню в админке
 * - Страница настроек
 * - Страница статистики
 * - Просмотр логов
 * - Сброс статистики
 * 
 * ИСПРАВЛЕНО в 1.0.6:
 * - Добавлены скрытые поля для чекбоксов (чтобы значение 0 сохранялось)
 * - Удалена настройка toggle_off_behavior (не используется)
 * - Удалена секция "Поведение"
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

class Leyka_Close_Admin {
    
    /**
     * Слаг страницы меню
     *
     * @var string
     */
    private $menu_slug = 'leyka-close-campaign';
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Добавляем меню в админке
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Регистрируем настройки
        add_action('admin_init', [$this, 'register_settings']);
        
        // Подключаем стили и скрипты
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Обработчик сброса статистики
        add_action('admin_post_leyka_close_reset_stats', [$this, 'handle_reset_stats']);
        
    }
    
    // ============================================
    // МЕНЮ АДМИНКИ
    // ============================================
    
    /**
     * Добавить меню в админке
     *
     * @return void
     */
    public function add_admin_menu() {
        add_submenu_page(
            'leyka-boost',
            __('Close Campaign', 'leyka-boost'),
            __('Close Campaign', 'leyka-boost'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_settings_page']
        );
    }
    
    // ============================================
    // РЕГИСТРАЦИЯ НАСТРОЕК (ИСПРАВЛЕНО в 1.0.6)
    // ============================================
    
    /**
     * Зарегистрировать настройки
     *
     * @return void
     */
    public function register_settings() {
        // Секция основных настроек
        add_settings_section(
            'leyka_close_main_section',
            __('Main settings', 'leyka-boost'),
            [$this, 'render_main_section_desc'],
            $this->menu_slug . '-settings'
        );
        
        // Секция дизайна
        add_settings_section(
            'leyka_close_design_section',
            __('Design', 'leyka-boost'),
            [$this, 'render_design_section_desc'],
            $this->menu_slug . '-settings'
        );
        
        // Секция настроек рамки
        add_settings_section(
            'leyka_close_border_section',
            __('Block border settings', 'leyka-boost'),
            [$this, 'render_border_section_desc'],
            $this->menu_slug . '-settings'
        );
        
        // СЕКЦИЯ "ПОВЕДЕНИЕ" УДАЛЕНА в 1.0.6 (не используется)
        
        // ===== Поля основных настроек =====
        add_settings_field(
            'enabled',
            __('Module status', 'leyka-boost'),
            [$this, 'render_field_enabled'],
            $this->menu_slug . '-settings',
            'leyka_close_main_section'
        );
        
        add_settings_field(
            'min_amount_to_show',
            __('Minimum amount to show (₽)', 'leyka-boost'),
            [$this, 'render_field_min_amount'],
            $this->menu_slug . '-settings',
            'leyka_close_main_section'
        );
        
        add_settings_field(
            'auto_toggle_threshold',
            __('Auto-enable threshold (₽)', 'leyka-boost'),
            [$this, 'render_field_auto_threshold'],
            $this->menu_slug . '-settings',
            'leyka_close_main_section'
        );
        
        // ===== Поля дизайна =====
        add_settings_field(
            'active_color',
            __('Active color', 'leyka-boost'),
            [$this, 'render_field_active_color'],
            $this->menu_slug . '-settings',
            'leyka_close_design_section'
        );
        
        add_settings_field(
            'inactive_color',
            __('Inactive color', 'leyka-boost'),
            [$this, 'render_field_inactive_color'],
            $this->menu_slug . '-settings',
            'leyka_close_design_section'
        );
        
        add_settings_field(
            'button_text',
            __('Button text', 'leyka-boost'),
            [$this, 'render_field_button_text'],
            $this->menu_slug . '-settings',
            'leyka_close_design_section'
        );
        
        add_settings_field(
            'show_icon',
            __('Show icon', 'leyka-boost'),
            [$this, 'render_field_show_icon'],
            $this->menu_slug . '-settings',
            'leyka_close_design_section'
        );
        
        // ===== Поля настроек рамки =====
        add_settings_field(
            'border_color',
            __('Border color', 'leyka-boost'),
            [$this, 'render_field_border_color'],
            $this->menu_slug . '-settings',
            'leyka_close_border_section'
        );
        
        add_settings_field(
            'border_width',
            __('Border width (px)', 'leyka-boost'),
            [$this, 'render_field_border_width'],
            $this->menu_slug . '-settings',
            'leyka_close_border_section'
        );
        
        // ===== СЕКЦИЯ "ПОВЕДЕНИЕ" УДАЛЕНА в 1.0.6 =====
        // Поле toggle_off_behavior удалено
        
        // ===== РЕГИСТРАЦИЯ ВСЕХ НАСТРОЕК (ИСПРАВЛЕНО в 1.0.6) =====
        register_setting(
            $this->menu_slug . '-settings',
            'leyka_close_settings',
            [
                'type' => 'object',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'enabled' => true,
                    'min_amount_to_show' => 200,
                    'auto_toggle_threshold' => 1000,
                    'active_color' => '#43a047',
                    'inactive_color' => '#cccccc',
                    'button_text' => __('Close the whole campaign: {sum} ₽', 'leyka-boost'),
                    'show_icon' => true,
                    'border_color' => '#e9ecef',
                    'border_width' => 1,
                    // УДАЛЕНО в 1.0.6: 'toggle_off_behavior' => 'first_button',
                ]
            ]
        );
    }
    
    /**
     * Очистка и валидация настроек (ИСПРАВЛЕНО в 1.0.6)
     *
     * @param array $input Входные данные
     * @return array Очищенные данные
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Boolean поля (теперь работают корректно благодаря скрытым полям)
        $sanitized['enabled'] = isset($input['enabled']) ? (bool)$input['enabled'] : false;
        $sanitized['show_icon'] = isset($input['show_icon']) ? (bool)$input['show_icon'] : false;
        
        // Integer поля
        $sanitized['min_amount_to_show'] = isset($input['min_amount_to_show']) ? intval($input['min_amount_to_show']) : 200;
        $sanitized['auto_toggle_threshold'] = isset($input['auto_toggle_threshold']) ? intval($input['auto_toggle_threshold']) : 1000;
        $sanitized['border_width'] = isset($input['border_width']) ? intval($input['border_width']) : 1;
        
        // String поля
        $sanitized['active_color'] = isset($input['active_color']) ? sanitize_text_field($input['active_color']) : '#43a047';
        $sanitized['inactive_color'] = isset($input['inactive_color']) ? sanitize_text_field($input['inactive_color']) : '#cccccc';
        $sanitized['border_color'] = isset($input['border_color']) ? sanitize_text_field($input['border_color']) : '#e9ecef';
        $sanitized['button_text'] = isset($input['button_text']) ? sanitize_text_field($input['button_text']) : __('Close the whole campaign: {sum} ₽', 'leyka-boost');
        
        // УДАЛЕНО в 1.0.6: $sanitized['toggle_off_behavior'] = isset($input['toggle_off_behavior']) ? sanitize_text_field($input['toggle_off_behavior']) : 'first_button';
        
        return $sanitized;
    }
    
    // ============================================
    // ОТРИСОВКА СЕКЦИЙ
    // ============================================
    
    public function render_main_section_desc() {
        echo '<p class="description">' . esc_html__('Main plugin behavior settings.', 'leyka-boost') . '</p>';
    }
    
    public function render_design_section_desc() {
        echo '<p class="description">' . esc_html__('Toggle appearance.', 'leyka-boost') . '</p>';
    }
    
    public function render_border_section_desc() {
        echo '<p class="description">' . esc_html__('Configure the Close Campaign block border.', 'leyka-boost') . '</p>';
    }
    
    // СЕКЦИЯ "ПОВЕДЕНИЕ" УДАЛЕНА в 1.0.6
    
    // ============================================
    // ОТРИСОВКА ПОЛЕЙ (ИСПРАВЛЕНО в 1.0.6)
    // ============================================
    
    /**
     * Поле: Включить плагин
     * ИСПРАВЛЕНО: Добавлено скрытое поле value="0"
     */
    public function render_field_enabled() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['enabled']) ? $settings['enabled'] : true;
        // Скрытое поле (отправляется всегда, даже если чекбокс снят)
        echo '<input type="hidden" name="leyka_close_settings[enabled]" value="0">';
        echo '<label><input type="checkbox" name="leyka_close_settings[enabled]" value="1" ' . checked($value, 1, false) . '> ' . esc_html__('Active', 'leyka-boost') . '</label>';
    }
    
    public function render_field_min_amount() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['min_amount_to_show']) ? intval($settings['min_amount_to_show']) : 200;
        echo '<input type="number" name="leyka_close_settings[min_amount_to_show]" value="' . esc_attr($value) . '" class="small-text" min="0">';
        echo '<p class="description">' . esc_html__('Hide the toggle when the remaining amount is less than this value.', 'leyka-boost') . '</p>';
    }
    
    public function render_field_auto_threshold() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['auto_toggle_threshold']) ? intval($settings['auto_toggle_threshold']) : 1000;
        echo '<input type="number" name="leyka_close_settings[auto_toggle_threshold]" value="' . esc_attr($value) . '" class="small-text" min="0">';
        echo '<p class="description">' . esc_html__('Automatically select the toggle when the remaining amount is less than this value.', 'leyka-boost') . '</p>';
    }
    
    public function render_field_active_color() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['active_color']) ? $settings['active_color'] : '#43a047';
        echo '<input type="color" name="leyka_close_settings[active_color]" value="' . esc_attr($value) . '" class="color-picker">';
        echo '<p class="description">' . esc_html__('Active toggle color.', 'leyka-boost') . '</p>';
    }
    
    public function render_field_inactive_color() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['inactive_color']) ? $settings['inactive_color'] : '#cccccc';
        echo '<input type="color" name="leyka_close_settings[inactive_color]" value="' . esc_attr($value) . '" class="color-picker">';
        echo '<p class="description">' . esc_html__('Inactive toggle color.', 'leyka-boost') . '</p>';
    }
    
    public function render_field_button_text() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['button_text']) ? $settings['button_text'] : __('Close the whole campaign: {sum} ₽', 'leyka-boost');
        echo '<input type="text" name="leyka_close_settings[button_text]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . wp_kses_post(__('Use <code>{sum}</code> to insert the amount.', 'leyka-boost')) . '</p>';
    }
    
    /**
     * Поле: Показывать иконку
     * ИСПРАВЛЕНО: Добавлено скрытое поле value="0"
     */
    public function render_field_show_icon() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['show_icon']) ? $settings['show_icon'] : true;
        // Скрытое поле (отправляется всегда, даже если чекбокс снят)
        echo '<input type="hidden" name="leyka_close_settings[show_icon]" value="0">';
        echo '<label><input type="checkbox" name="leyka_close_settings[show_icon]" value="1" ' . checked($value, 1, false) . '> ' . esc_html__('Show icon', 'leyka-boost') . '</label>';
    }
    
    // ===== НОВЫЕ ПОЛЯ РАМКИ (1.0.1) =====
    public function render_field_border_color() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['border_color']) ? $settings['border_color'] : '#e9ecef';
        echo '<input type="color" name="leyka_close_settings[border_color]" value="' . esc_attr($value) . '" class="color-picker">';
        echo '<p class="description">' . esc_html__('Close Campaign block border color.', 'leyka-boost') . '</p>';
    }
    
    public function render_field_border_width() {
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $value = isset($settings['border_width']) ? intval($settings['border_width']) : 1;
        echo '<input type="number" name="leyka_close_settings[border_width]" value="' . esc_attr($value) . '" class="small-text" min="0" max="10" step="1">';
        echo '<p class="description">' . esc_html__('Border width in pixels (0 = no border).', 'leyka-boost') . '</p>';
    }
    
    // ===== ПОЛЯ ПОВЕДЕНИЯ УДАЛЕНЫ в 1.0.6 =====
    // render_field_toggle_behavior() удалён
    
    // ============================================
    // СТРАНИЦЫ АДМИНКИ
    // ============================================
    
    /**
     * Страница: Главная (Dashboard)
     *
     * @return void
     */
    public function render_dashboard_page() {
        $stats = get_option(LEYKA_CLOSE_OPTION_STATS, []);
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        
        $total_toggles = isset($stats['total_toggles']) ? intval($stats['total_toggles']) : 0;
        $total_activations = isset($stats['total_activations']) ? intval($stats['total_activations']) : 0;
        $total_deactivations = isset($stats['total_deactivations']) ? intval($stats['total_deactivations']) : 0;
        $campaigns_count = isset($stats['campaigns']) ? count($stats['campaigns']) : 0;
        $last_reset = isset($stats['last_reset']) ? $stats['last_reset'] : __('Never', 'leyka-boost');
        
        $plugin_enabled = isset($settings['enabled']) ? $settings['enabled'] : true;
        $leyka_active = class_exists('Leyka');
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        $logs_writable = is_object($wp_filesystem) && $wp_filesystem->is_writable(LEYKA_CLOSE_LOGS_DIR);
        ?>
        <div class="wrap leyka-close-admin">
            <h1><?php esc_html_e('Close Campaign dashboard', 'leyka-boost'); ?></h1>
            
            <!-- Статус системы -->
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h2><?php esc_html_e('System status', 'leyka-boost'); ?></h2>
                <table class="widefat" style="border:none;">
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Plugin:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;">
                            <span style="color: <?php echo $plugin_enabled ? 'green' : 'red'; ?>;">
                                <?php echo esc_html($plugin_enabled ? __('Active', 'leyka-boost') : __('Inactive', 'leyka-boost')); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong>Leyka:</strong></td>
                        <td style="padding: 10px;">
                            <span style="color: <?php echo $leyka_active ? 'green' : 'red'; ?>;">
                                <?php echo esc_html($leyka_active ? __('Active', 'leyka-boost') : __('Not found', 'leyka-boost')); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Log folder:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;">
                            <span style="color: <?php echo $logs_writable ? 'green' : 'red'; ?>;">
                                <?php echo esc_html($logs_writable ? __('Writable', 'leyka-boost') : __('Not writable', 'leyka-boost')); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Статистика -->
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h2><?php esc_html_e('Statistics', 'leyka-boost'); ?></h2>
                <table class="widefat" style="border:none;">
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Total toggles:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;"><?php echo number_format($total_toggles); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Activations:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;"><?php echo number_format($total_activations); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Deactivations:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;"><?php echo number_format($total_deactivations); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Campaigns with statistics:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;"><?php echo (int) $campaigns_count; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;"><strong><?php esc_html_e('Last reset:', 'leyka-boost'); ?></strong></td>
                        <td style="padding: 10px;"><?php echo esc_html($last_reset); ?></td>
                    </tr>
                </table>
                
                <!-- Кнопки действий -->
                <div style="margin-top: 20px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=leyka-close-campaign')); ?>" class="button button-primary"><?php esc_html_e('Settings', 'leyka-boost'); ?></a>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset close statistics? This action cannot be undone.', 'leyka-boost')); ?>');">
                        <input type="hidden" name="action" value="leyka_close_reset_stats">
                        <?php wp_nonce_field('leyka_close_reset_stats_nonce'); ?>
                        <button type="submit" class="button button-link-delete"><?php esc_html_e('Reset statistics', 'leyka-boost'); ?></button>
                    </form>
                </div>
            </div>
            
            <!-- Топ кампаний -->
            <?php if ($campaigns_count > 0): ?>
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h2><?php esc_html_e('Top campaigns', 'leyka-boost'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Campaign', 'leyka-boost'); ?></th>
                            <th><?php esc_html_e('Toggles', 'leyka-boost'); ?></th>
                            <th><?php esc_html_e('Activations', 'leyka-boost'); ?></th>
                            <th><?php esc_html_e('Deactivations', 'leyka-boost'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (class_exists('Leyka_Close_Statistics')) {
                            $stats_class = new Leyka_Close_Statistics();
                            $top_campaigns = $stats_class->get_top_campaigns(10);
                            foreach ($top_campaigns as $campaign_id => $data) {
                                $campaign = leyka_get_validated_campaign($campaign_id);
                                /* translators: %d: campaign ID */
                                $campaign_name = $campaign ? $campaign->title : sprintf(__('Campaign #%d', 'leyka-boost'), $campaign_id);
                                ?>
                                <tr>
                                    <td><?php echo esc_html($campaign_name); ?></td>
                                    <td><?php echo isset($data['toggles']) ? intval($data['toggles']) : 0; ?></td>
                                    <td><?php echo isset($data['activations']) ? intval($data['activations']) : 0; ?></td>
                                    <td><?php echo isset($data['deactivations']) ? intval($data['deactivations']) : 0; ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Страница: Настройки
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap leyka-close-admin">
            <h1><?php esc_html_e('Close Campaign settings', 'leyka-boost'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->menu_slug . '-settings');
                do_settings_sections($this->menu_slug . '-settings');
                submit_button(__('Save settings', 'leyka-boost'));
                ?>
            </form>
            
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h3><?php esc_html_e('Help', 'leyka-boost'); ?></h3>
                <ul>
                    <li><?php echo wp_kses_post(__('<strong>Minimum amount to show:</strong> if the remaining amount is lower, the toggle is hidden.', 'leyka-boost')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>Auto-enable threshold:</strong> if the remaining amount is lower, the toggle is selected automatically.', 'leyka-boost')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>Button text:</strong> use <code>{sum}</code> to insert the amount.', 'leyka-boost')); ?></li>
                    <li><?php echo wp_kses_post(__('<strong>Browser console:</strong> use only for debugging problems.', 'leyka-boost')); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    // ============================================
    // ОБРАБОТЧИКИ ДЕЙСТВИЙ
    // ============================================
    
    /**
     * Обработчик сброса статистики
     *
     * @return void
     */
    public function handle_reset_stats() {
        // Проверка прав
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'leyka-boost'));
        }
        
        // Проверка nonce
        check_admin_referer('leyka_close_reset_stats_nonce');
        
        // Сбрасываем статистику
        if (class_exists('Leyka_Close_Statistics')) {
            $stats = new Leyka_Close_Statistics();
            $stats->reset_stats();
        }
        
        // Логируем
        LeykaBoost_Logger::info('close-campaign', '[admin] ' . __('Statistics reset from admin.', 'leyka-boost'));
        
        // Редирект обратно
        wp_safe_redirect(admin_url('admin.php?page=leyka-close-campaign&reset=success'));
        exit;
    }
    
    // ============================================
    // ПОДКЛЮЧЕНИЕ РЕСУРСОВ
    // ============================================
    
    /**
     * Подключить стили и скрипты админки
     *
     * @param string $hook Текущая страница
     * @return void
     */
    public function enqueue_assets($hook) {
        // Проверяем что это наша страница
        if (strpos($hook, 'leyka-close-campaign') === false) {
            return;
        }
        
        // Стили
        wp_enqueue_style(
            'leyka-close-admin',
            LEYKA_CLOSE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            LEYKA_CLOSE_VERSION
        );
        
        // Скрипты
        wp_enqueue_script(
            'leyka-close-admin',
            LEYKA_CLOSE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            LEYKA_CLOSE_VERSION,
            true
        );
        
        wp_localize_script('leyka-close-admin', 'leykaCloseAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leyka_close_admin_nonce'),
            'consoleLogging' => false,
            'i18n' => [
                'confirmResetStats' => __('Are you sure? All statistics will be permanently deleted. This action cannot be undone.', 'leyka-boost'),
                'confirmClearLogs' => __('Are you sure? All logs will be deleted. This action cannot be undone.', 'leyka-boost'),
                'statsReset' => __('Statistics reset successfully.', 'leyka-boost'),
                'logsCleared' => __('Logs cleared successfully.', 'leyka-boost'),
                'preview' => __('Preview', 'leyka-boost'),
                /* translators: 1: auto-enable threshold amount, 2: minimum amount to show */
                'autoThresholdWarning' => __('Warning! Auto-enable threshold (%1$s ₽) is lower than the minimum amount to show (%2$s ₽). This means auto-enable will never trigger. Continue?', 'leyka-boost'),
                'error' => __('Error', 'leyka-boost'),
                'unknownError' => __('Unknown error', 'leyka-boost'),
                'connectionError' => __('Connection error', 'leyka-boost'),
            ],
        ]);
    }
}
