<?php
if (!defined('ABSPATH')) exit;

class VENEVENTS {
    private static $instance = null;
    public $cpt;
    public $frontend;
    public $account;
    public $ajax;
    public $notify;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->includes();
        $this->cpt = new VEN_CPT();
        $this->frontend = new VEN_Frontend();
        $this->account = new VEN_Account();
        $this->ajax = new VEN_Ajax();
        $this->notify = new VEN_Notify();
    }

    private function includes() {
        require_once VEN_PLUGIN_DIR . 'includes/class-cpt.php';
        require_once VEN_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once VEN_PLUGIN_DIR . 'includes/class-account.php';
        require_once VEN_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once VEN_PLUGIN_DIR . 'includes/class-notify.php';
    }
}