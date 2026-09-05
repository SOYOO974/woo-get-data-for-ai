/* WP Agent Bridge Admin Scripts */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Copy to clipboard handler
        $('.btn-copy').on('click', function(e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            var $target = $('#' + targetId);
            var textToCopy = $target.val() || $target.text();

            if (!textToCopy) return;

            var $btn = $(this);
            var originalHtml = $btn.html();

            navigator.clipboard.writeText(textToCopy).then(function() {
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> ' + (agentBridgeData.copiedText || 'Copied!'));
                $btn.addClass('button-primary').removeClass('button-secondary');

                setTimeout(function() {
                    $btn.html(originalHtml);
                    $btn.removeClass('button-primary');
                }, 2000);
            }).catch(function(err) {
                // Fallback for older browsers
                $target.select();
                document.execCommand('copy');
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> ' + (agentBridgeData.copiedText || 'Copied!'));
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 2000);
            });
        });

        // Toggle Token Visibility
        $('.btn-toggle-visibility').on('click', function(e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            var $input = $('#' + targetId);
            var isPassword = $input.attr('type') === 'password';

            if (isPassword) {
                $input.attr('type', 'text');
                $(this).html('<span class="dashicons dashicons-hidden"></span> Hide');
            } else {
                $input.attr('type', 'password');
                $(this).html('<span class="dashicons dashicons-visibility"></span> Show');
            }
        });

        // Regenerate Token via AJAX
        $('.btn-regenerate-token').on('click', function(e) {
            e.preventDefault();

            if (!confirm(agentBridgeData.confirmRegen)) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).addClass('updating-message');

            $.ajax({
                url: agentBridgeData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'agent_bridge_regenerate_token',
                    security: agentBridgeData.nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    if (response.success && response.data.token) {
                        $('#agent-bridge-token-input').val(response.data.token);
                        alert(response.data.message || 'Token regenerated successfully.');
                        location.reload();
                    } else {
                        alert(response.data && response.data.message ? response.data.message : 'Error regenerating token.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    alert('Network error occurred while communicating with WordPress.');
                }
            });
        });

        // Clear Logs via AJAX
        $('.btn-clear-logs').on('click', function(e) {
            e.preventDefault();

            if (!confirm(agentBridgeData.confirmClear)) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).addClass('updating-message');

            $.ajax({
                url: agentBridgeData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'agent_bridge_clear_logs',
                    security: agentBridgeData.nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error clearing logs.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    alert('Network error occurred.');
                }
            });
        });

        // Unlock IP via AJAX
        $('.btn-unlock-ip').on('click', function(e) {
            e.preventDefault();
            var ip = $(this).data('ip');
            var rowId = $(this).data('row');

            if (!confirm(agentBridgeData.confirmUnlock || 'Unlock this IP?')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: agentBridgeData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'agent_bridge_unlock_ip',
                    ip: ip,
                    security: agentBridgeData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#' + rowId).fadeOut(300, function() {
                            $(this).remove();
                            if ($('.table-locked-ips tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        $btn.prop('disabled', false);
                        alert(response.data && response.data.message ? response.data.message : 'Error unlocking IP.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    alert('Network error occurred while unlocking IP.');
                }
            });
        });
    });
})(jQuery);
