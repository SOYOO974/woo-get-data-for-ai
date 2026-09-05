<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Permissions;

$definitions = Permissions::get_module_definitions();
$saved_permissions = Permissions::get_permissions();

if (isset($_POST['agent_bridge_save_permissions']) && check_admin_referer('agent_bridge_save_permissions_nonce')) {
    $submitted = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
    $new_permissions = [];

    foreach (array_keys($definitions) as $key) {
        $new_permissions[$key] = !empty($submitted[$key]) ? 1 : 0;
    }

    update_option('wp_agent_bridge_permissions', $new_permissions, false);
    $saved_permissions = $new_permissions;

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Permissions updated successfully.', 'woo-get-data-for-ai') . '</p></div>';
}
?>

<form method="post" action="">
    <?php wp_nonce_field('agent_bridge_save_permissions_nonce'); ?>
    <div class="agent-bridge-card">
        <h3><?php esc_html_e('Granular Permissions Matrix', 'woo-get-data-for-ai'); ?></h3>
        <p class="description">
            <?php esc_html_e('Control exactly which system modules and resources your AI assistants or external tools are permitted to inspect. When a module is unchecked, the API will block requests with a 403 Forbidden response.', 'woo-get-data-for-ai'); ?>
        </p>

        <div class="permissions-table-wrap">
            <table class="wp-list-table widefat fixed striped table-permissions">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;"><?php esc_html_e('Allow', 'woo-get-data-for-ai'); ?></th>
                        <th style="width: 260px;"><?php esc_html_e('Module', 'woo-get-data-for-ai'); ?></th>
                        <th><?php esc_html_e('Description & Scope', 'woo-get-data-for-ai'); ?></th>
                        <th style="width: 240px;"><?php esc_html_e('Associated Endpoints', 'woo-get-data-for-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($definitions as $key => $data) : ?>
                        <?php $is_checked = !empty($saved_permissions[$key]); ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" name="permissions[<?php echo esc_attr($key); ?>]" value="1" id="perm_<?php echo esc_attr($key); ?>" <?php checked($is_checked, true); ?>>
                            </td>
                            <td>
                                <label for="perm_<?php echo esc_attr($key); ?>">
                                    <strong><?php echo esc_html($data['label']); ?></strong>
                                </label>
                            </td>
                            <td>
                                <p class="perm-desc"><?php echo esc_html($data['description']); ?></p>
                            </td>
                            <td>
                                <ul class="endpoints-list">
                                    <?php foreach ($data['endpoints'] as $ep) : ?>
                                        <li><code><?php echo esc_html($ep); ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="agent_bridge_save_permissions" class="button button-primary" value="<?php esc_attr_e('Save Permissions Matrix', 'woo-get-data-for-ai'); ?>">
        </p>
    </div>
</form>
