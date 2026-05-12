<?php
/**
 * Класс фронтенда плагина Leyka Close Campaign
 * Версия: 1.0.6
 *
 * Отвечает за:
 * - Вывод тумблера на формах Leyka
 * - Интеграция с формами пожертвований
 * - Логика работы тумблера (вкл/выкл/авто)
 * - Подключение JS/CSS на сайте
 * 
 * ИСПРАВЛЕНО в 1.0.6:
 * - Поддержка обоих шаблонов Leyka ("Нужна помощь" и "Стар")
 * - Ищет заголовки "Личные данные" и "Ваши данные" для позиционирования
 * - Вставляет тумблер перед секцией .section
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

class Leyka_Close_Frontend {
    
    /**
     * Конструктор
     */
    public function __construct() {
        // Подключаем скрипты и стили
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Внедрение тумблера через JS (единственный метод)
        add_action('wp_footer', [$this, 'inject_toggle_via_js'], 100);
    }
    
    // ============================================
    // ПОДКЛЮЧЕНИЕ РЕСУРСОВ
    // ============================================
    
    /**
     * Подключить стили и скрипты на сайте
     *
     * @return void
     */
    public function enqueue_assets() {
        // Проверяем включен ли плагин
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        if (empty($settings['enabled'])) {
            return;
        }
        
        // Подключаем только на страницах с формами Leyka
        if (!$this->is_leyka_page()) {
            return;
        }
        
        // Стили тумблера
        wp_enqueue_style(
            'leyka-close-frontend',
            LEYKA_CLOSE_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            LEYKA_CLOSE_VERSION
        );
        
        // Скрипт тумблера
        wp_enqueue_script(
            'leyka-close-frontend',
            LEYKA_CLOSE_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            LEYKA_CLOSE_VERSION,
            true
        );
        
        // Передаём данные в JS
        wp_localize_script('leyka-close-frontend', 'leykaCloseFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leyka_close_nonce'),
            'settings' => [
                'enabled' => true,
                'min_amount_to_show' => isset($settings['min_amount_to_show']) ? intval($settings['min_amount_to_show']) : 200,
                'auto_toggle_threshold' => isset($settings['auto_toggle_threshold']) ? intval($settings['auto_toggle_threshold']) : 1000,
                'active_color' => isset($settings['active_color']) ? $settings['active_color'] : '#43a047',
                'inactive_color' => isset($settings['inactive_color']) ? $settings['inactive_color'] : '#cccccc',
                'button_text' => isset($settings['button_text']) ? $settings['button_text'] : __('Close the whole campaign: {sum} ₽', 'leyka-boost'),
                'show_icon' => isset($settings['show_icon']) ? (bool)$settings['show_icon'] : true,
                'border_color' => isset($settings['border_color']) ? $settings['border_color'] : '#e9ecef',
                'border_width' => isset($settings['border_width']) ? intval($settings['border_width']) : 1,
                'console_logging' => false,
            ],
            'is_mobile' => wp_is_mobile(),
            'is_ios' => $this->is_ios(),
        ]);
    }
    
    // ============================================
    // ПРОВЕРКИ
    // ============================================
    
    /**
     * Проверить что мы на странице с формой Leyka
     *
     * @return bool
     */
    private function is_leyka_page() {
        // Сингулярная запись кампании
        if (is_singular('leyka_campaign')) {
            return true;
        }
        
        // Страница со шорткодом Leyka
        if (is_singular()) {
            $post = get_post();
            if ($post && has_shortcode($post->post_content, 'leyka_campaign_form')) {
                return true;
            }
            if ($post && has_shortcode($post->post_content, 'leyka_inline_campaign')) {
                return true;
            }
        }
        
        // Архив кампаний
        if (is_post_type_archive('leyka_campaign')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Проверить что это iOS устройство
     *
     * @return bool
     */
    private function is_ios() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        return preg_match('/iPad|iPhone|iPod/', $user_agent);
    }
    
    // ============================================
    // ВНЕДРЕНИЕ ТУМБЛЕРА (JS)
    // ============================================
    
    /**
     * Внедрить тумблер через JS (единственный метод)
     *
     * @return void
     */
    public function inject_toggle_via_js() {
        // Проверяем включен ли плагин
        $settings = get_option(LEYKA_CLOSE_OPTION_SETTINGS, []);
        if (empty($settings['enabled'])) {
            return;
        }
        
        // Проверяем что мы на странице Leyka
        if (!$this->is_leyka_page()) {
            return;
        }
        
        // Получаем ID кампании из шорткода
        $campaign_id = $this->get_campaign_id_from_page();
        if (!$campaign_id) {
            return;
        }
        
        // Получаем кампанию
        $campaign = leyka_get_validated_campaign($campaign_id);
        if (!$campaign) {
            return;
        }
        
        // Проверяем есть ли цель
        $goal = $campaign->target;
        if (!$goal || $goal <= 0) {
            return;
        }
        
        // Получаем собранную сумму
        $collected = $this->get_collected_amount($campaign_id, $campaign);
        $remaining = $goal - $collected;
        
        // Проверяем минимальную сумму для показа
        $min_amount_to_show = isset($settings['min_amount_to_show']) ? intval($settings['min_amount_to_show']) : 200;
        if ($remaining < $min_amount_to_show) {
            return;
        }
        
        // Проверяем не завершён ли сбор
        if ($remaining <= 0) {
            return;
        }
        
        // Формируем текст кнопки
        $button_text = isset($settings['button_text']) ? $settings['button_text'] : __('Close the whole campaign: {sum} ₽', 'leyka-boost');
        $button_text = str_replace('{sum}', number_format(intval(round($remaining)), 0, '.', ' '), $button_text);
        
        // Показывать иконку?
        $icon = isset($settings['show_icon']) && $settings['show_icon'] ? '💚 ' : '';
        
        // Получаем цвета и настройки рамки
        $active_color = isset($settings['active_color']) ? $settings['active_color'] : '#43a047';
        $inactive_color = isset($settings['inactive_color']) ? $settings['inactive_color'] : '#cccccc';
        $border_color = isset($settings['border_color']) ? $settings['border_color'] : '#e9ecef';
        $border_width = isset($settings['border_width']) ? intval($settings['border_width']) : 1;
        
        // Проверяем нужно ли авто-включение
        $auto_toggle_threshold = isset($settings['auto_toggle_threshold']) ? intval($settings['auto_toggle_threshold']) : 1000;
        $should_auto_toggle = $remaining > 0 && $remaining <= $auto_toggle_threshold;
        
        // Console logging is no longer exposed in Close Campaign settings.
        $console_logging = 'false';
        
        // Выводим скрипт для внедрения тумблера
        ?>
        <script>
        (function() {
            'use strict';
            
            // ============================================
            // ФУНКЦИЯ ЛОГИРОВАНИЯ (отключаемая)
            // ============================================
            const consoleLogging = <?php echo esc_js($console_logging); ?>;
            
            function log() {
                if (consoleLogging) {
                    console.log('🟢 Leyka Close:', ...arguments);
                }
            }
            
            function logError() {
                if (consoleLogging) {
                    console.error('🔴 Leyka Close:', ...arguments);
                }
            }
            
            function logWarn() {
                if (consoleLogging) {
                    console.warn('🟡 Leyka Close:', ...arguments);
                }
            }
            
            // ============================================
            // ОСНОВНАЯ ЛОГИКА
            // ============================================
            
            // Ждём пока форма загрузится
            function injectToggle() {
                const form = document.querySelector('form.leyka-payment-form, form[id*="leyka-pf"]');
                
                if (!form) {
                    log('Форма ещё не загружена, пробуем через 100мс');
                    setTimeout(injectToggle, 100);
                    return;
                }
                
                // Проверяем не внедрён уже ли
                if (form.querySelector('.leyka-close-campaign-toggle-wrapper')) {
                    log('Тумблер уже внедрён');
                    return;
                }
                
                log('Внедряем тумблер через JS');
                
                // ============================================
                // ИСПРАВЛЕНО в 1.0.6: Поддержка обоих шаблонов Leyka
                // ============================================
                
                // Ищем позицию для вставки тумблера
                let insertPosition = null;
                
                // Шаблон 1: "Нужна помощь" - перед "Личные данные"
                const personalTitle = Array.from(form.querySelectorAll('.section-title-text, h3, h4, h5, label, div')).find(el =>
                    el.textContent.includes('Личные данные') ||
                    el.textContent.includes('Personal')
                );
                
                // Шаблон 2: "Стар" - перед "Ваши данные"
                const yourDataTitle = Array.from(form.querySelectorAll('.section-title-text, h3, h4, h5, div')).find(el =>
                    el.textContent.includes('Ваши данные') ||
                    el.textContent.includes('Your data') ||
                    el.textContent.includes('ВАШИ ДАННЫЕ')
                );
                
                // Определяем куда вставлять
                if (personalTitle) {
                    // Шаблон "Нужна помощь"
                    insertPosition = personalTitle.closest('.section') || personalTitle.closest('div[class*="field"], div[class*="row"]') || personalTitle;
                } else if (yourDataTitle) {
                    // Шаблон "Стар"
                    insertPosition = yourDataTitle.closest('.section') || yourDataTitle;
                }
                
                // Создаём HTML тумблера
                const wrapper = document.createElement('div');
                wrapper.className = 'leyka-close-campaign-toggle-wrapper leyka-close-has-border';
                wrapper.setAttribute('data-campaign-id', '<?php echo esc_attr($campaign_id); ?>');
                wrapper.setAttribute('data-remaining', '<?php echo esc_attr(intval(round($remaining))); ?>');
                wrapper.setAttribute('data-auto-toggle', '<?php echo $should_auto_toggle ? '1' : '0'; ?>');
                
                // ИСПРАВЛЕНО в 1.0.5: Используем CSS переменные вместо inline-стилей
                wrapper.style.setProperty('--leyka-close-border-color', '<?php echo esc_js($border_color); ?>');
                wrapper.style.setProperty('--leyka-close-border-width', '<?php echo esc_js($border_width); ?>px');
                
                wrapper.innerHTML = `
                    <div class="leyka-close-campaign-toggle">
                        <div class="leyka-close-campaign-label">
                            <label for="leyka-close-toggle-<?php echo esc_attr($campaign_id); ?>" class="leyka-close-campaign-label-inner">
                                <span class="leyka-close-campaign-icon"><?php echo wp_kses_post($icon); ?></span>
                                <span class="leyka-close-campaign-text"><?php echo esc_html($button_text); ?></span>
                            </label>
                        </div>
                        <div class="leyka-close-campaign-switch">
                            <input type="checkbox" 
                                   id="leyka-close-toggle-<?php echo esc_attr($campaign_id); ?>" 
                                   class="leyka-close-campaign-checkbox"
                                   <?php echo $should_auto_toggle ? 'checked' : ''; ?>>
                            <label for="leyka-close-toggle-<?php echo esc_attr($campaign_id); ?>" 
                                   class="leyka-close-campaign-slider"
                                   data-active-color="<?php echo esc_attr($active_color); ?>"
                                   data-inactive-color="<?php echo esc_attr($inactive_color); ?>">
                                <span class="leyka-close-campaign-slider-dot"></span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" class="leyka-close-campaign-amount" value="<?php echo esc_attr(intval(round($remaining))); ?>">
                `;
                
                // ============================================
                // Вставка в форму (ИСПРАВЛЕНО в 1.0.6)
                // ============================================
                if (insertPosition) {
                    // Вставляем ПЕРЕД найденной секцией
                    insertPosition.parentNode.insertBefore(wrapper, insertPosition);
                    log('Тумблер внедрён перед секцией');
                } else {
                    // Фолбэк - вставляем перед кнопкой отправки
                    const submitButton = form.querySelector('input[type="submit"], button[type="submit"]');
                    if (submitButton) {
                        submitButton.parentNode.insertBefore(wrapper, submitButton);
                        log('Тумблер внедрён перед кнопкой отправки (фолбэк)');
                    } else {
                        form.appendChild(wrapper);
                        log('Тумблер внедрён в конец формы (фолбэк)');
                    }
                }
                
                // Инициализируем обработчики
                initToggleHandlers(wrapper, <?php echo intval($campaign_id); ?>, <?php echo intval($remaining); ?>);
            }
            
            /**
             * Инициализировать обработчики тумблера
             */
            function initToggleHandlers(wrapper, campaignId, remaining) {
                const checkbox = wrapper.querySelector('.leyka-close-campaign-checkbox');
                const slider = wrapper.querySelector('.leyka-close-campaign-slider');
                const form = wrapper.closest('form');
                
                if (!checkbox || !slider || !form) {
                    logError('Не найдены элементы тумблера');
                    return;
                }
                
                // Обработчик изменения
                checkbox.addEventListener('change', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isChecked = this.checked;
                    const activeColor = slider.dataset.activeColor || '<?php echo esc_js($active_color); ?>';
                    const inactiveColor = slider.dataset.inactiveColor || '<?php echo esc_js($inactive_color); ?>';
                    
                    log('Изменение тумблера', { checked: isChecked, campaignId });
                    
                    // Обновляем стиль (с проверкой на null)
                    slider.style.backgroundColor = isChecked ? activeColor : inactiveColor;
                    const dot = slider.querySelector('.leyka-close-campaign-slider-dot');
                    if (dot) {
                        dot.style.transform = isChecked ? 'translateX(26px)' : 'translateX(0)';
                    }
                    
                    // Выполняем действия
                    if (isChecked) {
                        activateToggle(form, remaining);
                    } else {
                        deactivateToggle(form);
                    }
                    
                    // Записываем статистику
                    recordAction(campaignId, isChecked ? 'activation' : 'deactivation', remaining);
                });
                
                // Для iOS
                if (<?php echo $this->is_ios() ? 'true' : 'false'; ?>) {
                    slider.addEventListener('touchend', function(e) {
                        e.preventDefault();
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }
                
                // Отслеживаем клики на кнопки сумм для авто-выключения
                trackAmountButtons(form, checkbox, slider);
                
                // Отслеживаем изменение поля суммы для авто-выключения
                trackAmountInput(form, checkbox, slider);
            }
            
            /**
             * Активация тумблера
             * ИСПРАВЛЕНО в 1.0.5: Используем CSS класс вместо inline-стилей
             */
            function activateToggle(form, amount) {
                log('Активация тумблера, сумма:', amount);
                
                // 1. Снимаем выделение со всех кнопок сумм
                const allSwiperItems = form.querySelectorAll('.swiper-item.selected, .swiper-item.active');
                allSwiperItems.forEach(item => {
                    item.classList.remove('selected', 'active');
                    item.removeAttribute('aria-selected');
                });
                
                // 2. Активируем flex-amount-item
                const flexAmountItem = form.querySelector('.flex-amount-item, .swiper-item[data-value="custom"]');
                
                if (flexAmountItem) {
                    flexAmountItem.classList.add('selected', 'active');
                    flexAmountItem.setAttribute('aria-selected', 'true');
                    
                    // ИСПРАВЛЕНО в 1.0.5: Добавляем CSS класс вместо inline-стилей
                    flexAmountItem.classList.add('leyka-close-active');
                    
                    log('Активирован flex-amount-item с классом leyka-close-active');
                }
                
                // 3. Находим поле ввода суммы
                const flexInput = form.querySelector('input[name="donate_amount_flex"], input.donate_amount_flex, .flex-amount-item input[type="number"]');
                
                if (flexInput) {
                    // Показываем если скрыто
                    if (flexInput.offsetParent === null || flexInput.type === 'hidden') {
                        flexInput.style.display = 'block';
                        flexInput.type = 'number';
                    }
                    
                    // Заполняем сумму (без фокуса)
                    flexInput.value = amount;
                    
                    // Триггерим только change событие
                    flexInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    
                    log('Сумма подставлена (без фокуса)');
                }
            }
            
            /**
             * Деактивация тумблера
             * ИСПРАВЛЕНО в 1.0.5: Удаляем CSS класс вместо сброса inline-стилей
             */
            function deactivateToggle(form) {
                log('Деактивация тумблера');
                
                // Снимаем выделение с flex-amount-item
                const flexAmountItem = form.querySelector('.flex-amount-item, .swiper-item[data-value="custom"]');
                
                if (flexAmountItem) {
                    flexAmountItem.classList.remove('selected', 'active');
                    flexAmountItem.removeAttribute('aria-selected');
                    
                    // ИСПРАВЛЕНО в 1.0.5: Удаляем CSS класс
                    flexAmountItem.classList.remove('leyka-close-active');
                    
                    log('Подсветка .flex-amount-item сброшена');
                }
                
                // НЕ активируем первую кнопку (чтобы не было чёрной рамки Leyka)
                log('Первая кнопка суммы не активируется (пользователь выберет сам)');
            }
            
            /**
             * Отслеживать клики на кнопки сумм для авто-выключения
             * ИСПРАВЛЕНО в 1.0.5: Сброс CSS класса
             */
            function trackAmountButtons(form, checkbox, slider) {
                const amountButtons = form.querySelectorAll('.swiper-item[data-value]:not([data-value="custom"]), .leyka-amount-btn');
                
                amountButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (checkbox.checked) {
                            log('Клик на кнопку суммы, выключаем тумблер');
                            
                            checkbox.checked = false;
                            slider.style.backgroundColor = slider.dataset.inactiveColor || '<?php echo esc_js($inactive_color); ?>';
                            const dot = slider.querySelector('.leyka-close-campaign-slider-dot');
                            if (dot) {
                                dot.style.transform = 'translateX(0)';
                            }
                            
                            // СБРОС CSS класса
                            const flexAmountItem = form.querySelector('.flex-amount-item');
                            if (flexAmountItem) {
                                flexAmountItem.classList.remove('leyka-close-active');
                            }
                            
                            deactivateToggle(form);
                            recordAction(parseInt(form.dataset.leykaCloseCampaignId || '<?php echo (int) $campaign_id; ?>'), 'deactivation', 0);
                        }
                    });
                });
            }
            
            /**
             * Отслеживать изменение поля суммы для авто-выключения
             * ИСПРАВЛЕНО в 1.0.5: Сброс CSS класса
             */
            function trackAmountInput(form, checkbox, slider) {
                const amountInput = form.querySelector('input[name="donate_amount_flex"], input.donate_amount_flex');
                
                if (!amountInput) return;
                
                amountInput.addEventListener('input', function() {
                    if (checkbox.checked) {
                        log('Изменение суммы вручную, выключаем тумблер');
                        
                        checkbox.checked = false;
                        slider.style.backgroundColor = slider.dataset.inactiveColor || '<?php echo esc_js($inactive_color); ?>';
                        const dot = slider.querySelector('.leyka-close-campaign-slider-dot');
                        if (dot) {
                            dot.style.transform = 'translateX(0)';
                        }
                        
                        // СБРОС CSS класса
                        const flexAmountItem = amountInput.closest('.flex-amount-item');
                        if (flexAmountItem) {
                            flexAmountItem.classList.remove('leyka-close-active');
                        }
                        
                        deactivateToggle(form);
                        recordAction(parseInt(form.dataset.leykaCloseCampaignId || '<?php echo (int) $campaign_id; ?>'), 'deactivation', 0);
                    }
                });
            }
            
            /**
             * Записать статистику
             */
            function recordAction(campaignId, actionType, amount) {
                const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                const nonce = '<?php echo esc_attr(wp_create_nonce('leyka_close_nonce')); ?>';
                
                if (!ajaxUrl || !nonce) {
                    logWarn('AJAX не настроен');
                    return;
                }
                
                const data = new URLSearchParams({
                    action: 'leyka_close_record_action',
                    nonce: nonce,
                    campaign_id: campaignId,
                    action_type: actionType,
                    amount: amount
                });
                
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: data.toString()
                })
                .then(response => response.json())
                .then(data => {
                    log('Статистика записана', data);
                })
                .catch(error => {
                    logError('Ошибка записи статистики', error);
                });
            }
            
            // Запускаем
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', injectToggle);
            } else {
                injectToggle();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Получить ID кампании со страницы (из шорткода)
     *
     * @return int|null ID кампании или null
     */
    private function get_campaign_id_from_page() {
        // Если это запись кампании
        if (is_singular('leyka_campaign')) {
            return get_the_ID();
        }
        
        // Если страница со шорткодом
        if (is_singular()) {
            $post = get_post();
            
            // Ищем шорткод [leyka_campaign_form id="123"]
            if (preg_match('/\[leyka_campaign_form[^\]]*id=["\']?(\d+)/', $post->post_content, $matches)) {
                return intval($matches[1]);
            }
            
            // Ищем шорткод [leyka_inline_campaign id="123"]
            if (preg_match('/\[leyka_inline_campaign[^\]]*id=["\']?(\d+)/', $post->post_content, $matches)) {
                return intval($matches[1]);
            }
        }
        
        return null;
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
        // Метод 4: Прямой SQL запрос к базе
        else {
            global $wpdb;
            $table = $wpdb->prefix . 'leyka_donations';
            
            $collected = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Leyka donations table, table name from wpdb prefix, safe.
                "SELECT SUM(amount) 
                 FROM %i
                 WHERE campaign_id = %d 
                 AND status IN ('funded', 'paid')",
                $table,
                $campaign_id
            ));
            
            $collected = $collected ? floatval($collected) : 0;
        }
        
        return $collected;
    }
}
