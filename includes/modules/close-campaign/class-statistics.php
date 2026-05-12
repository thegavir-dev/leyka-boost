<?php
/**
 * Класс сбора и хранения статистики плагина Leyka Close Campaign
 * 
 * Отвечает за:
 * - Подсчёт нажатий на тумблер
 * - Подсчёт включений/выключений
 * - Статистика по кампаниям
 * - Сброс статистики
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

class Leyka_Close_Statistics {
    
    /**
     * Ключ опции в БД
     * 
     * @var string
     */
    private $option_name = LEYKA_CLOSE_OPTION_STATS;
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Инициализация статистики если не существует
        $this->init_stats();
        
        // Хуки для сбора статистики
        add_action('leyka_close_toggle_on', [$this, 'record_activation'], 10, 2);
        add_action('leyka_close_toggle_off', [$this, 'record_deactivation'], 10, 2);
        add_action('leyka_close_toggle_click', [$this, 'record_toggle'], 10, 2);
        
        // Очистка старых данных (раз в месяц)
        if (is_admin()) {
            add_action('admin_init', [$this, 'maybe_cleanup_old_data']);
        }
    }
    
    // ============================================
    // ИНИЦИАЛИЗАЦИЯ
    // ============================================
    
    /**
     * Инициализировать статистику если не существует
     * 
     * @return void
     */
    private function init_stats() {
        $stats = get_option($this->option_name);
        
        if ($stats === false) {
            $default_stats = [
                'total_toggles' => 0,
                'total_activations' => 0,
                'total_deactivations' => 0,
                'campaigns' => [],
                'last_reset' => current_time('mysql'),
                'created_at' => current_time('mysql'),
            ];
            
            add_option($this->option_name, $default_stats);
            
            LeykaBoost_Logger::info('close-campaign', '[stats] Статистика инициализирована');
        }
    }
    
    // ============================================
    // СБОР СТАТИСТИКИ
    // ============================================
    
    /**
     * Записать нажатие на тумблер
     * 
     * @param int $campaign_id ID кампании
     * @param array $data Дополнительные данные
     * @return bool Успешность записи
     */
    public function record_toggle($campaign_id, $data = []) {
        $stats = get_option($this->option_name, []);
        
        // Увеличиваем общий счётчик
        $stats['total_toggles'] = isset($stats['total_toggles']) ? intval($stats['total_toggles']) + 1 : 1;
        
        // Увеличиваем счётчик кампании
        if (!isset($stats['campaigns'][$campaign_id])) {
            $stats['campaigns'][$campaign_id] = [
                'toggles' => 0,
                'activations' => 0,
                'deactivations' => 0,
                'last_toggle' => null,
            ];
        }
        
        $stats['campaigns'][$campaign_id]['toggles']++;
        $stats['campaigns'][$campaign_id]['last_toggle'] = current_time('mysql');
        
        // Сохраняем
        $result = update_option($this->option_name, $stats);
        
        LeykaBoost_Logger::debug('close-campaign', '[stats] Записано нажатие тумблера ' . wp_json_encode([
            'campaign_id' => $campaign_id,
            'total_toggles' => $stats['total_toggles'],
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));
        
        return $result;
    }
    
    /**
     * Записать включение тумблера
     * 
     * @param int $campaign_id ID кампании
     * @param array $data Дополнительные данные (сумма и т.д.)
     * @return bool Успешность записи
     */
    public function record_activation($campaign_id, $data = []) {
        $stats = get_option($this->option_name, []);
        
        // Увеличиваем общий счётчик
        $stats['total_activations'] = isset($stats['total_activations']) ? intval($stats['total_activations']) + 1 : 1;
        
        // Увеличиваем счётчик кампании
        if (!isset($stats['campaigns'][$campaign_id])) {
            $stats['campaigns'][$campaign_id] = [
                'toggles' => 0,
                'activations' => 0,
                'deactivations' => 0,
                'last_toggle' => null,
            ];
        }
        
        $stats['campaigns'][$campaign_id]['activations']++;
        $stats['campaigns'][$campaign_id]['last_activation'] = current_time('mysql');
        
        // Сохраняем сумму если передана
        if (!empty($data['amount'])) {
            $stats['campaigns'][$campaign_id]['last_amount'] = intval($data['amount']);
        }
        
        // Сохраняем
        $result = update_option($this->option_name, $stats);
        
        LeykaBoost_Logger::info('close-campaign', '[stats] Записано включение тумблера ' . wp_json_encode([
            'campaign_id' => $campaign_id,
            'total_activations' => $stats['total_activations'],
            'amount' => $data['amount'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        
        return $result;
    }
    
    /**
     * Записать выключение тумблера
     * 
     * @param int $campaign_id ID кампании
     * @param array $data Дополнительные данные
     * @return bool Успешность записи
     */
    public function record_deactivation($campaign_id, $data = []) {
        $stats = get_option($this->option_name, []);
        
        // Увеличиваем общий счётчик
        $stats['total_deactivations'] = isset($stats['total_deactivations']) ? intval($stats['total_deactivations']) + 1 : 1;
        
        // Увеличиваем счётчик кампании
        if (!isset($stats['campaigns'][$campaign_id])) {
            $stats['campaigns'][$campaign_id] = [
                'toggles' => 0,
                'activations' => 0,
                'deactivations' => 0,
                'last_toggle' => null,
            ];
        }
        
        $stats['campaigns'][$campaign_id]['deactivations']++;
        $stats['campaigns'][$campaign_id]['last_deactivation'] = current_time('mysql');
        
        // Сохраняем
        $result = update_option($this->option_name, $stats);
        
        LeykaBoost_Logger::info('close-campaign', '[stats] Записано выключение тумблера ' . wp_json_encode([
            'campaign_id' => $campaign_id,
            'total_deactivations' => $stats['total_deactivations'],
        ], JSON_UNESCAPED_UNICODE));
        
        return $result;
    }
    
    // ============================================
    // ЧТЕНИЕ СТАТИСТИКИ
    // ============================================
    
    /**
     * Получить всю статистику
     * 
     * @return array Массив статистики
     */
    public function get_all_stats() {
        return get_option($this->option_name, []);
    }
    
    /**
     * Получить общую статистику
     * 
     * @return array Общая статистика (total_*)
     */
    public function get_total_stats() {
        $stats = $this->get_all_stats();
        
        return [
            'total_toggles' => isset($stats['total_toggles']) ? intval($stats['total_toggles']) : 0,
            'total_activations' => isset($stats['total_activations']) ? intval($stats['total_activations']) : 0,
            'total_deactivations' => isset($stats['total_deactivations']) ? intval($stats['total_deactivations']) : 0,
            'last_reset' => isset($stats['last_reset']) ? $stats['last_reset'] : null,
        ];
    }
    
    /**
     * Получить статистику по кампании
     * 
     * @param int $campaign_id ID кампании
     * @return array Статистика кампании
     */
    public function get_campaign_stats($campaign_id) {
        $stats = $this->get_all_stats();
        
        if (isset($stats['campaigns'][$campaign_id])) {
            return $stats['campaigns'][$campaign_id];
        }
        
        return [
            'toggles' => 0,
            'activations' => 0,
            'deactivations' => 0,
            'last_toggle' => null,
        ];
    }
    
    /**
     * Получить топ кампаний по использованиям
     * 
     * @param int $limit Количество кампаний
     * @return array Топ кампаний
     */
    public function get_top_campaigns($limit = 10) {
        $stats = $this->get_all_stats();
        $campaigns = isset($stats['campaigns']) ? $stats['campaigns'] : [];
        
        // Сортируем по количеству нажатий
        uasort($campaigns, function($a, $b) {
            return ($b['toggles'] ?? 0) - ($a['toggles'] ?? 0);
        });
        
        // Возвращаем топ N
        return array_slice($campaigns, 0, $limit, true);
    }
    
    // ============================================
    // УПРАВЛЕНИЕ СТАТИСТИКОЙ
    // ============================================
    
    /**
     * Сбросить всю статистику
     * 
     * @return bool Успешность сброса
     */
    public function reset_stats() {
        $current_stats = get_option($this->option_name, []);
        
        $new_stats = [
            'total_toggles' => 0,
            'total_activations' => 0,
            'total_deactivations' => 0,
            'campaigns' => [],
            'last_reset' => current_time('mysql'),
            'created_at' => isset($current_stats['created_at']) ? $current_stats['created_at'] : current_time('mysql'),
        ];
        
        $result = update_option($this->option_name, $new_stats);
        
        LeykaBoost_Logger::info('close-campaign', '[stats] Статистика сброшена ' . wp_json_encode(['previous_total' => $current_stats['total_toggles'] ?? 0], JSON_UNESCAPED_UNICODE));
        
        return $result;
    }
    
    /**
     * Очистить старые данные кампаний (которые удалены)
     * 
     * @return int Количество удалённых записей
     */
    public function cleanup_deleted_campaigns() {
        $stats = get_option($this->option_name, []);
        $campaigns = isset($stats['campaigns']) ? $stats['campaigns'] : [];
        
        if (empty($campaigns)) {
            return 0;
        }
        
        $deleted_count = 0;
        
        foreach ($campaigns as $campaign_id => $data) {
            // Проверяем существует ли кампания
            $campaign = leyka_get_validated_campaign($campaign_id);
            
            if (!$campaign) {
                unset($stats['campaigns'][$campaign_id]);
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            update_option($this->option_name, $stats);
            
            LeykaBoost_Logger::info('close-campaign', '[stats] Очищены данные удалённых кампаний ' . wp_json_encode(['deleted' => $deleted_count], JSON_UNESCAPED_UNICODE));
        }
        
        return $deleted_count;
    }
    
    /**
     * Возможно очистить старые данные (раз в месяц)
     * 
     * @return void
     */
    public function maybe_cleanup_old_data() {
        $stats = get_option($this->option_name, []);
        $last_cleanup = isset($stats['last_cleanup']) ? $stats['last_cleanup'] : null;
        
        // Проверяем прошёл ли месяц
        if ($last_cleanup) {
            $last_cleanup_time = strtotime($last_cleanup);
            $current_time = current_time('timestamp');
            
            if (($current_time - $last_cleanup_time) < MONTH_IN_SECONDS) {
                return;
            }
        }
        
        // Очищаем
        $this->cleanup_deleted_campaigns();
        
        // Обновляем дату последней очистки
        $stats['last_cleanup'] = current_time('mysql');
        update_option($this->option_name, $stats);
    }
    
    // ============================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ============================================
    
    /**
     * Получить дату последнего сброса
     * 
     * @return string|null Дата в формате MySQL
     */
    public function get_last_reset_date() {
        $stats = $this->get_all_stats();
        return isset($stats['last_reset']) ? $stats['last_reset'] : null;
    }
    
    /**
     * Получить количество кампаний со статистикой
     * 
     * @return int Количество кампаний
     */
    public function get_campaigns_count() {
        $stats = $this->get_all_stats();
        return isset($stats['campaigns']) ? count($stats['campaigns']) : 0;
    }
}
