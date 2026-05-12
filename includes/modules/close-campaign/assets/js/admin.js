/**
 * Admin JavaScript для плагина Leyka Close Campaign
 * 
 * Отвечает за:
 * - Интерактив на странице настроек
 * - Подтверждение действий (сброс статистики, очистка логов)
 * - Динамическое обновление UI
 */

(function($) {
    'use strict';
    
    // ============================================
    // ИНИЦИАЛИЗАЦИЯ
    // ============================================
    
    const ADMIN_CONFIG = window.leykaCloseAdmin || {};
    const ADMIN_CONSOLE_LOGGING = !!ADMIN_CONFIG.consoleLogging;

    function adminLog(...args) {
        if (ADMIN_CONSOLE_LOGGING) {
            console.log('🟢 Leyka Close Admin:', ...args);
        }
    }

    $(document).ready(function() {
        adminLog('Инициализация');
        
        // Предупреждение о сбросе статистики
        initStatsReset();
        
        // Предупреждение об очистке логов
        initLogsClear();
        
        // Уведомления об успешных действиях
        initNotifications();
        
        // Цветовые пикеры
        initColorPickers();
        
        adminLog('Инициализация завершена');
    });
    
    // ============================================
    // СБРОС СТАТИСТИКИ
    // ============================================
    
    /**
     * Инициализация подтверждения сброса статистики
     */
    function initStatsReset() {
        const resetForm = $('form[action*="leyka_close_reset_stats"]');
        
        if (resetForm.length) {
            resetForm.on('submit', function(e) {
                const confirmed = confirm(window.leykaCloseAdmin.i18n.confirmResetStats);
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    }
    
    // ============================================
    // ОЧИСТКА ЛОГОВ
    // ============================================
    
    /**
     * Инициализация подтверждения очистки логов
     */
    function initLogsClear() {
        const clearForm = $('form[action*="leyka_close_clear_logs"]');
        
        if (clearForm.length) {
            clearForm.on('submit', function(e) {
                const confirmed = confirm(window.leykaCloseAdmin.i18n.confirmClearLogs);
                
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    }
    
    // ============================================
    // УВЕДОМЛЕНИЯ
    // ============================================
    
    /**
     * Показать уведомление об успешном действии
     */
    function initNotifications() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Уведомление о сбросе статистики
        if (urlParams.get('reset') === 'success') {
            showAdminNotice(window.leykaCloseAdmin.i18n.statsReset, 'success');
            cleanUrlParam('reset');
        }
        
        // Уведомление об очистке логов
        if (urlParams.get('clear') === 'success') {
            showAdminNotice(window.leykaCloseAdmin.i18n.logsCleared, 'success');
            cleanUrlParam('clear');
        }
    }
    
    /**
     * Показать админ-уведомление
     * 
     * @param {string} message Сообщение
     * @param {string} type Тип (success, error, warning)
     */
    function showAdminNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 
                           type === 'error' ? 'notice-error' : 'notice-warning';
        
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
            </div>
        `);
        
        // Вставляем после заголовка H1
        $('h1').first().after(notice);
        
        // Авто-скрытие через 5 секунд
        setTimeout(() => {
            notice.fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Очистить параметр из URL
     * 
     * @param {string} param Параметр для удаления
     */
    function cleanUrlParam(param) {
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete(param);
            window.history.replaceState({}, '', url);
        }
    }
    
    // ============================================
    // ЦВЕТОВЫЕ ПИКЕРЫ
    // ============================================
    
    /**
     * Инициализация цветовых пикеров WordPress
     */
    function initColorPickers() {
        if ($.fn.wpColorPicker) {
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    // Предпросмотр цвета в реальном времени
                    const color = ui.color.toString();
                    $(this).closest('tr').find('td').first().css('border-left-color', color);
                }
            });
        }
    }
    
    // ============================================
    // ПРЕДПРОСМОТР ТЕКСТА КНОПКИ
    // ============================================
    
    /**
     * Живой предпросмотр текста кнопки
     */
    $(document).on('input', 'input[name="leyka_close_settings[button_text]"]', function() {
        const text = $(this).val();
        const preview = text.replace('{sum}', '44 000');
        
        let previewContainer = $('#button-text-preview');
        
        if (!previewContainer.length) {
            previewContainer = $('<div id="button-text-preview" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;"></div>');
            $(this).closest('td').append(previewContainer);
        }
        
        previewContainer.html('<strong>' + window.leykaCloseAdmin.i18n.preview + ':</strong> ' + preview);
    });
    
    // ============================================
    // ПРОВЕРКА НАСТРОЕК ПЕРЕД СОХРАНЕНИЕМ
    // ============================================
    
    /**
     * Валидация настроек перед отправкой
     */
    $('form[action="options.php"]').on('submit', function(e) {
        const minAmount = parseInt($('input[name="leyka_close_settings[min_amount_to_show]"]').val()) || 0;
        const autoThreshold = parseInt($('input[name="leyka_close_settings[auto_toggle_threshold]"]]').val()) || 0;
        
        // Предупреждение если порог авто-включения меньше минимальной суммы
        if (autoThreshold < minAmount) {
            const confirmed = confirm(
                window.leykaCloseAdmin.i18n.autoThresholdWarning
                    .replace('%1$s', autoThreshold)
                    .replace('%2$s', minAmount)
            );
            
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        }
        
    });
    
    // ============================================
    // БЫСТРЫЕ ДЕЙСТВИЯ НА ГЛАВНОЙ
    // ============================================
    
    /**
     * Кнопка быстрой активации/деактивации плагина
     */
    $(document).on('click', '.leyka-close-quick-toggle', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $spinner = $button.find('.spinner');
        const enabled = $button.data('enabled') === '1';
        
        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        
        $.ajax({
            url: window.leykaCloseAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'leyka_close_quick_toggle',
                nonce: window.leykaCloseAdmin.nonce,
                enabled: enabled ? 0 : 1
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(window.leykaCloseAdmin.i18n.error + ': ' + (response.data.message || window.leykaCloseAdmin.i18n.unknownError));
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            },
            error: function() {
                alert(window.leykaCloseAdmin.i18n.connectionError);
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
})(jQuery);
