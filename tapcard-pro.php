<?php
/**
 * Plugin Name: TapCard Pro
 * Plugin URI: https://example.com/
 * Description: NFC + QR digital profile platform - Complete & Stable
 * Version: 3.0.0
 * Author: Apostolis Karamichalis
 * License: GPL2
 */

if (!defined('ABSPATH')) exit;

define('TAPCARD_VERSION', '3.0.0');
define('TAPCARD_DIR', plugin_dir_path(__FILE__));
define('TAPCARD_URL', plugin_dir_url(__FILE__));

class TapCardPro {
    private static $instance = null;
    const CPT = 'tapcard_profile';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Core hooks
        add_action('init', [$this, 'register_cpt'], 5);
        add_action('init', [$this, 'flush_rewrites'], 10);
        add_action('rest_api_init', [$this, 'register_rest_api']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'template_redirect'], 1);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_' . self::CPT, [$this, 'save_metabox'], 10, 2);
    }

    public function register_cpt() {
        register_post_type(self::CPT, array(
            'labels' => array(
                'name' => 'TapCard Profiles',
                'singular_name' => 'Profile',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-id-alt',
            'supports' => array('title'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
            'capability_type' => 'post',
        ));
    }

    public function flush_rewrites() {
        add_rewrite_rule(
            '^tapcard/([a-z0-9_-]+)/?$',
            'index.php?tapcard_profile=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($vars) {
        $vars[] = 'tapcard_profile';
        return $vars;
    }

    public function admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Settings',
            'Settings',
            'manage_options',
            'tapcard-settings',
            [$this, 'settings_page']
        );
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap" style="max-width:800px;margin-top:20px;">';
        echo '<h1>TapCard Pro v' . TAPCARD_VERSION . '</h1>';
        echo '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:20px 0;">';
        echo '<h2>Instructions</h2>';
        echo '<ol style="font-size:14px;line-height:1.8;">';
        echo '<li>Create a new profile (TapCard Profiles > Add New)</li>';
        echo '<li>Fill in all details (Name, Email, Phone, etc.)</li>';
        echo '<li>Publish the profile</li>';
        echo '<li>Copy the generated token/URL from the profile settings</li>';
        echo '<li>Share the URL: <code>yoursite.com/tapcard/{token}/</code></li>';
        echo '</ol>';
        echo '</div>';
        echo '<p><strong>Version:</strong> ' . TAPCARD_VERSION . '</p>';
        echo '<p><strong>Created by:</strong> Apostolis Karamichalis</p>';
        echo '</div>';
    }

    public function add_metabox() {
        add_meta_box(
            'tapcard_data',
            'Profile Information',
            [$this, 'metabox_content'],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function metabox_content($post) {
        wp_nonce_field('tapcard_nonce', '_tapcard_nonce');
        
        $fields = [
            'full_name' => 'Full Name',
            'company' => 'Company',
            'job_title' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone',
            'mobile' => 'Mobile',
            'website' => 'Website',
            'address' => 'Address',
            'photo_url' => 'Photo URL',
            'logo_url' => 'Logo URL',
        ];
        
        echo '<table class="form-table" style="width:100%;">';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, 'tapcard_' . $key, true);
            echo '<tr><th><label for="tapcard_' . $key . '">' . esc_html($label) . '</label></th>';
            echo '<td><input type="text" id="tapcard_' . $key . '" name="tapcard_' . $key . '" value="' . esc_attr($value) . '" style="width:100%;padding:8px;" /></td></tr>';
        }
        echo '</table>';
        
        // Display token
        $token = get_post_meta($post->ID, 'tapcard_token', true);
        if (!$token) {
            $token = strtolower(wp_generate_password(16, false));
            update_post_meta($post->ID, 'tapcard_token', $token);
        }
        
        $profile_url = home_url('/tapcard/' . $token . '/');
        echo '<div style="margin-top:20px;padding:15px;background:#e7f3ff;border:1px solid #2196F3;border-radius:5px;">';
        echo '<p><strong>Profile URL:</strong></p>';
        echo '<input type="text" readonly value="' . esc_url($profile_url) . '" style="width:100%;padding:10px;" />';
        echo '</div>';
    }

    public function save_metabox($post_id, $post) {
        if (!isset($_POST['_tapcard_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_tapcard_nonce'])), 'tapcard_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $fields = ['full_name', 'company', 'job_title', 'email', 'phone', 'mobile', 'website', 'address', 'photo_url', 'logo_url'];
        
        foreach ($fields as $field) {
            if (isset($_POST['tapcard_' . $field])) {
                update_post_meta($post_id, 'tapcard_' . $field, sanitize_text_field(wp_unslash($_POST['tapcard_' . $field])));
            }
        }
    }

    public function register_rest_api() {
        register_rest_route('tapcard/v1', '/profile/(?P<token>[a-z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => [$this, 'api_get_profile'],
            'permission_callback' => '__return_true',
        ));
    }

    public function api_get_profile($request) {
        $token = sanitize_text_field($request['token']);
        $post = $this->get_post_by_token($token);
        
        if (!$post) {
            return new WP_Error('not_found', 'Profile not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'id' => $post->ID,
            'token' => $token,
            'full_name' => get_post_meta($post->ID, 'tapcard_full_name', true),
            'company' => get_post_meta($post->ID, 'tapcard_company', true),
            'job_title' => get_post_meta($post->ID, 'tapcard_job_title', true),
            'email' => get_post_meta($post->ID, 'tapcard_email', true),
            'phone' => get_post_meta($post->ID, 'tapcard_phone', true),
            'mobile' => get_post_meta($post->ID, 'tapcard_mobile', true),
            'website' => get_post_meta($post->ID, 'tapcard_website', true),
            'address' => get_post_meta($post->ID, 'tapcard_address', true),
            'photo_url' => get_post_meta($post->ID, 'tapcard_photo_url', true),
            'logo_url' => get_post_meta($post->ID, 'tapcard_logo_url', true),
            'profile_url' => home_url('/tapcard/' . $token . '/'),
        ));
    }

    private function get_post_by_token($token) {
        $args = array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'meta_key' => 'tapcard_token',
            'meta_value' => $token,
            'posts_per_page' => 1,
        );
        $query = new WP_Query($args);
        return $query->have_posts() ? $query->posts[0] : null;
    }

    public function template_redirect() {
        $token = get_query_var('tapcard_profile');
        if (!$token) return;
        
        $post = $this->get_post_by_token($token);
        if (!$post) {
            wp_die('Profile not found', 404);
        }
        
        $this->render_profile($post);
        exit;
    }

    private function render_profile($post) {
        $data = array(
            'full_name' => get_post_meta($post->ID, 'tapcard_full_name', true),
            'company' => get_post_meta($post->ID, 'tapcard_company', true),
            'job_title' => get_post_meta($post->ID, 'tapcard_job_title', true),
            'email' => get_post_meta($post->ID, 'tapcard_email', true),
            'phone' => get_post_meta($post->ID, 'tapcard_phone', true),
            'mobile' => get_post_meta($post->ID, 'tapcard_mobile', true),
            'website' => get_post_meta($post->ID, 'tapcard_website', true),
            'photo_url' => get_post_meta($post->ID, 'tapcard_photo_url', true),
            'logo_url' => get_post_meta($post->ID, 'tapcard_logo_url', true),
        );
        
        wp_enqueue_style('tapcard-style', TAPCARD_URL . 'assets/style.css', array(), TAPCARD_VERSION);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($data['full_name'] ?: 'Digital Card'); ?></title>
            <?php wp_head(); ?>
        </head>
        <body style="margin:0;padding:0;background:#f3f4f6;">
            <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">
                <div style="background:white;border-radius:16px;padding:40px;max-width:500px;box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                    <?php if ($data['photo_url']): ?>
                        <img src="<?php echo esc_url($data['photo_url']); ?>" alt="Profile Photo" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin:0 auto 20px;display:block;">
                    <?php endif; ?>
                    
                    <h1 style="margin:0 0 8px;text-align:center;font-size:28px;color:#111827;"><?php echo esc_html($data['full_name']); ?></h1>
                    
                    <?php if ($data['company']): ?>
                        <p style="margin:0 0 5px;text-align:center;color:#6b7280;font-size:14px;"><?php echo esc_html($data['company']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($data['job_title']): ?>
                        <p style="margin:0 0 20px;text-align:center;color:#6b7280;font-size:14px;"><?php echo esc_html($data['job_title']); ?></p>
                    <?php endif; ?>
                    
                    <div style="display:flex;flex-direction:column;gap:10px;margin-top:20px;">
                        <?php if ($data['phone']): ?>
                            <a href="tel:<?php echo esc_attr($data['phone']); ?>" style="display:block;padding:12px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;text-align:center;font-weight:500;">📞 Call</a>
                        <?php endif; ?>
                        
                        <?php if ($data['email']): ?>
                            <a href="mailto:<?php echo esc_attr($data['email']); ?>" style="display:block;padding:12px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;text-align:center;font-weight:500;">✉️ Email</a>
                        <?php endif; ?>
                        
                        <?php if ($data['website']): ?>
                            <a href="<?php echo esc_url($data['website']); ?>" target="_blank" style="display:block;padding:12px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;text-align:center;font-weight:500;">🌐 Website</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    public static function activate() {
        self::instance()->register_cpt();
        self::instance()->flush_rewrites();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize plugin
if (function_exists('add_action')) {
    add_action('plugins_loaded', array('TapCardPro', 'instance'));
    register_activation_hook(__FILE__, array('TapCardPro', 'activate'));
    register_deactivation_hook(__FILE__, array('TapCardPro', 'deactivate'));
    TapCardPro::instance();
}
