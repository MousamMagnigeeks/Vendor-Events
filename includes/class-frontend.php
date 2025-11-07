<?php
if (!defined('ABSPATH')) exit;

class VEN_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('ven_add_event_form', array($this, 'render_form_shortcode'));
        add_action('init', array($this, 'handle_form_post'));

    }

    public function enqueue_assets() {
        wp_register_script('ven-form-js', VEN_PLUGIN_URL . 'assets/js/ven-form.js', array('jquery'), time(), true);
        wp_register_style('ven-style', VEN_PLUGIN_URL . 'assets/css/ven-style.css', array(), time());
        wp_localize_script('ven-form-js', 'ven_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ven_ajax_nonce'),
        ));
        wp_enqueue_style('ven-style');
        wp_enqueue_script('ven-form-js');

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
    }

    public function render_form_shortcode($atts = array()) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to submit an event.</p>';
        }
    
        $user = wp_get_current_user();
        $is_edit = isset($_GET['action'], $_GET['post_id']) && $_GET['action'] === 'edit' && is_numeric($_GET['post_id']);
        $post_id = $is_edit ? intval($_GET['post_id']) : 0;
    
        // Prefill values if editing
        $event_url      = $is_edit ? get_post_meta($post_id, 'ven_event_url', true) : '';
        $event_title    = $is_edit ? get_post_meta($post_id, 'ven_event_title', true) : '';
        $start_date     = $is_edit ? get_post_meta($post_id, 'ven_start_date', true) : '';
        $end_date       = $is_edit ? get_post_meta($post_id, 'ven_end_date', true) : '';
        $country        = $is_edit ? get_post_meta($post_id, 'ven_country', true) : 'US';
        $state_province = $is_edit ? get_post_meta($post_id, 'ven_state', true) : '';
        $vendor_status  = $is_edit ? get_post_meta($post_id, 'ven_vendor_status', true) : '';
        $phone          = $is_edit ? get_post_meta($post_id, 'ven_phone', true) : '';
    
        ob_start();
        ?>
        <form id="ven-add-event" method="post" class="ven-form">
            <?php wp_nonce_field('ven_add_event', 'ven_add_event_nonce'); ?>
            <input type="hidden" name="ven_action" value="<?php echo $is_edit ? 'edit_event' : 'add_event'; ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
            <?php endif; ?>
    
            <p>
                <label>Event URL (required)</label><br>
                <input type="url" name="event_url" id="ven_event_url" value="<?php echo esc_attr($event_url); ?>" required placeholder="https://">
                <?php if (!$is_edit): ?>
                    <button type="button" id="ven_fetch_title" class="button">Fetch Title</button>
                <?php endif; ?>
                <br><small>Official event website, Eventbrite or Facebook Event. Google search links are not accepted.</small>
            </p>
    
            <p>
                <label>Event Title (required)</label><br>
                <input type="text" name="event_title" id="ven_event_title" value="<?php echo esc_attr($event_title); ?>" required>
                <br><small>Please enter event title using letters and spaces only. Numbers (e.g. 25, 2025) and symbols are not allowed. Example: write "Fourth Annual Harvest Fair" istead of "4th Annual Harvest Fair".</small>
            </p>
    
            <p>
                <label>Start Date (required)</label><br>
                <input type="text" name="start_date" id="ven_start_date" value="<?php echo esc_attr($start_date); ?>" required>
            </p>
    
            <p>
                <label>End Date (optional)</label><br>
                <input type="text" name="end_date" id="ven_end_date" value="<?php echo esc_attr($end_date); ?>">
            </p>
    
            <p>
                <label>Country (required)</label><br>
                <select name="country" id="ven_country">
                    <option value="">Select Country</option>
                    <option value="US" <?php selected($country, 'US'); ?>>United States</option>
                    <option value="Canada" <?php selected($country, 'Canada'); ?>>Canada</option>
                </select>
            </p>
    
            <p>
                <label>State/Province (required)</label><br>
                <select name="state_province" id="ven_state_province" required>
                    <option value="">Select State/Province</option>
                </select>
            </p>

            <!-- Pass selected data to JS -->
            <script>
                window.venLocationData = {
                    selectedCountry: "<?php echo esc_js($country); ?>",
                    selectedState: "<?php echo esc_js($state_province); ?>"
                };
            </script>
    
            <p>
                <label>Vendor Status (required)</label><br>
                <select name="vendor_status" id="ven_vendor_status">
                    <option value="applied" <?php selected($vendor_status, 'applied'); ?>>Applied/Waitlist</option>
                    <option value="confirmed" <?php selected($vendor_status, 'confirmed'); ?>>Confirmed by Vendor</option>
                    <option value="skipping_this_year" <?php selected($vendor_status, 'skipping_this_year'); ?>>Skipping This Year, Returning Next Year</option>
                </select>
                <br><small>Events with status "Applied/Waitlist" are not published, but they remain on file for future openings.</small>
            </p>
    
            <p>
                <label>Email (required)</label><br>
                <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
            </p>
    
            <p>
                <label>Phone (required)</label><br>
                <input type="text" name="phone" value="<?php echo esc_attr($phone); ?>" required>
            </p>
    
            <p>
                <?php if ($is_edit): ?>
                    <button type="submit" name="save" value="update" class="button button-primary">Update Event</button>
                <?php else: ?>
                    <button type="submit" name="save" value="exit" class="button">Save & Exit</button>
                    <button type="submit" name="save" value="addnew" class="button">Save & Add New</button>
                <?php endif; ?>
            </p>
        </form>
        <div id="ven-response"></div>
        <?php
        return ob_get_clean();
    }    

    public function handle_form_post() {
        if (!isset($_POST['ven_action'])) return;
        if (!is_user_logged_in()) return;
    
        if (!isset($_POST['ven_add_event_nonce']) || !wp_verify_nonce($_POST['ven_add_event_nonce'], 'ven_add_event')) {
            wp_die('Nonce verification failed');
        }
    
        $user_id = get_current_user_id();
        $action_type = sanitize_text_field($_POST['ven_action']);
    
        // Gather form data
        $event_url     = esc_url_raw(trim($_POST['event_url'] ?? ''));
        $event_title   = sanitize_text_field(trim($_POST['event_title'] ?? ''));
        $start_date    = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date      = sanitize_text_field($_POST['end_date'] ?? '');
        $country       = sanitize_text_field($_POST['country'] ?? 'US');
        $state         = sanitize_text_field(trim($_POST['state_province'] ?? ''));
        $vendor_status = sanitize_text_field($_POST['vendor_status'] ?? 'applied');
        $email         = sanitize_email($_POST['email'] ?? '');
        $phone         = sanitize_text_field($_POST['phone'] ?? '');
        $save_action   = sanitize_text_field($_POST['save'] ?? 'exit');
    
        if (empty($event_url) || empty($event_title) || empty($start_date) || empty($state) || empty($phone)) {
            wp_die('Please fill all required fields.');
        }
    
        if (strpos($event_url, 'google.') !== false && (strpos($event_url, '/search') !== false || strpos($event_url, 'q=') !== false)) {
            wp_die('Google search links are not allowed.');
        }
    
        if (!preg_match('/^[A-Za-z\s]+$/u', $event_title)) {
            wp_die('Event title may only contain letters and spaces.');
        }
    
        $normalized_title = VEN_CPT::normalize_title($event_title);
        $start_month = date('Y-m', strtotime($start_date));
    
        $meta_fields = array(
            'ven_event_url'        => $event_url,
            'ven_event_title'      => $event_title,
            'ven_normalized_title' => $normalized_title,
            'ven_start_date'       => $start_date,
            'ven_end_date'         => $end_date,
            'ven_start_month'      => $start_month,
            'ven_country'          => $country,
            'ven_state'            => $state,
            'ven_vendor_status'    => $vendor_status,
            'ven_email'            => $email,
            'ven_phone'            => $phone,
            'ven_vendor_id'        => $user_id,
        );
    
        // 🔹 Handle Add vs Edit
        if ($action_type === 'add_event') {
            // Duplicate check only for new posts
            if (VEN_CPT::is_duplicate_confirmed($normalized_title, $start_month, $state, $event_url, $country)) {
                wp_die('A confirmed event with the same title, month, state, and URL already exists.');
            }
    
            // Create new post
            $post_id = wp_insert_post(array(
                'post_type'   => 'vendor_event',
                'post_title'  => $event_title,
                'post_status' => 'unpublished',
                'post_author' => $user_id,
            ));
    
            if (is_wp_error($post_id) || $post_id === 0) {
                wp_die('Failed to create event post.');
            }
    
            do_action('ven_event_created', $post_id);
        }
        elseif ($action_type === 'edit_event') {
            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id || get_post_field('post_author', $post_id) != $user_id) {
                wp_die('You are not allowed to edit this event.');
            }
    
            // Update post title
            wp_update_post(array(
                'ID'         => $post_id,
                'post_title' => $event_title,
            ));
    
            do_action('ven_event_updated', $post_id);
        } else {
            return;
        }
    
        // Save meta
        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    
        // Redirect
        wp_safe_redirect(add_query_arg('ven_status', 'saved', wc_get_account_endpoint_url('my-events')));
        exit;
    }       
}
