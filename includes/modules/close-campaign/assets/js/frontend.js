/**
 * Frontend JavaScript для плагина Leyka Close Campaign
 * Версия: 1.0.6
 * 
 * ⚠️  ВНИМАНИЕ: Вся основная логика теперь в inline-скрипте внутри class-frontend.php
 * Этот файл — заглушка для совместимости с wp_enqueue_script()
 * 
 * ИСПРАВЛЕНО в 1.0.6:
 * - Удалена дублирующая логика (теперь в inline-скрипте PHP)
 * - Оставлена только базовая инициализация для совместимости
 * - Нет конфликтов с inline-скриптом
 */

(function($) {
    'use strict';
    
    // ============================================
    // ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ
    // ============================================
    
    const CONFIG = window.leykaCloseFrontend || {};
    const SETTINGS = CONFIG.settings || {};
    const IS_MOBILE = CONFIG.is_mobile || false;
    const IS_IOS = CONFIG.is_ios || false;
    const CONSOLE_LOGGING = SETTINGS.console_logging || false;
    
    // ============================================
    // ФУНКЦИИ ЛОГИРОВАНИЯ (отключаемые)
    // ============================================
    
    /**
     * Логирование в консоль (отключаемое)
     */
    function log(...args) {
        return CONSOLE_LOGGING && args.length;
    }
    
    function logError(...args) {
        return CONSOLE_LOGGING && args.length;
    }
    
    function logWarn(...args) {
        if (CONSOLE_LOGGING) {
            console.warn('🟡 Leyka Close:', ...args);
        }
    }
    
    // ============================================
    // ИНИЦИАЛИЗАЦИЯ
    // ============================================
    
    /**
     * Инициализация после загрузки DOM
     * 
     * @return void
     */
    function init() {
        log('Инициализация...', CONFIG);
        
        // Проверяем включен ли плагин
        if (!SETTINGS.enabled) {
            log('Плагин отключен в настройках');
            return;
        }
        
        // Проверяем что inline-скрипт уже внедрил тумблер
        const wrapper = document.querySelector('.leyka-close-campaign-toggle-wrapper');
        
        if (wrapper) {
            // Inline-скрипт уже работает — не дублируем логику
            log('Тумблер уже внедрён inline-скриптом PHP');
            return;
        }
        
        // Если wrapper нет — форма ещё не загрузилась
        // Inline-скрипт из PHP обработает это сам
        log('Ожидаем внедрения тумблера из PHP');
    }
    
    // ============================================
    // ГЛОБАЛЬНАЯ ФУНКЦИЯ (для вызова из PHP)
    // ============================================
    
    /**
     * Инициализировать тумблер для кампании
     * Вызывается из PHP после рендера тумблера
     * 
     * @param {number} campaignId ID кампании
     * @return void
     */
    window.leykaCloseInitToggle = function(campaignId) {
        log('leykaCloseInitToggle вызван для', campaignId);
        
        // Вся логика инициализации теперь в inline-скрипте PHP
        // Эта функция оставлена для обратной совместимости
        log('leykaCloseInitToggle: логика в inline-скрипте PHP', { campaignId });
    };
    
    // ============================================
    // ЗАПУСК
    // ============================================
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Повторная попытка для AJAX-форм
    setTimeout(init, 1000);
    
})(jQuery);
