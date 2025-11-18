<?php
/**
 * Plugin Name: RNBA Email Sender
 * Plugin URI: https://rnba.com.ua
 * Description: Send emails to WooCommerce customers who purchased specific products
 * Version: 1.0.0
 * Author: RNBA
 * Author URI: https://rnba.com.ua
 * Text Domain: rnba-forum-email-NOTIFIER
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>RNBA Email Sender</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

class RNBA_Email_Sender {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_rnba_send_emails', array($this, 'ajax_send_emails'));
        add_action('wp_ajax_rnba_get_customers', array($this, 'ajax_get_customers'));
        add_action('wp_ajax_rnba_send_test_email', array($this, 'ajax_send_test_email'));

        // Register email templates
        add_action('init', array($this, 'register_templates'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'RNBA Email Sender',
            'RNBA Розсилка',
            'manage_woocommerce',
            'rnba-forum-email-NOTIFIER',
            array($this, 'render_admin_page'),
            'dashicons-email-alt',
            56
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_rnba-forum-email-NOTIFIER' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'rnba-forum-email-NOTIFIER-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'rnba-forum-email-NOTIFIER-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('rnba-forum-email-NOTIFIER-admin', 'rnbaEmailSender', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rnba_email_sender_nonce'),
        ));
    }

    public function register_templates() {
        // Templates are stored in the templates folder
        $this->templates = $this->get_available_templates();
    }

    public function get_available_templates() {
        $templates_dir = plugin_dir_path(__FILE__) . 'templates/';
        $templates = array();

        if (is_dir($templates_dir)) {
            $files = glob($templates_dir . '*.html');
            foreach ($files as $file) {
                $filename = basename($file, '.html');
                $templates[$filename] = array(
                    'name' => $this->get_template_name($filename),
                    'path' => $file,
                );
            }
        }

        return $templates;
    }

    private function get_template_name($filename) {
        // Convert filename to readable name
        $name = str_replace(array('-', '_'), ' ', $filename);
        return ucwords($name);
    }

    public function render_admin_page() {
        $templates = $this->get_available_templates();
        ?>
        <div class="wrap rnba-forum-email-NOTIFIER">
            <h1>RNBA Email Sender</h1>

            <div class="rnba-container">
                <!-- Left Column: Settings -->
                <div class="rnba-settings">
                    <div class="rnba-card">
                        <h2>Налаштування розсилки</h2>

                        <div class="rnba-field">
                            <label for="product_ids">ID товарів (через кому):</label>
                            <input type="text" id="product_ids" name="product_ids" placeholder="123, 456, 789" class="regular-text">
                            <p class="description">Введіть ID товарів WooCommerce, покупцям яких потрібно надіслати лист</p>
                        </div>

                        <div class="rnba-field">
                            <label for="email_template">Шаблон листа:</label>
                            <select id="email_template" name="email_template">
                                <option value="">-- Виберіть шаблон --</option>
                                <?php foreach ($templates as $key => $template) : ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="rnba-field">
                            <label for="email_subject">Тема листа:</label>
                            <input type="text" id="email_subject" name="email_subject" placeholder="Тема вашого листа" class="regular-text">
                        </div>

                        <div class="rnba-field">
                            <button type="button" id="get_customers" class="button button-secondary">
                                Знайти клієнтів
                            </button>
                        </div>
                    </div>

                    <!-- Test Email -->
                    <div class="rnba-card">
                        <h2>Тестова відправка</h2>

                        <div class="rnba-field">
                            <label for="test_email">Email для тесту:</label>
                            <input type="email" id="test_email" name="test_email" placeholder="test@example.com" class="regular-text">
                        </div>

                        <div class="rnba-field">
                            <button type="button" id="send_test" class="button button-secondary">
                                Надіслати тест
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Results & Actions -->
                <div class="rnba-results">
                    <div class="rnba-card">
                        <h2>Знайдені клієнти</h2>

                        <div id="customers_count" class="rnba-count">
                            <span class="count">0</span> клієнтів знайдено
                        </div>

                        <div id="customers_list" class="rnba-customers-list">
                            <p class="no-results">Спочатку введіть ID товарів та натисніть "Знайти клієнтів"</p>
                        </div>

                        <div class="rnba-field rnba-actions">
                            <button type="button" id="send_emails" class="button button-primary button-large" disabled>
                                Надіслати листи
                            </button>
                        </div>
                    </div>

                    <!-- Log -->
                    <div class="rnba-card">
                        <h2>Лог відправки</h2>
                        <div id="send_log" class="rnba-log">
                            <p class="log-empty">Лог порожній</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_get_customers() {
        check_ajax_referer('rnba_email_sender_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Недостатньо прав');
        }

        $product_ids = isset($_POST['product_ids']) ? sanitize_text_field($_POST['product_ids']) : '';

        if (empty($product_ids)) {
            wp_send_json_error('Введіть ID товарів');
        }

        // Parse product IDs
        $ids = array_map('trim', explode(',', $product_ids));
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            wp_send_json_error('Невірний формат ID товарів');
        }

        $customers = $this->get_customers_by_products($ids);

        wp_send_json_success(array(
            'customers' => $customers,
            'count' => count($customers),
        ));
    }

    public function get_customers_by_products($product_ids) {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        // Get all completed orders containing these products
        $query = $wpdb->prepare(
            "SELECT DISTINCT
                pm_email.meta_value as email,
                pm_first.meta_value as first_name,
                pm_last.meta_value as last_name,
                o.ID as order_id
            FROM {$wpdb->posts} o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->postmeta} pm_email ON o.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_first ON o.ID = pm_first.post_id AND pm_first.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm_last ON o.ID = pm_last.post_id AND pm_last.meta_key = '_billing_last_name'
            WHERE o.post_type IN ('shop_order', 'shop_order_placehold')
            AND o.post_status = 'wc-completed'
            AND oi.order_item_type = 'line_item'
            AND oim.meta_key = '_product_id'
            AND oim.meta_value IN ($placeholders)
            ORDER BY pm_email.meta_value ASC",
            ...$product_ids
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        // Remove duplicates by email
        $customers = array();
        foreach ($results as $row) {
            $email = strtolower($row['email']);
            if (!isset($customers[$email])) {
                $customers[$email] = array(
                    'email' => $row['email'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'order_id' => $row['order_id'],
                );
            }
        }

        return array_values($customers);
    }

    public function ajax_send_test_email() {
        check_ajax_referer('rnba_email_sender_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Недостатньо прав');
        }

        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';

        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error('Введіть коректний email для тесту');
        }

        if (empty($template)) {
            wp_send_json_error('Виберіть шаблон листа');
        }

        if (empty($subject)) {
            wp_send_json_error('Введіть тему листа');
        }

        $result = $this->send_email($test_email, $subject, $template, array(
            'first_name' => 'Тестовий',
            'last_name' => 'Користувач',
        ));

        if ($result) {
            wp_send_json_success('Тестовий лист надіслано на ' . $test_email);
        } else {
            wp_send_json_error('Помилка відправки. Перевірте налаштування WP Mail SMTP');
        }
    }

    public function ajax_send_emails() {
        check_ajax_referer('rnba_email_sender_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Недостатньо прав');
        }

        $product_ids = isset($_POST['product_ids']) ? sanitize_text_field($_POST['product_ids']) : '';
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';

        if (empty($template)) {
            wp_send_json_error('Виберіть шаблон листа');
        }

        if (empty($subject)) {
            wp_send_json_error('Введіть тему листа');
        }

        // Parse product IDs
        $ids = array_map('trim', explode(',', $product_ids));
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            wp_send_json_error('Невірний формат ID товарів');
        }

        $customers = $this->get_customers_by_products($ids);

        if (empty($customers)) {
            wp_send_json_error('Клієнтів не знайдено');
        }

        $sent = 0;
        $failed = 0;
        $log = array();

        foreach ($customers as $customer) {
            $result = $this->send_email(
                $customer['email'],
                $subject,
                $template,
                $customer
            );

            if ($result) {
                $sent++;
                $log[] = array(
                    'email' => $customer['email'],
                    'status' => 'success',
                    'message' => 'Надіслано',
                );
            } else {
                $failed++;
                $log[] = array(
                    'email' => $customer['email'],
                    'status' => 'error',
                    'message' => 'Помилка відправки',
                );
            }

            // Small delay to prevent overwhelming the mail server
            usleep(100000); // 0.1 second
        }

        wp_send_json_success(array(
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($customers),
            'log' => $log,
        ));
    }

    private function send_email($to, $subject, $template_key, $customer_data) {
        $templates = $this->get_available_templates();

        if (!isset($templates[$template_key])) {
            return false;
        }

        $template_path = $templates[$template_key]['path'];

        if (!file_exists($template_path)) {
            return false;
        }

        $content = file_get_contents($template_path);

        // Replace placeholders
        $placeholders = array(
            '{{first_name}}' => $customer_data['first_name'] ?? '',
            '{{last_name}}' => $customer_data['last_name'] ?? '',
            '{{email}}' => $customer_data['email'] ?? $to,
        );

        $content = str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $content
        );

        // Set content type to HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        // Use wp_mail (works with WP Mail SMTP)
        return wp_mail($to, $subject, $content, $headers);
    }
}

// Initialize plugin
add_action('plugins_loaded', array('RNBA_Email_Sender', 'get_instance'));
