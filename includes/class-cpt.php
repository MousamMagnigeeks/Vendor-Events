<?php
if (!defined('ABSPATH')) exit;

class VEN_CPT {

    public $post_type = 'vendor_event';

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_assets'));

        add_action('init', array($this, 'register_cpt'));
        add_action('add_meta_boxes', [$this, 'add_event_metabox']);
        add_action('save_post_' . $this->post_type, array($this, 'on_save'), 10, 3);
        add_action('init', array($this, 'register_unpublished_status'));

        add_action('admin_footer-post.php', array($this, 'add_unpublished_to_dropdown'));
        add_action('admin_footer-post-new.php', array($this, 'add_unpublished_to_dropdown'));
        add_action('admin_footer-edit.php', array($this, 'add_unpublished_to_dropdown_into_inline_edit'));

        // Add and manage the column
        add_filter( 'manage_vendor_event_posts_columns', [ $this, 'add_status_column' ] );
        add_action( 'manage_vendor_event_posts_custom_column', [ $this, 'render_status_column' ], 10, 2 );

        // Optional: make column sortable
        add_filter( 'manage_edit-vendor_event_sortable_columns', [ $this, 'make_status_sortable' ] );

        add_filter('views_edit-vendor_event', array($this, 'add_recently_published_tab'));
        add_action('pre_get_posts', array($this, 'filter_vendor_event_recently_published'));

        // Add admin columns & filters
        add_filter('manage_edit-' . $this->post_type . '_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . $this->post_type . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-' . $this->post_type . '_sortable_columns', [$this, 'make_columns_sortable']);
        add_action('pre_get_posts', [$this, 'handle_sorting']);

        // Add dropdown filters
        add_action('restrict_manage_posts', [$this, 'add_admin_filters']);
        add_filter('parse_query', [$this, 'filter_admin_query']);
    }

    public function admin_enqueue_assets($hook) {
        // only load on post edit screens
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        global $post;
        if ( empty( $post ) || $post->post_type !== 'vendor_event' ) {
            return;
        }

        wp_enqueue_script(
            'ven-unpublished-admin',
            VEN_PLUGIN_URL . 'assets/js/ven-unpublished-admin.js',
            [ 'jquery' ],
            '1.0',
            true
        );

        wp_enqueue_style(
            'ven-unpublished-admin',
            VEN_PLUGIN_URL . 'assets/css/ven-unpublished-admin.css',
            [],
            '1.0'
        );        

        wp_localize_script( 'ven-unpublished-admin', 'venUnpublish',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ven_unpublish_reason' ),
                'post_id'  => (int) $post->ID,
            ]
        );

    }
    public function register_cpt() {
        $this->post_type = 'vendor_event';
    
        $labels = array(
            'name' => 'Vendor Events',
            'singular_name' => 'Vendor Event',
            'menu_name' => 'Vendor Events'
        );
    
        $args = array(
            'labels' => $labels,
            'public' => true, // allow front-end submissions
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'supports' => array('title'),
            'menu_position' => 26,
        );
    
        register_post_type($this->post_type, $args);
    }    

    public function add_event_metabox() {
        add_meta_box(
            'vendor_event_details',
            __('Event Details', 'vendor-events'),
            [$this, 'render_event_metabox'],
            'vendor_event',
            'normal',
            'high'
        );
    }

    public function register_unpublished_status() {
        register_post_status('unpublished', [
            'label'                     => _x('Unpublished', 'post'),
            'public'                    => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Unpublished <span class="count">(%s)</span>', 'Unpublished <span class="count">(%s)</span>'),
        ]);
    }

    public function add_unpublished_to_dropdown() {
        global $post;
        if ( ! $post || $post->post_type !== 'vendor_event' ) {
            return;
        }

        $vendor_status = get_post_meta( $post->ID, 'ven_vendor_status', true );
        if ( $vendor_status !== 'applied' ) {
            return;
        }
        ?>
        <script>
            jQuery( function( $ ) {
                // add our custom post status
                $( '#post_status' ).append( '<option value="unpublished">Unpublished</option>' )

                <?php if( 'unpublished' === get_post_status() ) : ?>
                    $( '#post-status-display' ).text( 'Unpublished' )
                    $( '#post_status' ).val( 'unpublished' )
                <?php endif; ?>
            } )
        </script>
        <?php
    }

    public function add_unpublished_to_dropdown_into_inline_edit() {
        ?><script>
        jQuery( function($) {
            $( 'select[name="_status"]' ).append( '<option value="unpublished">Unpublished</option>' );
        } );
        </script><?php
    }

    /**
     * Add "Status" column to vendor_event post list.
     */
    public function add_status_column( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( $key === 'title' ) {
                $new_columns['post_status'] = __( 'Status', 'textdomain' );
            }
        }
        return $new_columns;
    }

    /**
     * Output the status value in each row.
     */
    public function render_status_column( $column, $post_id ) {
        if ( $column !== 'post_status' ) {
            return;
        }

        $status = get_post_status( $post_id );

        // Map status to readable label and color
        $labels = [
            'publish'     => '<span style="color:green;font-weight:600;">Published</span>',
            'draft'       => '<span style="color:gray;">Draft</span>',
            'pending'     => '<span style="color:orange;">Pending</span>',
            'trash'       => '<span style="color:red;">Trash</span>',
            'unpublished' => '<span style="color:#a00;">Unpublished</span>',
        ];

        echo isset( $labels[ $status ] ) ? $labels[ $status ] : esc_html( ucfirst( $status ) );
    }

    /**
     * Make the "Status" column sortable.
     */
    public function make_status_sortable( $columns ) {
        $columns['post_status'] = 'post_status';
        return $columns;
    }
    
    public function add_recently_published_tab($views) {
        global $wpdb;

        // Count how many were published in the last 7 days
        $recent_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $wpdb->posts 
            WHERE post_type = 'vendor_event' 
            AND post_status = 'publish' 
            AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        // Check if the "recent" filter is active
        $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'recently_published') ? ' class="current"' : '';

        // Add custom view link
        $views['recently_published'] = sprintf(
            '<a href="%s"%s>Recently Published <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=vendor_event&post_status=recently_published'),
            $class,
            $recent_count
        );

        return $views;
    }

    public function filter_vendor_event_recently_published($query) {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }

        if (isset($_GET['post_type']) && $_GET['post_type'] === 'vendor_event' && isset($_GET['post_status']) && $_GET['post_status'] === 'recently_published') {
            $query->set('post_type', 'vendor_event');
            $query->set('post_status', 'publish');
            $query->set('date_query', [
                [
                    'after' => '7 days ago'
                ]
            ]);
        }
    }
    public function render_event_metabox($post) {
        // Retrieve meta values
        $event_url      = get_post_meta($post->ID, 'ven_event_url', true);
        $event_title    = get_post_meta($post->ID, 'ven_event_title', true);
        $start_date     = get_post_meta($post->ID, 'ven_start_date', true);
        $end_date       = get_post_meta($post->ID, 'ven_end_date', true);
        $country        = get_post_meta($post->ID, 'ven_country', true);
        $state          = get_post_meta($post->ID, 'ven_state', true);
        $vendor_status  = get_post_meta($post->ID, 'ven_vendor_status', true);
        $email          = get_post_meta($post->ID, 'ven_email', true);
        $phone          = get_post_meta($post->ID, 'ven_phone', true);
        $vendor_id      = get_post_meta($post->ID, 'ven_vendor_id', true);

        $start_date_value = '';
        if (!empty($start_date)) {
            $timestamp = strtotime($start_date);
            if ($timestamp) {
                $start_date_value = date('Y-m-d', $timestamp);
            }
        }

        $end_date_value = '';
        if (!empty($end_date)) {
            $timestamp = strtotime($end_date);
            if ($timestamp) {
                $end_date_value = date('Y-m-d', $timestamp);
            }
        }
        ?>

        <table class="form-table">
            <tr>
                <th><label for="ven_event_url">Event URL</label></th>
                <td><input type="url" id="ven_event_url" name="ven_event_url" value="<?php echo esc_attr($event_url); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ven_event_title">Event Title</label></th>
                <td><input type="text" id="ven_event_title" name="ven_event_title" value="<?php echo esc_attr($event_title); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ven_start_date">Start Date</label></th>
                <td><input type="date" id="ven_start_date" name="ven_start_date" value="<?php echo esc_attr($start_date_value); ?>" /></td>
            </tr>
            <tr>
                <th><label for="ven_end_date">End Date</label></th>
                <td><input type="date" id="ven_end_date" name="ven_end_date" value="<?php echo esc_attr($end_date_value); ?>" /></td>
            </tr>
            <tr>
                <th><label for="ven_country">Country</label></th>
                <td>
                    <select name="ven_country" id="ven_country">
                        <option value="">Select Country</option>
                        <option value="US" <?php selected($country, 'US'); ?>>United States</option>
                        <option value="Canada" <?php selected($country, 'Canada'); ?>>Canada</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ven_state">State / Province</label></th>
                <td>
                    <select name="ven_state" id="ven_state_province">
                        <option value="">Select State/Province</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ven_vendor_status">Vendor Status</label></th>
                <td>
                    <select name="ven_vendor_status" id="ven_vendor_status">
                        <option value="applied" <?php selected($vendor_status, 'applied'); ?>>Applied/Waitlist</option>
                        <option value="confirmed" <?php selected($vendor_status, 'confirmed'); ?>>Confirmed by Organiser</option>
                        <option value="skipping_this_year" <?php selected($vendor_status, 'skipping_this_year'); ?>>Skipping This Year, Returning Next Year</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="ven_email">Vendor Email</label></th>
                <td><input type="email" id="ven_email" name="ven_email" value="<?php echo esc_attr($email); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ven_phone">Vendor Phone</label></th>
                <td><input type="text" id="ven_phone" name="ven_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="ven_vendor_id">Vendor ID (User)</label></th>
                <td><input type="number" id="ven_vendor_id" name="ven_vendor_id" value="<?php echo esc_attr($vendor_id); ?>" class="small-text" /></td>
            </tr>
        </table>
        <script>
            window.venLocationData = {
                selectedCountry: "<?php echo esc_js($country); ?>",
                selectedState: "<?php echo esc_js($state); ?>"
            };
        </script>
        <?php
    }

    public function on_save($post_ID, $post, $update) {
        // 1️⃣ Prevent saving during autosave or revisions
        if (wp_is_post_autosave($post_ID) || wp_is_post_revision($post_ID)) {
            return;
        }
    
        // 2️⃣ Verify user capabilities
        if (!current_user_can('edit_post', $post_ID)) {
            return;
        }
    
        // 3️⃣ Sanitize and save fields
        $fields = [
            'ven_event_url'      => 'esc_url_raw',
            'ven_event_title'    => 'sanitize_text_field',
            'ven_start_date'     => 'sanitize_text_field',
            'ven_end_date'       => 'sanitize_text_field',
            'ven_country'        => 'sanitize_text_field',
            'ven_state'          => 'sanitize_text_field',
            'ven_vendor_status'  => 'sanitize_text_field',
            'ven_email'          => 'sanitize_email',
            'ven_phone'          => 'sanitize_text_field',
            'ven_vendor_id'      => 'intval',
        ];
    
        foreach ($fields as $key => $sanitize_callback) {
            if (isset($_POST[$key])) {
                $value = call_user_func($sanitize_callback, $_POST[$key]);
                update_post_meta($post_ID, $key, $value);
            }
        }
    }    

    public function add_admin_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'title') {
                // Insert after the Title column
                $new_columns['ven_event_title'] = __('Event Title', 'vendor-events');
                $new_columns['ven_country'] = __('Country', 'vendor-events');
                $new_columns['ven_state'] = __('State', 'vendor-events');
                $new_columns['ven_vendor_status'] = __('Vendor Status', 'vendor-events');
                $new_columns['ven_vendor_id'] = __('Vendor ID', 'vendor-events');
            }
        }
        return $new_columns;
    }

    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'ven_event_title':
                echo esc_html(get_post_meta($post_id, 'ven_event_title', true));
                break;
    
            case 'ven_country':
                echo esc_html(get_post_meta($post_id, 'ven_country', true));
                break;
    
            case 'ven_state':
                echo esc_html(get_post_meta($post_id, 'ven_state', true));
                break;
    
            case 'ven_vendor_status':
                echo esc_html(ucwords(str_replace('_', ' ', get_post_meta($post_id, 'ven_vendor_status', true))));
                break;
    
            case 'ven_vendor_id':
                echo esc_html(get_post_meta($post_id, 'ven_vendor_id', true));
                break;
        }
    }

    public function make_columns_sortable($columns) {
        $columns['ven_event_title'] = 'ven_event_title';
        $columns['ven_country'] = 'ven_country';
        $columns['ven_state'] = 'ven_state';
        $columns['ven_vendor_status'] = 'ven_vendor_status';
        return $columns;
    }
    
    public function handle_sorting($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
    
        $orderby = $query->get('orderby');
    
        if (in_array($orderby, ['ven_event_title', 'ven_country', 'ven_state', 'ven_vendor_status'])) {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }
    }

    public function add_admin_filters() {
        global $typenow;
        if ($typenow !== $this->post_type) {
            return;
        }
    
        // Vendor Status Filter
        $selected_status = isset($_GET['ven_vendor_status']) ? $_GET['ven_vendor_status'] : '';
        $statuses = [
            'applied' => 'Applied/Waitlist',
            'confirmed' => 'Confirmed by Organiser',
            'skipping_this_year' => 'Skipping This Year',
        ];
    
        echo '<select name="ven_vendor_status">';
        echo '<option value="">All Vendor Statuses</option>';
        foreach ($statuses as $key => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($selected_status, $key, false), esc_html($label));
        }
        echo '</select>';
    
        // Country Filter
        $selected_country = isset($_GET['ven_country']) ? $_GET['ven_country'] : '';
        $countries = $this->get_unique_meta_values('ven_country');
        echo '<select name="ven_country">';
        echo '<option value="">All Countries</option>';
        foreach ($countries as $country) {
            printf('<option value="%s" %s>%s</option>', esc_attr($country), selected($selected_country, $country, false), esc_html($country));
        }
        echo '</select>';
    
        // State Filter
        $selected_state = isset($_GET['ven_state']) ? $_GET['ven_state'] : '';
        $states = $this->get_unique_meta_values('ven_state');
        echo '<select name="ven_state">';
        echo '<option value="">All States</option>';
        foreach ($states as $state) {
            printf('<option value="%s" %s>%s</option>', esc_attr($state), selected($selected_state, $state, false), esc_html($state));
        }
        echo '</select>';

        // 🔹 Vendor Name Filter
        $selected_vendor = isset($_GET['ven_vendor_id']) ? intval($_GET['ven_vendor_id']) : '';
        $vendors = $this->get_vendors_with_published_posts();

        echo '<select name="ven_vendor_id">';
        echo '<option value="">All Vendors</option>';
        foreach ($vendors as $vendor_id => $vendor_name) {
            printf('<option value="%d" %s>%s</option>', $vendor_id, selected($selected_vendor, $vendor_id, false), esc_html($vendor_name));
        }
        echo '</select>';
    }

    private function get_vendors_with_published_posts() {
        $args = [
            'post_type'      => $this->post_type,
            'post_status'    => 'all',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        $post_ids = get_posts($args);
    
        $vendors = [];
    
        foreach ($post_ids as $post_id) {
            $vendor_id = get_post_field('post_author', $post_id);
            if ($vendor_id) {
                $vendor = get_userdata($vendor_id);
                if ($vendor) {
                    $vendors[$vendor_id] = $vendor->display_name;
                }
            }
        }
    
        asort($vendors); // Sort alphabetically by name
        return $vendors;
    }    

    public function filter_admin_query($query) {
        global $pagenow;
        if ($pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== $this->post_type) {
            return;
        }
    
        $meta_query = [];
    
        if (!empty($_GET['ven_vendor_status'])) {
            $meta_query[] = [
                'key' => 'ven_vendor_status',
                'value' => sanitize_text_field($_GET['ven_vendor_status']),
            ];
        }
    
        if (!empty($_GET['ven_country'])) {
            $meta_query[] = [
                'key' => 'ven_country',
                'value' => sanitize_text_field($_GET['ven_country']),
            ];
        }
    
        if (!empty($_GET['ven_state'])) {
            $meta_query[] = [
                'key' => 'ven_state',
                'value' => sanitize_text_field($_GET['ven_state']),
            ];
        }
    
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        // 🔹 Filter by Vendor (Author)
        if (!empty($_GET['ven_vendor_id'])) {
            $query->set('author', intval($_GET['ven_vendor_id']));
        }
    }

    private function get_unique_meta_values($meta_key) {
        global $wpdb;
        $results = $wpdb->get_col(
            $wpdb->prepare("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
                            WHERE meta_key = %s AND meta_value != '' 
                            ORDER BY meta_value ASC", $meta_key)
        );
        return $results;
    }

    // Normalize title for duplicate checks: lowercase, collapse spaces
    public static function normalize_title($title) {
        $t = mb_strtolower($title, 'UTF-8');
        $t = preg_replace('/\s+/', ' ', trim($t));
        return $t;
    }

    // Exact duplicate check against confirmed events
    public static function is_duplicate_confirmed($normalized_title, $start_month, $state, $url, $country) {
        global $wpdb;
    
        $query = new WP_Query(array(
            'post_type'      => 'vendor_event',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'ven_normalized_title',
                    'value'   => $normalized_title,
                    'compare' => '='
                ),
                array(
                    'key'     => 'ven_start_month',
                    'value'   => $start_month,
                    'compare' => '='
                ),
                array(
                    'key'     => 'ven_state',
                    'value'   => $state,
                    'compare' => '='
                ),
                array(
                    'key'     => 'ven_country',
                    'value'   => $country,
                    'compare' => '='
                ),
                array(
                    'key'     => 'ven_event_url',
                    'value'   => $url,
                    'compare' => '='
                ),
                array(
                    'key'     => 'ven_vendor_status',
                    'value'   => 'confirmed',
                    'compare' => '='
                ),
            ),
            'fields' => 'ids',
        ));
    
        if ($query->have_posts()) {
            error_log('Duplicate confirmed: ' . print_r($query->posts, true)); // ✅ Debug line
            return true;
        } else {
            error_log('No duplicate found for: ' . $normalized_title . ' | ' . $start_month . ' | ' . $state);
            return false;
        }
    }    
}
