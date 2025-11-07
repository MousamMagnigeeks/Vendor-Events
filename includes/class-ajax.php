<?php
if (!defined('ABSPATH')) exit;

class VEN_Ajax {

    public function __construct() {
        add_action('wp_ajax_ven_fetch_title', array($this, 'fetch_title'));
        add_action('wp_ajax_nopriv_ven_fetch_title', array($this, 'fetch_title'));
        add_action('wp_ajax_ven_update_event_status', array($this, 'ven_update_event_status'));
        add_action( 'wp_ajax_ven_send_unpublish_reason', [ $this, 'ajax_send_unpublish_reason' ] );
    }

    public function ajax_send_unpublish_reason() {
        check_ajax_referer( 'ven_unpublish_reason', 'nonce' );
    
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $reason  = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
    
        if ( ! $post_id || empty( $reason ) ) {
            wp_send_json_error( [ 'message' => 'Invalid request. Provide a reason.' ] );
        }
    
        // ensure current user can edit this post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
        }
    
        // save meta
        update_post_meta( $post_id, 'unpublish_reason', $reason );
        update_post_meta( $post_id, 'last_unpublished_by', get_current_user_id() );
        update_post_meta( $post_id, 'last_unpublished_at', current_time( 'mysql' ) );
    
        // set post_status to 'unpublished' if not already (use wp_update_post so WP hooks run)
        $post = get_post( $post_id );
        if ( $post && $post->post_status !== 'unpublished' ) {
            wp_update_post( [
                'ID' => $post_id,
                'post_status' => 'unpublished',
            ] );
        }
    
        // Send email to vendor (post author)
        $vendor_id = get_post_field( 'post_author', $post_id );
        $vendor    = get_userdata( $vendor_id );
        $to        = $vendor ? $vendor->user_email : '';
    
        if ( $to ) {
            $subject = sprintf( 'Your event "%s" was unpublished', get_the_title( $post_id ) );
            $message = sprintf(
                "Hello %s,\n\nYour event \"%s\" has been unpublished by the site team.\n\nReason:\n%s\n\nRegards,\n%s",
                $vendor ? $vendor->display_name : 'Vendor',
                get_the_title( $post_id ),
                $reason,
                get_bloginfo( 'name' )
            );
    
            // optional headers (from)
            $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
    
            wp_mail( $to, $subject, $message, $headers );
        }
    
        wp_send_json_success( [ 'message' => 'Reason saved and email sent to vendor.' ] );
    }
    
    public function fetch_title() {
        check_ajax_referer('ven_ajax_nonce', 'nonce');

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        if (empty($url)) {
            wp_send_json_error('No URL provided');
        }

        // Basic URL validation and reject google search links
        if (strpos($url, 'google.') !== false && (strpos($url, '/search') !== false || strpos($url, 'q=') !== false)) {
            wp_send_json_error('Google/search links are not allowed');
        }

        // Use wp_remote_get with a small timeout
        $res = wp_remote_get($url, array('timeout' => 5, 'redirection' => 5));
        if (is_wp_error($res)) {
            wp_send_json_error('Unable to fetch URL: ' . $res->get_error_message());
        }
        $body = wp_remote_retrieve_body($res);
        if (empty($body)) {
            wp_send_json_error('Empty response from URL');
        }

        // Try to find og:title then <title>
        $title = '';
        if (preg_match('/<meta property=["\']og:title["\'] content=["\']([^"\']+)["\']/', $body, $m)) {
            $title = $m[1];
        } elseif (preg_match('/<meta name=["\']twitter:title["\'] content=["\']([^"\']+)["\']/', $body, $m)) {
            $title = $m[1];
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $body, $m)) {
            $title = $m[1];
        }
        $title = trim(html_entity_decode(strip_tags($title)));

        if (empty($title)) {
            wp_send_json_error('No usable title found on page');
        }

        // Return sanitized
        wp_send_json_success(array('title' => wp_strip_all_tags($title)));
    }

    public function ven_update_event_status() {
        // 🔐 Security check (if nonce passed from JS)
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ven_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
    
        $event_id = intval($_POST['event_id'] ?? 0);
        $status   = sanitize_text_field($_POST['status'] ?? '');
        $user_id  = get_current_user_id();
    
        if (!$event_id || !$status) {
            wp_send_json_error(['message' => 'Invalid data']);
        }
    
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'vendor_event') {
            wp_send_json_error(['message' => 'Invalid event']);
        }
    
        // 🧾 Get old status
        $old_status = get_post_meta($event_id, 'ven_vendor_status', true);
    
        // 🧠 Check permissions (author or admin)
        if ((int)$event->post_author !== (int)$user_id && !current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
    
        // ✅ Update new status
        update_post_meta($event_id, 'ven_vendor_status', $status);
    
        // 🎯 Send email if confirmed → skipping_this_year
        if ($old_status === 'confirmed' && $status === 'skipping_this_year') {
            $this->ven_send_skip_year_email($event_id);
        }
    
        // 🎫 Status labels
        $labels = [
            'applied'            => 'Applied/Waitlist',
            'confirmed'          => 'Confirmed by Organiser',
            'skipping_this_year' => 'Skipping This Year, Returning Next Year',
        ];
    
        $label = $labels[$status] ?? ucfirst($status);
    
        wp_send_json_success([
            'new_status'       => $status,
            'new_status_label' => $label,
        ]);
    }
    
    private function ven_send_skip_year_email($event_id) {
        $event_title = get_post_meta($event_id, 'ven_event_title', true);
        $vendor_id   = get_post_meta($event_id, 'ven_vendor_id', true);
        $vendor      = get_userdata($vendor_id);
    
        $start_date  = get_post_meta($event_id, 'ven_start_date', true);
        $end_date    = get_post_meta($event_id, 'ven_end_date', true);
        $country     = get_post_meta($event_id, 'ven_country', true);
        $state       = get_post_meta($event_id, 'ven_state', true);
    
        if (!$vendor) {
            return;
        }
    
        $vendor_email = $vendor->user_email;
        $vendor_name  = $vendor->display_name;
        $admin_email  = get_option('admin_email');
    
        $subject = 'Event Update: "' . $event_title . '" is skipping this year';
    
        // 🎯 Notify all "applied" vendors about the open slot
        $applied_vendors = get_posts([
            'post_type'      => 'vendor_event',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'ven_vendor_status',
                    'value' => 'applied',
                ]
            ],
        ]);
    
        if ($applied_vendors) {
            foreach ($applied_vendors as $applied_event_id) {
                $applied_vendor_id = get_post_meta($applied_event_id, 'ven_vendor_id', true);
                $applied_vendor    = get_userdata($applied_vendor_id);
                if (!$applied_vendor) continue;
    
                $to_email = $applied_vendor->user_email;
                $to_name  = $applied_vendor->display_name;
    
                $subject_applied = '🎟️ A Slot Just Opened Up for ' . $event_title;
                $message_applied  = '<h2 style="color:#2271b1;margin-bottom:10px;">A Vendor Slot is Now Available!</h2>';
                $message_applied .= '<p>Dear ' . esc_html($to_name) . ',</p>';
                $message_applied .= '<p>A confirmed vendor has decided to skip this year for the event <strong>"' . esc_html($event_title) . '"</strong>.</p>';
                $message_applied .= '<p>This means a new slot has opened up — and since you previously applied, we encourage you to <strong>reapply</strong> or confirm your interest soon!</p>';
                $message_applied .= '<table style="border-collapse:collapse;width:100%;max-width:600px;margin-top:10px;">';
                $message_applied .= '<tr><td><strong>Event:</strong></td><td>' . esc_html($event_title) . '</td></tr>';
                $message_applied .= '<tr><td><strong>Location:</strong></td><td>' . esc_html($state . ', ' . $country) . '</td></tr>';
                $message_applied .= '<tr><td><strong>Dates:</strong></td><td>' . esc_html($start_date . ' to ' . $end_date) . '</td></tr>';
                $message_applied .= '</table>';
                $message_applied .= '<p style="margin-top:15px;">You can check your dashboard or contact the organizer for next steps.</p>';
                $message_applied .= '<p>Warm regards,<br><strong>The Vendor Events Team</strong></p>';
    
                wp_mail(
                    $to_email,
                    $subject_applied,
                    $message_applied,
                    ['Content-Type: text/html; charset=UTF-8']
                );
            }
        }
    
        // 🎫 Email to the vendor whose event was changed to skipping
        $message  = '<h2 style="color:#2271b1;margin-bottom:10px;">Event Status Update</h2>';
        $message .= '<p>Dear ' . esc_html($vendor_name) . ',</p>';
        $message .= '<p>Your confirmed event <strong>"' . esc_html($event_title) . '"</strong> has been updated to <strong>Skipping This Year</strong>.</p>';
        $message .= '<p>You’ll need to reapply when registrations open next year.</p>';
        $message .= '<p>Thank you for being part of our vendor community!</p>';
        $message .= '<p>Warm regards,<br><strong>The Vendor Events Team</strong></p>';
    
        wp_mail(
            $vendor_email,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    
        // 🧾 Send admin a summary
        $admin_message  = '<h2 style="color:#2271b1;">Event Skipping Notice</h2>';
        $admin_message .= '<p>The following event has been marked as <strong>Skipping This Year</strong>:</p>';
        $admin_message .= '<ul>';
        $admin_message .= '<li><strong>Event:</strong> ' . esc_html($event_title) . '</li>';
        $admin_message .= '<li><strong>Vendor:</strong> ' . esc_html($vendor_name) . ' (' . esc_html($vendor_email) . ')</li>';
        $admin_message .= '<li><strong>Location:</strong> ' . esc_html($state . ', ' . $country) . '</li>';
        $admin_message .= '</ul>';
        $admin_message .= '<p>All vendors with status <strong>Applied/Waitlist</strong> have been notified about the open slot.</p>';
    
        wp_mail(
            $admin_email,
            '[Admin Notice] ' . $subject,
            $admin_message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }    
    
}
