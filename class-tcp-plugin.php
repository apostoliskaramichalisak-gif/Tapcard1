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
        add_rewrite_rule(
            '^tapcard/([A-Za-z0-9_-]+)/?$',
            'index.php?tcp_profile_token=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^tapcard-settings/?$',
            'index.php?tcp_user_app=1',
            'top'
        );
        add_rewrite_rule(
            '^tapcard-user/?$',
            'index.php?tcp_user_app=1',
            'top'
        );
        add_rewrite_rule(
            '^tapcard-customer/?$',
            'index.php?tcp_customer_app=1',
            'top'
        );
        add_rewrite_rule(
            '^tapcard-activate/([A-Za-z0-9_-]+)/?$',
            'index.php?tcp_activate_token=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^tapcard-sw\.js$',
            'index.php?tcp_sw=1',
            'top'
        );
        add_rewrite_rule(
            '^tapcard-manifest\.json$',
            'index.php?tcp_manifest=1',
            'top'
        );
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
        add_submenu_page(
            'edit.php?post_type=' . $this->post_type,
            'TapCard Pro Settings',
            'Settings',
            'manage_options',
            'tcp-settings',
            [$this, 'settings_page']
        );
        add_submenu_page('edit.php?post_type=' . $this->post_type, 'TapCard Customers', 'Customers', 'manage_options', 'tcp-customers', [$this, 'customers_page']);
    }

    public function tag_columns($columns) {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox">',
            'title' => 'Tag',
            'tcp_uid' => 'NFC UID',
            'tcp_status' => 'Status',
            'tcp_profile' => 'Assigned Profile',
            'date' => 'Date',
        ];
    }

    public function tag_column_content($column, $post_id) {
        if ($column === 'tcp_uid') {
            echo esc_html(get_post_meta($post_id, '_tcp_tag_uid', true));
        } elseif ($column === 'tcp_status') {
            echo esc_html(ucfirst(get_post_meta($post_id, '_tcp_tag_status', true) ?: 'available'));
        } elseif ($column === 'tcp_profile') {
            $profile_id = (int)get_post_meta($post_id, '_tcp_tag_profile_id', true);
            if ($profile_id) {
                echo '<a href="' . esc_url(get_edit_post_link($profile_id)) . '">' . esc_html(get_the_title($profile_id)) . '</a>';
            } else {
                echo '—';
            }
        }
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
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
        add_meta_box(
            'tcp_profile_data',
            'TapCard Profile Data',
            [$this, 'profile_metabox'],
            $this->post_type,
            'normal',
            'high'
        );
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
            'viber' => 'Viber',
            'telegram' => 'Telegram',
            'efood' => 'eFood URL',
            'wolt' => 'Wolt URL',
            'box' => 'BOX URL',
            'card_background' => 'Card background color',
            'button_background' => 'Button background color',
            'button_text' => 'Button text color',
            'text_color' => 'Text color',
            'icon_phone' => 'Phone icon', 'icon_mobile' => 'Mobile icon', 'icon_email' => 'Email icon',
            'icon_website' => 'Website icon', 'icon_google_service' => 'Google icon', 'icon_maps_url' => 'Maps icon',
            'icon_facebook' => 'Facebook icon', 'icon_instagram' => 'Instagram icon', 'icon_linkedin' => 'LinkedIn icon',
            'icon_tiktok' => 'TikTok icon', 'icon_youtube' => 'YouTube icon', 'icon_whatsapp' => 'WhatsApp icon',
            'icon_viber' => 'Viber icon', 'icon_telegram' => 'Telegram icon', 'icon_efood' => 'eFood icon',
            'icon_wolt' => 'Wolt icon', 'icon_box' => 'BOX icon',
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

    public function add_tag_meta_boxes() {
        add_meta_box(
            'tcp_tag_data',
            'NFC Tag Assignment',
            [$this, 'tag_metabox'],
            $this->tag_post_type,
            'normal',
            'high'
        );
    }

    public function tag_metabox($post) {
        wp_nonce_field('tcp_save_tag', 'tcp_tag_nonce');
        $uid = get_post_meta($post->ID, '_tcp_tag_uid', true);
        $status = get_post_meta($post->ID, '_tcp_tag_status', true) ?: 'available';
        $profile_id = (int) get_post_meta($post->ID, '_tcp_tag_profile_id', true);

        $profiles = get_posts([
            'post_type' => $this->post_type,
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<p><label><strong>NFC UID</strong></label><input class="widefat" name="tcp_tag_uid" value="' . esc_attr($uid) . '" placeholder="e.g. 04:A2:19:8F:..."></p>';
        echo '<p><label><strong>Status</strong></label><select class="widefat" name="tcp_tag_status">';
        foreach (['available'=>'Available','assigned'=>'Assigned','disabled'=>'Disabled'] as $k=>$v) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($status, $k, false) . '>' . esc_html($v) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label><strong>Assigned profile</strong></label><select class="widefat" name="tcp_tag_profile_id">';
        echo '<option value="0">— Not assigned —</option>';
        foreach ($profiles as $profile) {
            echo '<option value="' . esc_attr($profile->ID) . '" ' . selected($profile_id, $profile->ID, false) . '>' . esc_html($profile->post_title) . '</option>';
        }
        echo '</select></p>';

        if ($profile_id) {
            $token = get_post_meta($profile_id, '_tcp_token', true);
            if ($token) {
                $url = home_url('/tapcard/' . rawurlencode($token) . '/');
                echo '<p><strong>NDEF URL to write:</strong><br><input class="widefat" readonly value="' . esc_attr($url) . '"></p>';
                echo '<p>Write this URL to the NFC tag as an NDEF URL record. The tag itself does not store personal data.</p>';
            }
        }
    }

    public function save_tag($post_id, $post) {
        if (!isset($_POST['tcp_tag_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_tag_nonce'])), 'tcp_save_tag')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $uid = isset($_POST['tcp_tag_uid']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['tcp_tag_uid']))) : '';
        $status = isset($_POST['tcp_tag_status']) ? sanitize_key($_POST['tcp_tag_status']) : 'available';
        $profile_id = isset($_POST['tcp_tag_profile_id']) ? absint($_POST['tcp_tag_profile_id']) : 0;

        if (!in_array($status, ['available','assigned','disabled'], true)) {
            $status = 'available';
        }
        if ($profile_id && get_post_type($profile_id) !== $this->post_type) {
            $profile_id = 0;
        }
        if ($profile_id) {
            $status = 'assigned';
        } elseif ($status === 'assigned') {
            $status = 'available';
        }

        update_post_meta($post_id, '_tcp_tag_uid', $uid);
        update_post_meta($post_id, '_tcp_tag_status', $status);
        update_post_meta($post_id, '_tcp_tag_profile_id', $profile_id);
    }

    public function profile_metabox($post) {
        wp_nonce_field('tcp_save_profile', 'tcp_profile_nonce');
        $fields = $this->fields();
        $visibility = $this->get_visibility($post->ID);
        $order = $this->get_field_order($post->ID);
        echo '<div class="tcp-v15-media"><p><strong>Logo / Photo / Icons</strong></p><p class="description">Επιλέξτε εικόνες από το WordPress Media Library. Οι εικόνες αποθηκεύονται στην κάρτα.</p></div>';
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_tcp_' . $key, true);
            if (strpos($key, 'icon_') === 0) {
                $defaults = ['phone'=>'☎️','mobile'=>'📱','email'=>'✉️','website'=>'🌐','google_service'=>'⭐','maps_url'=>'📍','facebook'=>'f','instagram'=>'◎','linkedin'=>'in','tiktok'=>'♪','youtube'=>'▶️','whatsapp'=>'🟢','viber'=>'💬','telegram'=>'➤','efood'=>'🍴','wolt'=>'🛵','box'=>'📦'];
                $icon_key = substr($key,5);
                echo '<tr><th><label>' . esc_html($label) . '</label></th><td>';
                echo '<input class="widefat tcp-media-url" id="tcp_' . esc_attr($key) . '" type="text" name="tcp_' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="Default icon or Media Library image URL">';
                echo '<p><button type="button" class="button tcp-media-pick" data-target="tcp_' . esc_attr($key) . '">Choose from Media Library</button> ';
                echo '<button type="button" class="button tcp-media-clear" data-target="tcp_' . esc_attr($key) . '">Clear</button></p>';
                echo '<span class="tcp-icon-preview" data-preview="tcp_' . esc_attr($key) . '" style="font-size:24px">' . esc_html($value && filter_var($value,FILTER_VALIDATE_URL) ? '' : ($defaults[$icon_key] ?? '•')) . '</span>';
                echo '</td></tr>';
            } else {
                $type = ($key === 'mobile') ? 'tel' : (in_array($key, ['website','maps_url','google_service','facebook','instagram','linkedin','tiktok','youtube','efood','wolt','box'], true) ? 'url' : (in_array($key, ['card_background','button_background','button_text','text_color'], true) ? 'color' : 'text'));
                echo '<tr><th><label>' . esc_html($label) . '</label></th><td><input class="widefat" type="' . esc_attr($type) . '" name="tcp_' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                if (in_array($key,['logo_url','photo_url'],true)) {
                    echo '<p><button type="button" class="button tcp-media-pick" data-target="tcp_' . esc_attr($key) . '">Choose from Media Library</button> <button type="button" class="button tcp-media-clear" data-target="tcp_' . esc_attr($key) . '">Clear</button></p>';
                }
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '<h3>Customer Card — visibility & order</h3><p class="description">Drag fields to reorder them and uncheck fields you do not want customers to see.</p>';
        echo '<ul id="tcp-field-sortable" style="max-width:650px;background:#fff;border:1px solid #ddd;padding:8px">';
        foreach ($order as $key) {
            echo '<li draggable="true" data-field="' . esc_attr($key) . '" style="padding:10px;border-bottom:1px solid #eee;cursor:grab">';
            echo '<label><input type="checkbox" name="tcp_visible[' . esc_attr($key) . ']" value="1" ' . checked(!empty($visibility[$key]), true, false) . '> ' . esc_html($fields[$key]) . '</label></li>';
        }
        echo '</ul><input type="hidden" name="tcp_field_order" id="tcp_field_order" value="' . esc_attr(implode(',', $order)) . '">';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){const l=document.getElementById("tcp-field-sortable"),h=document.getElementById("tcp_field_order");if(!l||!h)return;let d=null;l.querySelectorAll("li").forEach(x=>{x.addEventListener("dragstart",()=>d=x);x.addEventListener("dragover",e=>{e.preventDefault();if(!d||d===x)return;const r=x.getBoundingClientRect();l.insertBefore(d,e.clientY<r.top+r.height/2?x:x.nextSibling);});});const f=l.closest("form");if(f)f.addEventListener("submit",()=>h.value=[...l.children].map(x=>x.dataset.field).join(","));});</script>';
        echo '<script>jQuery(function($){$(document).on("click",".tcp-media-pick",function(e){e.preventDefault();var b=$(this),target=$("#"+b.data("target"));var frame=wp.media({title:"Choose from Media Library",button:{text:"Use this image"},multiple:false,library:{type:"image"}});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();target.val(a.url).trigger("change");});frame.open();});$(document).on("click",".tcp-media-clear",function(){$("#"+$(this).data("target")).val("").trigger("change");});});</script>';
        $token = get_post_meta($post->ID, '_tcp_token', true);
        if (!$token) { $token = $this->generate_token(); update_post_meta($post->ID, '_tcp_token', $token); }
        $url = home_url('/tapcard/' . rawurlencode($token) . '/');
        echo '<h3>Published Card</h3><p><input class="widefat" readonly value="' . esc_attr($url) . '"></p>';
        echo '<p><img alt="TapCard QR" style="max-width:280px" src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($url) . '"></p>';
        echo '<p><strong>NFC:</strong> write this URL to the NFC tag as an NDEF URL record.</p>';
    }

    public function save_profile($post_id, $post) {
        if (!isset($_POST['tcp_profile_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_profile_nonce'])), 'tcp_save_profile')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach ($this->fields() as $key => $label) {
            $raw = isset($_POST['tcp_' . $key]) ? wp_unslash($_POST['tcp_' . $key]) : '';
            if (strpos($key, 'icon_') === 0) {
                $value = filter_var($raw, FILTER_VALIDATE_URL) ? esc_url_raw($raw) : sanitize_key($raw);
            } elseif (in_array($key, ['card_background','button_background','button_text','text_color'], true)) {
                $value = sanitize_hex_color($raw) ?: '';
            } else {
                $value = in_array($key, ['website','maps_url','google_service','facebook','instagram','linkedin','tiktok','youtube','efood','wolt','box'], true)
                    ? esc_url_raw($raw) : sanitize_text_field($raw);
            }
            update_post_meta($post_id, '_tcp_' . $key, $value);
        }

        $posted = isset($_POST['tcp_visible']) && is_array($_POST['tcp_visible']) ? $_POST['tcp_visible'] : [];
        $visibility = [];
        foreach ($this->fields() as $key => $label) { $visibility[$key] = !empty($posted[$key]); }
        update_post_meta($post_id, '_tcp_visibility', $visibility);
        $raw_order = isset($_POST['tcp_field_order']) ? sanitize_text_field(wp_unslash($_POST['tcp_field_order'])) : '';
        $order = array_values(array_intersect(array_filter(array_map('sanitize_key', explode(',', $raw_order))), array_keys($this->fields())));
        update_post_meta($post_id, '_tcp_field_order', $order);
        if (!get_post_meta($post_id, '_tcp_token', true)) {
            update_post_meta($post_id, '_tcp_token', $this->generate_token());
        }
    }

    private function generate_token() {
        do {
            $token = strtolower(wp_generate_password(24, false, false));
            $query = new WP_Query([
                'post_type' => $this->post_type,
                'meta_key' => '_tcp_token',
                'meta_value' => $token,
                'fields' => 'ids',
                'posts_per_page' => 1,
            ]);
        } while ($query->have_posts());

        return $token;
    }

    private function get_profile_by_token($token) {
        $q = new WP_Query([
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'meta_key' => '_tcp_token',
            'meta_value' => sanitize_text_field($token),
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]);
        if (!$q->have_posts()) return 0;
        return (int) $q->posts[0];
    }

    private function profile_payload($post_id) {
        $data = [
            'id' => $post_id,
            'token' => get_post_meta($post_id, '_tcp_token', true),
            'profile_name' => get_post_meta($post_id, '_tcp_profile_name', true) ?: get_the_title($post_id),
        ];
        foreach ($this->fields() as $key => $label) {
            $data[$key] = get_post_meta($post_id, '_tcp_' . $key, true);
        }
        $token = $data['token'];
        $data['profile_url'] = home_url('/tapcard/' . rawurlencode($token) . '/');
        $data['visibility'] = $this->get_visibility($post_id);
        $data['field_order'] = $this->get_field_order($post_id);
        $data['card_background'] = get_post_meta($post_id, '_tcp_card_background', true) ?: '#ffffff';
        $data['button_background'] = get_post_meta($post_id, '_tcp_button_background', true) ?: '#eeeeee';
        $data['button_text'] = get_post_meta($post_id, '_tcp_button_text', true) ?: '#202020';
        $data['text_color'] = get_post_meta($post_id, '_tcp_text_color', true) ?: '#202020';
        return $data;
    }

    /** Frontend Media Library AJAX — Created by Apostolis Karamichalis. */
    private function verify_frontend_media_nonce() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Η σύνδεση έληξε. Συνδεθείτε ξανά.'], 401);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!$nonce || !wp_verify_nonce($nonce, 'tcp_frontend_media')) {
            wp_send_json_error(['message' => 'Η συνεδρία της βιβλιοθήκης έληξε. Κάντε ανανέωση της εφαρμογής και δοκιμάστε ξανά.'], 403);
        }
    }

    public function ajax_media_library() {
        $this->verify_frontend_media_nonce();
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = min(60, max(12, absint($_POST['per_page'] ?? 36)));
        $search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
            'fields' => 'ids',
        ];
        if ($search !== '') $args['s'] = $search;
        $q = new WP_Query($args);
        $items = [];
        foreach ((array) $q->posts as $id) {
            $url = wp_get_attachment_url($id);
            if (!$url) continue;
            $thumb = wp_get_attachment_image_url($id, 'medium') ?: wp_get_attachment_image_url($id, 'thumbnail') ?: $url;
            $items[] = [
                'id' => (int) $id,
                'url' => $url,
                'thumb' => $thumb,
                'title' => get_the_title($id) ?: wp_basename($url),
            ];
        }
        wp_send_json_success([
            'items' => $items,
            'page' => $page,
            'pages' => (int) $q->max_num_pages,
            'total' => (int) $q->found_posts,
        ]);
    }

    public function ajax_media_upload() {
        $this->verify_frontend_media_nonce();
        if (empty($_FILES['file'])) wp_send_json_error(['message' => 'Δεν επιλέχθηκε εικόνα.'], 400);
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $id = media_handle_upload('file', 0);
        if (is_wp_error($id)) wp_send_json_error(['message' => $id->get_error_message()], 400);
        wp_update_post(['ID' => $id, 'post_author' => get_current_user_id()]);
        $url = wp_get_attachment_url($id);
        wp_send_json_success([
            'id' => (int) $id,
            'url' => $url,
            'thumb' => wp_get_attachment_image_url($id, 'medium') ?: $url,
            'title' => get_the_title($id) ?: wp_basename($url),
        ]);
    }

    private function authorize_frontend_media_request() {
        if (is_user_logged_in()) return true;
        $token = '';
        if (isset($_GET['media_token'])) {
            $token = (string) wp_unslash($_GET['media_token']);
        } elseif (isset($_POST['media_token'])) {
            $token = (string) wp_unslash($_POST['media_token']);
        } elseif (isset($_SERVER['HTTP_X_TAPCARD_MEDIA_TOKEN'])) {
            $token = (string) wp_unslash($_SERVER['HTTP_X_TAPCARD_MEDIA_TOKEN']);
        }
        if (!$token) return false;
        $parts = explode(':', $token);
        if (count($parts) !== 3) return false;
        $uid = absint($parts[0]);
        $exp = absint($parts[1]);
        $sig = sanitize_text_field($parts[2]);
        if (!$uid || !$exp || $exp < time() || !preg_match('/^[a-f0-9]{64}$/', $sig)) return false;
        $expected = hash_hmac('sha256', $uid . ':' . $exp . ':tapcard-media', wp_salt('auth'));
        if (!hash_equals($expected, $sig)) return false;
        wp_set_current_user($uid);
        return (bool) get_current_user_id();
    }

    public function rest_media(WP_REST_Request $request) {
        $uid = get_current_user_id();
        if (!$uid && !$this->authorize_frontend_media_request()) return new WP_Error('forbidden','Δεν επιτρέπεται η πρόσβαση στη βιβλιοθήκη.',['status'=>403]);
        $uid = get_current_user_id();
        $page=max(1,(int)$request->get_param('page')); $per_page=min(60,max(12,(int)($request->get_param('per_page')?:36)));
        $search=sanitize_text_field((string)$request->get_param('search'));
        $args=['post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image','posts_per_page'=>$per_page,'paged'=>$page,'orderby'=>'date','order'=>'DESC','no_found_rows'=>false,'fields'=>'ids'];
        if($search!=='')$args['s']=$search;
        $q = new WP_Query($args);
        $items=[]; foreach((array)$q->posts as $id){$url=wp_get_attachment_url($id); if(!$url)continue; $items[]=['id'=>(int)$id,'url'=>$url,'title'=>get_the_title($id),'thumb'=>wp_get_attachment_image_url($id,'thumbnail')?:$url];}
        return rest_ensure_response(['items'=>$items,'page'=>$page,'per_page'=>$per_page,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages]);
    }

    public function rest_upload_media(WP_REST_Request $request) {
        $uid=get_current_user_id(); if(!$uid && !$this->authorize_frontend_media_request())return new WP_Error('forbidden','Δεν επιτρέπεται η πρόσβαση στη βιβλιοθήκη.',['status'=>403]);
        $uid=get_current_user_id();
        if(empty($_FILES['file']))return new WP_Error('missing_file','No file',['status'=>400]);
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
        $id=media_handle_upload('file',0);
        if(is_wp_error($id))return $id;
        wp_update_post(['ID'=>$id,'post_author'=>$uid]);
        return rest_ensure_response(['id'=>$id,'url'=>wp_get_attachment_url($id),'title'=>get_the_title($id)]);
    }

    public function register_rest_routes() {
        register_rest_route('tapcard/v1', '/profile/(?P<token>[A-Za-z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_get_profile'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tapcard/v1', '/qr/(?P<token>[A-Za-z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_qr'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('tapcard/v1', '/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'rest_get_me'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('tapcard/v1', '/me', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'rest_save_me'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
        register_rest_route('tapcard/v1', '/me/media', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this,'rest_media'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('tapcard/v1', '/me/media', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this,'rest_upload_media'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function rest_qr(WP_REST_Request $request) {
        $id = $this->get_profile_by_token($request['token']);
        if (!$id) {
            return new WP_Error('profile_not_found', 'Profile not found.', ['status' => 404]);
        }

        // Minimal QR placeholder endpoint for v1.1: returns the canonical URL.
        // A pure-PHP QR encoder can replace this endpoint in the next build without changing the public API.
        $token = get_post_meta($id, '_tcp_token', true);
        return rest_ensure_response([
            'profile_url' => home_url('/tapcard/' . rawurlencode($token) . '/'),
            'qr_format' => 'url',
        ]);
    }

    public function rest_get_profile(WP_REST_Request $request) {
        $id = $this->get_profile_by_token($request['token']);
        if (!$id) {
            return new WP_Error('profile_not_found', 'Profile not found.', ['status' => 404]);
        }
        return rest_ensure_response($this->profile_payload($id));
    }

    public function rest_get_me() {
        $user_id = get_current_user_id();
        $linked = (int)get_user_meta($user_id,'_tcp_onboarding_profile_id',true);
        $q = new WP_Query([
            'post_type' => $this->post_type,
            'post__in' => $linked ? [$linked] : [0],
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        if (!$q->have_posts()) {
            $fallback=$this->user_profile_id($user_id);
            if($fallback) return rest_ensure_response(['profile'=>$this->profile_payload($fallback)]);
            return rest_ensure_response(['profile' => null]);
        }
        return rest_ensure_response(['profile' => $this->profile_payload((int)$q->posts[0])]);
    }

    public function rest_save_me(WP_REST_Request $request) {
        // v1.5.5: settings saves are always bound to the authenticated user's linked profile.
        // Created by Apostolis Karamichalis.
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $linked = (int) get_user_meta($user_id, '_tcp_onboarding_profile_id', true);
        $post_id = ($linked && get_post_type($linked) === $this->post_type && (int)get_post_field('post_author',$linked)===$user_id) ? $linked : $this->user_profile_id($user_id);

        $full_name = isset($params['full_name']) ? sanitize_text_field($params['full_name']) : '';
        if (!$post_id) {
            $post_id = wp_insert_post([
                'post_type' => $this->post_type,
                'post_status' => 'publish',
                'post_title' => $full_name ?: wp_get_current_user()->display_name,
                'post_author' => $user_id,
            ]);
        } else {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => $full_name ?: get_the_title($post_id),
            ]);
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (isset($params['profile_name'])) {
            $profile_name = sanitize_text_field($params['profile_name']);
            if ($profile_name !== '') {
                update_post_meta($post_id, '_tcp_profile_name', $profile_name);
                wp_update_post(['ID'=>$post_id,'post_title'=>$profile_name]);
            }
        }

        if (isset($params['visibility']) && is_array($params['visibility'])) {
            $visibility=[]; foreach ($this->fields() as $key=>$label) { $visibility[$key]=!empty($params['visibility'][$key]); }
            update_post_meta($post_id,'_tcp_visibility',$visibility);
        }
        if (isset($params['field_order']) && is_array($params['field_order'])) {
            $allowed=array_keys($this->fields());
            $order=array_values(array_intersect(array_map('sanitize_key',$params['field_order']),$allowed));
            update_post_meta($post_id,'_tcp_field_order',array_values(array_unique($order)));
        }

        foreach ($this->fields() as $key => $label) {
            if (array_key_exists($key, $params)) {
                if (strpos($key, 'icon_') === 0) {
                    $raw_icon = (string)$params[$key];
                    $value = filter_var($raw_icon, FILTER_VALIDATE_URL) ? esc_url_raw($raw_icon) : sanitize_key($raw_icon);
                } else {
                $value = in_array($key, ['website','maps_url','google_service','facebook','instagram','linkedin','tiktok','youtube','efood','wolt','box'], true)
                    ? esc_url_raw($params[$key]) : sanitize_text_field($params[$key]);
                }
                update_post_meta($post_id, '_tcp_' . $key, $value);
            }
        }

        if (!get_post_meta($post_id, '_tcp_token', true)) {
            update_post_meta($post_id, '_tcp_token', $this->generate_token());
        }
        // Keep Customers table synchronized with the profile saved from Settings.
        update_user_meta($user_id, '_tcp_primary_profile_id', (int)$post_id);
        update_user_meta($user_id, '_tcp_onboarding_profile_id', (int)$post_id);

        return rest_ensure_response($this->profile_payload($post_id));
    }

    /** WooCommerce completed-order onboarding — Created by Apostolis Karamichalis. */
    public function woocommerce_completed_order($order_id) {
        if (!$order_id || !function_exists('wc_get_order')) return;
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_billing_email()) return;
        if (get_post_meta($order_id, '_tcp_onboarding_sent', true)) return;
        $email = sanitize_email($order->get_billing_email());
        $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $user = get_user_by('email', $email);
        if (!$user) {
            $login = sanitize_user(current(explode('@',$email)), true) ?: 'tapcard';
            $base=$login; $n=1; while(username_exists($login)) $login=$base.$n++;
            $uid=wp_create_user($login, wp_generate_password(32,true,true), $email);
            if (is_wp_error($uid)) return;
            wp_update_user(['ID'=>$uid,'display_name'=>$name ?: $email,'role'=>'subscriber']);
            $user=get_user_by('id',$uid);
        }
        $profile_id = $this->user_profile_id($user->ID);
        if (!$profile_id) {
            $profile_id=wp_insert_post(['post_type'=>$this->post_type,'post_status'=>'publish','post_author'=>$user->ID,'post_title'=>$name ?: $user->display_name ?: 'Digital Card']);
            if ($profile_id && !is_wp_error($profile_id)) update_post_meta($profile_id,'_tcp_token',$this->generate_token());
        }
        $key=bin2hex(random_bytes(24));
        update_user_meta($user->ID,'_tcp_onboarding_token_hash',hash('sha256',$key));
        update_user_meta($user->ID,'_tcp_onboarding_expires',time()+DAY_IN_SECONDS);
        update_user_meta($user->ID,'_tcp_onboarding_profile_id',(int)$profile_id);
        $activate=add_query_arg('tcp_activate', rawurlencode($key), home_url('/'));
        $settings=TCP_URL . 'settings-pwa/index.php';
        $subject='Η ψηφιακή σας κάρτα είναι έτοιμη';
        $display=esc_html($name ?: $user->display_name);
        $body='<html><body style="font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:24px;color:#111827"><h1>Η ψηφιακή σας κάρτα είναι έτοιμη</h1><p>Γεια σας '.$display.',</p><p>Σας δημιουργήσαμε τον λογαριασμό σας για το TapCard.</p><p><a href="'.esc_url($activate).'" style="display:inline-block;padding:14px 20px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:12px;font-size:17px">Ορίστε τον κωδικό σας</a></p><p>Μετά τον ορισμό κωδικού, ανοίξτε την εφαρμογή <strong>TapCard Settings</strong> και αποθηκεύστε την στην αρχική οθόνη.</p><p><a href="'.esc_url($settings).'?onboarding=1" style="display:inline-block;padding:14px 20px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:12px;font-size:17px">Άνοιγμα / Εγκατάσταση «TapCard Settings»</a></p><p style="font-size:14px;color:#6b7280">Android: Εγκατάσταση εφαρμογής. iPhone: Κοινοποίηση → Προσθήκη στην οθόνη Αφετηρίας.</p><p>TapCard — Created by Apostolis Karamichalis</p></body></html>';
        $sent=wp_mail($email,$subject,$body,['Content-Type: text/html; charset=UTF-8']);
        if ($sent) update_post_meta($order_id,'_tcp_onboarding_sent',time());
    }
    private function user_profile_id($uid){
        $selected=(int)get_user_meta($uid,'_tcp_primary_profile_id',true);
        if($selected && get_post_type($selected)===$this->post_type && (int)get_post_field('post_author',$selected)===(int)$uid) return $selected;
        $ids=get_posts(['post_type'=>$this->post_type,'post_status'=>['publish','draft','private'],'author'=>(int)$uid,'posts_per_page'=>1,'fields'=>'ids']);
        return $ids ? (int)$ids[0] : 0;
    }
    public function admin_resend_onboarding(){
        if(!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('tcp_resend_onboarding');
        $uid=absint($_GET['user_id']??0); $u=get_user_by('id',$uid);
        if($u){ $settings=TCP_URL . 'settings-pwa/index.php'; $key=bin2hex(random_bytes(24)); update_user_meta($u->ID,'_tcp_onboarding_token_hash',hash('sha256',$key)); update_user_meta($u->ID,'_tcp_onboarding_expires',time()+DAY_IN_SECONDS); $activate=add_query_arg('tcp_activate', rawurlencode($key), home_url('/')); $body='<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#202020"><h2>Πρόσβαση στο TapCard</h2><p><a href="'.esc_url($activate).'" style="display:inline-block;padding:14px 20px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:12px;font-size:17px">Ορίστε τον κωδικό σας</a></p><p>Μετά τον ορισμό κωδικού, ανοίξτε την εφαρμογή TapCard Settings.</p><p><a href="'.esc_url($settings).'?onboarding=1" style="display:inline-block;padding:14px 20px;background:#7c3aed;color:#fff;text-decoration:none;border-radius:12px;font-size:17px">Άνοιγμα / Εγκατάσταση «TapCard Settings»</a></p><p style="font-size:14px;color:#6b7280">Android: Εγκατάσταση εφαρμογής. iPhone: Κοινοποίηση → Προσθήκη στην οθόνη Αφετηρίας.</p><p>TapCard — Created by Apostolis Karamichalis</p></body></html>'; wp_mail($u->user_email,'TapCard — πρόσβαση στην εφαρμογή',$body,['Content-Type: text/html; charset=UTF-8']); }
        wp_safe_redirect(admin_url('edit.php?post_type='.$this->post_type.'&page=tcp-customers&resent=1')); exit;
    }
    public function admin_save_customer_profile(){
        if(!current_user_can('manage_options')) wp_die('Forbidden');
        $uid=absint($_POST['user_id']??0); if(!$uid) wp_die('Invalid user');
        check_admin_referer('tcp_admin_save_customer_'.$uid,'tcp_admin_customer_nonce');
        $pid=absint($_POST['profile_id']??0);
        if($pid && get_post_type($pid)===$this->post_type){
            wp_update_post(['ID'=>$pid,'post_author'=>$uid]);
            update_user_meta($uid,'_tcp_primary_profile_id',$pid);
        } else delete_user_meta($uid,'_tcp_primary_profile_id');
        wp_safe_redirect(admin_url('edit.php?post_type='.$this->post_type.'&page=tcp-customers&saved=1')); exit;
    }
    public function admin_delete_customer(){
        if(!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('tcp_delete_customer');
        $uid=absint($_GET['user_id']??0); $u=get_user_by('id',$uid);
        if(!$u || user_can($u,'administrator')) wp_die('Customer cannot be deleted');
        $ids=get_posts(['post_type'=>$this->post_type,'author'=>$uid,'post_status'=>['publish','draft','private','trash'],'posts_per_page'=>-1,'fields'=>'ids']);
        foreach($ids as $pid) wp_delete_post($pid,true);
        if(function_exists('wp_delete_user')) wp_delete_user($uid);
        wp_safe_redirect(admin_url('edit.php?post_type='.$this->post_type.'&page=tcp-customers&deleted=1')); exit;
    }
    public function customers_page(){
        if(!current_user_can('manage_options')) return;
        $users=get_users(['role__in'=>['subscriber','customer'],'orderby'=>'registered','order'=>'DESC']);
        $profiles=get_posts(['post_type'=>$this->post_type,'post_status'=>['publish','draft','private'],'posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC']);
        echo '<div class="wrap"><h1>TapCard Customers</h1><p>Πελάτες και το profile που είναι συνδεδεμένο με τον λογαριασμό τους.</p>';
        if(isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>Το profile αποθηκεύτηκε.</p></div>';
        if(isset($_GET['resent'])) echo '<div class="notice notice-success is-dismissible"><p>Το email ενεργοποίησης στάλθηκε ξανά.</p></div>';
        if(isset($_GET['deleted'])) echo '<div class="notice notice-success is-dismissible"><p>Ο χρήστης και το συνδεδεμένο profile διαγράφηκαν οριστικά.</p></div>';
        echo '<table class="widefat striped"><thead><tr><th>Όνομα χρήστη</th><th>Email</th><th>Profile</th><th>Εγγραφή</th><th>Ενέργειες</th></tr></thead><tbody>';
        foreach($users as $u){
            $pid=(int)get_user_meta($u->ID,'_tcp_primary_profile_id',true);
            if($pid && get_post_type($pid)!==$this->post_type) $pid=0;
            echo '<tr><td><strong>'.esc_html($u->display_name?:$u->user_login).'</strong><br><code>'.esc_html($u->user_login).'</code></td><td>'.esc_html($u->user_email).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:8px;align-items:center"><input type="hidden" name="action" value="tcp_admin_save_customer"><input type="hidden" name="user_id" value="'.(int)$u->ID.'">'.wp_nonce_field('tcp_admin_save_customer_'.$u->ID,'tcp_admin_customer_nonce',true,false).'<select name="profile_id" style="min-width:220px"><option value="0">— Χωρίς profile —</option>';
            foreach($profiles as $p) echo '<option value="'.(int)$p->ID.'" '.selected($pid,$p->ID,false).'>'.esc_html($p->post_title).' (#'.(int)$p->ID.')</option>';
            echo '</select><button type="submit" class="button button-primary">Αποθήκευση</button></form></td><td>'.esc_html(mysql2date(get_option('date_format'),$u->user_registered)).'</td><td>';
            $res=wp_nonce_url(admin_url('admin-post.php?action=tcp_resend_onboarding&user_id='.$u->ID),'tcp_resend_onboarding');
            $del=wp_nonce_url(admin_url('admin-post.php?action=tcp_delete_customer&user_id='.$u->ID),'tcp_delete_customer');
            echo '<a class="button" href="'.esc_url($res).'">Επαναποστολή email ενεργοποίησης</a> <a class="button button-link-delete" style="color:#b32d2e" href="'.esc_url($del).'" onclick="return confirm(\'Να διαγραφεί ο χρήστης και το profile οριστικά;\')">Διαγραφή</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function frontend_assets() {
        // Assets are enqueued only by the PWA templates below.
    }

    public function template_redirect_assets() {
        if (get_query_var('tcp_sw')) {
            nocache_headers();
            header('Content-Type: application/javascript; charset=UTF-8');
            header('Service-Worker-Allowed: /');
            echo "/* TapCard Pro v1.5.2 service worker — Created by Apostolis Karamichalis */\n";
            echo "const CACHE='tapcard-pro-v1-5-2';\n";
            echo "self.addEventListener('install',e=>{self.skipWaiting()});\n";
            echo "self.addEventListener('activate',e=>{e.waitUntil(self.clients.claim())});\n";
            echo "self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(fetch(e.request).catch(()=>caches.match(e.request)))})";
            exit;
        }
        if (get_query_var('tcp_manifest')) {
            nocache_headers();
            header('Content-Type: application/manifest+json; charset=UTF-8');
            echo wp_json_encode([
                'name'=>'TapCard Settings',
                'short_name'=>'TapCard Settings',
                'start_url'=>home_url('/tapcard-settings/'),
                'scope'=>home_url('/tapcard-settings/'),
                'display'=>'standalone',
                'background_color'=>'#f3f4f6',
                'theme_color'=>'#111827',
                'description'=>'TapCard Settings PWA — Created by Apostolis Karamichalis.',
            ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function template_redirect() {
        // v1.5.3 robust activation fallback: works even when rewrite rules are stale.
        $activation_token = get_query_var('tcp_activate_token');
        if (!$activation_token && isset($_GET['tcp_activate'])) {
            $activation_token = sanitize_text_field(wp_unslash($_GET['tcp_activate']));
        }
        if ($activation_token) {
            $this->render_activation_app($activation_token);
            exit;
        }

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

        if (isset($_GET['tcp_lost_password'])) {
            $this->render_lost_password_app();
            exit;
        }

        if (isset($_GET['tcp_set_password']) && isset($_GET['key']) && isset($_GET['login'])) {
            $this->render_reset_password_app();
            exit;
        }

        if (get_query_var('tcp_user_app')) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tcp_auth_action'])) {
                $this->handle_auth_post();
            }
            if (!is_user_logged_in()) {
                $this->render_auth_app();
            } else {
                $this->render_user_app();
            }
            exit;
        }

        if (get_query_var('tcp_customer_app')) {
            $this->render_customer_app('');
            exit;
        }
    }

    private function render_shell($title, $body_class, $app_html) {
        wp_enqueue_style('tcp-app', TCP_URL . 'assets/app.css', [], TCP_VERSION);
        wp_enqueue_script('tcp-app', TCP_URL . 'assets/app.js', [], TCP_VERSION, true);
        wp_enqueue_style('tcp-v14-final', TCP_URL . 'assets/v14-final.css', [], TCP_VERSION);
        wp_enqueue_style('tcp-v154-final', TCP_URL . 'assets/v154-final.css', [], TCP_VERSION);
        wp_enqueue_script('tcp-v154-final', TCP_URL . 'assets/v154-final.js', ['tcp-app'], TCP_VERSION, true);
        $tcp_media_uid = get_current_user_id();
        $tcp_media_exp = time() + (6 * HOUR_IN_SECONDS);
        $tcp_media_token = $tcp_media_uid ? $tcp_media_uid . ':' . $tcp_media_exp . ':' . hash_hmac('sha256', $tcp_media_uid . ':' . $tcp_media_exp . ':tapcard-media', wp_salt('auth')) : '';
        wp_localize_script('tcp-app', 'TapCardPro', [
            'apiRoot' => esc_url_raw(rest_url('tapcard/v1')),
            'wpRoot' => esc_url_raw(rest_url()),
            'pluginUrl' => esc_url_raw(TCP_URL),
            'siteUrl' => esc_url_raw(home_url('/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'mediaNonce' => wp_create_nonce('tcp_frontend_media'),
            'mediaToken' => $tcp_media_token,
        ]);

        ?><!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
            <meta name="theme-color" content="#111827">
            <?php if (strpos($body_class, 'tcp-user-page') !== false): ?>
            <link rel="manifest" href="<?php echo esc_url(TCP_URL . 'settings-pwa/manifest.php'); ?>">
            <meta name="application-name" content="TapCard">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-title" content="TapCard">
            <meta name="mobile-web-app-capable" content="yes">
            <?php endif; ?>
            <link rel="stylesheet" href="<?php echo esc_url(TCP_URL . 'assets/v14-final.css'); ?>?v=1.4.0">
            <title><?php echo esc_html($title); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="<?php echo esc_attr($body_class); ?>">
            <main id="tapcard-app" class="tcp-app"><?php echo $app_html; ?></main>
            <?php wp_footer(); ?>
        </body>
        </html><?php
    }

    private function handle_auth_post() {
        $action = sanitize_key(wp_unslash($_POST['tcp_auth_action'] ?? ''));
        if (!isset($_POST['tcp_auth_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_auth_nonce'])), 'tcp_auth')) return;
        if ($action === 'login') {
            $creds = ['user_login'=>sanitize_text_field(wp_unslash($_POST['log'] ?? '')), 'user_password'=>$_POST['pwd'] ?? '', 'remember'=>true];
            $user = wp_signon($creds, is_ssl());
            if (!is_wp_error($user)) { wp_safe_redirect(home_url('/tapcard-settings/')); exit; }
            set_transient('tcp_auth_error', $user->get_error_message(), 60);
        } elseif ($action === 'register') {
            $email=sanitize_email(wp_unslash($_POST['user_email'] ?? '')); $pass=$_POST['user_pass'] ?? ''; $name=sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
            if (!is_email($email) || email_exists($email)) set_transient('tcp_auth_error','Το email δεν είναι διαθέσιμο.',60);
            elseif (strlen($pass)<8) set_transient('tcp_auth_error','Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.',60);
            else { $uid=wp_create_user($email,$pass,$email); if(!is_wp_error($uid)){wp_update_user(['ID'=>$uid,'display_name'=>$name]); wp_set_auth_cookie($uid,true,is_ssl()); wp_safe_redirect(home_url('/tapcard-settings/')); exit;} set_transient('tcp_auth_error',$uid->get_error_message(),60); }
        }
    }

    private function render_activation_app($token) {
        $hash=hash('sha256',sanitize_text_field($token));
        $users=get_users(['meta_key'=>'_tcp_onboarding_token_hash','meta_value'=>$hash,'number'=>1,'fields'=>'all']);
        $user=$users?$users[0]:null;
        $error='';
        if(!$user || (int)get_user_meta($user->ID,'_tcp_onboarding_expires',true)<time()){
            $error='Ο σύνδεσμος έχει λήξει ή δεν είναι έγκυρος. Ζητήστε νέο email ενεργοποίησης.';
        } elseif($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tcp_activate_nonce'])){
            if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_activate_nonce'])),'tcp_activate')) $error='Η συνεδρία έληξε. Ανοίξτε ξανά τον σύνδεσμο από το email.';
            else{$p1=(string)wp_unslash($_POST['pass1']??'');$p2=(string)wp_unslash($_POST['pass2']??'');if(strlen($p1)<10)$error='Ο κωδικός πρέπει να έχει τουλάχιστον 10 χαρακτήρες.';elseif($p1!==$p2)$error='Οι δύο κωδικοί δεν είναι ίδιοι.';else{reset_password($user,$p1);delete_user_meta($user->ID,'_tcp_onboarding_token_hash');delete_user_meta($user->ID,'_tcp_onboarding_expires');wp_set_auth_cookie($user->ID,true,is_ssl());wp_safe_redirect(TCP_URL.'settings-pwa/index.php?onboarding=1');exit;}}
        }
        nocache_headers();status_header(200);
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#111827"><title>Ορισμός κωδικού</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f1f5f9;color:#0f172a;font-family:system-ui,-apple-system,sans-serif}.card{width:min(100%,460px);background:#fff;border-radius:24px;padding:30px;box-shadow:0 24px 70px rgba(15,23,42,.14)}h1{font-size:30px;margin:0 0 10px}p{font-size:16px;line-height:1.5}.err{background:#fef2f2;color:#991b1b;padding:14px;border-radius:12px}.field{display:block;margin:18px 0}.field span{display:block;font-weight:700;font-size:17px;margin-bottom:8px}.password-wrap{position:relative}.field input{width:100%;min-height:56px;border:1px solid #cbd5e1;border-radius:14px;padding:12px 95px 12px 15px;font-size:18px}.show{position:absolute;right:7px;top:7px;min-height:42px;border:0;border-radius:10px;background:#e5e7eb;padding:8px 12px;font-weight:700}.btn{width:100%;min-height:58px;border:0;border-radius:14px;background:#111827;color:#fff;font-size:19px;font-weight:800}.credit{padding:18px 12px;text-align:center;font:600 12px/1.4 system-ui;color:#64748b}</style></head><body><main class="card"><h1>Όρισε τον κωδικό σου</h1><p>Δημιούργησε τον προσωπικό σου κωδικό για να αποκτήσεις πρόσβαση στο TapCard Settings.</p><?php if($error): ?><div class="err"><?php echo esc_html($error); ?></div><?php endif; ?><?php if($user instanceof WP_User): ?><form method="post" action="<?php echo esc_url(add_query_arg('tcp_activate',rawurlencode($token),home_url('/'))); ?>"><?php wp_nonce_field('tcp_activate','tcp_activate_nonce'); ?><label class="field"><span>Νέος κωδικός</span><div class="password-wrap"><input id="tcp-pass1" type="password" name="pass1" autocomplete="new-password" minlength="10" required><button class="show" type="button" data-target="tcp-pass1">Εμφάνιση</button></div></label><label class="field"><span>Επανάληψη κωδικού</span><div class="password-wrap"><input id="tcp-pass2" type="password" name="pass2" autocomplete="new-password" minlength="10" required><button class="show" type="button" data-target="tcp-pass2">Εμφάνιση</button></div></label><button class="btn" type="submit">Αποθήκευση και άνοιγμα εφαρμογής</button></form><?php endif; ?></main><div class="credit">Ο κώδικας είναι από Apostolis Karamichalis</div><script>document.addEventListener('click',function(e){var b=e.target.closest('.show');if(!b)return;var x=document.getElementById(b.dataset.target);if(!x)return;x.type=x.type==='password'?'text':'password';b.textContent=x.type==='password'?'Εμφάνιση':'Απόκρυψη';});</script></body></html><?php exit;
    }

    private function render_lost_password_app() {
        $message='';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tcp_lost_nonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_lost_nonce'])),'tcp_lost')) {
                $email=sanitize_email(wp_unslash($_POST['user_email']??''));
                $user=$email?get_user_by('email',$email):false;
                if ($user) {
                    $key=get_password_reset_key($user);
                    if(!is_wp_error($key)){
                        $url=add_query_arg(['tcp_set_password'=>'1','key'=>$key,'login'=>$user->user_login],home_url('/tapcard-settings/'));
                        $body='<html><body style="font-family:Arial,sans-serif;line-height:1.6"><h2>TapCard</h2><p>Ζητήθηκε αλλαγή κωδικού.</p><p><a href="'.esc_url($url).'" style="display:inline-block;padding:13px 18px;background:#202020;color:#fff;text-decoration:none;border-radius:10px">Ορισμός νέου κωδικού</a></p><p>Ο σύνδεσμος είναι ασφαλής και ισχύει σύμφωνα με την πολιτική του WordPress.</p><p>TapCard — Created by Apostolis Karamichalis</p></body></html>';
                        wp_mail($user->user_email,'TapCard — Αλλαγή κωδικού',$body,['Content-Type: text/html; charset=UTF-8']);
                    }
                }
                // Do not reveal whether the email exists.
                $message='Αν υπάρχει λογαριασμός με αυτό το email, θα λάβετε μήνυμα με οδηγίες αλλαγής κωδικού.';
            }
        }
        $html='<section class="tcp-auth-card"><h1>Ξέχασα τον κωδικό μου</h1><p class="tcp-muted">Πληκτρολογήστε το email του λογαριασμού σας.</p>'.
            ($message?'<div class="tcp-auth-success">'.esc_html($message).'</div>':'').
            '<form method="post" class="tcp-form">'.wp_nonce_field('tcp_lost','tcp_lost_nonce',true,false).
            '<label>Email<input type="email" name="user_email" required autocomplete="email"></label>'.
            '<button class="tcp-button" type="submit">Αποστολή email αλλαγής κωδικού</button>'.
            '<a class="tcp-forgot" href="'.esc_url(home_url('/tapcard-settings/')).'">Επιστροφή στη σύνδεση</a></form></section>';
        $this->render_shell('TapCard — Αλλαγή κωδικού','tcp-auth-page',$html);
    }

    private function render_reset_password_app() {
        $login=sanitize_user(wp_unslash($_GET['login']??''),true);
        $key=sanitize_text_field(wp_unslash($_GET['key']??''));
        $user=check_password_reset_key($key,$login); $err='';
        if(!is_wp_error($user) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tcp_reset_nonce'])) {
            if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tcp_reset_nonce'])),'tcp_reset_'.$user->ID)) $err='Η συνεδρία έληξε.';
            else {
                $p1=(string)wp_unslash($_POST['pass1']??'');$p2=(string)wp_unslash($_POST['pass2']??'');
                if(strlen($p1)<8)$err='Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
                elseif($p1!==$p2)$err='Οι δύο κωδικοί δεν είναι ίδιοι.';
                else { reset_password($user,$p1); wp_set_auth_cookie($user->ID,true,is_ssl()); wp_safe_redirect(TCP_URL . 'settings-pwa/index.php?onboarding=1'); exit; }
            }
        }
        if(is_wp_error($user))$err='Ο σύνδεσμος έχει λήξει ή δεν είναι έγκυρος. Ζητήστε νέο email.';
        $html='<section class="tcp-auth-card"><h1>Ορίστε νέο κωδικό</h1>'.
            ($err?'<div class="tcp-auth-error">'.esc_html($err).'</div>':'').
            (is_wp_error($user)?'<a class="tcp-button" href="'.esc_url(add_query_arg('tcp_lost_password','1',home_url('/tapcard-settings/'))).'">Ζητήστε νέο σύνδεσμο</a>':
            '<form method="post" class="tcp-form">'.wp_nonce_field('tcp_reset_'.$user->ID,'tcp_reset_nonce',true,false).
            '<label>Νέος κωδικός<div class="tcp-password-wrap"><input id="tcp-reset-pass1" type="password" name="pass1" minlength="8" required><button type="button" class="tcp-show-password" data-target="tcp-reset-pass1">Εμφάνιση</button></div></label>'.
            '<label>Επανάληψη κωδικού<div class="tcp-password-wrap"><input id="tcp-reset-pass2" type="password" name="pass2" minlength="8" required><button type="button" class="tcp-show-password" data-target="tcp-reset-pass2">Εμφάνιση</button></div></label>'.
            '<button class="tcp-button" type="submit">Αποθήκευση κωδικού και άνοιγμα εφαρμογής</button></form>');
        $this->render_shell('TapCard — Νέος κωδικός','tcp-auth-page',$html);
    }

    private function render_auth_app() {
        $err=get_transient('tcp_auth_error'); if($err)delete_transient('tcp_auth_error');
        $html='<section class="tcp-auth-card"><div class="tcp-auth-logo">Digital Card</div><h1>Καλώς ήρθατε</h1><p class="tcp-muted">Δημιουργήστε και διαχειριστείτε τα ψηφιακά profile σας.</p>'.($err?'<div class="tcp-auth-error">'.wp_kses_post($err).'</div>':'').'
        <div class="tcp-auth-grid"><form method="post" class="tcp-form"><h2>Σύνδεση</h2>'.wp_nonce_field('tcp_auth','tcp_auth_nonce',true,false).'<input type="hidden" name="tcp_auth_action" value="login"><label>Email<input type="email" name="log" required></label><label>Κωδικός<div class="tcp-password-wrap"><input id="tcp-login-password" type="password" name="pwd" required><button type="button" class="tcp-show-password" data-target="tcp-login-password">Εμφάνιση</button></div></label><button class="tcp-button" type="submit">Σύνδεση</button><a class="tcp-forgot" href="' . esc_url(add_query_arg('tcp_lost_password','1',home_url('/tapcard-settings/'))) . '">Ξέχασα τον κωδικό μου</a></form>
        <form method="post" class="tcp-form"><h2>Εγγραφή</h2>'.wp_nonce_field('tcp_auth','tcp_auth_nonce',true,false).'<input type="hidden" name="tcp_auth_action" value="register"><label>Όνομα<input name="display_name" required></label><label>Email<input type="email" name="user_email" required></label><label>Κωδικός<div class="tcp-password-wrap"><input id="tcp-register-password" type="password" name="user_pass" minlength="8" required><button type="button" class="tcp-show-password" data-target="tcp-register-password">Εμφάνιση</button></div></label><button class="tcp-button" type="submit">Δημιουργία λογαριασμού</button></form></div></section>';
        $this->render_shell('Digital Card — Settings','tcp-auth-page',$html);
    }

    public function render_user_app() {
        $html = '
        <section class="tcp-dashboard">
          <div id="tcp-install-card" class="tcp-install-card" hidden aria-hidden="true">
            <div class="tcp-install-dialog">
              <button id="tcp-install-close" class="tcp-install-close" type="button" aria-label="Κλείσιμο">×</button>
              <h2>Αποθήκευση εφαρμογής</h2>
              <p>Αποθηκεύστε το TapCard στην αρχική οθόνη για γρήγορη πρόσβαση και διαχείριση της κάρτας σας.</p>
              <button id="tcp-install-now" class="tcp-button" type="button">Αποθήκευση εφαρμογής</button>
              <div id="tcp-install-fallback" class="tcp-install-fallback" hidden>
                <strong>Αν δεν εμφανιστεί αυτόματα η εγκατάσταση:</strong>
                <p>Android Chrome: ⋮ → <b>Εγκατάσταση εφαρμογής</b> ή <b>Προσθήκη στην αρχική οθόνη</b>.</p>
                <p>iPhone/iPad: Safari → Κοινή χρήση → <b>Προσθήκη στην οθόνη Αφετηρίας</b>.</p>
              </div>
            </div>
          </div>
          <header class="tcp-topbar"><div><strong>Digital Profile</strong><span>Settings</span></div><div><button id="tcp-install-app" class="tcp-icon-button" type="button" hidden>Εγκατάσταση εφαρμογής</button> <button id="tcp-preview-btn" class="tcp-icon-button" type="button" disabled>Preview</button></div></header>
          <div class="tcp-dashboard-grid">
            <section class="tcp-card"><h1 id="tcp-profile-mode">Digital Profile</h1><p id="tcp-profile-status" class="tcp-muted">Ελέγχεται αν υπάρχει ήδη profile. Αν δεν υπάρχει, δημιουργήστε το. Αν υπάρχει, μπορείτε μόνο να επεξεργαστείτε και να αποθηκεύσετε αλλαγές.</p>
              <form id="tcp-user-form" class="tcp-form">
                <label>Όνομα profile<input name="profile_name" required></label>
                <label>Full name<input name="full_name" required></label><label>Company<input name="company"></label><label>Job title<input name="job_title"></label>
                <label>Photo URL<input name="photo_url" type="url"></label><label>Upload Photo<input type="file" accept="image/*" data-target="photo_url"></label><label>Logo URL<input name="logo_url" type="url"></label><label>Upload Logo<input type="file" accept="image/*" data-target="logo_url"></label><div class="tcp-media-library-box"><button type="button" class="tcp-button secondary" data-media-target="logo_url">Choose Logo from Media Library</button> <button type="button" class="tcp-button secondary" data-media-target="photo_url">Choose Photo from Media Library</button></div><label>Email<input name="email" type="email"></label><label>Phone<input name="phone" type="tel"></label><label>Mobile<input name="mobile" type="tel"></label><label>Website<input name="website" type="url"></label>
                <label>Address<input name="address"></label><label>Google service / Reviews URL<input name="google_service" type="url"></label><label>Google Maps URL<input name="maps_url" type="url"></label><label>Facebook username/link<input name="facebook" type="url" data-auto-link="facebook" data-source="facebook"></label><label>Instagram username/link<input name="instagram" type="url" data-auto-link="instagram" data-source="instagram"></label><label>LinkedIn<input name="linkedin" type="url"></label><label>TikTok<input name="tiktok" type="url"></label><label>YouTube<input name="youtube" type="url"></label><label>eFood URL<input name="efood" type="url"></label><label>Wolt URL<input name="wolt" type="url"></label><label>BOX URL<input name="box" type="url"></label><label>WhatsApp<input name="whatsapp"></label><label>Viber<input name="viber"></label><label>Telegram<input name="telegram"></label><div class="tcp-visibility-order"><h3>Πεδία εμφάνισης & σειρά</h3><div id="tcp-visible-fields"></div><div id="tcp-field-order"></div></div><p class="tcp-muted">Για social/delivery μπορείτε να δώσετε username ή URL· το link δημιουργείται αυτόματα όπου υπάρχει σαφής βάση.</p><label>Card background<input name="card_background" type="color" value="#ffffff"></label><label>Button background<input name="button_background" type="color" value="#eeeeee"></label><label>Button text color<input name="button_text" type="color" value="#202020"></label><label>Text color<input name="text_color" type="color" value="#202020"></label><div class="tcp-icon-selection"><h3>Εικονίδια εφαρμογών</h3><p class="tcp-muted">Επιλέξτε ένα έτοιμο εικονίδιο ή εικόνα από το WordPress Media Library για κάθε εφαρμογή.</p><label>Phone<input name="icon_phone" type="url" data-icon-select="phone" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_phone">Choose icon from Media Library</button></label><label>Mobile<input name="icon_mobile" type="url" data-icon-select="mobile" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_mobile">Choose icon from Media Library</button></label><label>Email<input name="icon_email" type="url" data-icon-select="email" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_email">Choose icon from Media Library</button></label><label>Website<input name="icon_website" type="url" data-icon-select="website" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_website">Choose icon from Media Library</button></label><label>Google<input name="icon_google_service" type="url" data-icon-select="google_service" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_google_service">Choose icon from Media Library</button></label><label>Maps<input name="icon_maps_url" type="url" data-icon-select="maps_url" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_maps_url">Choose icon from Media Library</button></label><label>Facebook<input name="icon_facebook" type="url" data-icon-select="facebook" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_facebook">Choose icon from Media Library</button></label><label>Instagram<input name="icon_instagram" type="url" data-icon-select="instagram" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_instagram">Choose icon from Media Library</button></label><label>LinkedIn<input name="icon_linkedin" type="url" data-icon-select="linkedin" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_linkedin">Choose icon from Media Library</button></label><label>TikTok<input name="icon_tiktok" type="url" data-icon-select="tiktok" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_tiktok">Choose icon from Media Library</button></label><label>YouTube<input name="icon_youtube" type="url" data-icon-select="youtube" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_youtube">Choose icon from Media Library</button></label><label>WhatsApp<input name="icon_whatsapp" type="url" data-icon-select="whatsapp" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_whatsapp">Choose icon from Media Library</button></label><label>Viber<input name="icon_viber" type="url" data-icon-select="viber" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_viber">Choose icon from Media Library</button></label><label>Telegram<input name="icon_telegram" type="url" data-icon-select="telegram" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_telegram">Choose icon from Media Library</button></label><label>eFood<input name="icon_efood" type="url" data-icon-select="efood" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_efood">Choose icon from Media Library</button></label><label>Wolt<input name="icon_wolt" type="url" data-icon-select="wolt" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_wolt">Choose icon from Media Library</button></label><label>BOX<input name="icon_box" type="url" data-icon-select="box" placeholder="Default icon or Media Library image URL"><button type="button" class="tcp-button secondary tcp-pwa-media" data-icon-target="icon_box">Choose icon from Media Library</button></label></div></div>
                <button class="tcp-button tcp-save-button" type="submit">Save Changes</button><button id="tcp-settings-exit" class="tcp-button tcp-exit-button" type="button">Έξοδος</button>
              </form><div id="tcp-user-result" class="tcp-result"></div>
            </section>
            <aside class="tcp-card"><h2>Card Preview</h2><div id="tcp-user-card-preview" class="tcp-mini-preview">Loading…</div><div id="tcp-user-qr" class="tcp-qr-box" hidden></div><button id="tcp-copy-url" class="tcp-button secondary" type="button">Copy Card Link</button></aside>
          </div>
        </section>';
        $this->render_shell('TapCard Pro — User App', 'tcp-user-page', $html);
    }

    private function render_customer_app($token) {
        $html = '
        <section class="tcp-card tcp-customer-card">
            <div id="tcp-customer-loading">Loading profile…</div>
            <div id="tcp-customer-content" hidden>
                <div id="tcp-card-style-vars"></div>
                <div class="tcp-identity"><img id="tcp-logo" class="tcp-logo" alt="" hidden><img id="tcp-photo" class="tcp-avatar" alt="" hidden></div>
                <h1 id="tcp-name" class="tcp-customer-name"></h1>
                <p id="tcp-company" class="tcp-muted"></p>
                <p id="tcp-title" class="tcp-muted"></p>
                <div id="tcp-actions" class="tcp-actions"></div>
                <button id="tcp-save-contact" class="tcp-button secondary" type="button">Αποθήκευση επαφής</button>
                <button id="tcp-share" class="tcp-button" type="button">Μοίρασε τη κάρτα</button>
                <button id="tcp-public-exit" class="tcp-button tcp-exit-button" type="button">Έξοδος</button>
                <div id="tcp-share-modal" class="tcp-share-modal" hidden>
                  <div class="tcp-share-dialog" role="dialog" aria-modal="true" aria-labelledby="tcp-share-title">
                    <button id="tcp-share-close" class="tcp-share-close" type="button" aria-label="Κλείσιμο">×</button>
                    <h2 id="tcp-share-title">Μοίρασε τη κάρτα</h2>
                    <div id="tcp-share-qr" class="tcp-share-qr"></div>
                    <label class="tcp-share-url-label">URL κάρτας<input id="tcp-share-url" readonly></label>
                    <div class="tcp-share-actions">
                      <button id="tcp-copy-card-url" class="tcp-button secondary" type="button">Αποθήκευση URL</button>
                      <button id="tcp-download-card-qr" class="tcp-button secondary" type="button">Αποθήκευση QR</button>
                      <button id="tcp-native-share" class="tcp-button" type="button">Κοινοποίηση</button>
                      <a id="tcp-email-share" class="tcp-button secondary" href="#">Email</a>
                      <a id="tcp-sms-share" class="tcp-button secondary" href="#">SMS</a>
                      <a id="tcp-whatsapp-share" class="tcp-button secondary" href="#" target="_blank" rel="noopener">WhatsApp</a>
                    </div>
                    <div id="tcp-share-result" class="tcp-result"></div>
                  </div>
                </div>
            </div>
        </section>
        <script>window.TapCardInitialToken=' . wp_json_encode($token) . ';</script>';
        $this->render_shell('TapCard Pro — Customer', 'tcp-customer-page', $html);
    }
}
