/**
 * ZW TTVGPT Admin JavaScript
 *
 * Handles the admin interface interactions
 */
(function($) {
    'use strict';

    // Constants
    const SELECTORS = {
        button: '#zw-ttvgpt-generate',
        status: '#zw-ttvgpt-status',
        result: '#zw-ttvgpt-result',
        summary: '#zw-ttvgpt-summary',
        wordCount: '#zw-ttvgpt-word-count',
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
        $(document).on('click', SELECTORS.button, handleGenerateClick);
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

        // Make AJAX request
        $.ajax({
            url: zwTTVGPT.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zw_ttvgpt_generate',
                nonce: zwTTVGPT.nonce,
                post_id: postId,
                content: content,
                regions: regions,
                save_to_acf: $button.hasClass('zw-ttvgpt-inline-generate') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    handleSuccess(response.data, $button.hasClass('zw-ttvgpt-inline-generate'));
                } else {
                    showStatus('error', response.data || zwTTVGPT.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('ZW TTVGPT Error:', error);
                showStatus('error', zwTTVGPT.strings.error + ': ' + error);
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
    function handleSuccess(data, saveToAcf) {
        showStatus('success', zwTTVGPT.strings.success);

        if (saveToAcf) {
            // Update ACF fields with animation
            animateText($(SELECTORS.acfSummaryField), data.summary);
            $(SELECTORS.acfGptField).val(data.summary);
        } else {
            // Show in meta box
            $(SELECTORS.result).slideDown();
            $(SELECTORS.summary).val(data.summary);
            $(SELECTORS.wordCount).text(`Aantal woorden: ${data.word_count}`);
        }
    }

    /**
     * Animate text typing effect
     */
    function animateText($element, text) {
        let index = 0;
        $element.val('').prop('disabled', true);

        function typeCharacter() {
            if (index < text.length) {
                $element.val($element.val() + text.charAt(index));
                index++;

                let delay = Math.random() * (ANIMATION_DELAY.max - ANIMATION_DELAY.min) + ANIMATION_DELAY.min;
                if (text.charAt(index - 1) === ' ') {
                    delay += ANIMATION_DELAY.space;
                }

                setTimeout(typeCharacter, delay);
            } else {
                $element.prop('disabled', false);
            }
        }

        typeCharacter();
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
        const $status = $(SELECTORS.status);
        const cssClass = type === 'error' ? 'notice-error' : 'notice-success';

        $status
            .removeClass('notice-error notice-success')
            .addClass(`notice ${cssClass}`)
            .html(`<p>${message}</p>`)
            .slideDown();

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $status.slideUp();
            }, zwTTVGPT.timeouts.successMessage || 3000);
        }
    }

    // Initialize when ready
    init();

})(jQuery);