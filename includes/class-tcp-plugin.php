<?php
/**
 * TapCard Pro core.
 * Created by Apostolis Karamichalis.
 */

defined('ABSPATH') || exit;

class TCP_Plugin {
    private static $instance = null;
    private $post_type = 'tcp_profile';
    private $tag_post_type = 'tcp_nfc_tag';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_tag_post_type']);
        add_action('init', [$this, 'register_rewrites']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('add_meta_boxes_' . $this->tag_post_type, [$this, 'add_tag_meta_boxes']);
        add_action('save_post_' . $this->tag_post_type, [$this, 'save_tag'], 10, 2);
        add_filter('manage_' . $this->tag_post_type . '_posts_columns', [$this, 'tag_columns']);
        add_action('manage_' . $this->tag_post_type . '_posts_custom_column', [$this, 'tag_column_content'], 10, 2);
        add_action('save_post_' . $this->post_type, [$this, 'save_profile'], 10, 2);
        add_action('template_redirect', [$this, 'template_redirect']);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('woocommerce_order_status_completed', [$this, 'woocommerce_completed_order'], 20, 1);
        add_action('admin_post_tcp_resend_onboarding', [$this, 'admin_resend_onboarding']);
        add_action('admin_post_tcp_admin_save_customer', [$this, 'admin_save_customer_profile']);
        add_action('admin_post_tcp_delete_customer', [$this, 'admin_delete_customer']);
        add_action('admin_enqueue_scripts', [$this, 'admin_media_assets']);
        add_action('wp_ajax_tcp_media_library', [$this, 'ajax_media_library']);
        add_action('wp_ajax_tcp_media_upload', [$this, 'ajax_media_upload']);
        add_action('template_redirect', [$this, 'template_redirect_assets'], 1);
    }

    public static function activate() {
        $self = self::instance();
        $self->register_post_type();
        $self->register_tag_post_type();
        $self->register_rewrites();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function register_post_type() {
        register_post_type($this->post_type, [
            'labels' => [
                'name' => 'TapCard Profiles',
                'singular_name' => 'TapCard Profile',
                'add_new_item' => 'Add TapCard Profile',
                'edit_item' => 'Edit TapCard Profile',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-id-alt',
            'supports' => ['title', 'author'],
            'show_in_rest' => false,
        ]);
    }

    public function register_tag_post_type() {
        register_post_type($this->tag_post_type, [
            'labels' => [
                'name' => 'NFC Tags',
                'singular_name' => 'NFC Tag',
                'add_new_item' => 'Add NFC Tag',
                'edit_item' => 'Edit NFC Tag',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . $this->post_type,
            'supports' => ['title'],
            'show_in_rest' => false,
        ]);
    }

    public function register_rewrites() {
        add_rewrite_rule('^tapcard/([A-Za-z0-9_-]+)/?$', 'index.php?tcp_profile_token=$matches[1]', 'top');
        add_rewrite_rule('^tapcard-settings/?$', 'index.php?tcp_user_app=1', 'top');
        add_rewrite_rule('^tapcard-user/?$', 'index.php?tcp_user_app=1', 'top');
        add_rewrite_rule('^tapcard-customer/?$', 'index.php?tcp_customer_app=1', 'top');
        add_rewrite_rule('^tapcard-activate/([A-Za-z0-9_-]+)/?$', 'index.php?tcp_activate_token=$matches[1]', 'top');
        add_rewrite_rule('^tapcard-sw\.js$', 'index.php?tcp_sw=1', 'top');
        add_rewrite_rule('^tapcard-manifest\.json$', 'index.php?tcp_manifest=1', 'top');
    }

    public function query_vars($vars) {
        $vars[] = 'tcp_profile_token';
        $vars[] = 'tcp_user_app';
        $vars[] = 'tcp_customer_app';
        $vars[] = 'tcp_activate_token';
        $vars[] = 'tcp_sw';
        $vars[] = 'tcp_manifest';
        return $vars;
    }

    public function admin_menu() {
        add_submenu_page('edit.php?post_type=' . $this->post_type, 'TapCard Pro Settings', 'Settings', 'manage_options', 'tcp-settings', [$this, 'settings_page']);
        add_submenu_page('edit.php?post_type=' . $this->post_type, 'TapCard Customers', 'Customers', 'manage_options', 'tcp-customers', [$this, 'customers_page']);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>TapCard Pro</h1>
            <p><strong>Version:</strong> <?php echo esc_html(TCP_VERSION); ?></p>
            <p>Created by <strong>Apostolis Karamichalis</strong>.</p>
            <h2>Mobile applications</h2>
            <ul>
                <li>User App: <code><?php echo esc_url(home_url('/tapcard-user/')); ?></code></li>
                <li>Customer App: <code><?php echo esc_url(home_url('/tapcard-customer/')); ?></code></li>
                <li>Profile URL pattern: <code><?php echo esc_url(home_url('/tapcard/{PROFILE_TOKEN}/')); ?></code></li>
            </ul>
        </div>
        <?php
    }

    public function admin_media_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== $this->post_type) return;
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }

    public function add_meta_boxes() {
        add_meta_box('tcp_profile_data', 'TapCard Profile Data', [$this, 'profile_metabox'], $this->post_type, 'normal', 'high');
    }

    private function fields() {
        return [
            'full_name' => 'Full name',
            'company' => 'Company',
            'job_title' => 'Job title',
            'photo_url' => 'Photo URL',
            'logo_url' => 'Logo URL',
            'email' => 'Email',
            'phone' => 'Phone',
            'mobile' => 'Mobile',
            'website' => 'Website',
            'address' => 'Address',
            'maps_url' => 'Google Maps URL',
            'google_service' => 'Google service / Reviews URL',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'whatsapp' => 'WhatsApp',
        ];
    }

    private function default_visibility() {
        $visibility = [];
        foreach ($this->fields() as $key => $label) { $visibility[$key] = true; }
        return $visibility;
    }

    private function get_visibility($post_id) {
        $saved = get_post_meta($post_id, '_tcp_visibility', true);
        return is_array($saved) ? wp_parse_args($saved, $this->default_visibility()) : $this->default_visibility();
    }

    private function get_field_order($post_id) {
        $keys = array_keys($this->fields());
        $saved = get_post_meta($post_id, '_tcp_field_order', true);
        if (!is_array($saved)) return $keys;
        $saved = array_values(array_intersect($saved, $keys));
        return array_values(array_unique(array_merge($saved, array_diff($keys, $saved))));
    }

    public function profile_metabox($post) {
        wp_nonce_field('tcp_save_profile', 'tcp_profile_nonce');
        $fields = $this->fields();
        $visibility = $this->get_visibility($post->ID);
        $order = $this->get_field_order($post->ID);
        
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_tcp_' . $key, true);
            echo '<tr><th><label>' . esc_html($label) . '</label></th><td>';
            echo '<input class="widefat" type="text" name="tcp_' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        
        $token = get_post_meta($post->ID, '_tcp_token', true);
        if (!$token) { 
            $token = $this->generate_token(); 
            update_post_meta($post->ID, '_tcp_token', $token); 
        }
        $url = home_url('/tapcard/' . rawurlencode($token) . '/');
        echo '<h3>Published Card</h3><p><input class="widefat" readonly value="' . esc_attr($url) . '"></p>';
    }

    public function save_profile($post_id, $post) {
        if (!isset($_POST['tcp_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_profile_nonce'])), 'tcp_save_profile')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach ($this->fields() as $key => $label) {
            $raw = isset($_POST['tcp_' . $key]) ? wp_unslash($_POST['tcp_' . $key]) : '';
            $value = sanitize_text_field($raw);
            update_post_meta($post_id, '_tcp_' . $key, $value);
        }

        if (!get_post_meta($post_id, '_tcp_token', true)) {
            update_post_meta($post_id, '_tcp_token', $this->generate_token());
        }
    }

    private function generate_token() {
        do {
            $token = strtolower(wp_generate_password(24, false, false));
            $query = new WP_Query(['post_type' => $this->post_type, 'meta_key' => '_tcp_token', 'meta_value' => $token, 'fields' => 'ids', 'posts_per_page' => 1]);
        } while ($query->have_posts());
        return $token;
    }

    private function get_profile_by_token($token) {
        $q = new WP_Query(['post_type' => $this->post_type, 'post_status' => 'publish', 'meta_key' => '_tcp_token', 'meta_value' => sanitize_text_field($token), 'fields' => 'ids', 'posts_per_page' => 1]);
        if (!$q->have_posts()) return 0;
        return (int) $q->posts[0];
    }

    private function profile_payload($post_id) {
        $data = ['id' => $post_id, 'token' => get_post_meta($post_id, '_tcp_token', true)];
        foreach ($this->fields() as $key => $label) {
            $data[$key] = get_post_meta($post_id, '_tcp_' . $key, true);
        }
        $token = $data['token'];
        $data['profile_url'] = home_url('/tapcard/' . rawurlencode($token) . '/');
        $data['visibility'] = $this->get_visibility($post_id);
        $data['field_order'] = $this->get_field_order($post_id);
        return $data;
    }

    public function rest_media(WP_REST_Request $request) {
        $uid = get_current_user_id();
        if (!$uid) return new WP_Error('forbidden', 'Δεν επιτρέπεται η πρόσβαση στη βιβλιοθήκη.', ['status' => 403]);
        
        $page = max(1, (int)$request->get_param('page'));
        $per_page = min(60, max(12, (int)($request->get_param('per_page') ?: 36)));
        $search = sanitize_text_field((string)$request->get_param('search'));
        
        $args = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => $per_page, 'paged' => $page, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => false, 'fields' => 'ids'];
        
        if ($search !== '') $args['s'] = $search;
        
        $q = new WP_Query($args);
        $items = [];
        foreach ((array)$q->posts as $id) {
            $url = wp_get_attachment_url($id);
            if (!$url) continue;
            $thumb = wp_get_attachment_image_url($id, 'medium') ?: $url;
            $items[] = ['id' => (int)$id, 'url' => $url, 'thumb' => $thumb, 'name' => get_the_title($id) ?: wp_basename($url)];
        }
        
        return rest_ensure_response(['items' => $items, 'page' => $page, 'pages' => (int)$q->max_num_pages]);
    }

    public function register_rest_routes() {
        register_rest_route('tapcard/v1', '/profile/(?P<token>[A-Za-z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_get_profile'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tapcard/v1', '/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_get_me'],
            'permission_callback' => function () { return is_user_logged_in(); },
        ]);

        register_rest_route('tapcard/v1', '/me/media', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_media'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_get_profile(WP_REST_Request $request) {
        $id = $this->get_profile_by_token($request['token']);
        if (!$id) return new WP_Error('profile_not_found', 'Profile not found.', ['status' => 404]);
        return rest_ensure_response($this->profile_payload($id));
    }

    public function rest_get_me() {
        $user_id = get_current_user_id();
        $q = new WP_Query(['post_type' => $this->post_type, 'author' => $user_id, 'posts_per_page' => 1, 'fields' => 'ids']);
        if (!$q->have_posts()) return rest_ensure_response(['profile' => null]);
        return rest_ensure_response(['profile' => $this->profile_payload((int)$q->posts[0])]);
    }

    public function frontend_assets() {}

    public function template_redirect() {
        if (get_query_var('tcp_profile_token')) {
            $token = get_query_var('tcp_profile_token');
            $id = $this->get_profile_by_token($token);
            if (!$id) {
                status_header(404);
                wp_die('TapCard profile not found.');
            }
            $this->render_customer_app($token);
            exit;
        }

        if (get_query_var('tcp_user_app')) {
            if (!is_user_logged_in()) {
                wp_die('Please log in.');
            } else {
                $this->render_user_app();
            }
            exit;
        }
    }

    public function template_redirect_assets() {
        if (get_query_var('tcp_manifest')) {
            nocache_headers();
            header('Content-Type: application/manifest+json; charset=UTF-8');
            echo wp_json_encode([
                'name' => 'TapCard Settings',
                'short_name' => 'TapCard',
                'start_url' => home_url('/tapcard-settings/'),
                'display' => 'standalone',
                'background_color' => '#f3f4f6',
                'theme_color' => '#111827',
                'description' => 'TapCard Settings PWA',
                'permissions' => ['storage', 'photos']
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function render_user_app() {
        $html = '<section class="tcp-dashboard"><h1>TapCard User Settings</h1><p>User application here</p></section>';
        $this->render_shell('TapCard - User', 'tcp-user-page', $html);
    }

    private function render_customer_app($token) {
        $html = '<section class="tcp-customer-card"><div id="tcp-customer-loading">Loading profile…</div></section>';
        wp_enqueue_script('tcp-app', TCP_URL . 'assets/app.js', [], TCP_VERSION, true);
        $this->render_shell('TapCard - Customer', 'tcp-customer-page', $html);
    }

    private function render_shell($title, $body_class, $app_html) {
        wp_enqueue_style('tcp-app', TCP_URL . 'assets/app.css', [], TCP_VERSION);
        wp_localize_script('tcp-app', 'TapCardPro', [
            'apiRoot' => esc_url_raw(rest_url('tapcard/v1')),
            'pluginUrl' => esc_url_raw(TCP_URL),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="<?php echo esc_attr($body_class); ?>">
            <main><?php echo $app_html; ?></main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    public function add_tag_meta_boxes() {}
    public function tag_columns($columns) { return $columns; }
    public function tag_column_content($column, $post_id) {}
    public function save_tag($post_id, $post) {}
    public function ajax_media_library() {}
    public function ajax_media_upload() {}
    public function woocommerce_completed_order($order_id) {}
    public function admin_resend_onboarding() {}
    public function admin_save_customer_profile() {}
    public function admin_delete_customer() {}
    public function customers_page() {}
}
