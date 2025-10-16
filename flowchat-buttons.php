<?php
/*
Plugin Name: Talkify - Advanced Social & Messaging Platform
Plugin URI: https://github.com/peculiargadgets/flowchat-message/
Description: 🚀 Chaty Pro Style - 20+ messaging platforms with smart display rules, A/B testing, QR codes, contact forms, advanced analytics, and premium themes. The ultimate customer engagement solution for WordPress 6.8.3 and WooCommerce 10.2.2
Version: 3.0.0
Author: Nabil Amin Hridoy
Author URI: https://nabilaminhridoy.vercel.app
Text Domain: talkify
Domain Path: /languages
License: GPL v3 or later
Requires at least: 6.0
Tested up to: 6.8.3
WC requires at least: 8.0
WC tested up to: 10.2.2
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

define('FLOWCHAT_VERSION', '3.0.0');
define('FLOWCHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOWCHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLOWCHAT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include Chat Windows Class
require_once FLOWCHAT_PLUGIN_DIR . 'includes/class-flowchat-chat-windows.php';

// Check WordPress and WooCommerce compatibility
function talkify_check_compatibility() {
    global $wp_version;
    
    // WordPress version check
    if (version_compare($wp_version, '6.0', '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Talkify:</strong> Requires WordPress 6.0 or higher. You are running WordPress <?php echo esc_html($wp_version); ?>.</p>
            </div>
            <?php
        });
        deactivate_plugins(FLOWCHAT_PLUGIN_BASENAME);
        return;
    }
    
    // PHP version check
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Talkify:</strong> Requires PHP 7.4 or higher. You are running PHP <?php echo esc_html(PHP_VERSION); ?>.</p>
            </div>
            <?php
        });
        deactivate_plugins(FLOWCHAT_PLUGIN_BASENAME);
        return;
    }
    
    // WooCommerce check (optional but recommended)
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        if (version_compare(WC()->version, '8.0', '<')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning">
                    <p><strong>Talkify:</strong> For best WooCommerce compatibility, please update to WooCommerce 8.0 or higher.</p>
                </div>
                <?php
            });
        }
    }
}
add_action('plugins_loaded', 'talkify_check_compatibility');

// HPOS compatibility for WooCommerce
function talkify_declare_hpos_compatibility() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FLOWCHAT_PLUGIN_BASENAME, true);
    }
}
add_action('before_woocommerce_init', 'talkify_declare_hpos_compatibility');

// Plugin activation hook with compatibility checks
register_activation_hook(__FILE__, 'talkify_activate');
function talkify_activate($network_wide = false) {
    // Check multisite activation
    if (is_multisite() && $network_wide) {
        $sites = get_sites(['fields' => 'ids']);
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            talkify_install();
            restore_current_blog();
        }
    } else {
        talkify_install();
    }
    
    // Set default options with WooCommerce integration
    $default_options = [
        'global_settings' => [
            'position' => 'bottom-right',
            'animation' => 'fade-in',
            'button_size' => 'medium',
            'button_shape' => 'round',
            'z_index' => '999999',
            'enable_analytics' => '1',
            'enable_scheduling' => '0',
            'schedule_start' => '00:00',
            'schedule_end' => '23:59',
            'enable_device_targeting' => '0',
            'desktop_visibility' => '1',
            'mobile_visibility' => '1',
            'wc_integration' => '1',
            'wc_product_pages' => '1',
            'wc_cart_page' => '1',
            'wc_checkout_page' => '0',
            'wc_shop_page' => '1',
            'theme' => 'default',
            'custom_css' => '',
            'enable_ab_testing' => '0',
            'ab_test_duration' => '7',
            'enable_qr_codes' => '0',
            'qr_code_size' => '150',
            'enable_contact_form' => '0',
            'contact_form_fields' => 'name,email,message',
            'enable_proactive_triggers' => '0',
            'trigger_scroll_percentage' => '75',
            'trigger_time_on_page' => '30',
            'enable_exit_intent' => '0',
            'enable_multi_language' => '0',
            'default_language' => 'en',
            'enable_rtl' => '0',
            'enable_chat_windows' => '1'
        ],
        'chat_windows' => [
            'enable_chat_windows' => '1',
            'max_windows' => '3',
            'auto_open' => '0',
            'auto_open_delay' => '5000',
            'sound_enabled' => '1',
            'desktop_notification' => '1',
            'session_storage' => '1',
            'message_storage' => '1'
        ],
        'wc_settings' => [
            'enable_product_help' => '0',
            'product_help_platform' => 'whatsapp',
            'product_help_text' => 'Need help with this product?',
            'enable_order_support' => '0',
            'order_support_platform' => 'whatsapp',
            'order_support_text' => 'Questions about your order?',
            'enable_cart_abandonment' => '0',
            'cart_abandonment_platform' => 'whatsapp',
            'cart_abandonment_delay' => '300',
            'cart_abandonment_text' => 'Still have questions about your order?'
        ]
    ];
    
    if (!get_option('talkify_settings')) {
        add_option('talkify_settings', $default_options);
    }
    
    // Schedule analytics cleanup
    if (!wp_next_scheduled('talkify_cleanup_analytics')) {
        wp_schedule_event(time(), 'daily', 'talkify_cleanup_analytics');
    }
    
    // Flush rewrite rules for WordPress 6.8+ compatibility
    flush_rewrite_rules();
}

function talkify_install() {
    // Create custom tables for analytics if needed
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'talkify_analytics';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        platform varchar(50) NOT NULL,
        clicks int(11) NOT NULL DEFAULT 0,
        page_url varchar(500) DEFAULT '',
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY platform (platform),
        KEY date (date)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'talkify_deactivate');
function talkify_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('talkify_cleanup_analytics');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Cleanup old analytics data
add_action('talkify_cleanup_analytics', 'talkify_cleanup_old_analytics');
function talkify_cleanup_old_analytics() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'talkify_analytics';
    $wpdb->query("DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
}

class Talkify_Plugin {

    private $supported_platforms = [
        'whatsapp' => ['name' => 'WhatsApp', 'icon' => 'fab fa-whatsapp', 'default_color' => '#25D366', 'category' => 'messaging'],
        'messenger' => ['name' => 'Facebook Messenger', 'icon' => 'fab fa-facebook-messenger', 'default_color' => '#006AFF', 'category' => 'messaging'],
        'telegram' => ['name' => 'Telegram', 'icon' => 'fab fa-telegram', 'default_color' => '#0088CC', 'category' => 'messaging'],
        'viber' => ['name' => 'Viber', 'icon' => 'fab fa-viber', 'default_color' => '#7360F2', 'category' => 'messaging'],
        'signal' => ['name' => 'Signal', 'icon' => 'fas fa-signal', 'default_color' => '#2592E6', 'category' => 'messaging'],
        'instagram' => ['name' => 'Instagram', 'icon' => 'fab fa-instagram', 'default_color' => '#E4405F', 'category' => 'social'],
        'twitter' => ['name' => 'Twitter/X', 'icon' => 'fab fa-x-twitter', 'default_color' => '#000000', 'category' => 'social'],
        'linkedin' => ['name' => 'LinkedIn', 'icon' => 'fab fa-linkedin', 'default_color' => '#0077B5', 'category' => 'social'],
        'call' => ['name' => 'Phone Call', 'icon' => 'fas fa-phone-alt', 'default_color' => '#25D366', 'category' => 'contact'],
        'email' => ['name' => 'Email', 'icon' => 'fas fa-envelope', 'default_color' => '#EA4335', 'category' => 'contact'],
        'sms' => ['name' => 'SMS', 'icon' => 'fas fa-sms', 'default_color' => '#20C15C', 'category' => 'contact'],
        'skype' => ['name' => 'Skype', 'icon' => 'fab fa-skype', 'default_color' => '#00AFF0', 'category' => 'messaging'],
        'discord' => ['name' => 'Discord', 'icon' => 'fab fa-discord', 'default_color' => '#5865F2', 'category' => 'social'],
        'tiktok' => ['name' => 'TikTok', 'icon' => 'fab fa-tiktok', 'default_color' => '#000000', 'category' => 'social'],
        'youtube' => ['name' => 'YouTube', 'icon' => 'fab fa-youtube', 'default_color' => '#FF0000', 'category' => 'social'],
        'snapchat' => ['name' => 'Snapchat', 'icon' => 'fab fa-snapchat', 'default_color' => '#FFFC00', 'category' => 'social'],
        'pinterest' => ['name' => 'Pinterest', 'icon' => 'fab fa-pinterest', 'default_color' => '#BD081C', 'category' => 'social'],
        'reddit' => ['name' => 'Reddit', 'icon' => 'fab fa-reddit', 'default_color' => '#FF4500', 'category' => 'social'],
        'wechat' => ['name' => 'WeChat', 'icon' => 'fab fa-weixin', 'default_color' => '#7BB32E', 'category' => 'messaging'],
        'line' => ['name' => 'LINE', 'icon' => 'fab fa-line', 'default_color' => '#00C300', 'category' => 'messaging'],
        'custom' => ['name' => 'Custom Channel', 'icon' => 'fas fa-plus', 'default_color' => '#6C757D', 'category' => 'custom']
    ];

    private $widget_themes = [
        'default' => ['name' => 'Default', 'style' => 'modern'],
        'minimal' => ['name' => 'Minimal', 'style' => 'clean'],
        'colorful' => ['name' => 'Colorful', 'style' => 'vibrant'],
        'dark' => ['name' => 'Dark', 'style' => 'dark'],
        'gradient' => ['name' => 'Gradient', 'style' => 'gradient'],
        'neon' => ['name' => 'Neon', 'style' => 'neon'],
        'glass' => ['name' => 'Glass', 'style' => 'glassmorphism'],
        'retro' => ['name' => 'Retro', 'style' => 'retro']
    ];

    private $is_woocommerce_active = false;

    public function __construct() {
        // Check if WooCommerce is active
        $this->is_woocommerce_active = class_exists('WooCommerce');
        
        // Initialize WordPress hooks with 6.8.3 compatibility
        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_block_patterns']);
        add_action('wp_footer', [$this, 'render_frontend_buttons']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('wp_ajax_flowchat_track_click', [$this, 'track_click']);
        add_action('wp_ajax_nopriv_flowchat_track_click', [$this, 'track_click']);
        add_action('wp_head', [$this, 'add_custom_css']);
        add_action('wp_ajax_flowchat_clear_analytics', [$this, 'clear_analytics']);
        
        // WooCommerce specific hooks
        if ($this->is_woocommerce_active) {
            add_action('woocommerce_before_single_product', [$this, 'render_product_help_button']);
            add_action('woocommerce_before_cart', [$this, 'render_cart_help_button']);
            add_action('woocommerce_after_checkout_form', [$this, 'render_checkout_support_button']);
            add_action('woocommerce_thankyou', [$this, 'render_order_support_button']);
            add_action('wp_footer', [$this, 'render_cart_abandonment_trigger']);
            add_filter('woocommerce_get_settings_pages', [$this, 'add_woocommerce_settings']);
            add_action('woocommerce_update_options_settings', [$this, 'save_woocommerce_settings']);
            
            // WooCommerce HPOS compatibility
            add_action('woocommerce_before_order_object_save', [$this, 'track_order_analytics'], 10, 2);
        }
        
        // WordPress 6.8+ block editor integration
        add_action('init', [$this, 'register_block_editor_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor']);
        
        // Performance hooks for WordPress 6.8+
        add_action('wp_head', [$this, 'add_preconnect_links']);
        add_filter('script_loader_tag', [$this, 'add_defer_async_attributes'], 10, 3);
        
        // REST API endpoints for WordPress 6.8+
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Privacy and GDPR compliance for WordPress 6.8+
        add_action('wp_privacy_personal_data_exporters', [$this, 'register_personal_data_exporter']);
        add_action('wp_privacy_personal_data_erasers', [$this, 'register_personal_data_eraser']);
        
        // Advanced Features - Chaty Pro Style
        add_action('wp_ajax_flowchat_contact_form', [$this, 'handle_contact_form']);
        add_action('wp_ajax_nopriv_flowchat_contact_form', [$this, 'handle_contact_form']);
        add_action('wp_ajax_flowchat_generate_qr', [$this, 'generate_qr_code']);
        add_action('wp_ajax_flowchat_ab_test_track', [$this, 'track_ab_test']);
        add_action('wp_ajax_nopriv_flowchat_ab_test_track', [$this, 'track_ab_test']);
        add_action('wp_footer', [$this, 'render_proactive_triggers']);
        add_action('wp_head', [$this, 'add_theme_styles']);
        add_action('wp_head', [$this, 'add_multi_language_support']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_advanced_scripts']);
        
        // Chat Windows Feature
        add_action('wp_ajax_flowchat_send_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_nopriv_flowchat_send_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_flowchat_get_chat_history', [$this, 'get_chat_history']);
        add_action('wp_ajax_nopriv_flowchat_get_chat_history', [$this, 'get_chat_history']);
        
        // Initialize Chat Windows
        if (class_exists('Talkify_Chat_Windows')) {
            new Talkify_Chat_Windows();
        }
        
        // Shortcode support
        add_shortcode('flowchat_buttons', [$this, 'render_shortcode']);
        add_shortcode('flowchat_contact', [$this, 'render_contact_shortcode']);
        add_shortcode('flowchat_qr', [$this, 'render_qr_shortcode']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('talkify', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    // WordPress 6.8+ Block Patterns Registration
    public function register_block_patterns() {
        if (function_exists('register_block_pattern')) {
            register_block_pattern('talkify/social-contact', [
                'title' => __('FlowChat Social Contact Buttons', 'talkify'),
                'description' => __('Display floating social media and messaging buttons', 'talkify'),
                'categories' => ['widgets'],
                'content' => '<!-- wp:html -->[flowchat_buttons]<!-- /wp:html -->',
            ]);
        }
    }

    // Block Editor Assets for WordPress 6.8+
    public function register_block_editor_assets() {
        wp_register_script(
            'flowchat-block-editor',
            FLOWCHAT_PLUGIN_URL . 'assets/block-editor.js',
            ['wp-blocks', 'wp-element', 'wp-components'],
            FLOWCHAT_VERSION,
            true
        );
        
        wp_register_style(
            'flowchat-block-editor',
            FLOWCHAT_PLUGIN_URL . 'assets/block-editor.css',
            ['wp-edit-blocks'],
            FLOWCHAT_VERSION
        );
    }

    public function enqueue_block_editor() {
        wp_enqueue_script('flowchat-block-editor');
        wp_enqueue_style('flowchat-block-editor');
    }

    // Performance optimization for WordPress 6.8+
    public function add_preconnect_links() {
        echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">' . "\n";
    }

    public function add_defer_async_attributes($tag, $handle, $src) {
        $defer_scripts = ['flowchat-frontend-js'];
        $async_scripts = ['flowchat-admin-js'];
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        if (in_array($handle, $async_scripts)) {
            return str_replace('<script ', '<script async ', $tag);
        }
        
        return $tag;
    }

    // REST API endpoints for WordPress 6.8+
    public function register_rest_routes() {
        register_rest_route('flowchat/v1', '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics_rest'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('flowchat/v1', '/settings', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_settings_rest'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function get_analytics_rest($request) {
        $analytics = get_option('flowchat_analytics', []);
        return new WP_REST_Response($analytics, 200);
    }

    public function handle_settings_rest($request) {
        $method = $request->get_method();
        
        if ($method === 'GET') {
            $settings = get_option("talkify_settings")', []);
            return new WP_REST_Response($settings, 200);
        } elseif ($method === 'POST') {
            $settings = $request->get_json_params();
            update_option('flowchat_settings', $settings);
            return new WP_REST_Response(['success' => true], 200);
        }
    }

    // Privacy and GDPR compliance
    public function register_personal_data_exporter($exporters) {
        $exporters[] = [
            'exporter_friendly_name' => __('FlowChat Buttons Analytics', 'talkify'),
            'callback' => [$this, 'export_personal_data'],
        ];
        return $exporters;
    }

    public function register_personal_data_eraser($erasers) {
        $erasers[] = [
            'eraser_friendly_name' => __('FlowChat Buttons Analytics', 'talkify'),
            'callback' => [$this, 'erase_personal_data'],
        ];
        return $erasers;
    }

    public function export_personal_data($email_address, $page = 1) {
        $export_items = [];
        
        // Export analytics data related to this user's email
        global $wpdb;
        $table_name = $wpdb->prefix . 'flowchat_analytics';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_agent LIKE %s",
            '%' . $email_address . '%'
        ));
        
        foreach ($results as $result) {
            $export_items[] = [
                'group_id' => 'flowchat-analytics',
                'group_label' => __('FlowChat Analytics', 'talkify'),
                'item_id' => 'flowchat-' . $result->id,
                'data' => [
                    [
                        'name' => __('Platform', 'talkify'),
                        'value' => $result->platform,
                    ],
                    [
                        'name' => __('Date', 'talkify'),
                        'value' => $result->date,
                    ],
                    [
                        'name' => __('Page URL', 'talkify'),
                        'value' => $result->page_url,
                    ],
                ],
            ];
        }
        
        return [
            'data' => $export_items,
            'done' => true,
        ];
    }

    public function erase_personal_data($email_address, $page = 1) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'flowchat_analytics';
        
        $items_removed = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE user_agent LIKE %s",
            '%' . $email_address . '%'
        ));
        
        return [
            'items_removed' => $items_removed,
            'items_retained' => false,
            'messages' => [],
            'done' => true,
        ];
    }

    // WooCommerce Integration Methods
    
    public function render_product_help_button() {
        $options = get_option("talkify_settings")');
        $wc_settings = $options['wc_settings'] ?? [];
        
        if (!($wc_settings['enable_product_help'] ?? false)) {
            return;
        }
        
        $platform = $wc_settings['product_help_platform'] ?? 'whatsapp';
        $text = $wc_settings['product_help_text'] ?? 'Need help with this product?';
        $product = wc_get_product();
        
        if (!$product) {
            return;
        }
        
        $link = $this->get_platform_link($platform, [
            'product_name' => $product->get_name(),
            'product_url' => get_permalink($product->get_id()),
            'message' => $text
        ]);
        
        ?>
        <div class="flowchat-product-help">
            <a href="<?php echo esc_url($link); ?>" 
               class="flowchat-product-help-btn"
               target="_blank"
               rel="noopener noreferrer">
                <i class="<?php echo esc_attr($this->supported_platforms[$platform]['icon']); ?>"></i>
                <?php echo esc_html($text); ?>
            </a>
        </div>
        <?php
    }
    
    public function render_cart_help_button() {
        $options = get_option("talkify_settings")');
        $wc_settings = $options['wc_settings'] ?? [];
        
        if (!($wc_settings['enable_cart_help'] ?? false)) {
            return;
        }
        
        $platform = $wc_settings['cart_help_platform'] ?? 'whatsapp';
        $text = $wc_settings['cart_help_text'] ?? 'Need help with your cart?';
        
        $link = $this->get_platform_link($platform, [
            'message' => $text,
            'cart_url' => wc_get_cart_url()
        ]);
        
        ?>
        <div class="flowchat-cart-help">
            <a href="<?php echo esc_url($link); ?>" 
               class="flowchat-cart-help-btn"
               target="_blank"
               rel="noopener noreferrer">
                <i class="<?php echo esc_attr($this->supported_platforms[$platform]['icon']); ?>"></i>
                <?php echo esc_html($text); ?>
            </a>
        </div>
        <?php
    }
    
    public function render_checkout_support_button() {
        $options = get_option("talkify_settings")');
        $wc_settings = $options['wc_settings'] ?? [];
        
        if (!($wc_settings['enable_checkout_support'] ?? false)) {
            return;
        }
        
        $platform = $wc_settings['checkout_support_platform'] ?? 'whatsapp';
        $text = $wc_settings['checkout_support_text'] ?? 'Need help during checkout?';
        
        $link = $this->get_platform_link($platform, [
            'message' => $text,
            'checkout_url' => wc_get_checkout_url()
        ]);
        
        ?>
        <div class="flowchat-checkout-support">
            <a href="<?php echo esc_url($link); ?>" 
               class="flowchat-checkout-support-btn"
               target="_blank"
               rel="noopener noreferrer">
                <i class="<?php echo esc_attr($this->supported_platforms[$platform]['icon']); ?>"></i>
                <?php echo esc_html($text); ?>
            </a>
        </div>
        <?php
    }
    
    public function render_order_support_button($order_id) {
        $options = get_option("talkify_settings")');
        $wc_settings = $options['wc_settings'] ?? [];
        
        if (!($wc_settings['enable_order_support'] ?? false)) {
            return;
        }
        
        $platform = $wc_settings['order_support_platform'] ?? 'whatsapp';
        $text = $wc_settings['order_support_text'] ?? 'Questions about your order?';
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $link = $this->get_platform_link($platform, [
            'order_number' => $order->get_order_number(),
            'order_status' => $order->get_status(),
            'message' => $text
        ]);
        
        ?>
        <div class="flowchat-order-support">
            <a href="<?php echo esc_url($link); ?>" 
               class="flowchat-order-support-btn"
               target="_blank"
               rel="noopener noreferrer">
                <i class="<?php echo esc_attr($this->supported_platforms[$platform]['icon']); ?>"></i>
                <?php echo esc_html($text); ?>
            </a>
        </div>
        <?php
    }
    
    public function render_cart_abandonment_trigger() {
        $options = get_option("talkify_settings")');
        $wc_settings = $options['wc_settings'] ?? [];
        
        if (!($wc_settings['enable_cart_abandonment'] ?? false) || !WC()->cart->get_cart_contents_count()) {
            return;
        }
        
        $platform = $wc_settings['cart_abandonment_platform'] ?? 'whatsapp';
        $delay = intval($wc_settings['cart_abandonment_delay'] ?? 300); // 5 minutes default
        $text = $wc_settings['cart_abandonment_text'] ?? 'Still have questions about your order?';
        
        $link = $this->get_platform_link($platform, [
            'message' => $text,
            'cart_url' => wc_get_cart_url()
        ]);
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            setTimeout(function() {
                if (flowchat_cart_abandonment_shown !== true) {
                    $('.talkify-container').addClass('cart-abandonment-active');
                    
                    // Show abandonment notification
                    var notification = $('<div class="flowchat-cart-abandonment-notification">' +
                        '<p><?php echo esc_js($text); ?></p>' +
                        '<a href="<?php echo esc_url($link); ?>" class="button" target="_blank">' +
                        '<i class="<?php echo esc_attr($this->supported_platforms[$platform]['icon']); ?>"></i> ' +
                        '<?php echo esc_html($this->supported_platforms[$platform]['name']); ?>' +
                        '</a>' +
                        '<button class="close-notification">&times;</button>' +
                        '</div>');
                    
                    $('body').append(notification);
                    
                    notification.slideDown();
                    
                    $('.close-notification').on('click', function() {
                        notification.slideUp(function() {
                            notification.remove();
                        });
                        flowchat_cart_abandonment_shown = true;
                        localStorage.setItem('flowchat_cart_abandonment_dismissed', 'true');
                    });
                }
            }, <?php echo $delay * 1000; ?>);
        });
        
        var flowchat_cart_abandonment_shown = localStorage.getItem('flowchat_cart_abandonment_dismissed') !== 'true';
        </script>
        <?php
    }
    
    private function get_platform_link($platform, $data = []) {
        $options = get_option("talkify_settings")');
        $base_link = $options[$platform . '_link'] ?? '#';
        
        if ($platform === 'whatsapp') {
            $phone = preg_replace('/[^0-9]/', '', $base_link);
            $message = sprintf(
                "Hi! I have a question about: %s\n\nProduct: %s\n\n%s",
                $data['message'] ?? '',
                $data['product_name'] ?? '',
                $data['product_url'] ?? wc_get_cart_url()
            );
            return "https://wa.me/{$phone}?text=" . urlencode($message);
        } elseif ($platform === 'messenger') {
            return $base_link . '?text=' . urlencode($data['message'] ?? '');
        } elseif ($platform === 'telegram') {
            return $base_link . '?text=' . urlencode($data['message'] ?? '');
        }
        
        return $base_link;
    }
    
    public function add_woocommerce_settings($settings) {
        $settings[] = include(FLOWCHAT_PLUGIN_DIR . 'includes/wc-settings.php');
        return $settings;
    }
    
    public function save_woocommerce_settings() {
        $wc_settings = $_POST['wc_settings'] ?? [];
        $options = get_option("talkify_settings")', []);
        $options['wc_settings'] = $wc_settings;
        update_option('flowchat_settings', $options);
    }
    
    public function track_order_analytics($order, $data_store) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        
        // Track order-related analytics
        $platform = $this->get_primary_platform();
        if ($platform) {
            $this->track_analytics($platform, 'order_completed', [
                'order_id' => $order->get_id(),
                'order_total' => $order->get_total(),
                'order_status' => $order->get_status()
            ]);
        }
    }
    
    private function get_primary_platform() {
        $options = get_option("talkify_settings")');
        
        foreach ($this->supported_platforms as $platform_id => $platform_info) {
            if ($options[$platform_id . '_enable'] ?? false) {
                return $platform_id;
            }
        }
        
        return false;
    }
    
    public function clear_analytics() {
        check_ajax_referer('flowchat_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'flowchat_analytics';
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        update_option('flowchat_analytics', []);
        
        wp_send_json_success(['message' => 'Analytics data cleared successfully']);
    }
  // Frontend buttons display with WooCommerce integration
    public function render_frontend_buttons() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        
        // Check WooCommerce page visibility
        if ($this->is_woocommerce_active && !$this->should_display_on_wc_page($global_settings)) {
            return;
        }
        
        // Check scheduling
        if ($global_settings['enable_scheduling'] && !$this->is_in_schedule($global_settings)) {
            return;
        }
        
        // Check device targeting
        if ($global_settings['enable_device_targeting'] && !$this->is_visible_for_device($global_settings)) {
            return;
        }
        
        // Check page targeting
        if (!$this->should_display_on_current_page($options)) {
            return;
        }
        
        $enabled_platforms = [];
        foreach ($this->supported_platforms as $platform_id => $platform_info) {
            if ($options[$platform_id . '_enable'] ?? false) {
                $enabled_platforms[$platform_id] = array_merge($platform_info, [
                    'link' => $options[$platform_id . '_link'] ?? '#',
                    'text_label' => $options[$platform_id . '_text_label'] ?? $platform_info['name'],
                    'show_text' => $options[$platform_id . '_text'] ?? true,
                    'color_start' => $options[$platform_id . '_color_start'] ?? $platform_info['default_color'],
                    'color_end' => $options[$platform_id . '_color_end'] ?? $this->darken_color($platform_info['default_color']),
                    'custom_icon' => $options[$platform_id . '_custom_icon'] ?? '',
                    'custom_icon_type' => $options[$platform_id . '_custom_icon_type'] ?? 'font-awesome'
                ]);
            }
        }
        
        if (empty($enabled_platforms)) {
            return;
        }
        
        $position_class = $this->get_position_class($global_settings['position'] ?? 'bottom-right');
        $animation_class = $global_settings['animation'] ?? 'fade-in';
        $size_class = $global_settings['button_size'] ?? 'medium';
        $shape_class = $global_settings['button_shape'] ?? 'round';
        $z_index = $global_settings['z_index'] ?? '999999';
        
        // Add WooCommerce specific classes
        $wc_classes = [];
        if ($this->is_woocommerce_active) {
            if (is_product()) {
                $wc_classes[] = 'wc-product-page';
            } elseif (is_cart()) {
                $wc_classes[] = 'wc-cart-page';
            } elseif (is_checkout()) {
                $wc_classes[] = 'wc-checkout-page';
            } elseif (is_shop() || is_product_category() || is_product_tag()) {
                $wc_classes[] = 'wc-shop-page';
            }
        }
        
        ?>
        <div class="talkify-container <?php echo esc_attr($position_class); ?> <?php echo esc_attr($animation_class); ?> <?php echo esc_attr($size_class); ?> <?php echo esc_attr($shape_class); ?> <?php echo esc_attr(implode(' ', $wc_classes)); ?>" 
             style="z-index: <?php echo esc_attr($z_index); ?>"
             data-enable-analytics="<?php echo esc_attr($global_settings['enable_analytics'] ?? '0'); ?>"
             <?php if ($this->is_woocommerce_active): ?>
             data-wc-active="true"
             <?php endif; ?>>
            <?php foreach ($enabled_platforms as $platform_id => $platform) : ?>
                <div class="flowchat-btn-wrapper" data-platform="<?php echo esc_attr($platform_id); ?>">
                    <?php if ($platform['show_text']) : ?>
                        <span class="flowchat-btn-label"><?php echo esc_html($platform['text_label']); ?></span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($platform['link']); ?>" 
                       class="flowchat-btn <?php echo esc_attr($platform_id); ?>-btn"
                       target="_blank"
                       rel="noopener noreferrer"
                       style="background: linear-gradient(135deg, <?php echo $platform['color_start']; ?>, <?php echo $platform['color_end']; ?>);">
                        <?php if ($platform['custom_icon'] && $platform['custom_icon_type'] === 'upload') : ?>
                            <img src="<?php echo esc_url($platform['custom_icon']); ?>" alt="<?php echo esc_attr($platform['name']); ?>" class="custom-icon">
                        <?php elseif ($platform['custom_icon'] && $platform['custom_icon_type'] === 'font-awesome') : ?>
                            <i class="<?php echo esc_attr($platform['custom_icon']); ?>"></i>
                        <?php else : ?>
                            <i class="<?php echo esc_attr($platform['icon']); ?>"></i>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($global_settings['enable_analytics']) : ?>
            <script>
            jQuery(document).ready(function($) {
                $('.flowchat-btn').on('click', function(e) {
                    var platform = $(this).closest('.flowchat-btn-wrapper').data('platform');
                    var container = $('.talkify-container');
                    var wcActive = container.data('wc-active');
                    
                    // Enhanced tracking for WooCommerce
                    var trackingData = {
                        action: 'flowchat_track_click',
                        platform: platform,
                        page: window.location.href,
                        user_agent: navigator.userAgent
                    };
                    
                    if (wcActive) {
                        trackingData.is_wc_page = 'true';
                        if (typeof wc_single_product_params !== 'undefined') {
                            trackingData.product_id = wc_single_product_params.product_id;
                        }
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            trackingData.cart_page = 'true';
                        }
                    }
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: trackingData
                    });
                });
            });
            </script>
        <?php endif; ?>
        <?php
    }

    private function should_display_on_wc_page($global_settings) {
        if (!$this->is_woocommerce_active) {
            return true;
        }
        
        $wc_integration = $global_settings['wc_integration'] ?? '1';
        if ($wc_integration !== '1') {
            return true; // Show on all pages if WC integration is disabled
        }
        
        // Check specific WooCommerce page settings
        if (is_product() && !($global_settings['wc_product_pages'] ?? '1')) {
            return false;
        }
        
        if (is_cart() && !($global_settings['wc_cart_page'] ?? '1')) {
            return false;
        }
        
        if (is_checkout() && !($global_settings['wc_checkout_page'] ?? '0')) {
            return false;
        }
        
        if ((is_shop() || is_product_category() || is_product_tag()) && !($global_settings['wc_shop_page'] ?? '1')) {
            return false;
        }
        
        return true;
    }
            return;
        }
        
        // Check device targeting
        if ($global_settings['enable_device_targeting'] && !$this->is_visible_for_device($global_settings)) {
            return;
        }
        
        // Check page targeting
        if (!$this->should_display_on_current_page($options)) {
            return;
        }
        
        $enabled_platforms = [];
        foreach ($this->supported_platforms as $platform_id => $platform_info) {
            if ($options[$platform_id . '_enable'] ?? false) {
                $enabled_platforms[$platform_id] = array_merge($platform_info, [
                    'link' => $options[$platform_id . '_link'] ?? '#',
                    'text_label' => $options[$platform_id . '_text_label'] ?? $platform_info['name'],
                    'show_text' => $options[$platform_id . '_text'] ?? true,
                    'color_start' => $options[$platform_id . '_color_start'] ?? $platform_info['default_color'],
                    'color_end' => $options[$platform_id . '_color_end'] ?? $this->darken_color($platform_info['default_color']),
                    'custom_icon' => $options[$platform_id . '_custom_icon'] ?? '',
                    'custom_icon_type' => $options[$platform_id . '_custom_icon_type'] ?? 'font-awesome'
                ]);
            }
        }
        
        if (empty($enabled_platforms)) {
            return;
        }
        
        $position_class = $this->get_position_class($global_settings['position'] ?? 'bottom-right');
        $animation_class = $global_settings['animation'] ?? 'fade-in';
        $size_class = $global_settings['button_size'] ?? 'medium';
        $shape_class = $global_settings['button_shape'] ?? 'round';
        $z_index = $global_settings['z_index'] ?? '999999';
        
        ?>
        <div class="talkify-container <?php echo esc_attr($position_class); ?> <?php echo esc_attr($animation_class); ?> <?php echo esc_attr($size_class); ?> <?php echo esc_attr($shape_class); ?>" 
             style="z-index: <?php echo esc_attr($z_index); ?>"
             data-enable-analytics="<?php echo esc_attr($global_settings['enable_analytics'] ?? '0'); ?>">
            <?php foreach ($enabled_platforms as $platform_id => $platform) : ?>
                <div class="flowchat-btn-wrapper" data-platform="<?php echo esc_attr($platform_id); ?>">
                    <?php if ($platform['show_text']) : ?>
                        <span class="flowchat-btn-label"><?php echo esc_html($platform['text_label']); ?></span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($platform['link']); ?>" 
                       class="flowchat-btn <?php echo esc_attr($platform_id); ?>-btn"
                       target="_blank"
                       rel="noopener noreferrer"
                       style="background: linear-gradient(135deg, <?php echo $platform['color_start']; ?>, <?php echo $platform['color_end']; ?>);">
                        <?php if ($platform['custom_icon'] && $platform['custom_icon_type'] === 'upload') : ?>
                            <img src="<?php echo esc_url($platform['custom_icon']); ?>" alt="<?php echo esc_attr($platform['name']); ?>" class="custom-icon">
                        <?php elseif ($platform['custom_icon'] && $platform['custom_icon_type'] === 'font-awesome') : ?>
                            <i class="<?php echo esc_attr($platform['custom_icon']); ?>"></i>
                        <?php else : ?>
                            <i class="<?php echo esc_attr($platform['icon']); ?>"></i>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($global_settings['enable_analytics']) : ?>
            <script>
            jQuery(document).ready(function($) {
                $('.flowchat-btn').on('click', function(e) {
                    var platform = $(this).closest('.flowchat-btn-wrapper').data('platform');
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'flowchat_track_click',
                            platform: platform,
                            page: window.location.href,
                            user_agent: navigator.userAgent
                        }
                    });
                });
            });
            </script>
        <?php endif; ?>
        <?php
    }

    private function is_in_schedule($settings) {
        $current_time = current_time('H:i');
        $start_time = $settings['schedule_start'] ?? '00:00';
        $end_time = $settings['schedule_end'] ?? '23:59';
        
        return $current_time >= $start_time && $current_time <= $end_time;
    }

    private function is_visible_for_device($settings) {
        $is_mobile = wp_is_mobile();
        
        if ($is_mobile && !($settings['mobile_visibility'] ?? '1')) {
            return false;
        }
        
        if (!$is_mobile && !($settings['desktop_visibility'] ?? '1')) {
            return false;
        }
        
        return true;
    }

    private function should_display_on_current_page($options) {
        $targeting = $options['page_targeting'] ?? [];
        
        if (empty($targeting)) {
            return true;
        }
        
        $display_on = $targeting['display_on'] ?? 'all';
        
        if ($display_on === 'all') {
            return true;
        }
        
        if ($display_on === 'specific') {
            $selected_pages = $targeting['selected_pages'] ?? [];
            $selected_posts = $targeting['selected_posts'] ?? [];
            
            if (is_page() && in_array(get_the_ID(), $selected_pages)) {
                return true;
            }
            
            if (is_single() && in_array(get_the_ID(), $selected_posts)) {
                return true;
            }
            
            return false;
        }
        
        if ($display_on === 'exclude') {
            $excluded_pages = $targeting['excluded_pages'] ?? [];
            $excluded_posts = $targeting['excluded_posts'] ?? [];
            
            if (is_page() && in_array(get_the_ID(), $excluded_pages)) {
                return false;
            }
            
            if (is_single() && in_array(get_the_ID(), $excluded_posts)) {
                return false;
            }
            
            return true;
        }
        
        return true;
    }

    private function get_position_class($position) {
        $positions = [
            'bottom-right' => 'bottom-right',
            'bottom-left' => 'bottom-left',
            'top-right' => 'top-right',
            'top-left' => 'top-left',
            'center-right' => 'center-right',
            'center-left' => 'center-left'
        ];
        
        return $positions[$position] ?? 'bottom-right';
    }

    private function darken_color($color) {
        // Simple color darkening function
        $color = str_replace('#', '', $color);
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        $r = max(0, $r - 30);
        $g = max(0, $g - 30);
        $b = max(0, $b - 30);
        
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    public function track_click() {
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $page = esc_url_raw($_POST['page'] ?? '');
        $user_agent = sanitize_text_field($_POST['user_agent'] ?? '');
        
        $analytics = get_option('flowchat_analytics', []);
        $today = date('Y-m-d');
        
        if (!isset($analytics[$today])) {
            $analytics[$today] = [];
        }
        
        if (!isset($analytics[$today][$platform])) {
            $analytics[$today][$platform] = 0;
        }
        
        $analytics[$today][$platform]++;
        
        update_option('flowchat_analytics', $analytics);
        
        wp_die();
    }

    public function add_custom_css() {
        $options = get_option("talkify_settings")');
        $custom_css = $options['custom_css'] ?? '';
        
        if (!empty($custom_css)) {
            echo '<style id="flowchat-custom-css">' . wp_kses_post($custom_css) . '</style>';
        }
    }

    // Admin menu setup
    public function add_admin_menu() {
        add_menu_page(
            'FlowChat Buttons Pro',
            'FlowChat Pro',
            'manage_options',
            'flowchat-settings',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            80
        );
        
        add_submenu_page(
            'flowchat-settings',
            'Channels',
            'Channels',
            'manage_options',
            'flowchat-settings',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'Display Rules',
            'Display Rules',
            'manage_options',
            'flowchat-display',
            [$this, 'render_display_rules_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'Themes & Styles',
            'Themes & Styles',
            'manage_options',
            'flowchat-themes',
            [$this, 'render_themes_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'Contact Forms',
            'Contact Forms',
            'manage_options',
            'flowchat-contact',
            [$this, 'render_contact_forms_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'QR Codes',
            'QR Codes',
            'manage_options',
            'flowchat-qr',
            [$this, 'render_qr_codes_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'A/B Testing',
            'A/B Testing',
            'manage_options',
            'flowchat-abtesting',
            [$this, 'render_ab_testing_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'Analytics',
            'Analytics',
            'manage_options',
            'flowchat-analytics',
            [$this, 'render_analytics_page']
        );
        
        add_submenu_page(
            'flowchat-settings',
            'Pro Features',
            'Pro Features',
            'manage_options',
            'flowchat-pro',
            [$this, 'render_pro_features_page']
        );
    }

    // Settings registration
    public function register_settings() {
        register_setting('flowchat_settings_group', 'flowchat_settings');
        
        // Global Settings Section
        add_settings_section('flowchat_global_section', 'Global Settings', null, 'flowchat-settings');
        
        $global_fields = [
            'position' => ['label' => 'Button Position', 'type' => 'select', 'options' => [
                'bottom-right' => 'Bottom Right',
                'bottom-left' => 'Bottom Left', 
                'top-right' => 'Top Right',
                'top-left' => 'Top Left',
                'center-right' => 'Center Right',
                'center-left' => 'Center Left'
            ]],
            'animation' => ['label' => 'Animation Effect', 'type' => 'select', 'options' => [
                'fade-in' => 'Fade In',
                'slide-in' => 'Slide In',
                'bounce-in' => 'Bounce In',
                'zoom-in' => 'Zoom In',
                'none' => 'No Animation'
            ]],
            'button_size' => ['label' => 'Button Size', 'type' => 'select', 'options' => [
                'small' => 'Small (40px)',
                'medium' => 'Medium (50px)',
                'large' => 'Large (60px)',
                'extra-large' => 'Extra Large (70px)'
            ]],
            'button_shape' => ['label' => 'Button Shape', 'type' => 'select', 'options' => [
                'round' => 'Round',
                'square' => 'Square',
                'rounded' => 'Rounded Square'
            ]],
            'z_index' => ['label' => 'Z-Index', 'type' => 'text', 'default' => '999999']
        ];
        
        foreach ($global_fields as $id => $field) {
            add_settings_field(
                'global_settings[' . $id . ']',
                $field['label'],
                [$this, 'render_field_by_type'],
                'flowchat-settings',
                'flowchat_global_section',
                ['id' => 'global_settings[' . $id . ']', 'field' => $field]
            );
        }
        
        // Platform Settings Section
        add_settings_section('flowchat_platforms_section', 'Platform Settings', null, 'flowchat-settings');
        
        foreach ($this->supported_platforms as $platform_id => $platform_info) {
            // Enable checkbox
            add_settings_field(
                $platform_id . '_enable',
                'Enable ' . $platform_info['name'],
                [$this, 'render_checkbox_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_enable']
            );
            
            // Link field
            add_settings_field(
                $platform_id . '_link',
                $platform_info['name'] . ' Link',
                [$this, 'render_text_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_link', 'placeholder' => $this->get_placeholder($platform_id)]
            );
            
            // Text label
            add_settings_field(
                $platform_id . '_text_label',
                $platform_info['name'] . ' Button Text',
                [$this, 'render_text_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_text_label', 'default' => $platform_info['name']]
            );
            
            // Show text toggle
            add_settings_field(
                $platform_id . '_text',
                'Show Text (' . $platform_info['name'] . ')',
                [$this, 'render_toggle_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_text']
            );
            
            // Color fields
            add_settings_field(
                $platform_id . '_color_start',
                'Primary Color (' . $platform_info['name'] . ')',
                [$this, 'render_color_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_color_start', 'default' => $platform_info['default_color']]
            );
            
            add_settings_field(
                $platform_id . '_color_end',
                'Secondary Color (' . $platform_info['name'] . ')',
                [$this, 'render_color_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_color_end']
            );
            
            // Custom icon
            add_settings_field(
                $platform_id . '_custom_icon',
                'Custom Icon (' . $platform_info['name'] . ')',
                [$this, 'render_icon_field'],
                'flowchat-settings',
                'flowchat_platforms_section',
                ['id' => $platform_id . '_custom_icon', 'platform_id' => $platform_id]
            );
        }
        
        // Advanced Settings Section
        add_settings_section('flowchat_advanced_section', 'Advanced Settings', null, 'flowchat-settings');
        
        $advanced_fields = [
            'enable_analytics' => ['label' => 'Enable Click Analytics', 'type' => 'checkbox'],
            'enable_scheduling' => ['label' => 'Enable Time Scheduling', 'type' => 'checkbox'],
            'schedule_start' => ['label' => 'Show From (HH:MM)', 'type' => 'time', 'default' => '00:00'],
            'schedule_end' => ['label' => 'Show Until (HH:MM)', 'type' => 'time', 'default' => '23:59'],
            'enable_device_targeting' => ['label' => 'Enable Device Targeting', 'type' => 'checkbox'],
            'desktop_visibility' => ['label' => 'Show on Desktop', 'type' => 'checkbox'],
            'mobile_visibility' => ['label' => 'Show on Mobile', 'type' => 'checkbox']
        ];
        
        foreach ($advanced_fields as $id => $field) {
            add_settings_field(
                'global_settings[' . $id . ']',
                $field['label'],
                [$this, 'render_field_by_type'],
                'flowchat-settings',
                'flowchat_advanced_section',
                ['id' => 'global_settings[' . $id . ']', 'field' => $field]
            );
        }
        
        // Page Targeting Section
        add_settings_section('flowchat_targeting_section', 'Page Targeting', null, 'flowchat-settings');
        
        add_settings_field(
            'page_targeting[display_on]',
            'Display On',
            [$this, 'render_select_field'],
            'flowchat-settings',
            'flowchat_targeting_section',
            ['id' => 'page_targeting[display_on]', 'options' => [
                'all' => 'All Pages',
                'specific' => 'Specific Pages/Posts',
                'exclude' => 'Exclude Specific Pages/Posts'
            ]]
        );
        
        add_settings_field(
            'page_targeting[selected_pages]',
            'Select Pages',
            [$this, 'render_pages_select'],
            'flowchat-settings',
            'flowchat_targeting_section',
            ['id' => 'page_targeting[selected_pages]']
        );
        
        add_settings_field(
            'page_targeting[selected_posts]',
            'Select Posts',
            [$this, 'render_posts_select'],
            'flowchat-settings',
            'flowchat_targeting_section',
            ['id' => 'page_targeting[selected_posts]']
        );
        
        // Custom CSS Section
        add_settings_section('flowchat_css_section', 'Custom CSS', null, 'flowchat-settings');
        
        add_settings_field(
            'custom_css',
            'Custom CSS Code',
            [$this, 'render_textarea_field'],
            'flowchat-settings',
            'flowchat_css_section',
            ['id' => 'custom_css', 'rows' => 10]
        );
    }

    private function get_placeholder($platform_id) {
        $placeholders = [
            'whatsapp' => 'https://wa.me/1234567890',
            'messenger' => 'https://m.me/yourpage',
            'telegram' => 'https://t.me/yourusername',
            'viber' => 'viber://chat?number=1234567890',
            'signal' => 'https://signal.me/#p/1234567890',
            'instagram' => 'https://instagram.com/yourusername',
            'twitter' => 'https://twitter.com/yourusername',
            'linkedin' => 'https://linkedin.com/in/yourprofile',
            'call' => 'tel:+1234567890',
            'email' => 'mailto:your@email.com',
            'sms' => 'sms:+1234567890',
            'skype' => 'skype:yourusername?chat',
            'discord' => 'https://discord.gg/yourinvite',
            'tiktok' => 'https://tiktok.com/@yourusername',
            'youtube' => 'https://youtube.com/yourchannel'
        ];
        
        return $placeholders[$platform_id] ?? '#';
    }

    // Field renderers
    public function render_checkbox_field($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $value = $this->get_nested_value($options, $id);
        echo '<input type="checkbox" name="flowchat_settings[' . $id . ']" value="1" ' . checked(1, $value, false) . '>';
    }

    public function render_text_field($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $value = $this->get_nested_value($options, $id);
        $placeholder = $args['placeholder'] ?? '';
        echo '<input type="text" class="regular-text" name="flowchat_settings[' . $id . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
    }

    public function render_textarea_field($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $value = $this->get_nested_value($options, $id);
        $rows = $args['rows'] ?? 5;
        echo '<textarea name="flowchat_settings[' . $id . ']" rows="' . $rows . '" class="large-text">' . esc_textarea($value) . '</textarea>';
    }

    public function render_toggle_field($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $value = $this->get_nested_value($options, $id);
        echo '<label class="switch"><input type="checkbox" name="flowchat_settings[' . $id . ']" value="1" ' . checked(1, $value, false) . '><span class="slider"></span></label>';
    }
    
    public function render_color_field($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $value = $this->get_nested_value($options, $id);
        $default = $args['default'] ?? '';
        echo '<input type="text" class="color-picker" name="flowchat_settings[' . $id . ']" value="' . esc_attr($value) . '" data-default-color="' . esc_attr($default) . '">';
    }

    public function render_select_field($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $value = $this->get_nested_value($options, $id);
        $options_list = $args['options'] ?? [];
        
        echo '<select name="flowchat_settings[' . $id . ']">';
        foreach ($options_list as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    }

    public function render_field_by_type($args) {
        $field = $args['field'];
        $id = $args['id'];
        
        switch ($field['type']) {
            case 'checkbox':
                $this->render_checkbox_field(['id' => $id]);
                break;
            case 'select':
                $this->render_select_field(['id' => $id, 'options' => $field['options']]);
                break;
            case 'time':
                $this->render_text_field(['id' => $id]);
                break;
            default:
                $this->render_text_field(['id' => $id]);
                break;
        }
    }

    public function render_icon_field($args) {
        $options = get_option("talkify_settings")');
        $platform_id = $args['platform_id'];
        
        $custom_icon = $options[$platform_id . '_custom_icon'] ?? '';
        $icon_type = $options[$platform_id . '_custom_icon_type'] ?? 'font-awesome';
        
        echo '<div class="icon-field-wrapper">';
        echo '<label><input type="radio" name="flowchat_settings[' . $platform_id . '_custom_icon_type]" value="default" ' . checked('default', $icon_type, false) . '> Default Icon</label><br>';
        echo '<label><input type="radio" name="flowchat_settings[' . $platform_id . '_custom_icon_type]" value="font-awesome" ' . checked('font-awesome', $icon_type, false) . '> Font Awesome Class</label><br>';
        echo '<input type="text" name="flowchat_settings[' . $platform_id . '_custom_icon]" value="' . esc_attr($custom_icon) . '" placeholder="fas fa-custom-icon" class="regular-text"><br>';
        echo '<label><input type="radio" name="flowchat_settings[' . $platform_id . '_custom_icon_type]" value="upload" ' . checked('upload', $icon_type, false) . '> Upload Custom Icon</label><br>';
        echo '<input type="text" name="flowchat_settings[' . $platform_id . '_custom_icon]" value="' . esc_attr($custom_icon) . '" placeholder="Enter image URL" class="regular-text">';
        echo '<button type="button" class="button upload-icon-button" data-target="flowchat_settings[' . $platform_id . '_custom_icon]">Upload Image</button>';
        echo '</div>';
    }

    public function render_pages_select($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $selected_pages = $this->get_nested_value($options, $id);
        
        $pages = get_pages();
        echo '<select name="flowchat_settings[' . $id . '][]" multiple class="regular-text" style="height: 150px;">';
        foreach ($pages as $page) {
            $selected = is_array($selected_pages) && in_array($page->ID, $selected_pages) ? 'selected' : '';
            echo '<option value="' . $page->ID . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Hold Ctrl/Cmd to select multiple pages</p>';
    }

    public function render_posts_select($args) {
        $options = get_option("talkify_settings")');
        $id = $args['id'];
        $selected_posts = $this->get_nested_value($options, $id);
        
        $posts = get_posts(['numberposts' => -1, 'post_status' => 'publish']);
        echo '<select name="flowchat_settings[' . $id . '][]" multiple class="regular-text" style="height: 150px;">';
        foreach ($posts as $post) {
            $selected = is_array($selected_posts) && in_array($post->ID, $selected_posts) ? 'selected' : '';
            echo '<option value="' . $post->ID . '" ' . $selected . '>' . esc_html($post->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Hold Ctrl/Cmd to select multiple posts</p>';
    }

    private function get_nested_value($array, $key) {
        $keys = explode('[', str_replace(']', '', $key));
        $value = $array;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return '';
            }
        }
        
        return $value;
    }

    // Admin page with tabs
    public function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'global';
        ?>
        <div class="wrap flowchat-admin">
            <h1>FlowChat Buttons Settings</h1>
            
            <div class="flowchat-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="?page=flowchat-settings&tab=global" class="nav-tab <?php echo $current_tab === 'global' ? 'nav-tab-active' : ''; ?>">Global Settings</a>
                    <a href="?page=flowchat-settings&tab=platforms" class="nav-tab <?php echo $current_tab === 'platforms' ? 'nav-tab-active' : ''; ?>">Platforms</a>
                    <a href="?page=flowchat-settings&tab=advanced" class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
                    <a href="?page=flowchat-settings&tab=targeting" class="nav-tab <?php echo $current_tab === 'targeting' ? 'nav-tab-active' : ''; ?>">Page Targeting</a>
                    <a href="?page=flowchat-settings&tab=css" class="nav-tab <?php echo $current_tab === 'css' ? 'nav-tab-active' : ''; ?>">Custom CSS</a>
                </nav>
                
                <div class="tab-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('flowchat_settings_group');
                        
                        switch ($current_tab) {
                            case 'global':
                                do_settings_sections('flowchat-settings', 'flowchat_global_section');
                                break;
                            case 'platforms':
                                do_settings_sections('flowchat-settings', 'flowchat_platforms_section');
                                break;
                            case 'advanced':
                                do_settings_sections('flowchat-settings', 'flowchat_advanced_section');
                                break;
                            case 'targeting':
                                do_settings_sections('flowchat-settings', 'flowchat_targeting_section');
                                break;
                            case 'css':
                                do_settings_sections('flowchat-settings', 'flowchat_css_section');
                                break;
                        }
                        
                        submit_button('Save Settings', 'primary', 'submit', true);
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_analytics_page() {
        $analytics = get_option('flowchat_analytics', []);
        ?>
        <div class="wrap flowchat-admin">
            <h1>FlowChat Analytics Pro</h1>
            
            <div class="analytics-overview">
                <h2>Advanced Analytics Dashboard</h2>
                <?php if (empty($analytics)) : ?>
                    <p>No analytics data available yet. Enable analytics in settings and wait for user interactions.</p>
                <?php else : ?>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <h3>Total Clicks</h3>
                            <div class="analytics-number"><?php echo array_sum(array_column($analytics, 'clicks')); ?></div>
                        </div>
                        <div class="analytics-card">
                            <h3>Top Platform</h3>
                            <div class="analytics-number"><?php echo $this->get_top_platform($analytics); ?></div>
                        </div>
                        <div class="analytics-card">
                            <h3>Conversion Rate</h3>
                            <div class="analytics-number"><?php echo $this->get_conversion_rate(); ?>%</div>
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Platform</th>
                                <th>Clicks</th>
                                <th>Page</th>
                                <th>Device</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_clicks = 0;
                            foreach ($analytics as $date => $platforms) {
                                foreach ($platforms as $platform => $clicks) {
                                    $total_clicks += $clicks;
                                    echo '<tr>';
                                    echo '<td>' . esc_html($date) . '</td>';
                                    echo '<td>' . esc_html(ucfirst($platform)) . '</td>';
                                    echo '<td>' . esc_html($clicks) . '</td>';
                                    echo '<td>' . esc_html($platforms['page_url'] ?? 'N/A') . '</td>';
                                    echo '<td>' . esc_html($platforms['device'] ?? 'N/A') . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4">Total Clicks</th>
                                <th><?php echo esc_html($total_clicks); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="analytics-actions">
                        <button type="button" class="button" id="clear-analytics">Clear Analytics Data</button>
                        <button type="button" class="button" id="export-analytics">Export Analytics</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function render_display_rules_page() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        ?>
        <div class="wrap flowchat-admin">
            <h1>Smart Display Rules</h1>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php settings_fields('flowchat_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Page Targeting</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="flowchat_settings[global_settings][enable_page_targeting]" value="1" <?php checked($global_settings['enable_page_targeting'] ?? '0'); ?>>
                                        Enable page-specific targeting
                                    </label>
                                </fieldset>
                                
                                <div class="page-targeting-options">
                                    <h4>Include Pages</h4>
                                    <select name="flowchat_settings[global_settings][included_pages][]" multiple class="regular-text">
                                        <?php
                                        $pages = get_pages();
                                        $included_pages = $global_settings['included_pages'] ?? [];
                                        foreach ($pages as $page) {
                                            $selected = in_array($page->ID, $included_pages) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    
                                    <h4>Exclude Pages</h4>
                                    <select name="flowchat_settings[global_settings][excluded_pages][]" multiple class="regular-text">
                                        <?php
                                        $excluded_pages = $global_settings['excluded_pages'] ?? [];
                                        foreach ($pages as $page) {
                                            $selected = in_array($page->ID, $excluded_pages) ? 'selected' : '';
                                            echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Post Type Targeting</th>
                            <td>
                                <select name="flowchat_settings[global_settings][targeted_post_types][]" multiple class="regular-text">
                                    <?php
                                    $post_types = get_post_types(['public' => true], 'objects');
                                    $targeted_post_types = $global_settings['targeted_post_types'] ?? [];
                                    foreach ($post_types as $post_type) {
                                        $selected = in_array($post_type->name, $targeted_post_types) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($post_type->name) . '" ' . $selected . '>' . esc_html($post_type->label) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">Show buttons only on selected post types.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Time Scheduling</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="flowchat_settings[global_settings][enable_scheduling]" value="1" <?php checked($global_settings['enable_scheduling'] ?? '0'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Enable time-based display</span>
                                
                                <div class="schedule-options">
                                    <label>Start Time: <input type="time" name="flowchat_settings[global_settings][schedule_start]" value="<?php echo esc_attr($global_settings['schedule_start'] ?? '00:00'); ?>"></label>
                                    <label>End Time: <input type="time" name="flowchat_settings[global_settings][schedule_end]" value="<?php echo esc_attr($global_settings['schedule_end'] ?? '23:59'); ?>"></label>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Device Targeting</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="flowchat_settings[global_settings][enable_device_targeting]" value="1" <?php checked($global_settings['enable_device_targeting'] ?? '0'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Enable device-specific display</span>
                                
                                <div class="device-options">
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][desktop_visibility]" value="1" <?php checked($global_settings['desktop_visibility'] ?? '1'); ?>> Desktop</label>
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][mobile_visibility]" value="1" <?php checked($global_settings['mobile_visibility'] ?? '1'); ?>> Mobile</label>
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][tablet_visibility]" value="1" <?php checked($global_settings['tablet_visibility'] ?? '1'); ?>> Tablet</label>
                                </div>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function render_themes_page() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        ?>
        <div class="wrap flowchat-admin">
            <h1>Themes & Styles</h1>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php settings_fields('flowchat_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Widget Theme</th>
                            <td>
                                <div class="theme-selector">
                                    <?php foreach ($this->widget_themes as $theme_id => $theme_info): ?>
                                        <div class="theme-option">
                                            <input type="radio" name="flowchat_settings[global_settings][theme]" value="<?php echo esc_attr($theme_id); ?>" <?php checked($global_settings['theme'] ?? 'default', $theme_id); ?> id="theme-<?php echo esc_attr($theme_id); ?>">
                                            <label for="theme-<?php echo esc_attr($theme_id); ?>">
                                                <div class="theme-preview theme-<?php echo esc_attr($theme_id); ?>">
                                                    <div class="preview-button"></div>
                                                </div>
                                                <span><?php echo esc_html($theme_info['name']); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Custom CSS</th>
                            <td>
                                <textarea name="flowchat_settings[global_settings][custom_css]" rows="10" class="large-text" placeholder="Add your custom CSS here..."><?php echo esc_textarea($global_settings['custom_css'] ?? ''); ?></textarea>
                                <p class="description">Add custom CSS to override default styles.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Multi-language Support</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="flowchat_settings[global_settings][enable_multi_language]" value="1" <?php checked($global_settings['enable_multi_language'] ?? '0'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Enable multi-language support</span>
                                
                                <div class="language-options">
                                    <label>Default Language: 
                                        <select name="flowchat_settings[global_settings][default_language]">
                                            <option value="en" <?php selected($global_settings['default_language'] ?? 'en', 'en'); ?>>English</option>
                                            <option value="es" <?php selected($global_settings['default_language'] ?? 'en', 'es'); ?>>Spanish</option>
                                            <option value="fr" <?php selected($global_settings['default_language'] ?? 'en', 'fr'); ?>>French</option>
                                            <option value="de" <?php selected($global_settings['default_language'] ?? 'en', 'de'); ?>>German</option>
                                            <option value="pt" <?php selected($global_settings['default_language'] ?? 'en', 'pt'); ?>>Portuguese</option>
                                            <option value="it" <?php selected($global_settings['default_language'] ?? 'en', 'it'); ?>>Italian</option>
                                        </select>
                                    </label>
                                    
                                    <label>
                                        <input type="checkbox" name="flowchat_settings[global_settings][enable_rtl]" value="1" <?php checked($global_settings['enable_rtl'] ?? '0'); ?>>
                                        Enable RTL support
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function render_contact_forms_page() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        ?>
        <div class="wrap flowchat-admin">
            <h1>Contact Forms</h1>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php settings_fields('flowchat_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Contact Forms</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="flowchat_settings[global_settings][enable_contact_form]" value="1" <?php checked($global_settings['enable_contact_form'] ?? '0'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Enable built-in contact forms</span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Form Fields</th>
                            <td>
                                <div class="form-fields-selector">
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][contact_form_fields][]" value="name" <?php echo in_array('name', $global_settings['contact_form_fields'] ?? []) ? 'checked' : ''; ?>> Name</label>
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][contact_form_fields][]" value="email" <?php echo in_array('email', $global_settings['contact_form_fields'] ?? []) ? 'checked' : ''; ?>> Email</label>
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][contact_form_fields][]" value="phone" <?php echo in_array('phone', $global_settings['contact_form_fields'] ?? []) ? 'checked' : ''; ?>> Phone</label>
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][contact_form_fields][]" value="message" <?php echo in_array('message', $global_settings['contact_form_fields'] ?? []) ? 'checked' : ''; ?>> Message</label>
                                    <label><input type="checkbox" name="flowchat_settings[global_settings][contact_form_fields][]" value="company" <?php echo in_array('company', $global_settings['contact_form_fields'] ?? []) ? 'checked' : ''; ?>> Company</label>
                                </div>
                                <p class="description">Select which fields to include in the contact form.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Email Notifications</th>
                            <td>
                                <label>Recipient Email: <input type="email" name="flowchat_settings[global_settings][contact_email]" value="<?php echo esc_attr($global_settings['contact_email'] ?? get_option('admin_email')); ?>" class="regular-text"></label>
                                <p class="description">Email address to receive contact form submissions.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>Shortcode Usage</h3>
                    <p>Use the following shortcodes to display contact forms:</p>
                    <code>[flowchat_contact platform="whatsapp"]</code>
                    <code>[flowchat_contact platform="messenger" title="Contact Us"]</code>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function render_qr_codes_page() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        ?>
        <div class="wrap flowchat-admin">
            <h1>QR Code Generator</h1>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php settings_fields('flowchat_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable QR Codes</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="flowchat_settings[global_settings][enable_qr_codes]" value="1" <?php checked($global_settings['enable_qr_codes'] ?? '0'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Enable QR code generation</span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">QR Code Size</th>
                            <td>
                                <input type="number" name="flowchat_settings[global_settings][qr_code_size]" value="<?php echo esc_attr($global_settings['qr_code_size'] ?? '150'); ?>" min="100" max="500" step="10">
                                <p class="description">Size of generated QR codes in pixels.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>QR Code Generator</h3>
                    <div class="qr-generator">
                        <select id="qr-platform">
                            <?php foreach ($this->supported_platforms as $platform_id => $platform_info): ?>
                                <option value="<?php echo esc_attr($platform_id); ?>"><?php echo esc_html($platform_info['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="qr-data" placeholder="Enter phone number, username, or URL">
                        <button type="button" id="generate-qr" class="button">Generate QR Code</button>
                        <div id="qr-result"></div>
                    </div>
                    
                    <h3>Shortcode Usage</h3>
                    <p>Use the following shortcode to display QR codes:</p>
                    <code>[flowchat_qr platform="whatsapp" data="+1234567890" size="150"]</code>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function render_ab_testing_page() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        ?>
        <div class="wrap flowchat-admin">
            <h1>A/B Testing</h1>
            
            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php settings_fields('flowchat_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable A/B Testing</th>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="flowchat_settings[global_settings][enable_ab_testing]" value="1" <?php checked($global_settings['enable_ab_testing'] ?? '0'); ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Enable A/B testing for buttons</span>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Test Duration</th>
                            <td>
                                <input type="number" name="flowchat_settings[global_settings][ab_test_duration]" value="<?php echo esc_attr($global_settings['ab_test_duration'] ?? '7'); ?>" min="1" max="30">
                                <p class="description">Duration of A/B test in days.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Test Variants</th>
                            <td>
                                <div class="ab-variants">
                                    <h4>Control (Current)</h4>
                                    <div class="variant-config">
                                        <label>Position: <select name="flowchat_settings[global_settings][control_position]">
                                            <option value="bottom-right" <?php selected($global_settings['control_position'] ?? 'bottom-right', 'bottom-right'); ?>>Bottom Right</option>
                                            <option value="bottom-left" <?php selected($global_settings['control_position'] ?? 'bottom-right', 'bottom-left'); ?>>Bottom Left</option>
                                            <option value="top-right" <?php selected($global_settings['control_position'] ?? 'bottom-right', 'top-right'); ?>>Top Right</option>
                                        </select></label>
                                        <label>Size: <select name="flowchat_settings[global_settings][control_size]">
                                            <option value="small" <?php selected($global_settings['control_size'] ?? 'medium', 'small'); ?>>Small</option>
                                            <option value="medium" <?php selected($global_settings['control_size'] ?? 'medium', 'medium'); ?>>Medium</option>
                                            <option value="large" <?php selected($global_settings['control_size'] ?? 'medium', 'large'); ?>>Large</option>
                                        </select></label>
                                    </div>
                                    
                                    <h4>Variant A</h4>
                                    <div class="variant-config">
                                        <label>Position: <select name="flowchat_settings[global_settings][variant_a_position]">
                                            <option value="bottom-right" <?php selected($global_settings['variant_a_position'] ?? 'bottom-left', 'bottom-right'); ?>>Bottom Right</option>
                                            <option value="bottom-left" <?php selected($global_settings['variant_a_position'] ?? 'bottom-left', 'bottom-left'); ?>>Bottom Left</option>
                                            <option value="top-right" <?php selected($global_settings['variant_a_position'] ?? 'bottom-left', 'top-right'); ?>>Top Right</option>
                                        </select></label>
                                        <label>Size: <select name="flowchat_settings[global_settings][variant_a_size]">
                                            <option value="small" <?php selected($global_settings['variant_a_size'] ?? 'large', 'small'); ?>>Small</option>
                                            <option value="medium" <?php selected($global_settings['variant_a_size'] ?? 'large', 'medium'); ?>>Medium</option>
                                            <option value="large" <?php selected($global_settings['variant_a_size'] ?? 'large', 'large'); ?>>Large</option>
                                        </select></label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function render_pro_features_page() {
        ?>
        <div class="wrap flowchat-admin">
            <h1>FlowChat Pro Features</h1>
            
            <div class="pro-features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Smart Display Rules</h3>
                    <p>Show buttons on specific pages, at specific times, or to specific devices for maximum engagement.</p>
                    <a href="?page=flowchat-display" class="button">Configure</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🎨</div>
                    <h3>Premium Themes</h3>
                    <p>Choose from 8 beautiful themes including Glass, Neon, Gradient, and more to match your brand.</p>
                    <a href="?page=flowchat-themes" class="button">Customize</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Contact Forms</h3>
                    <p>Built-in contact forms with customizable fields and email notifications.</p>
                    <a href="?page=flowchat-contact" class="button">Setup</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>QR Code Generation</h3>
                    <p>Generate QR codes for all messaging platforms to offline engagement.</p>
                    <a href="?page=flowchat-qr" class="button">Generate</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🧪</div>
                    <h3>A/B Testing</h3>
                    <p>Test different button positions, sizes, and colors to optimize conversion rates.</p>
                    <a href="?page=flowchat-abtesting" class="button">Start Testing</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🤖</div>
                    <h3>Proactive Triggers</h3>
                    <p>AI-powered triggers based on scroll depth, time on page, and exit intent.</p>
                    <span class="badge badge-success">Active</span>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🌍</div>
                    <h3>Multi-language Support</h3>
                    <p>Support for 6+ languages with RTL compatibility for global reach.</p>
                    <a href="?page=flowchat-themes" class="button">Configure</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Advanced Analytics</h3>
                    <p>Detailed analytics with conversion tracking, device breakdown, and export capabilities.</p>
                    <a href="?page=flowchat-analytics" class="button">View Analytics</a>
                </div>
            </div>
            
            <div class="pro-stats">
                <h2>Plugin Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">20+</div>
                        <div class="stat-label">Supported Platforms</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">8</div>
                        <div class="stat-label">Premium Themes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">15+</div>
                        <div class="stat-label">Advanced Features</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">WooCommerce Compatible</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    // Helper methods for analytics
    private function get_top_platform($analytics) {
        $platform_clicks = [];
        foreach ($analytics as $date => $platforms) {
            foreach ($platforms as $platform => $data) {
                $clicks = is_array($data) ? ($data['clicks'] ?? 0) : $data;
                $platform_clicks[$platform] = ($platform_clicks[$platform] ?? 0) + $clicks;
            }
        }
        return !empty($platform_clicks) ? array_keys($platform_clicks, max($platform_clicks))[0] : 'N/A';
    }
    
    private function get_conversion_rate() {
        // Simple conversion rate calculation
        $total_clicks = 0;
        $total_visitors = wp_statistics_visitors('total') ?? 1000;
        
        $analytics = get_option('flowchat_analytics', []);
        foreach ($analytics as $date => $platforms) {
            foreach ($platforms as $platform => $data) {
                $clicks = is_array($data) ? ($data['clicks'] ?? 0) : $data;
                $total_clicks += $clicks;
            }
        }
        
        return $total_visitors > 0 ? round(($total_clicks / $total_visitors) * 100, 2) : 0;
    }

    // Frontend styles
    public function enqueue_styles() {
        wp_enqueue_style('font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
        wp_enqueue_style('flowchat-frontend', FLOWCHAT_PLUGIN_URL . 'assets/frontend.css', [], FLOWCHAT_VERSION);
        wp_enqueue_script('flowchat-frontend-js', FLOWCHAT_PLUGIN_URL . 'assets/frontend.js', ['jquery'], FLOWCHAT_VERSION, true);
    }

    // Admin styles
    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'flowchat-settings') === false && strpos($hook, 'flowchat-analytics') === false) return;
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('flowchat-admin', FLOWCHAT_PLUGIN_URL . 'assets/admin.css', [], FLOWCHAT_VERSION);
        wp_enqueue_script('flowchat-admin-js', FLOWCHAT_PLUGIN_URL . 'assets/admin.js', ['jquery', 'wp-color-picker', 'media-upload'], FLOWCHAT_VERSION, true);
        
        wp_localize_script('flowchat-admin-js', 'flowchat_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flowchat_admin_nonce')
        ]);
    }
    
    // Advanced Features - Chaty Pro Style Methods
    
    public function enqueue_advanced_scripts() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        
        if ($global_settings['enable_contact_form'] || $global_settings['enable_proactive_triggers'] || $global_settings['enable_ab_testing']) {
            wp_enqueue_script('flowchat-advanced-js', FLOWCHAT_PLUGIN_URL . 'assets/advanced.js', ['jquery'], FLOWCHAT_VERSION, true);
            
            wp_localize_script('flowchat-advanced-js', 'flowchat_advanced', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('flowchat_advanced_nonce'),
                'enable_contact_form' => $global_settings['enable_contact_form'] ?? '0',
                'enable_proactive_triggers' => $global_settings['enable_proactive_triggers'] ?? '0',
                'trigger_scroll_percentage' => $global_settings['trigger_scroll_percentage'] ?? '75',
                'trigger_time_on_page' => $global_settings['trigger_time_on_page'] ?? '30',
                'enable_exit_intent' => $global_settings['enable_exit_intent'] ?? '0',
                'enable_ab_testing' => $global_settings['enable_ab_testing'] ?? '0',
                'ab_test_variant' => $this->get_ab_test_variant()
            ]);
        }
    }
    
    public function add_theme_styles() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        $theme = $global_settings['theme'] ?? 'default';
        
        if ($theme !== 'default') {
            echo '<style id="flowchat-theme-styles">';
            echo $this->get_theme_styles($theme);
            echo '</style>';
        }
        
        // Custom CSS
        $custom_css = $global_settings['custom_css'] ?? '';
        if (!empty($custom_css)) {
            echo '<style id="flowchat-custom-styles">';
            echo esc_html($custom_css);
            echo '</style>';
        }
    }
    
    private function get_theme_styles($theme) {
        $styles = [
            'minimal' => '
                .flowchat-btn { box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.05); }
                .flowchat-btn:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
                .flowchat-btn-label { background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); }
            ',
            'colorful' => '
                .flowchat-btn { 
                    background: linear-gradient(45deg, var(--btn-color), var(--btn-color-light)) !important;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    animation: colorShift 3s ease-in-out infinite;
                }
                @keyframes colorShift {
                    0%, 100% { filter: hue-rotate(0deg); }
                    50% { filter: hue-rotate(30deg); }
                }
            ',
            'dark' => '
                .talkify-container { filter: brightness(0.9) contrast(1.1); }
                .flowchat-btn { box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
                .flowchat-btn-label { background: rgba(0,0,0,0.95); }
            ',
            'gradient' => '
                .flowchat-btn { 
                    background: linear-gradient(135deg, var(--btn-color), var(--btn-color-dark)) !important;
                    position: relative;
                    overflow: hidden;
                }
                .flowchat-btn::after {
                    content: "";
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
                    transform: rotate(45deg);
                    transition: all 0.5s;
                }
                .flowchat-btn:hover::after { transform: rotate(45deg) translateY(100%); }
            ',
            'neon' => '
                .flowchat-btn { 
                    box-shadow: 0 0 20px var(--btn-color), 0 0 40px var(--btn-color);
                    animation: neonGlow 2s ease-in-out infinite alternate;
                }
                @keyframes neonGlow {
                    from { box-shadow: 0 0 10px var(--btn-color), 0 0 20px var(--btn-color); }
                    to { box-shadow: 0 0 20px var(--btn-color), 0 0 40px var(--btn-color); }
                }
            ',
            'glass' => '
                .flowchat-btn { 
                    background: rgba(255,255,255,0.1) !important;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.2);
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                }
                .flowchat-btn-label { 
                    background: rgba(0,0,0,0.8);
                    backdrop-filter: blur(15px);
                    border: 1px solid rgba(255,255,255,0.1);
                }
            ',
            'retro' => '
                .flowchat-btn { 
                    background: var(--btn-color) !important;
                    box-shadow: 4px 4px 0px rgba(0,0,0,0.3);
                    transform: skew(-5deg);
                    border: 2px solid #000;
                }
                .flowchat-btn:hover { transform: skew(-5deg) translate(-2px, 2px); }
                .flowchat-btn i { transform: skew(5deg); }
            '
        ];
        
        return $styles[$theme] ?? '';
    }
    
    public function add_multi_language_support() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        
        if ($global_settings['enable_multi_language']) {
            $current_lang = determine_locale();
            $default_lang = $global_settings['default_language'] ?? 'en';
            
            if ($current_lang !== $default_lang) {
                echo '<script>var flowchat_current_lang = "' . esc_js($current_lang) . '";</script>';
            }
            
            if ($global_settings['enable_rtl']) {
                echo '<style>.talkify-container{direction:rtl;}</style>';
            }
        }
    }
    
    public function handle_contact_form() {
        check_ajax_referer('flowchat_advanced_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error(['message' => __('Please fill all required fields.', 'talkify')]);
        }
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'talkify')]);
        }
        
        // Send email notification
        $to = get_option('admin_email');
        $subject = sprintf(__('New Contact Form Submission from %s', 'talkify'), $name);
        $body = sprintf(
            __("Name: %s\nEmail: %s\nPlatform: %s\nMessage: %s", 'talkify'),
            $name,
            $email,
            $platform,
            $message
        );
        
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: ' . $name . ' <' . $email . '>'];
        
        if (wp_mail($to, $subject, $body, $headers)) {
            // Track conversion
            $this->track_conversion('contact_form', $platform);
            
            wp_send_json_success(['message' => __('Thank you for your message. We will get back to you soon!', 'talkify')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send message. Please try again.', 'talkify')]);
        }
    }
    
    public function generate_qr_code() {
        check_ajax_referer('flowchat_advanced_nonce', 'nonce');
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $data = sanitize_text_field($_POST['data'] ?? '');
        $size = intval($_POST['size'] ?? 150);
        
        if (empty($platform) || empty($data)) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'talkify')]);
        }
        
        // Generate QR code using Google Charts API
        $qr_url = 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&chl=' . urlencode($data);
        
        $response = wp_remote_get($qr_url);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Failed to generate QR code.', 'talkify')]);
        }
        
        $qr_image = wp_remote_retrieve_body($response);
        
        wp_send_json_success([
            'qr_code' => 'data:image/png;base64,' . base64_encode($qr_image),
            'download_url' => $qr_url
        ]);
    }
    
    public function track_ab_test() {
        check_ajax_referer('flowchat_advanced_nonce', 'nonce');
        
        $variant = sanitize_text_field($_POST['variant'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($variant) || empty($platform)) {
            return;
        }
        
        // Track A/B test conversion
        $this->track_conversion('ab_test_' . $variant, $platform);
    }
    
    private function get_ab_test_variant() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        
        if (!$global_settings['enable_ab_testing']) {
            return 'control';
        }
        
        // Simple A/B test - assign variant based on user ID or session
        $user_id = get_current_user_id();
        $session_id = session_id();
        
        if ($user_id) {
            $variant = ($user_id % 2) ? 'variant_a' : 'variant_b';
        } else {
            $variant = (crc32($session_id) % 2) ? 'variant_a' : 'variant_b';
        }
        
        return $variant;
    }
    
    public function render_proactive_triggers() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        
        if (!$global_settings['enable_proactive_triggers']) {
            return;
        }
        
        $platforms = $options['channels'] ?? [];
        $first_platform = '';
        
        foreach ($platforms as $platform_id => $platform_data) {
            if ($platform_data['active'] ?? false) {
                $first_platform = $platform_id;
                break;
            }
        }
        
        if (empty($first_platform)) {
            return;
        }
        
        $platform_info = $this->supported_platforms[$first_platform];
        $message = __('Need help? We\'re here to assist you!', 'talkify');
        $link = $this->get_platform_link($first_platform, ['message' => $message]);
        
        ?>
        <div id="flowchat-proactive-popup" class="flowchat-proactive-popup" style="display: none;">
            <div class="flowchat-proactive-content">
                <div class="flowchat-proactive-header">
                    <i class="<?php echo esc_attr($platform_info['icon']); ?>"></i>
                    <h4><?php echo esc_html($platform_info['name']); ?></h4>
                    <button class="flowchat-proactive-close">&times;</button>
                </div>
                <div class="flowchat-proactive-body">
                    <p><?php echo esc_html($message); ?></p>
                    <a href="<?php echo esc_url($link); ?>" class="flowchat-proactive-btn" target="_blank">
                        <?php esc_html_e('Start Chat', 'talkify'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'platforms' => '',
            'position' => 'bottom-right',
            'size' => 'medium',
            'theme' => 'default'
        ], $atts);
        
        // Custom shortcode rendering logic
        ob_start();
        $this->render_frontend_buttons(true, $atts);
        return ob_get_clean();
    }
    
    public function render_contact_shortcode($atts) {
        $atts = shortcode_atts([
            'platform' => 'whatsapp',
            'title' => __('Contact Us', 'talkify'),
            'button_text' => __('Send Message', 'talkify')
        ], $atts);
        
        ob_start();
        ?>
        <div class="flowchat-contact-form-container">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <form class="flowchat-contact-form" data-platform="<?php echo esc_attr($atts['platform']); ?>">
                <div class="form-group">
                    <input type="text" name="name" placeholder="<?php esc_attr_e('Your Name', 'talkify'); ?>" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="<?php esc_attr_e('Your Email', 'talkify'); ?>" required>
                </div>
                <div class="form-group">
                    <textarea name="message" placeholder="<?php esc_attr_e('Your Message', 'talkify'); ?>" required></textarea>
                </div>
                <button type="submit" class="flowchat-contact-submit">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
                <div class="flowchat-form-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_qr_shortcode($atts) {
        $atts = shortcode_atts([
            'platform' => 'whatsapp',
            'data' => '',
            'size' => '150',
            'title' => __('Scan to Chat', 'talkify')
        ], $atts);
        
        if (empty($atts['data'])) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="flowchat-qr-container">
            <h4><?php echo esc_html($atts['title']); ?></h4>
            <div class="flowchat-qr-code" data-platform="<?php echo esc_attr($atts['platform']); ?>" data-data="<?php echo esc_attr($atts['data']); ?>" data-size="<?php echo esc_attr($atts['size']); ?>">
                <div class="qr-placeholder"><?php esc_html_e('Loading QR Code...', 'talkify'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function track_conversion($type, $platform) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'flowchat_analytics';
        
        $wpdb->insert(
            $table_name,
            [
                'date' => current_time('mysql'),
                'platform' => $platform,
                'clicks' => 1,
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        // Also track conversion type
        $conversion_table = $wpdb->prefix . 'flowchat_conversions';
        
        // Create conversion table if not exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$conversion_table'") !== $conversion_table) {
            $sql = "CREATE TABLE $conversion_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                conversion_type varchar(50) NOT NULL,
                platform varchar(50) NOT NULL,
                page_url varchar(500) DEFAULT '',
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY conversion_type (conversion_type),
                KEY platform (platform),
                KEY date (date)
            ) " . $wpdb->get_charset_collate();
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
        $wpdb->insert(
            $conversion_table,
            [
                'date' => current_time('mysql'),
                'conversion_type' => $type,
                'platform' => $platform,
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    // Enhanced Smart Display Rules
    public function should_display_buttons() {
        $options = get_option("talkify_settings")');
        $global_settings = $options['global_settings'] ?? [];
        
        // Check WooCommerce page targeting
        if ($this->is_woocommerce_active && !$this->should_display_on_wc_page($global_settings)) {
            return false;
        }
        
        // Check scheduling
        if ($global_settings['enable_scheduling'] && !$this->is_in_schedule($global_settings)) {
            return false;
        }
        
        // Check device targeting
        if ($global_settings['enable_device_targeting'] && !$this->is_visible_for_device($global_settings)) {
            return false;
        }
        
        // Check page-specific targeting
        if (!$this->should_display_on_current_page($global_settings)) {
            return false;
        }
        
        return true;
    }
    
    private function should_display_on_current_page($global_settings) {
        // Check for specific page exclusions
        $excluded_pages = $global_settings['excluded_pages'] ?? [];
        $current_page_id = get_the_ID();
        
        if (!empty($excluded_pages) && in_array($current_page_id, $excluded_pages)) {
            return false;
        }
        
        // Check for specific page inclusions
        $included_pages = $global_settings['included_pages'] ?? [];
        if (!empty($included_pages) && !in_array($current_page_id, $included_pages)) {
            return false;
        }
        
        // Check for post type targeting
        $targeted_post_types = $global_settings['targeted_post_types'] ?? [];
        if (!empty($targeted_post_types)) {
            $current_post_type = get_post_type();
            if (!in_array($current_post_type, $targeted_post_types)) {
                return false;
            }
        }
        
        return true;
    }
    
    // Enhanced platform link generation with advanced features
    public function get_platform_link($platform, $args = []) {
        $options = get_option("talkify_settings")');
        $channels = $options['channels'] ?? [];
        $platform_data = $channels[$platform] ?? [];
        
        $default_message = $args['message'] ?? $platform_data['default_message'] ?? __('Hello! I need help.', 'talkify');
        
        switch ($platform) {
            case 'whatsapp':
                $phone = $platform_data['phone_number'] ?? '';
                $message = urlencode($default_message);
                return "https://wa.me/{$phone}?text={$message}";
                
            case 'messenger':
                $page_id = $platform_data['page_id'] ?? '';
                $message = urlencode($default_message);
                return "https://m.me/{$page_id}?text={$message}";
                
            case 'telegram':
                $username = $platform_data['username'] ?? '';
                $message = urlencode($default_message);
                return "https://t.me/{$username}?text={$message}";
                
            case 'viber':
                $number = $platform_data['phone_number'] ?? '';
                return "viber://chat?number={$number}";
                
            case 'signal':
                $phone = $platform_data['phone_number'] ?? '';
                $message = urlencode($default_message);
                return "sgnl://signal.me/#p/{$phone}?text={$message}";
                
            case 'instagram':
                $username = $platform_data['username'] ?? '';
                return "https://instagram.com/{$username}";
                
            case 'twitter':
                $username = $platform_data['username'] ?? '';
                $message = urlencode($default_message);
                return "https://twitter.com/messages/compose?recipient_id={$username}&text={$message}";
                
            case 'linkedin':
                $profile_url = $platform_data['profile_url'] ?? '';
                $message = urlencode($default_message);
                return "{$profile_url}?message={$message}";
                
            case 'call':
                $phone = $platform_data['phone_number'] ?? '';
                return "tel:{$phone}";
                
            case 'email':
                $email = $platform_data['email'] ?? '';
                $subject = urlencode($platform_data['subject'] ?? __('Inquiry from Website', 'talkify'));
                $body = urlencode($default_message);
                return "mailto:{$email}?subject={$subject}&body={$body}";
                
            case 'sms':
                $phone = $platform_data['phone_number'] ?? '';
                $message = urlencode($default_message);
                return "sms:{$phone}?body={$message}";
                
            case 'skype':
                $username = $platform_data['username'] ?? '';
                $message = urlencode($default_message);
                return "skype:{$username}?chat&message={$message}";
                
            case 'discord':
                $invite_link = $platform_data['invite_link'] ?? '';
                return $invite_link;
                
            case 'tiktok':
                $username = $platform_data['username'] ?? '';
                return "https://tiktok.com/@{$username}";
                
            case 'youtube':
                $channel_url = $platform_data['channel_url'] ?? '';
                return $channel_url;
                
            case 'snapchat':
                $username = $platform_data['username'] ?? '';
                return "https://snapchat.com/add/{$username}";
                
            case 'pinterest':
                $profile_url = $platform_data['profile_url'] ?? '';
                return $profile_url;
                
            case 'reddit':
                $username = $platform_data['username'] ?? '';
                return "https://reddit.com/user/{$username}";
                
            case 'wechat':
                $wechat_id = $platform_data['wechat_id'] ?? '';
                return "weixin://dl/chat?{$wechat_id}";
                
            case 'line':
                $line_id = $platform_data['line_id'] ?? '';
                return "https://line.me/R/ti/p/@{$line_id}";
                
            case 'custom':
                return $platform_data['custom_url'] ?? '#';
                
            default:
                return '#';
        }
    }
    
    /**
     * Handle chat message submission
     */
    public function handle_chat_message() {
        check_ajax_referer('flowchat_chat_nonce', 'nonce');
        
        $chat_id = sanitize_text_field($_POST['chat_id'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(['message' => __('Message cannot be empty', 'talkify')]);
        }
        
        // Store message in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'talkify_messages';
        
        // Create table if not exists
        $this->ensure_messages_table();
        
        $wpdb->insert(
            $table_name,
            [
                'chat_id' => $chat_id,
                'platform' => $platform,
                'message' => $message,
                'sender_type' => 'user',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        // Track analytics
        $this->track_chat_analytics($chat_id, $platform);
        
        // Generate bot response
        $bot_response = $this->get_bot_response($message, $platform);
        
        // Store bot response
        $wpdb->insert(
            $table_name,
            [
                'chat_id' => $chat_id,
                'platform' => $platform,
                'message' => $bot_response,
                'sender_type' => 'bot',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        wp_send_json_success([
            'message' => __('Message sent successfully', 'talkify'),
            'timestamp' => current_time('mysql'),
            'bot_response' => $bot_response
        ]);
    }
    
    /**
     * Get chat history
     */
    public function get_chat_history() {
        check_ajax_referer('flowchat_chat_nonce', 'nonce');
        
        $chat_id = sanitize_text_field($_POST['chat_id'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'talkify_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE chat_id = %s ORDER BY created_at ASC LIMIT 50",
            $chat_id
        ));
        
        wp_send_json_success(['messages' => $messages]);
    }
    
    /**
     * Ensure messages table exists
     */
    private function ensure_messages_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'talkify_messages';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            chat_id varchar(50) NOT NULL,
            platform varchar(50) NOT NULL,
            message text NOT NULL,
            sender_type varchar(10) NOT NULL DEFAULT 'user',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY chat_id (chat_id),
            KEY platform (platform),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track chat analytics
     */
    private function track_chat_analytics($chat_id, $platform) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'talkify_analytics';
        
        $wpdb->insert(
            $table_name,
            [
                'date' => current_time('mysql'),
                'platform' => $platform,
                'clicks' => 1,
                'page_url' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }
    
    /**
     * Get bot response
     */
    private function get_bot_response($user_message, $platform) {
        $responses = [
            __('Thank you for your message! Our team will get back to you shortly.', 'talkify'),
            __('I understand your concern. Let me help you with that.', 'talkify'),
            __('Great question! Let me find the information for you.', 'talkify'),
            __('I\'m here to help! Could you provide more details?', 'talkify'),
            __('Thanks for reaching out. How can I assist you today?', 'talkify')
        ];
        
        return $responses[array_rand($responses)];
    }
}

new Talkify_Plugin();