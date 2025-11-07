<?php
if (!defined('ABSPATH')) exit;

class VEN_Notify {

    public function __construct() {
        add_action('ven_event_created', array($this, 'on_event_created'),10,1);
        add_action('ven_event_deleted', array($this, 'on_event_deleted'),10,1);

        // 🕒 Schedule Daily Digest
        add_action('wp', [$this, 'schedule_daily_digest']);
        add_action('vendor_event_daily_digest', [$this, 'send_daily_digest']);
    }

    public function on_event_created($post_id) {
        $post = get_post($post_id);
        $admin_email = get_option('admin_email');
        $event_title = get_post_meta($post_id, 'ven_event_title', true);
        $vendor_id   = get_post_meta($post_id, 'ven_vendor_id', true);
        $vendor      = get_userdata($vendor_id);

        $vendor_name  = $vendor ? esc_html($vendor->display_name) : 'Unknown Vendor';
        $vendor_email = $vendor ? esc_html($vendor->user_email) : 'N/A';

        $edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');

        $subject = '🆕 New Vendor Event Submitted: ' . $event_title;

        $message  = '<h2 style="color:#2271b1;margin-bottom:10px;">New Event Submitted</h2>';
        $message .= '<p>A new vendor event has been submitted and is awaiting review.</p>';
        $message .= '<table style="border-collapse:collapse;width:100%;max-width:600px;">';
        $message .= '<tr><td style="padding:6px 10px;"><strong>Event Title:</strong></td><td>' . esc_html($event_title) . '</td></tr>';
        $message .= '<tr><td style="padding:6px 10px;"><strong>Vendor Name:</strong></td><td>' . $vendor_name . '</td></tr>';
        $message .= '<tr><td style="padding:6px 10px;"><strong>Vendor Email:</strong></td><td>' . $vendor_email . '</td></tr>';
        $message .= '<tr><td style="padding:6px 10px;"><strong>Status:</strong></td><td>' . esc_html($post->post_status) . '</td></tr>';
        $message .= '</table>';
        $message .= '<p><a href="' . esc_url($edit_link) . '" style="background:#2271b1;color:#fff;padding:8px 14px;border-radius:4px;text-decoration:none;">Review Event</a></p>';

        wp_mail(
            $admin_email,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    public function on_event_deleted($post_id) {
        $admin_email = get_option('admin_email');
        $event_title = get_post_meta($post_id, 'ven_event_title', true);
        $vendor_email = get_post_meta($post_id, 'ven_email', true);
        $vendor_status = get_post_meta($post_id, 'ven_vendor_status', true);
    
        $subject = '🚨 Vendor Event Deleted: ' . $event_title;
        $message = "<p>The following vendor event has been deleted:</p>";
        $message .= "<ul>
                        <li><strong>Title:</strong> " . esc_html($event_title) . "</li>
                        <li><strong>Vendor Email:</strong> " . esc_html($vendor_email) . "</li>
                        <li><strong>Status:</strong> " . esc_html($vendor_status) . "</li>
                        <li><strong>Deleted By:</strong> " . esc_html(wp_get_current_user()->user_email) . "</li>
                    </ul>";
    
        wp_mail(
            $admin_email,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    public function schedule_daily_digest() {
        if (!wp_next_scheduled('vendor_event_daily_digest')) {
            wp_schedule_event(time(), 'daily', 'vendor_event_daily_digest');
        }
    }

    public function send_daily_digest() {
        $admin_email = get_option('admin_email');
    
        // Get all events created or updated today
        $today = date('Y-m-d');
        $args = [
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'date_query' => [
                [
                    'after' => $today . ' 00:00:00',
                    'before' => $today . ' 23:59:59',
                    'inclusive' => true,
                ],
            ],
            'posts_per_page' => -1,
        ];
    
        $events = get_posts($args);
        if (empty($events)) {
            return; // nothing to send
        }
    
        $body = "<h2>📅 Vendor Events Daily Digest — " . date('F j, Y') . "</h2>";
        $body .= "<p>The following vendor events were created or updated today:</p>";
        $body .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>
                    <thead><tr>
                        <th>Event Title</th>
                        <th>Country</th>
                        <th>State</th>
                        <th>Status</th>
                        <th>Vendor Email</th>
                    </tr></thead><tbody>";
    
        foreach ($events as $event) {
            $body .= sprintf(
                "<tr>
                    <td><a href='%s'>%s</a></td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>",
                get_edit_post_link($event->ID),
                esc_html(get_post_meta($event->ID, 'ven_event_title', true)),
                esc_html(get_post_meta($event->ID, 'ven_country', true)),
                esc_html(get_post_meta($event->ID, 'ven_state', true)),
                esc_html(get_post_meta($event->ID, 'ven_vendor_status', true)),
                esc_html(get_post_meta($event->ID, 'ven_email', true))
            );
        }
        $body .= "</tbody></table>";
    
        wp_mail(
            $admin_email,
            'Vendor Events Daily Digest — ' . date('F j, Y'),
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
    
}
