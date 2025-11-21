/**
 * AI Spam Closer - Client-side JavaScript
 */

(function($) {
    'use strict';
    
    // Configuration from server
    var config = window.AI_SPAM_CLOSER_CONFIG || {};
    
    /**
     * Show notification message
     */
    function showNotification(message, type) {
        type = type || 'info'; // info, success, error, warning
        
        var alertClass = 'alert-' + type;
        if (type === 'error') alertClass = 'alert-danger';
        
        var $alert = $('<div>')
            .addClass('alert ' + alertClass + ' ai-spam-closer-alert')
            .html('<strong>' + (type === 'error' ? 'Error: ' : '') + '</strong> ' + message)
            .hide();
        
        // Remove any existing alerts
        $('.ai-spam-closer-alert').remove();
        
        // Insert at top of page
        $('#content').prepend($alert);
        $alert.slideDown(300);
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $alert.slideUp(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    /**
     * Analyze ticket for spam and close if detected
     */
    function analyzeTicket() {
        var $link = $('.ai-spam-closer-btn');
        var $menuItem = $link.parent('li');
        var originalHtml = $link.html();
        
        // Disable link and show loading state
        $link.css('pointer-events', 'none').html('<i class="icon-refresh icon-spin"></i> Checking...');
        
        $.ajax({
            url: config.ajax_url + '/analyze',
            type: 'POST',
            data: {
                ticket_id: config.ticket_id
            },
            dataType: 'json',
            success: function(response) {
                if (config.enable_logging) {
                    console.log('AI Spam Closer - Response:', response);
                }
                
                if (response.success) {
                    if (response.is_spam) {
                        if (response.closed) {
                            showNotification(
                                '<strong>Spam Detected!</strong> Ticket has been closed as spam.<br>' +
                                'Matched keywords: <em>' + (response.matched_keywords || []).join(', ') + '</em>',
                                'warning'
                            );
                            
                            // Reload page after short delay to show closed status
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showNotification(
                                'Spam detected but ticket was not closed. ' + (response.message || ''),
                                'warning'
                            );
                        }
                    } else {
                        var message = 'No spam detected. This ticket appears to be legitimate.';
                        
                        // Add debug info if available
                        if (response.debug) {
                            message += '<br><br><strong>Debug Info:</strong><br>';
                            message += 'Keywords configured: ' + response.debug.keywords_count + '<br>';
                            if (response.debug.keywords && response.debug.keywords.length > 0) {
                                message += 'Keywords: ' + response.debug.keywords.join(', ') + '<br>';
                            }
                            if (response.debug.keyword_check_result) {
                                message += 'Keyword matches: ' + (response.debug.keyword_check_result.matched_keywords || []).length + '<br>';
                                if (response.debug.keyword_check_result.matched_keywords && response.debug.keyword_check_result.matched_keywords.length > 0) {
                                    message += 'Matched: ' + response.debug.keyword_check_result.matched_keywords.join(', ') + '<br>';
                                }
                            }
                            message += 'Content length: ' + response.debug.content_length + ' bytes<br>';
                            if (response.debug.content_preview) {
                                message += 'Content preview: <code>' + response.debug.content_preview.substring(0, 150) + '...</code>';
                            }
                        }
                        
                        showNotification(message, 'success');
                    }
                } else {
                    showNotification(
                        'Analysis failed: ' + (response.error || 'Unknown error'),
                        'error'
                    );
                }
            },
            error: function(xhr, status, error) {
                if (config.enable_logging) {
                    console.error('AI Spam Closer - AJAX Error:', status, error);
                }
                showNotification(
                    'Request failed: ' + (error || 'Network error'),
                    'error'
                );
            },
            complete: function() {
                // Re-enable link
                $link.css('pointer-events', '').html(originalHtml);
            }
        });
    }
    
    /**
     * Initialize plugin UI
     */
    function init() {
        // Wait for DOM to be ready
        $(document).ready(function() {
            // Find the More dropdown menu
            var $moreDropdown = $('#action-dropdown-more ul');
            
            if (!$moreDropdown.length) {
                if (config.enable_logging) {
                    console.log('AI Spam Closer - More dropdown not found');
                }
                return;
            }
            
            // Create menu item in the same format as other items
            var $menuItem = $('<li>')
                .append(
                    $('<a>')
                        .addClass('ai-spam-closer-btn')
                        .attr('href', '#spam-check')
                        .html('<i class="icon-ban-circle"></i> ' + 'Check for Spam and Close')
                        .on('click', function(e) {
                            e.preventDefault();
                            if (confirm('Check this ticket for spam and close if detected?')) {
                                analyzeTicket();
                            }
                        })
                );
            
            // Insert as first item in the More dropdown
            $moreDropdown.prepend($menuItem);
            
            if (config.enable_logging) {
                console.log('AI Spam Closer - UI initialized');
            }
        });
    }
    
    // Initialize
    init();
    
})(jQuery);

