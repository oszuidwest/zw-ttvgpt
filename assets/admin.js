/**
 * ZW TTVGPT Admin JavaScript
 *
 * Handles the admin interface interactions
 */
(function($) {
    'use strict';

    // Constants
    const SELECTORS = {
        contentEditor: '.wp-editor-area',
        acfSummaryField: '#' + zwTTVGPT.acfFields.summary,
        acfGptField: '#' + zwTTVGPT.acfFields.gpt_marker,
        regionCheckboxes: '#regiochecklist input[type="checkbox"]:checked'
    };

    const ANIMATION_DELAY = zwTTVGPT.animationDelay || {
        min: 20,
        max: 50,
        space: 30
    };

    /**
     * Initialize the plugin
     */
    function init() {
        $(document).ready(function() {
            bindEvents();
            injectGenerateButton();
        });
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Event binding now handled in injectGenerateButton
    }

    /**
     * Inject generate button into ACF field
     */
    function injectGenerateButton() {
        const $acfField = $(SELECTORS.acfSummaryField);
        if ($acfField.length === 0) {
            return;
        }

        const $button = $('<button>')
            .attr({
                type: 'button',
                class: 'button button-secondary zw-ttvgpt-inline-generate',
                'data-post-id': $('#post_ID').val()
            })
            .text(zwTTVGPT.strings.buttonText)
            .css('margin-top', '8px');

        $acfField.parent().append($button);

        // Bind click event to the new button
        $button.on('click', handleGenerateClick);
    }

    /**
     * Handle generate button click
     */
    function handleGenerateClick(e) {
        e.preventDefault();

        const $button = $(this);
        
        // Prevent double clicks
        if ($button.prop('disabled')) {
            return;
        }
        
        const postId = $button.data('post-id');
        const content = getEditorContent();

        if (!content) {
            showStatus('error', 'Geen content gevonden om samen te vatten.');
            return;
        }

        // Get selected regions
        const regions = getSelectedRegions();

        // Disable button and show loading state
        setLoadingState($button, true);
        
        // Store button reference globally for other functions
        $button.data('is-generating', true);
        
        // Start showing loading messages in the ACF field
        showLoadingMessages($button);

        // Make AJAX request
        $.ajax({
            url: zwTTVGPT.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zw_ttvgpt_generate',
                nonce: zwTTVGPT.nonce,
                post_id: postId,
                content: content,
                regions: regions
            },
            success: function(response) {
                if (response.success) {
                    handleSuccess(response.data, $button);
                } else {
                    clearLoadingMessages();
                    // WordPress wp_send_json_error sends the message in response.data
                    const errorMessage = typeof response.data === 'string' ? response.data : (response.data?.message || zwTTVGPT.strings.error);
                    showStatus('error', errorMessage);
                    // Re-enable button on error
                    setLoadingState($button, false);
                    $button.data('is-generating', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('ZW TTVGPT Error:', error, xhr.responseText);
                clearLoadingMessages();
                
                // Try to parse error message from response
                let errorMessage = zwTTVGPT.strings.error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.data) {
                        errorMessage = typeof response.data === 'string' ? response.data : (response.data.message || errorMessage);
                    }
                } catch (e) {
                    // If parsing fails, use generic error
                    errorMessage = error ? `${zwTTVGPT.strings.error}: ${error}` : zwTTVGPT.strings.error;
                }
                
                showStatus('error', errorMessage);
                // Re-enable button on error
                setLoadingState($button, false);
                $button.data('is-generating', false);
            },
            complete: function() {
                // Complete handler kept empty intentionally
            }
        });
    }

    /**
     * Get content from the editor
     */
    function getEditorContent() {
        // Try to get content from TinyMCE first
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            return tinyMCE.activeEditor.getContent({ format: 'text' });
        }

        // Fall back to textarea
        const $textarea = $(SELECTORS.contentEditor);
        if ($textarea.length > 0) {
            return $textarea.val();
        }

        // Try Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            const content = wp.data.select('core/editor').getEditedPostContent();
            // Strip HTML tags
            const temp = document.createElement('div');
            temp.innerHTML = content;
            return temp.textContent || temp.innerText || '';
        }

        return '';
    }

    /**
     * Get selected regions from checkboxes
     */
    function getSelectedRegions() {
        const regions = [];
        $(SELECTORS.regionCheckboxes).each(function() {
            const $label = $(this).parent();
            const labelText = $label.text().trim();
            if (labelText) {
                regions.push(labelText);
            }
        });
        return regions;
    }

    /**
     * Handle successful response
     */
    function handleSuccess(data, $button) {
        const $acfField = $(SELECTORS.acfSummaryField);
        const getMessageCount = $acfField.data('message-count');
        const currentMessageCount = getMessageCount ? getMessageCount() : 0;
        
        // Ensure at least 2 messages have been shown
        const minMessages = 2;
        const messagesNeeded = minMessages - currentMessageCount;
        
        if (messagesNeeded > 0) {
            // Wait for remaining messages before showing summary
            const waitTime = messagesNeeded * 2500; // 2.5 seconds per message
            setTimeout(function() {
                // Update ACF fields with animation
                animateText($acfField, data.summary, $button);
                $(SELECTORS.acfGptField).val(data.summary);
            }, waitTime);
        } else {
            // Already shown enough messages
            // Update ACF fields with animation
            animateText($acfField, data.summary, $button);
            $(SELECTORS.acfGptField).val(data.summary);
        }
    }

    /**
     * Animate text typing effect - ChatGPT style
     */
    function animateText($element, text, $button) {
        let index = 0;
        let isThinking = true;
        const thinkingChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        let thinkingIndex = 0;
        let thinkingInterval;
        
        // Clear any loading messages interval
        const messageInterval = $element.data('message-interval');
        if (messageInterval) {
            clearInterval(messageInterval);
            $element.removeData('message-interval');
        }
        
        $element.val('').prop('disabled', true);
        
        // Show thinking animation first
        thinkingInterval = setInterval(function() {
            $element.val(thinkingChars[thinkingIndex % thinkingChars.length]);
            thinkingIndex++;
        }, 50);
        
        // Start typing after a brief "thinking" period
        setTimeout(function() {
            clearInterval(thinkingInterval);
            $element.val('');
            isThinking = false;
            typeCharacter();
        }, 300);

        function typeCharacter() {
            if (index < text.length) {
                // Type multiple characters at once for faster effect - more random chunks
                const rand = Math.random();
                const charsToType = rand < 0.15 ? 5 : rand < 0.35 ? 4 : rand < 0.6 ? 3 : rand < 0.85 ? 2 : 1;
                let newText = '';
                
                for (let i = 0; i < charsToType && index < text.length; i++) {
                    newText += text.charAt(index);
                    index++;
                }
                
                $element.val($element.val() + newText);
                
                // Scroll to bottom if needed
                $element[0].scrollTop = $element[0].scrollHeight;

                // Much faster and more random delays
                let delay;
                const lastChar = text.charAt(index - 1);
                if (lastChar === '.' || lastChar === '!' || lastChar === '?') {
                    // Short pause after sentences
                    delay = Math.random() * 20 + 15;
                } else if (lastChar === ',' || lastChar === ';') {
                    // Tiny pause after commas
                    delay = Math.random() * 8 + 5;
                } else if (lastChar === ' ') {
                    // Almost no pause after words
                    delay = Math.random() * 5 + 2;
                } else {
                    // Very fast typing within words
                    delay = Math.random() * 3 + 1;
                }

                setTimeout(typeCharacter, delay);
            } else {
                // Add a subtle pulse effect when done
                $element.addClass('zw-ttvgpt-complete');
                setTimeout(function() {
                    $element.removeClass('zw-ttvgpt-complete');
                    $element.prop('disabled', false);
                    
                    // Ensure button is re-enabled when typing completes
                    if ($button && $button.data('is-generating')) {
                        setLoadingState($button, false);
                        $button.data('is-generating', false);
                    }
                }, 300);
            }
        }
    }

    /**
     * Set loading state for button
     */
    function setLoadingState($button, isLoading) {
        if (isLoading) {
            $button
                .prop('disabled', true)
                .addClass('updating-message');
            
            // Start thinking animation
            const thinkingChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
            let thinkingIndex = 0;
            
            const thinkingInterval = setInterval(function() {
                $button.html(thinkingChars[thinkingIndex % thinkingChars.length] + ' ' + zwTTVGPT.strings.generating);
                thinkingIndex++;
            }, 80);
            
            // Store interval for cleanup
            $button.data('thinking-interval', thinkingInterval);
        } else {
            // Clear thinking animation
            const thinkingInterval = $button.data('thinking-interval');
            if (thinkingInterval) {
                clearInterval(thinkingInterval);
                $button.removeData('thinking-interval');
            }
            
            $button
                .prop('disabled', false)
                .removeClass('updating-message')
                .text(zwTTVGPT.strings.buttonText);
        }
    }

    /**
     * Show status message
     */
    function showStatus(type, message) {
        // Create a temporary notice above the ACF field
        const $acfField = $(SELECTORS.acfSummaryField);
        if ($acfField.length === 0) return;
        
        // Remove any existing status
        $('.zw-ttvgpt-status').remove();
        
        const cssClass = type === 'error' ? 'notice-error' : 'notice-success';
        const $status = $(`<div class="notice ${cssClass} zw-ttvgpt-status" style="margin: 10px 0;"><p>${message}</p></div>`);
        
        $acfField.parent().prepend($status);
        $status.slideDown();

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $status.slideUp(function() {
                    $(this).remove();
                });
            }, zwTTVGPT.timeouts.successMessage || 3000);
        }
    }

    /**
     * Clear loading messages and restore ACF field
     */
    function clearLoadingMessages() {
        const $acfField = $(SELECTORS.acfSummaryField);
        const messageInterval = $acfField.data('message-interval');
        if (messageInterval) {
            clearInterval(messageInterval);
            $acfField.removeData('message-interval');
        }
        // Restore original placeholder if it had one
        $acfField.val('').prop('disabled', false).removeAttr('placeholder');
    }

    /**
     * Show loading messages in ACF field while generating
     */
    function showLoadingMessages($button) {
        const $acfField = $(SELECTORS.acfSummaryField);
        if ($acfField.length === 0 || !zwTTVGPT.strings.loadingMessages) return;
        
        // Create a shuffled copy of messages
        const messages = [...zwTTVGPT.strings.loadingMessages];
        for (let i = messages.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [messages[i], messages[j]] = [messages[j], messages[i]];
        }
        
        let messageIndex = 0;
        let messageCount = 0;
        let messageInterval;
        
        // Clear the field and disable it
        $acfField.prop('disabled', true).attr('placeholder', '');
        
        // Show first message immediately
        $acfField.val(messages[messageIndex]);
        messageIndex++;
        messageCount++;
        
        // Cycle through randomized messages
        messageInterval = setInterval(function() {
            if (messageIndex >= messages.length) {
                // Reshuffle when we've shown all messages
                for (let i = messages.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [messages[i], messages[j]] = [messages[j], messages[i]];
                }
                messageIndex = 0;
            }
            
            // Smooth transition to next message
            const nextMessage = messages[messageIndex];
            let charIndex = 0;
            
            // Gradually replace current text with next message
            const transitionInterval = setInterval(function() {
                if (charIndex <= nextMessage.length) {
                    $acfField.val(nextMessage.substring(0, charIndex));
                    charIndex += 2; // Type 2 chars at a time for smooth transition
                } else {
                    clearInterval(transitionInterval);
                }
            }, 20);
            
            messageIndex++;
            messageCount++;
            
            // Re-enable button after showing 2 messages (only if still generating)
            if (messageCount === 2 && $button && $button.data('is-generating')) {
                setLoadingState($button, false);
                // Don't remove is-generating flag here, let completion handle it
            }
        }, 2500);
        
        // Store interval and count for cleanup
        $acfField.data('message-interval', messageInterval);
        $acfField.data('message-count', () => messageCount);
    }

    // Initialize when ready
    init();

})(jQuery);