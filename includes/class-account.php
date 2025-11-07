<?php
if (!defined('ABSPATH')) exit;

class VEN_Account {

    public function __construct() {
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'), 100);
        add_action('init', array($this, 'add_endpoint'));
        add_action('woocommerce_account_my-events_endpoint', array($this, 'render_account_events'));
        add_action('admin_post_ven_delete_event', array($this, 'handle_delete_event'));
    }

    public function add_menu_item($items) {
        $items = array_slice($items, 0, 1, true) + array('my-events' => 'My Events') + array_slice($items, 1, NULL, true);
        return $items;
    }
    
    public function add_endpoint() {
        add_rewrite_endpoint('my-events', EP_PAGES);
    }

    public function render_account_events() {
        if (!is_user_logged_in()) {
            echo '<p>Please login.</p>';
            return;
        }

        $user_id = get_current_user_id();
    
        // 🔹 Check if user clicked "Add Event"
        if (isset($_GET['action']) && $_GET['action'] === 'add') {
            echo '<h2>Add New Event</h2>';
            echo do_shortcode('[ven_add_event_form]');
            return;
        }

        // 🔹 Check if user clicked "Edit Event"
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['post_id'])) {
            $post_id = intval($_GET['post_id']);
            $event = get_post($post_id);

            if (!$event || $event->post_type !== 'vendor_event') {
                echo '<p>Invalid event.</p>';
                return;
            }

            // Only allow event owner or admin to edit
            if ($event->post_author != $user_id && !current_user_can('administrator')) {
                echo '<p>You do not have permission to edit this event.</p>';
                return;
            }

            echo '<h2>Edit Event</h2>';
            echo do_shortcode('[ven_add_event_form edit_event="' . esc_attr($post_id) . '"]');
            return;
        }
    
        // 🔹 Otherwise, show list of events
        $user_id = get_current_user_id();
    
        $q = new WP_Query(array(
            'post_type'      => 'vendor_event',
            'posts_per_page' => -1,
            'author'         => $user_id,
            'orderby'        => 'meta_value',
            'meta_key'       => 'ven_start_date',
            'order'          => 'DESC',
            'post_status'    => ['publish', 'unpublished'],
        ));
        ?>
        <h2>Your Events</h2>
        <p>
            <a href="<?php echo esc_url(add_query_arg('action', 'add', wc_get_account_endpoint_url('my-events'))); ?>" class="button">
                Add New Event
            </a>
        </p>
        <div class="ven-events-table-wrapper">
            <table class="ven-events-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Post Status</th> 
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>State</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($q->have_posts()) { while ($q->have_posts()) { $q->the_post();
                        $pid = get_the_ID();
                        $title = get_post_meta($pid, 'ven_event_title', true) ?: get_the_title($pid);
                        $status = get_post_meta($pid, 'ven_vendor_status', true);
                        $start = get_post_meta($pid, 'ven_start_date', true);
                        $state = get_post_meta($pid, 'ven_state', true);
                        $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ven_delete_event&post=' . $pid), 'ven_delete_event_' . $pid);
                        $edit_link = add_query_arg(array('action' => 'edit', 'post_id' => $pid), wc_get_account_endpoint_url('my-events'));

                        // ✅ Get actual post status (publish / unpublished / draft etc.)
                        $post_status = get_post_status($pid);

                        // Optional: make it more readable with colors
                        $status_labels = [
                            'publish'     => '<span style="color:green;font-weight:600;">Published</span>',
                            'unpublished' => '<span style="color:#a00;font-weight:600;">Unpublished</span>',
                            'draft'       => '<span style="color:gray;">Draft</span>',
                            'pending'     => '<span style="color:orange;">Pending</span>',
                            'trash'       => '<span style="color:red;">Trashed</span>',
                        ];
                    ?>
                    <tr>
                        <td><?php echo esc_html($title); ?></td>
                        <td>
                            <?php echo isset($status_labels[$post_status]) 
                                ? $status_labels[$post_status] 
                                : esc_html(ucfirst($post_status)); ?>
                        </td>
                        <td>
                            <select class="ven-status-select-frontend" data-event-id="<?php echo esc_attr($pid); ?>">
                                <option value="applied" <?php selected($status, 'applied'); ?>>Applied/Waitlist</option>
                                <option value="confirmed" <?php selected($status, 'confirmed'); ?>>Confirmed by Organiser</option>
                                <option value="skipping_this_year" <?php selected($status, 'skipping_this_year'); ?>>Skipping This Year, Returning Next Year</option>
                            </select>
                        </td>
                        <td><?php echo esc_html($start); ?></td>
                        <td><?php echo esc_html($state); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_link); ?>" class="tbutton">Edit</a>
                            <a href="<?php echo esc_url($delete_url); ?>" class="tbutton">Delete</a>
                        </td>
                    </tr>
                    <?php } wp_reset_postdata(); } else { ?>
                    <tr><td colspan="5">You have not added any events yet.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }    

    public function handle_delete_event() {
        if (!isset($_GET['post']) || !is_user_logged_in()) {
            wp_safe_redirect(wp_get_referer());
            exit;
        }
        $post_id = intval($_GET['post']);
        if (!wp_verify_nonce($_SERVER['REQUEST_URI'], 'ven_delete_event_' . $post_id)) {
            // fallback: check nonce param
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ven_delete_event_' . $post_id)) {
                wp_die('Security check failed.');
            }
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_safe_redirect(wp_get_referer());
            exit;
        }
        $current = get_current_user_id();
        if ((int)$post->post_author !== (int)$current && !current_user_can('manage_options')) {
            wp_die('You do not have permission to delete this event.');
        }

        // admin notification
        do_action('ven_event_deleted', $post_id);

        // delete post
        wp_delete_post($post_id, true);

        wp_safe_redirect(add_query_arg('ven_deleted', '1', wp_get_referer()));
        exit;
    }
}