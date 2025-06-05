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
        
        // Start showing loading messages in the ACF field
        showLoadingMessages();

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
                    handleSuccess(response.data);
                } else {
                    clearLoadingMessages();
                    // WordPress wp_send_json_error sends the message in response.data
                    const errorMessage = typeof response.data === 'string' ? response.data : (response.data?.message || zwTTVGPT.strings.error);
                    showStatus('error', errorMessage);
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
            },
            complete: function() {
                setLoadingState($button, false);
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
    function handleSuccess(data) {
        showStatus('success', zwTTVGPT.strings.success);

        // Update ACF fields with animation
        animateText($(SELECTORS.acfSummaryField), data.summary);
        $(SELECTORS.acfGptField).val(data.summary);
    }

    /**
     * Animate text typing effect - ChatGPT style
     */
    function animateText($element, text) {
        let index = 0;
        let isThinking = true;
        const thinkingChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        let thinkingIndex = 0;
        let thinkingInterval;
        
        // Clear any fun messages interval
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
                // Type multiple characters at once for faster effect
                const charsToType = Math.random() < 0.5 ? 3 : Math.random() < 0.8 ? 2 : 1;
                let newText = '';
                
                for (let i = 0; i < charsToType && index < text.length; i++) {
                    newText += text.charAt(index);
                    index++;
                }
                
                $element.val($element.val() + newText);
                
                // Scroll to bottom if needed
                $element[0].scrollTop = $element[0].scrollHeight;

                // Variable delay for more natural typing (3x faster)
                let delay;
                if (text.charAt(index - 1) === '.' || text.charAt(index - 1) === '!' || text.charAt(index - 1) === '?') {
                    // Longer pause after sentences
                    delay = Math.random() * 30 + 50;
                } else if (text.charAt(index - 1) === ',' || text.charAt(index - 1) === ';') {
                    // Medium pause after commas
                    delay = Math.random() * 15 + 25;
                } else if (text.charAt(index - 1) === ' ') {
                    // Short pause after words
                    delay = Math.random() * 10 + 7;
                } else {
                    // Fast typing within words
                    delay = Math.random() * 5 + 2;
                }

                setTimeout(typeCharacter, delay);
            } else {
                // Add a subtle pulse effect when done
                $element.addClass('zw-ttvgpt-complete');
                setTimeout(function() {
                    $element.removeClass('zw-ttvgpt-complete');
                    $element.prop('disabled', false);
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
                .addClass('updating-message')
                .text(zwTTVGPT.strings.generating);
        } else {
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
    function showLoadingMessages() {
        const $acfField = $(SELECTORS.acfSummaryField);
        if ($acfField.length === 0 || !zwTTVGPT.strings.loadingMessages) return;
        
        // Create a shuffled copy of messages
        const messages = [...zwTTVGPT.strings.loadingMessages];
        for (let i = messages.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [messages[i], messages[j]] = [messages[j], messages[i]];
        }
        
        let messageIndex = 0;
        let messageInterval;
        
        // Clear the field and disable it
        $acfField.prop('disabled', true).attr('placeholder', '');
        
        // Show first message immediately
        $acfField.val(messages[messageIndex]);
        messageIndex++;
        
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
        }, 2500);
        
        // Store interval for cleanup
        $acfField.data('message-interval', messageInterval);
    }

    // Initialize when ready
    init();

})(jQuery);