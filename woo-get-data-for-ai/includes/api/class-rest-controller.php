<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;
use WPAgentBridge\Permissions;
use WPAgentBridge\Redaction;
use WPAgentBridge\Access_Logger;

abstract class Rest_Controller {

    /**
     * REST API Namespace.
     */
    const NAMESPACE = 'agent-bridge/v1';

    /**
     * Register routes for this controller.
     */
    abstract public function register_routes();

    /**
     * Check authentication, permissions and log request.
     *
     * @param \WP_REST_Request $request
     * @param string $module_key
     * @return true|\WP_Error
     */
    public function check_access(\WP_REST_Request $request, $module_key = '') {
        $endpoint = $request->get_route();

        // 1. Verify Security (Bearer token, method, rate limit, IP)
        $security_check = Security::verify_request($request);
        if (is_wp_error($security_check)) {
            $status = $security_check->get_error_data()['status'] ?? 401;
            Access_Logger::log_request($endpoint, $status);
            return $security_check;
        }

        // 2. Verify Module Permission (if endpoint is part of a module)
        if (!empty($module_key)) {
            $perm_check = Permissions::check_module_permission($module_key);
            if (is_wp_error($perm_check)) {
                Access_Logger::log_request($endpoint, 403);
                return $perm_check;
            }
        }

        // Log successful access
        Access_Logger::log_request($endpoint, 200);

        return true;
    }

    /**
     * Return a sanitized and redacted JSON response.
     *
     * @param mixed $data
     * @param int $status
     * @return \WP_REST_Response
     */
    protected function response($data, $status = 200) {
        // Redact any sensitive tokens, passwords, or customer PII before sending
        $sanitized_data = Redaction::redact_data($data);

        return new \WP_REST_Response($sanitized_data, $status);
    }

    /**
     * Return a standardized WP_Error response.
     *
     * @param string $code
     * @param string $message
     * @param int $status
     * @return \WP_Error
     */
    protected function error($code, $message, $status = 400) {
        return new \WP_Error($code, $message, ['status' => $status]);
    }
}
