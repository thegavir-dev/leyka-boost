<?php
/**
 * Класс AJAX обработчиков плагина Leyka Close Campaign
 * 
 * Отвечает за:
 * - Получение актуальных сумм кампании
 * - Запись действий пользователя (вкл/выкл тумблера)
 * - Проверки и валидация
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

class Leyka_Close_Ajax {
    
    /**
     * Конструктор
     */
    public function __construct() {
        // AJAX действия для авторизованных и неавторизованных пользователей
        add_action('wp_ajax_leyka_close_get_sums', [$this, 'ajax_get_sums']);
        add_action('wp_ajax_nopriv_leyka_close_get_sums', [$this, 'ajax_get_sums']);
        
        add_action('wp_ajax_leyka_close_record_action', [$this, 'ajax_record_action']);
        add_action('wp_ajax_nopriv_leyka_close_record_action', [$this, 'ajax_record_action']);
    }
    
    // ============================================
    // ПОЛУЧЕНИЕ СУММ КАМПАНИИ
    // ============================================
    
    /**
     * AJAX: Получить суммы кампании (цель, собрано, остаток)
     * 
     * @return void JSON ответ
     */
    public function ajax_get_sums() {
        // Проверка nonce
        if (!check_ajax_referer('leyka_close_nonce', 'nonce', false)) {
            $this->json_error('invalid_nonce', __('Invalid nonce.', 'leyka-boost'));
        }
        
        // Получаем ID кампании
        $campaign_id = isset($_POST['campaign_id']) ? intval(wp_unslash($_POST['campaign_id'])) : 0;
        
        if (!$campaign_id) {
            $this->json_error('no_campaign_id', __('Campaign ID is missing.', 'leyka-boost'));
        }
        
        // Получаем кампанию
        $campaign = leyka_get_validated_campaign($campaign_id);
        
        if (!$campaign) {
            $this->json_error('campaign_not_found', __('Campaign not found.', 'leyka-boost'), ['campaign_id' => $campaign_id]);
        }
        
        // Получаем целевую сумму
        $goal = $campaign->target;
        
        if (!$goal || $goal <= 0) {
            $this->json_error('no_goal', __('The campaign has no target amount.', 'leyka-boost'), ['campaign_id' => $campaign_id]);
        }
        
        // Получаем собранную сумму (несколько методов для надёжности)
        $collected = $this->get_collected_amount($campaign_id, $campaign);
        
        // Считаем остаток
        $remaining = $goal - $collected;
        
        // Получаем настройки плагина
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        $min_amount_to_show = isset($settings['min_amount_to_show']) ? intval($settings['min_amount_to_show']) : 200;
        $auto_toggle_threshold = isset($settings['auto_toggle_threshold']) ? intval($settings['auto_toggle_threshold']) : 1000;
        
        // Логируем
        LeykaBoost_Logger::debug('close-campaign', '[ajax] Получены суммы кампании ' . wp_json_encode([
            'campaign_id' => $campaign_id,
            'goal' => $goal,
            'collected' => $collected,
            'remaining' => $remaining,
        ], JSON_UNESCAPED_UNICODE));
        
        // Формируем ответ
        $response = [
            'success' => true,
            'data' => [
                'campaign_id' => $campaign_id,
                'goal' => intval($goal),
                'collected' => intval($collected),
                'remaining' => intval(round($remaining)),
                'formatted_remaining' => number_format(intval(round($remaining)), 0, '.', ' '),
                'formatted_goal' => number_format(intval($goal), 0, '.', ' '),
                'formatted_collected' => number_format(intval($collected), 0, '.', ' '),
                'should_show' => $remaining >= $min_amount_to_show,
                'should_auto_toggle' => $remaining > 0 && $remaining <= $auto_toggle_threshold,
                'is_complete' => $remaining <= 0,
            ],
        ];
        
        wp_send_json($response);
    }
    
    // ============================================
    // ЗАПИСЬ ДЕЙСТВИЙ ПОЛЬЗОВАТЕЛЯ
    // ============================================
    
    /**
     * AJAX: Записать действие пользователя (вкл/выкл/клик тумблера)
     * 
     * @return void JSON ответ
     */
    public function ajax_record_action() {
        // Проверка nonce
        if (!check_ajax_referer('leyka_close_nonce', 'nonce', false)) {
            $this->json_error('invalid_nonce', __('Invalid nonce.', 'leyka-boost'));
        }
        
        // Получаем данные
        $campaign_id = isset($_POST['campaign_id']) ? intval(wp_unslash($_POST['campaign_id'])) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';
        $amount = isset($_POST['amount']) ? intval(wp_unslash($_POST['amount'])) : 0;
        
        if (!$campaign_id) {
            $this->json_error('no_campaign_id', __('Campaign ID is missing.', 'leyka-boost'));
        }
        
        if (!in_array($action, ['toggle', 'activation', 'deactivation'])) {
            $this->json_error('invalid_action', __('Invalid action.', 'leyka-boost'), ['action' => $action]);
        }
        
        // Записываем статистику
        if (class_exists('Leyka_Close_Statistics')) {
            $stats = new Leyka_Close_Statistics();
            
            switch ($action) {
                case 'toggle':
                    $stats->record_toggle($campaign_id, ['amount' => $amount]);
                    break;
                case 'activation':
                    $stats->record_activation($campaign_id, ['amount' => $amount]);
                    break;
                case 'deactivation':
                    $stats->record_deactivation($campaign_id);
                    break;
            }
        }
        
        // Логируем
        LeykaBoost_Logger::info('close-campaign', '[ajax] Записано действие пользователя ' . wp_json_encode([
            'campaign_id' => $campaign_id,
            'action' => $action,
            'amount' => $amount,
        ], JSON_UNESCAPED_UNICODE));
        
        wp_send_json_success([
            'campaign_id' => $campaign_id,
            'action' => $action,
            'amount' => $amount,
        ]);
    }
    
    // ============================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ============================================
    
    /**
     * Получить собранную сумму кампании
     * 
     * @param int $campaign_id ID кампании
     * @param object $campaign Объект кампании
     * @return float Собранная сумма
     */
    private function get_collected_amount($campaign_id, $campaign = null) {
        $collected = 0;
        
        // Метод 1: Через метод get_collected_amount() если есть
        if ($campaign && method_exists($campaign, 'get_collected_amount')) {
            $collected = floatval($campaign->get_collected_amount());
        }
        // Метод 2: Через свойство collected_amount
        elseif ($campaign && isset($campaign->collected_amount) && is_numeric($campaign->collected_amount)) {
            $collected = floatval($campaign->collected_amount);
        }
        // Метод 3: Через свойство raised
        elseif ($campaign && isset($campaign->raised) && is_numeric($campaign->raised)) {
            $collected = floatval($campaign->raised);
        }
        // Метод 4: Прямой SQL запрос к базе (самый надёжный)
        else {
            global $wpdb;
            $table = $wpdb->prefix . 'leyka_donations';
            
            $collected = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) 
                 FROM $table 
                 WHERE campaign_id = %d 
                 AND status IN ('funded', 'paid')",
                $campaign_id
            ));
            
            $collected = $collected ? floatval($collected) : 0;
        }
        
        return $collected;
    }
    
    /**
     * Отправить JSON ошибку
     * 
     * @param string $code Код ошибки
     * @param string $message Сообщение
     * @param array $data Дополнительные данные
     * @return void
     */
    private function json_error($code, $message, $data = []) {
        // Логируем ошибку
        LeykaBoost_Logger::error('close-campaign', '[ajax] ' . $message . ' ' . wp_json_encode(array_merge(['code' => $code], $data), JSON_UNESCAPED_UNICODE));
        
        wp_send_json_error([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
    }
    
    /**
     * Отправить JSON успех
     * 
     * @param array $data Данные
     * @return void
     */
    private function json_success($data = []) {
        wp_send_json_success($data);
    }
}
