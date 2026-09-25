/* WP Agent Bridge Admin Scripts */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Relocate any stray admin notices out of the plugin header card
        $('.agent-bridge-header').find('.notice, div.updated, div.error').insertBefore('.agent-bridge-header');

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
                $(this).html('<span class="dashicons dashicons-hidden"></span> ' + (agentBridgeData.hideText || 'Hide'));
            } else {
                $input.attr('type', 'password');
                $(this).html('<span class="dashicons dashicons-visibility"></span> ' + (agentBridgeData.showText || 'Show'));
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
                        alert(response.data.message || agentBridgeData.tokenRegenerated || 'Token regenerated successfully.');
                        location.reload();
                    } else {
                        alert(response.data && response.data.message ? response.data.message : (agentBridgeData.errorRegen || 'Error regenerating token.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    alert(agentBridgeData.networkError || 'Network error occurred while communicating with WordPress.');
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
                        alert(agentBridgeData.errorClear || 'Error clearing logs.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    alert(agentBridgeData.networkError || 'Network error occurred.');
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
                        alert(response.data && response.data.message ? response.data.message : (agentBridgeData.errorUnlock || 'Error unlocking IP.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    alert(agentBridgeData.networkError || 'Network error occurred while unlocking IP.');
                }
            });
        });

        // Reset all failed attempts and IP lockouts via AJAX
        $('.btn-reset-failures').on('click', function(e) {
            e.preventDefault();

            if (!confirm(agentBridgeData.confirmReset || 'Reset all lockout counters and unblock all IP addresses?')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).addClass('updating-message');

            $.ajax({
                url: agentBridgeData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'agent_bridge_reset_failures',
                    security: agentBridgeData.nonce
                },
                success: function(response) {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    if (response.success) {
                        alert(response.data.message || agentBridgeData.lockoutsCleared || 'All lockouts have been cleared.');
                        location.reload();
                    } else {
                        alert(response.data && response.data.message ? response.data.message : (agentBridgeData.errorReset || 'Error resetting lockouts.'));
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('updating-message');
                    alert(agentBridgeData.networkError || 'Network error occurred while resetting lockouts.');
                }
            });
        });

        // Dismiss first-time onboarding notice via AJAX
        $(document).on('click', '.agent-bridge-onboarding-notice .notice-dismiss', function() {
            var $notice = $(this).closest('.agent-bridge-onboarding-notice');
            var nonce = $notice.data('nonce') || (typeof agentBridgeData !== 'undefined' ? agentBridgeData.nonce : '');
            var ajaxUrl = (typeof agentBridgeData !== 'undefined' ? agentBridgeData.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php'));

            $.post(ajaxUrl, {
                action: 'agent_bridge_dismiss_onboarding',
                security: nonce
            });
        });

        // Copy prompt snippet handler
        $(document).on('click', '.btn-copy-prompt', function(e) {
            e.preventDefault();
            var promptText = $(this).data('prompt');
            if (!promptText) return;

            var $btn = $(this);
            var originalHtml = $btn.html();
            var copiedMsg = (typeof agentBridgeData !== 'undefined' && agentBridgeData.copiedText) ? agentBridgeData.copiedText : 'Copied!';

            navigator.clipboard.writeText(promptText).then(function() {
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> ' + copiedMsg);
                $btn.addClass('button-primary').removeClass('button-secondary');

                setTimeout(function() {
                    $btn.html(originalHtml);
                    $btn.removeClass('button-primary');
                }, 2000);
            }).catch(function() {
                var $temp = $('<textarea>').val(promptText).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                $btn.html('<span class="dashicons dashicons-yes-alt"></span> ' + copiedMsg);
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 2000);
            });
        });

        // ==========================================================================
        // Automatic & Manual Update Checker for Settings Page
        // ==========================================================================
        var $btnCheckUpdates = $('#btn-check-updates');
        if ($btnCheckUpdates.length) {
            var isCheckingUpdates = false;

            function checkPluginUpdates(force, isManual) {
                if (isCheckingUpdates) return;
                isCheckingUpdates = true;

                var $icon = $btnCheckUpdates.find('.dashicons');
                var $statusMsg = $('.update-check-status-msg');

                if (isManual) {
                    $btnCheckUpdates.prop('disabled', true).addClass('is-checking');
                    $icon.addClass('spin-clockwise');
                    $statusMsg.text(agentBridgeData.checkingUpdates || 'Checking for updates...').removeClass('status-success status-error').show();
                }

                $.ajax({
                    url: agentBridgeData.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'agent_bridge_check_for_updates',
                        security: agentBridgeData.nonce,
                        force: force ? 1 : 0
                    },
                    success: function(response) {
                        if (response && response.success && response.data) {
                            var data = response.data;
                            if (data.has_update) {
                                renderUpdateBanner(data);
                                $('.badge-update-dot').css('display', 'inline-flex');
                                if (isManual) {
                                    var msg = (agentBridgeData.updateAvailableMsg || 'New version v%s available!').replace('%s', data.new_version || '');
                                    $statusMsg.text(msg).addClass('status-success').show();
                                }
                            } else {
                                $('.badge-update-dot').hide();
                                $('#agent-bridge-update-banner').slideUp(250);
                                if (isManual) {
                                    $statusMsg.text(agentBridgeData.upToDateMsg || 'WP Agent Bridge is up to date.').addClass('status-success').show();
                                    setTimeout(function() {
                                        $statusMsg.fadeOut(400);
                                    }, 4000);
                                }
                            }
                        } else if (isManual) {
                            $statusMsg.text(agentBridgeData.errorCheckUpdates || 'Could not check for updates.').addClass('status-error').show();
                        }
                    },
                    error: function() {
                        if (isManual) {
                            $statusMsg.text(agentBridgeData.networkError || 'Network error occurred.').addClass('status-error').show();
                        }
                    },
                    complete: function() {
                        isCheckingUpdates = false;
                        if (isManual) {
                            $btnCheckUpdates.prop('disabled', false).removeClass('is-checking');
                            $icon.removeClass('spin-clockwise');
                        }
                    }
                });
            }

            function renderUpdateBanner(data) {
                var $banner = $('#agent-bridge-update-banner');
                if (!$banner.length) return;

                $banner.find('.update-new-version').text(data.new_version || '');
                var updateBtnText = (agentBridgeData.updateToText || 'Update to v%s Now').replace('%s', data.new_version || '');
                $banner.find('.btn-update-text').text(updateBtnText);

                if (data.update_url) {
                    $banner.find('#btn-update-now').attr('href', data.update_url).attr('data-update-url', data.update_url);
                }
                if (data.release_url) {
                    $banner.find('#btn-view-release-notes').attr('href', data.release_url);
                }

                $banner.slideDown(300);
            }

            // Automatic silent check when visiting the settings page
            checkPluginUpdates(false, false);

            // Manual check when clicking the button
            $btnCheckUpdates.on('click', function(e) {
                e.preventDefault();
                checkPluginUpdates(true, true);
            });

            // Loading state when clicking update button
            $(document).on('click', '#agent-bridge-update-banner .btn-update-now', function() {
                var $btn = $(this);
                $btn.addClass('updating-message disabled');
                $btn.find('.dashicons').addClass('spin-clockwise');
                $btn.find('.btn-update-text').text(agentBridgeData.updatingText || 'Updating...');
            });
        }
    });
})(jQuery);
