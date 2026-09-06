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
        // Intercept synthetic profiling request if token is present (zero idle overhead)
        Api\Performance_Controller::init_profiling_catcher();

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
        $controller_classes = [
            Api\System_Controller::class,
            Api\Theme_Controller::class,
            Api\Code_Controller::class,
            Api\Elementor_Controller::class,
            Api\Wpcode_Controller::class,
            Api\Logs_Controller::class,
            Api\Scheduler_Controller::class,
            Api\Flowmattic_Controller::class,
            Api\Analytics_Controller::class,
            Api\Meta_Controller::class,
            Api\Woocommerce_Controller::class,
            Api\Content_Controller::class,
            Api\Performance_Controller::class,
        ];

        foreach ($controller_classes as $class) {
            try {
                if (class_exists($class)) {
                    $controller = new $class();
                    $controller->register_routes();
                } else {
                    error_log(sprintf('[WP Agent Bridge] REST controller class "%s" not found during route registration.', $class));
                }
            } catch (\Throwable $e) {
                error_log(sprintf('[WP Agent Bridge] Failed to initialize REST controller "%s": %s', $class, $e->getMessage()));
            }
        }
    }
}
