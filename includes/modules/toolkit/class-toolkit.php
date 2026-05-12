<?php
if (!defined('ABSPATH')) {
    exit;
}

class Leyka_Toolkit {

    const OPTION_KEY = 'leyka_toolkit_settings';

    private static $instance = null;
    private static $settings_cache = null;
    private static $render_count = 0;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function is_leyka_compatible() {
        return defined('LEYKA_VERSION') && version_compare(LEYKA_VERSION, LEYKA_TOOLKIT_MIN_LEYKA_VERSION, '>=');
    }

    public static function activate() {
        global $wp_version;

        if (version_compare(PHP_VERSION, LEYKA_TOOLKIT_MIN_PHP_VERSION, '<')) {
            deactivate_plugins(plugin_basename(LEYKA_TOOLKIT_FILE));
            wp_die(
                /* translators: %s: minimum required PHP version */
                esc_html(sprintf(__('Leyka Toolkit requires PHP %s or higher.', 'leyka-boost'), LEYKA_TOOLKIT_MIN_PHP_VERSION)),
                esc_html__('Leyka Toolkit activation error', 'leyka-boost'),
                ['back_link' => true]
            );
        }

        if (version_compare($wp_version, LEYKA_TOOLKIT_MIN_WP_VERSION, '<')) {
            deactivate_plugins(plugin_basename(LEYKA_TOOLKIT_FILE));
            wp_die(
                /* translators: %s: minimum required WordPress version */
                esc_html(sprintf(__('Leyka Toolkit requires WordPress %s or higher.', 'leyka-boost'), LEYKA_TOOLKIT_MIN_WP_VERSION)),
                esc_html__('Leyka Toolkit activation error', 'leyka-boost'),
                ['back_link' => true]
            );
        }

        if (!defined('LEYKA_VERSION')) {
            deactivate_plugins(plugin_basename(LEYKA_TOOLKIT_FILE));
            wp_die(
                esc_html__('Leyka Toolkit requires the Leyka plugin to be installed and active.', 'leyka-boost'),
                esc_html__('Leyka Toolkit activation error', 'leyka-boost'),
                ['back_link' => true]
            );
        }

        if (version_compare(LEYKA_VERSION, LEYKA_TOOLKIT_MIN_LEYKA_VERSION, '<')) {
            deactivate_plugins(plugin_basename(LEYKA_TOOLKIT_FILE));
            wp_die(
                /* translators: %s: minimum required Leyka version */
                esc_html(sprintf(__('Leyka Toolkit requires Leyka %s or higher.', 'leyka-boost'), LEYKA_TOOLKIT_MIN_LEYKA_VERSION)),
                esc_html__('Leyka Toolkit activation error', 'leyka-boost'),
                ['back_link' => true]
            );
        }

        $current = get_option(self::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }
        update_option(self::OPTION_KEY, array_merge(self::defaults(), $current));

    }

    public static function defaults() {
        return [
            'enabled'                  => 1,
            'label'                    => __('Subscribe to news', 'leyka-boost'),
            'checked'                  => 0,
            'tag'                      => 'newsletter',
            'recurring_agree_enabled'  => 0,
            'recurring_agree_required' => 1,
            'recurring_agree_label'    => __('I agree to automatic monthly charges', 'leyka-boost'),
            'recurring_agree_hint'     => __('You can cancel the recurring donation at any time', 'leyka-boost'),
            'recurring_agree_hint_url' => '',
            'recurring_agree_tag'      => 'recurring-agree',
        ];
    }

    public static function log($message) {
        LeykaBoost_Logger::debug('toolkit', $message);
    }

    public static function settings_data() {
        if (self::$settings_cache !== null) {
            return self::$settings_cache;
        }

        $settings = get_option(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        self::$settings_cache = array_merge(self::defaults(), $settings);
        return self::$settings_cache;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'render_dependency_notice']);

        if (!self::is_leyka_compatible()) {
            return;
        }

        // Render a temporary block near submit, then move its real checkbox nodes
        // into the native agreements block on DOM ready.
        $templates = apply_filters('leyka_toolkit_supported_templates', ['need-help', 'star', 'revo']);
        $s = self::settings_data();
        foreach ($templates as $tpl) {
            add_filter(
                'leyka_' . sanitize_key($tpl) . '_template_final_submit',
                [$this, 'render_subscribe_block'],
                5
            );

            if (!empty($s['recurring_agree_enabled'])) {
                add_filter(
                    'leyka_' . sanitize_key($tpl) . '_template_final_submit',
                    [$this, 'render_recurring_agree_block'],
                    6
                );
            }
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);

        // Save subscribe checkbox value and defer tag assignment.
        add_action('leyka_new_donation_added', [$this, 'handle_subscribe'], 20, 1);
        add_action('leyka_new_donation_added', [$this, 'handle_recurring_agree'], 20, 1);

        // Assign donor tag when donation becomes funded (callback from payment gateway).
        add_action('transition_post_status', [$this, 'on_donation_funded'], 9999, 3);
    }

    public function register_menu() {
        add_submenu_page(
            'leyka-boost',
            __('Toolkit', 'leyka-boost'),
            __('Toolkit', 'leyka-boost'),
            'manage_options',
            'leyka-toolkit',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting(
            'leyka_toolkit_group',
            self::OPTION_KEY,
            [$this, 'sanitize_settings']
        );
    }

    public function render_dependency_notice() {
        if (!current_user_can('activate_plugins') || self::is_leyka_compatible()) {
            return;
        }

        if (!defined('LEYKA_VERSION')) {
            $message = __('Leyka Toolkit is inactive because the Leyka plugin is not active.', 'leyka-boost');
        } else {
            $message = sprintf(
                /* translators: 1: minimum required Leyka version, 2: current Leyka version */
                __('Leyka Toolkit requires Leyka %1$s or higher. Current Leyka version: %2$s.', 'leyka-boost'),
                LEYKA_TOOLKIT_MIN_LEYKA_VERSION,
                LEYKA_VERSION
            );
        }

        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    public function sanitize_settings($input) {
        $defaults = self::defaults();

        if (!is_array($input)) {
            return $defaults;
        }

        $current = get_option(self::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        $output = [];
        $output['enabled']                  = empty($input['enabled']) ? 0 : 1;
        $output['checked']                  = empty($input['checked']) ? 0 : 1;
        $output['recurring_agree_enabled']  = empty($input['recurring_agree_enabled']) ? 0 : 1;
        $output['recurring_agree_required'] = empty($input['recurring_agree_required']) ? 0 : 1;

        $label = isset($input['label']) ? sanitize_text_field(wp_unslash($input['label'])) : $defaults['label'];
        $output['label'] = $label !== '' ? $label : $defaults['label'];

        $tag = isset($input['tag']) ? sanitize_key(wp_unslash($input['tag'])) : $defaults['tag'];
        $output['tag'] = $tag !== '' ? $tag : $defaults['tag'];

        $recurring_tag = isset($input['recurring_agree_tag']) ? sanitize_key(wp_unslash($input['recurring_agree_tag'])) : $defaults['recurring_agree_tag'];
        $output['recurring_agree_tag'] = $recurring_tag !== '' ? $recurring_tag : $defaults['recurring_agree_tag'];

        $recurring_label = isset($input['recurring_agree_label']) ? sanitize_text_field(wp_unslash($input['recurring_agree_label'])) : $defaults['recurring_agree_label'];
        $output['recurring_agree_label'] = $recurring_label !== '' ? $recurring_label : $defaults['recurring_agree_label'];

        $output['recurring_agree_hint'] = isset($input['recurring_agree_hint'])
            ? sanitize_text_field(wp_unslash($input['recurring_agree_hint']))
            : '';

        $output['recurring_agree_hint_url'] = isset($input['recurring_agree_hint_url'])
            ? esc_url_raw(wp_unslash($input['recurring_agree_hint_url']))
            : '';

        self::$settings_cache = null;
        return $output;
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $s = self::settings_data();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Leyka Toolkit', 'leyka-boost'); ?></h1>
            <p><?php echo esc_html(sprintf(
                /* translators: %s: Toolkit module version */
                __('Version %s', 'leyka-boost'),
                LEYKA_TOOLKIT_VERSION
            )); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('leyka_toolkit_group'); ?>

                <h2><?php esc_html_e('Newsletter subscription', 'leyka-boost'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Subscription checkbox', 'leyka-boost'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked(!empty($s['enabled'])); ?>>
                                    <?php esc_html_e('Active', 'leyka-boost'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Checkbox text', 'leyka-boost'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[label]" value="<?php echo esc_attr($s['label']); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Checked by default', 'leyka-boost'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[checked]" value="1" <?php checked(!empty($s['checked'])); ?>>
                                    <?php esc_html_e('Yes', 'leyka-boost'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Donor tag', 'leyka-boost'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[tag]" value="<?php echo esc_attr($s['tag']); ?>">
                                <p class="description"><?php esc_html_e('Added to the donor after a successful donation with the checkbox selected.', 'leyka-boost'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e('Monthly charge consent', 'leyka-boost'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Consent checkbox', 'leyka-boost'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recurring_agree_enabled]" value="1" <?php checked(!empty($s['recurring_agree_enabled'])); ?>>
                                    <?php esc_html_e('Active', 'leyka-boost'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Required checkbox', 'leyka-boost'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recurring_agree_required]" value="1" <?php checked(!empty($s['recurring_agree_required'])); ?>>
                                    <?php esc_html_e('Yes', 'leyka-boost'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('If the checkbox is required, the donor cannot submit a recurring donation form without consent.', 'leyka-boost'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Checkbox text', 'leyka-boost'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recurring_agree_label]" value="<?php echo esc_attr($s['recurring_agree_label']); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Hint text', 'leyka-boost'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recurring_agree_hint]" value="<?php echo esc_attr($s['recurring_agree_hint']); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Cancellation link', 'leyka-boost'); ?></th>
                            <td>
                                <input type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recurring_agree_hint_url]" value="<?php echo esc_attr($s['recurring_agree_hint_url']); ?>">
                                <p class="description"><?php esc_html_e('Leave the field empty to hide the link.', 'leyka-boost'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Donor tag', 'leyka-boost'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recurring_agree_tag]" value="<?php echo esc_attr($s['recurring_agree_tag']); ?>">
                                <p class="description"><?php esc_html_e('Added to the donor after a successful donation with recurring charge consent.', 'leyka-boost'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save settings', 'leyka-boost')); ?>
            </form>
        </div>
        <?php
    }

    public function render_subscribe_block($html) {
        $s = self::settings_data();

        if (empty($s['enabled'])) {
            return $html;
        }

        self::$render_count++;
        $field_id = 'leyka-boost-subscribe-' . self::$render_count;
        $checked = !empty($s['checked']) ? ' checked="checked"' : '';

        $block = '
	<div class="donor__oferta studioavp-leyka-addon-subscribe" aria-hidden="true">
	    <span class="studioavp-leyka-addon-subscribe-inner">
	        ' . wp_nonce_field('leyka_toolkit_donation_action', 'leyka_toolkit_nonce', true, false) . '
	        <input type="checkbox" name="leyka_donor_subscribed" id="' . esc_attr($field_id) . '" value="1"' . $checked . '>
        <label for="' . esc_attr($field_id) . '" class="studioavp-leyka-addon-subscribe-label">
            <svg class="svg-icon icon-checkbox-check"><use xlink:href="#icon-checkbox-check"></use></svg>
            ' . esc_html($s['label']) . '
        </label>
    </span>
</div>';

        return $block . $html;
    }

    public function render_recurring_agree_block($html) {
        $s = self::settings_data();

        if (empty($s['recurring_agree_enabled'])) {
            return $html;
        }

        self::$render_count++;
        $field_id = 'leyka-boost-recurring-' . self::$render_count;
        $required = !empty($s['recurring_agree_required']) ? '1' : '0';

        $hint = '';
        if (!empty($s['recurring_agree_hint']) || !empty($s['recurring_agree_hint_url'])) {
            $hint = '
<div class="studioavp-lt-recurring-hint" aria-hidden="true">
    ' . esc_html($s['recurring_agree_hint']);

            if (!empty($s['recurring_agree_hint_url'])) {
                $hint .= ' <a href="' . esc_url($s['recurring_agree_hint_url']) . '">' . esc_html__('learn more', 'leyka-boost') . '</a>';
            }

            $hint .= '
</div>';
        }

        $block = '
	<div class="donor__oferta studioavp-lt-recurring-agree" aria-hidden="true" data-required="' . esc_attr($required) . '">
	    <span class="studioavp-lt-recurring-agree-inner">
	        ' . wp_nonce_field('leyka_toolkit_donation_action', 'leyka_toolkit_nonce', true, false) . '
	        <input type="checkbox" name="leyka_recurring_agreed" id="' . esc_attr($field_id) . '" value="1" class="leyka_agree">
        <label for="' . esc_attr($field_id) . '" class="studioavp-lt-recurring-agree-label">
            <svg class="svg-icon icon-checkbox-check"><use xlink:href="#icon-checkbox-check"></use></svg>
            ' . esc_html($s['recurring_agree_label']) . '
        </label>
    </span>
</div>';

        return $block . $html . $hint;
    }

    public function enqueue_front_assets() {
        if (is_admin()) {
            return;
        }

        $s = self::settings_data();
        if (empty($s['enabled']) && empty($s['recurring_agree_enabled'])) {
            return;
        }

        $deps = wp_script_is('leyka-public', 'registered') ? ['leyka-public'] : [];
        wp_register_script('leyka-boost-front', '', $deps, LEYKA_TOOLKIT_VERSION, true);
        wp_enqueue_script('leyka-boost-front');

        $js_parts = [];

        if (!empty($s['enabled'])) {
            $js_parts[] = "
    var blocks = document.querySelectorAll('.studioavp-leyka-addon-subscribe');
    blocks.forEach(function (block) {
        var form = block.closest('form');
        if (!form) return;

        var agreementSpan = form.querySelector('[data-leyka-boost-target=\"subscribe\"]')
            || form.querySelector('.section__fields.agreements .donor__oferta span')
            || form.querySelector('.donor__oferta:not(.studioavp-leyka-addon-subscribe):not(.studioavp-lt-recurring-agree) span');
        var inner = block.querySelector('.studioavp-leyka-addon-subscribe-inner');

        if (!agreementSpan || !inner) {
            block.style.setProperty('display', 'block', 'important');
            block.setAttribute('aria-hidden', 'false');
            return;
        }

        while (inner.firstChild) {
            agreementSpan.appendChild(inner.firstChild);
        }

        block.parentNode.removeChild(block);
    });
";
        }

        if (!empty($s['recurring_agree_enabled'])) {
            $js_parts[] = "
    var recurringBlocks = document.querySelectorAll('.studioavp-lt-recurring-agree');
    recurringBlocks.forEach(function (block) {
        var form = block.closest('form');
        if (!form) return;

        var holder = block;
        var holderDisplay = 'block';
        var targetSpan = form.querySelector('[data-leyka-boost-target=\"recurring\"]')
            || form.querySelector('.section__fields.agreements .donor__oferta span')
            || form.querySelector('.donor__oferta:not(.studioavp-lt-recurring-agree):not(.studioavp-leyka-addon-subscribe) span');
        var inner = block.querySelector('.studioavp-lt-recurring-agree-inner');

        if (targetSpan && inner) {
            var subscribeInput = targetSpan.querySelector('[name=\"leyka_donor_subscribed\"]');
            inner.style.display = 'contents';
            if (subscribeInput) {
                targetSpan.insertBefore(inner, subscribeInput);
            } else {
                targetSpan.appendChild(inner);
            }
            block.parentNode.removeChild(block);
            holder = inner;
            holderDisplay = 'contents';
        }

        var isRequired = block.getAttribute('data-required') === '1';
        var submitBtn = form.querySelector('input[type=\"submit\"], button[type=\"submit\"]');

        function showRecurringError() {
            if (holder.querySelector('.studioavp-lt-recurring-error')) {
                if (typeof holder.scrollIntoView === 'function') {
                    holder.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            var err = document.createElement('div');
            err.className = 'studioavp-lt-recurring-error';
            err.setAttribute('role', 'alert');
            err.style.cssText = 'color:#e74c3c;font-size:13px;margin-top:4px;';
            err.textContent = recurringErrorText;
            holder.appendChild(err);

            if (typeof holder.scrollIntoView === 'function') {
                holder.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        function removeRecurringError() {
            var err = holder.querySelector('.studioavp-lt-recurring-error');
            if (err) {
                err.remove();
            }
        }

        function shouldBlockRecurringSubmit() {
            if (!isRequired) return false;

            var agreeInput = form.querySelector('[name=\"leyka_recurring_agreed\"]');
            if (!agreeInput) return false;

            var currentDisplay = holder.style.display;
            var isVisible = currentDisplay !== 'none' && currentDisplay !== '';
            if (currentDisplay === '') {
                isVisible = getComputedStyle(holder).display !== 'none';
            }

            isVisible = isVisible && getComputedStyle(agreeInput).display !== 'none';
            return isVisible && !agreeInput.checked;
        }

        function lockSubmitButton() {
            if (submitBtn) {
                submitBtn.setAttribute('disabled', 'disabled');
            }
        }

        function triggerLeykaValidation() {
            var emailInput = form.querySelector('[name=\"leyka_donor_email\"]');
            if (!emailInput) return;

            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
            emailInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function syncRecurringAgree(checked) {
            var agreeInput = form.querySelector('[name=\"leyka_recurring_agreed\"]');
            var hintBlock = form.querySelector('.studioavp-lt-recurring-hint');
            var agreeHolder = holder;

            if (!agreeInput || !agreeHolder) return;

            if (checked) {
                agreeHolder.style.setProperty('display', holderDisplay, 'important');
                agreeHolder.setAttribute('aria-hidden', 'false');
                if (hintBlock) {
                    hintBlock.style.setProperty('display', 'block', 'important');
                    hintBlock.setAttribute('aria-hidden', 'false');
                }
                if (shouldBlockRecurringSubmit()) {
                    lockSubmitButton();
                }
            } else {
                agreeHolder.style.setProperty('display', 'none', 'important');
                agreeHolder.setAttribute('aria-hidden', 'true');
                agreeInput.checked = false;
                removeRecurringError();
                if (hintBlock) {
                    hintBlock.style.setProperty('display', 'none', 'important');
                    hintBlock.setAttribute('aria-hidden', 'true');
                }
                triggerLeykaValidation();
            }
        }

        if (isRequired && submitBtn && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.attributeName !== 'disabled') return;

                    var isNowEnabled = !submitBtn.hasAttribute('disabled');
                    if (isNowEnabled && shouldBlockRecurringSubmit()) {
                        lockSubmitButton();
                    }
                });
            });
            observer.observe(submitBtn, { attributes: true, attributeFilter: ['disabled'] });
        }

        if (isRequired && submitBtn) {
            submitBtn.addEventListener('click', function (event) {
                if (shouldBlockRecurringSubmit()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showRecurringError();
                }
            }, true);
        }

        if (isRequired) {
            form.addEventListener('submit', function (event) {
                if (shouldBlockRecurringSubmit()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showRecurringError();
                }
            }, true);

            var agreeInputRef = form.querySelector('[name=\"leyka_recurring_agreed\"]');
            if (agreeInputRef) {
                agreeInputRef.addEventListener('change', function () {
                    removeRecurringError();
                    if (this.checked) {
                        triggerLeykaValidation();
                    } else if (shouldBlockRecurringSubmit()) {
                        lockSubmitButton();
                    }
                });
            }

            if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
                var nativeSubmit = HTMLFormElement.prototype.submit;
                form.submit = function () {
                    if (shouldBlockRecurringSubmit()) {
                        showRecurringError();
                        return false;
                    }

                    return nativeSubmit.call(form);
                };
            }
        }

        var recurringCb = form.querySelector('input.leyka-recurring');
        if (recurringCb) {
            syncRecurringAgree(recurringCb.checked);
            recurringCb.addEventListener('change', function () {
                syncRecurringAgree(this.checked);
            });
            return;
        }

        var recurringInput = form.querySelector('input[name=\"leyka_recurring\"]');
        var periodicityLinks = form.querySelectorAll('[data-periodicity]');

        if (!recurringInput || !periodicityLinks.length) return;

        syncRecurringAgree(recurringInput.value === '1');

        periodicityLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                setTimeout(function () {
                    syncRecurringAgree(recurringInput.value === '1');
                }, 0);
            });
        });
    });
";
        }

        $js = "document.addEventListener('DOMContentLoaded', function () {\n";
        $js .= 'var recurringErrorText = ' . wp_json_encode(__('Consent is required.', 'leyka-boost')) . ";\n";
        $js .= implode("\n\n", $js_parts) . "\n});";
        wp_add_inline_script('leyka-boost-front', $js);

        wp_register_style('leyka-boost-front', false, [], LEYKA_TOOLKIT_VERSION);
        wp_enqueue_style('leyka-boost-front');

        $css_parts = [];

        if (!empty($s['enabled'])) {
            $css_parts[] = '.studioavp-leyka-addon-subscribe{' . "\n"
                . '    display:none !important;' . "\n"
                . '}' . "\n"
                . '.studioavp-leyka-addon-subscribe-label,' . "\n"
                . '.leyka-tpl-star-form .section .section__fields .donor__oferta .studioavp-leyka-addon-subscribe-label,' . "\n"
                . '.leyka-screen-form .section .section__fields .donor__oferta .studioavp-leyka-addon-subscribe-label{' . "\n"
                . '    margin-top: 14px;' . "\n"
                . '    margin-bottom: 0;' . "\n"
                . '}';
        }

        if (!empty($s['recurring_agree_enabled'])) {
            $css_parts[] = '.studioavp-lt-recurring-agree{' . "\n"
                . '    display: none !important;' . "\n"
                . '}' . "\n"
                . '.studioavp-lt-recurring-agree-label,' . "\n"
                . '.leyka-tpl-star-form .section .section__fields .donor__oferta .studioavp-lt-recurring-agree-label,' . "\n"
                . '.leyka-screen-form .section .section__fields .donor__oferta .studioavp-lt-recurring-agree-label{' . "\n"
                . '    margin-top: 14px;' . "\n"
                . '    margin-bottom: 0;' . "\n"
                . '}' . "\n"
                . '.studioavp-lt-recurring-hint{' . "\n"
                . '    display: none !important;' . "\n"
                . '    text-align: center;' . "\n"
                . '    font-size: 13px;' . "\n"
                . '    color: var(--leyka-need-help-color-text-light, #666666);' . "\n"
                . '    margin-top: 12px;' . "\n"
                . '    padding: 0;' . "\n"
                . '}';
        }

        $css = implode("\n", $css_parts);
        wp_add_inline_style('leyka-boost-front', $css);
    }

    public function handle_recurring_agree($donation_id) {
        if (!isset($_POST['leyka_recurring_agreed'])) {
            return;
        }

        if (!isset($_POST['leyka_toolkit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leyka_toolkit_nonce'])), 'leyka_toolkit_donation_action')) {
            return;
        }

        $s = self::settings_data();
        if (empty($s['recurring_agree_enabled'])) {
            return;
        }

        update_post_meta((int) $donation_id, 'leyka_recurring_agreed', 1);
        self::log('handle_recurring_agree: meta saved, donation_id=' . $donation_id);

        $donor_email = '';
        if (function_exists('leyka_get_donation')) {
            $donation = leyka_get_donation((int) $donation_id);
            if ($donation) {
                $donor_email = !empty($donation->donor_email) ? $donation->donor_email : '';
                self::log('handle_recurring_agree: donation email=' . $donor_email);
            }
        }

        if (!$donor_email) {
            $donor_email = get_post_meta((int) $donation_id, 'leyka_donor_email', true);
            self::log('handle_recurring_agree: email from meta=' . $donor_email);
        }

        if (!$donor_email) {
            self::log('handle_recurring_agree: exit — no donor email');
            return;
        }

        $donor_email = sanitize_email($donor_email);
        $tag = !empty($s['recurring_agree_tag']) ? $s['recurring_agree_tag'] : 'recurring-agree';

        add_action('shutdown', function () use ($donor_email, $tag) {
            self::assign_donor_tag($donor_email, $tag);
        });

        self::log('handle_recurring_agree: deferred tag, email=' . $donor_email . ', tag=' . $tag);
    }

    public function handle_subscribe($donation_id) {
        if (!isset($_POST['leyka_donor_subscribed'])) {
            return;
        }

        if (!isset($_POST['leyka_toolkit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leyka_toolkit_nonce'])), 'leyka_toolkit_donation_action')) {
            return;
        }

        self::log('handle_subscribe: start, donation_id=' . $donation_id);

        $s = self::settings_data();
        if (empty($s['enabled'])) {
            self::log('handle_subscribe: exit — plugin disabled');
            return;
        }

        // 1. Save subscription flag (Leyka's native meta key).
        update_post_meta((int) $donation_id, 'leyka_donor_subscribed', 1);
        self::log('handle_subscribe: meta saved');

        // 2. Set magic property on donation object (if available).
        $donor_email = '';
        if (function_exists('leyka_get_donation')) {
            $donation = leyka_get_donation((int) $donation_id);
            if ($donation) {
                $donor_email = !empty($donation->donor_email) ? $donation->donor_email : '';
                self::log('handle_subscribe: donation found, email=' . $donor_email);
            }
        }

        // Fallback: get email from meta.
        if (!$donor_email) {
            $donor_email = get_post_meta((int) $donation_id, 'leyka_donor_email', true);
            self::log('handle_subscribe: email from meta=' . $donor_email);
        }

        if (!$donor_email) {
            self::log('handle_subscribe: exit — no donor email');
            return;
        }

        // 3. Defer tag assignment to shutdown — Leyka creates donor later.
        $donor_email = sanitize_email($donor_email);
        $tag = !empty($s['tag']) ? $s['tag'] : 'newsletter';

        add_action('shutdown', function () use ($donor_email, $tag) {
            self::assign_donor_tag($donor_email, $tag);
        });

        self::log('handle_subscribe: deferred to shutdown, email=' . $donor_email . ', tag=' . $tag);
    }

    public function on_donation_funded($new_status, $old_status, $post) {
        if (!$post || $post->post_type !== 'leyka_donation') {
            return;
        }

        if ($new_status !== 'funded') {
            return;
        }

        $donation_id = $post->ID;
        $s = self::settings_data();
        $subscribed = get_post_meta($donation_id, 'leyka_donor_subscribed', true);
        $recurring_agreed = get_post_meta($donation_id, 'leyka_recurring_agreed', true);
        $should_assign_subscribe = !empty($subscribed) && !empty($s['enabled']);
        $should_assign_recurring = !empty($recurring_agreed) && !empty($s['recurring_agree_enabled']);

        if (!$should_assign_subscribe && !$should_assign_recurring) {
            return;
        }

        $donor_email = get_post_meta($donation_id, 'leyka_donor_email', true);
        if (!$donor_email) {
            self::log('on_donation_funded: exit — no email');
            return;
        }

        if ($should_assign_subscribe) {
            self::log('on_donation_funded: donation #' . $donation_id . ' funded, subscribed=1');

            $tag = !empty($s['tag']) ? $s['tag'] : 'newsletter';

            // Defer to shutdown — Leyka may create WP user later in this request.
            add_action('shutdown', function () use ($donor_email, $tag) {
                self::assign_donor_tag($donor_email, $tag);
            });

            self::log('on_donation_funded: subscribed tag deferred, email=' . $donor_email);
        }

        if ($should_assign_recurring) {
            self::log('on_donation_funded: donation #' . $donation_id . ' funded, recurring_agreed=1');

            $tag = !empty($s['recurring_agree_tag']) ? $s['recurring_agree_tag'] : 'recurring-agree';

            add_action('shutdown', function () use ($donor_email, $tag) {
                self::assign_donor_tag($donor_email, $tag);
            });

            self::log('on_donation_funded: recurring tag deferred, email=' . $donor_email);
        }
    }

    public static function assign_donor_tag($donor_email, $tag) {
        $donor_email = sanitize_email($donor_email);
        $tag = sanitize_key($tag);
        self::log('assign_donor_tag: start, email=' . $donor_email . ', tag=' . $tag);

        if (!$donor_email || !$tag) {
            self::log('assign_donor_tag: exit — invalid email or tag');
            return;
        }

        $donor_id = 0;
        // Flush user cache to avoid stale "not found" from earlier in this request.
        wp_cache_delete($donor_email, 'useremail');

        // Strategy 1: WP user by email (Leyka with personal accounts).
        $wp_user = get_user_by('email', $donor_email);
        if ($wp_user) {
            $donor_id = (int) $wp_user->ID;
            self::log('assign_donor_tag: found WP user #' . $donor_id);
        }

        if (!$donor_id) {
            self::log('assign_donor_tag: exit — donor not found');
            return;
        }
        self::log('assign_donor_tag: donor found, id=' . $donor_id);

        // Check taxonomy.
        $taxonomy = 'donors_tag';

        if (!taxonomy_exists($taxonomy)) {
            self::log('assign_donor_tag: exit — taxonomy does not exist');
            return;
        }

        // Create term if needed.
        if (!term_exists($tag, $taxonomy)) {
            self::log('assign_donor_tag: creating term');
            $term = wp_insert_term($tag, $taxonomy);
            if (is_wp_error($term)) {
                self::log('assign_donor_tag: wp_insert_term error: ' . $term->get_error_message());
                return;
            }
        }

        // Assign tag to donor.
        $existing = wp_get_object_terms($donor_id, $taxonomy, ['fields' => 'names']);

        if (is_wp_error($existing)) {
            self::log('assign_donor_tag: wp_get_object_terms error: ' . $existing->get_error_message());
            return;
        }

        if (!in_array($tag, (array) $existing, true)) {
            $result = wp_set_object_terms($donor_id, [$tag], $taxonomy, true);
            if (is_wp_error($result)) {
                self::log('assign_donor_tag: wp_set_object_terms error: ' . $result->get_error_message());
            } else {
                self::log('assign_donor_tag: tag assigned, term_ids=' . implode(',', (array) $result));
            }
        } else {
            self::log('assign_donor_tag: tag already assigned');
        }
    }
}
