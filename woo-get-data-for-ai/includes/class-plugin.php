<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * Run plugin hooks.
     */
    public function run() {
        // Load text domain for internationalization (i18n)
        add_action('init', [$this, 'load_textdomain']);

        // Initialize Admin UI
        if (is_admin()) {
            $admin = new Admin\Admin_Settings();
            $admin->init();
        }

        // Register REST API routes
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'woo-get-data-for-ai',
            false,
            dirname(WOO_GET_DATA_AI_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Register all REST API controllers.
     */
    public function register_rest_routes() {
        $controllers = [
            new Api\System_Controller(),
            new Api\Theme_Controller(),
            new Api\Code_Controller(),
            new Api\Elementor_Controller(),
            new Api\Wpcode_Controller(),
            new Api\Logs_Controller(),
            new Api\Scheduler_Controller(),
            new Api\Flowmattic_Controller(),
            new Api\Analytics_Controller(),
            new Api\Meta_Controller(),
            new Api\Woocommerce_Controller(),
        ];

        foreach ($controllers as $controller) {
            $controller->register_routes();
        }
    }
}
