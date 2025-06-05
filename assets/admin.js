/**
 * ZW TTVGPT Admin JavaScript
 *
 * Handles the admin interface interactions
 * @param $
 */
(function ($) {
	'use strict';

	// Constants
	const SELECTORS = {
			contentEditor: '.wp-editor-area',
			acfSummaryField: `#${zwTTVGPT.acfFields.summary}`,
			acfGptField: `#${zwTTVGPT.acfFields.gpt_marker}`,
			regionCheckboxes: '#regiochecklist input[type="checkbox"]:checked',
		},
		// Animation delay configuration removed - not currently used

		THINKING_CHARS = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
		THINKING_SPEED = 80, // Ms between thinking chars
		// Typing delay lookup
		TYPING_DELAYS = {
			sentence: [15, 20], // [min, variance]
			comma: [5, 8],
			space: [2, 5],
			default: [1, 3],
		};

	// Cache frequently used elements
	let $cachedAcfField = null,
		$cachedGptField = null;

	/**
	 * Initialize the plugin
	 */
	function init() {
		$(document).ready(function () {
			// Cache elements
			$cachedAcfField = $(SELECTORS.acfSummaryField);
			$cachedGptField = $(SELECTORS.acfGptField);

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
		if (!$cachedAcfField || $cachedAcfField.length === 0) {
			return;
		}

		const $button = $('<button>')
			.attr({
				type: 'button',
				class: 'button button-secondary zw-ttvgpt-inline-generate',
				'data-post-id': $('#post_ID').val(),
			})
			.text(zwTTVGPT.strings.buttonText)
			.css('margin-top', '8px');

		$cachedAcfField.parent().append($button);

		// Bind click event to the new button
		$button.on('click', handleGenerateClick);
	}

	/**
	 * Handle generate button click
	 * @param e
	 */
	function handleGenerateClick(e) {
		e.preventDefault();

		const $button = $(this);

		// Prevent double clicks
		if ($button.prop('disabled')) {
			return;
		}

		const content = getEditorContent(),
			postId = $button.data('post-id');

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
		showLoadingMessages();

		// Make AJAX request
		$.ajax({
			url: zwTTVGPT.ajaxUrl,
			type: 'POST',
			data: {
				action: 'zw_ttvgpt_generate',
				nonce: zwTTVGPT.nonce,
				post_id: postId,
				content,
				regions,
			},
			success(response) {
				if (response.success) {
					handleSuccess(response.data, $button);
				} else {
					clearLoadingMessages();
					// WordPress wp_send_json_error sends the message in response.data
					const errorMessage =
						typeof response.data === 'string'
							? response.data
							: response.data?.message || zwTTVGPT.strings.error;
					showStatus('error', errorMessage);
					// Re-enable button on error
					setLoadingState($button, false);
					$button.data('is-generating', false);
				}
			},
			error(xhr, status, error) {
				console.error('ZW TTVGPT Error:', error, xhr.responseText);
				clearLoadingMessages();

				// Try to parse error message from response
				let errorMessage = zwTTVGPT.strings.error;
				try {
					const response = JSON.parse(xhr.responseText);
					if (response && response.data) {
						errorMessage =
							typeof response.data === 'string'
								? response.data
								: response.data.message || errorMessage;
					}
				} catch (parseError) {
					// If parsing fails, use generic error
					errorMessage = error
						? `${zwTTVGPT.strings.error}: ${error}`
						: zwTTVGPT.strings.error;
				}

				showStatus('error', errorMessage);
				// Re-enable button on error
				setLoadingState($button, false);
				$button.data('is-generating', false);
			},
			complete() {
				// Complete handler kept empty intentionally
			},
		});
	}

	/**
	 * Get content from the editor
	 */
	function getEditorContent() {
		// Try to get content from TinyMCE first
		if (
			typeof tinyMCE !== 'undefined' &&
			tinyMCE.activeEditor &&
			!tinyMCE.activeEditor.isHidden()
		) {
			return tinyMCE.activeEditor.getContent({ format: 'text' });
		}

		// Fall back to textarea
		const $textarea = $(SELECTORS.contentEditor);
		if ($textarea.length > 0) {
			return $textarea.val();
		}

		// Try Gutenberg
		if (
			typeof wp !== 'undefined' &&
			wp.data &&
			wp.data.select('core/editor')
		) {
			const content = wp.data
					.select('core/editor')
					.getEditedPostContent(),
				// Strip HTML tags
				temp = document.createElement('div');
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
		$(SELECTORS.regionCheckboxes).each(function () {
			const $label = $(this).parent(),
				labelText = $label.text().trim();
			if (labelText) {
				regions.push(labelText);
			}
		});
		return regions;
	}

	/**
	 * Utility: Fisher-Yates shuffle
	 * @param array
	 */
	function shuffleArray(array) {
		const shuffled = [...array];
		for (let i = shuffled.length - 1; i > 0; i--) {
			const j = Math.floor(Math.random() * (i + 1));
			[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
		}
		return shuffled;
	}

	/**
	 * Manage thinking animation with unified interval handling
	 * @param element
	 * @param text
	 */
	function startThinkingAnimation(element, text = '') {
		let index = 0;
		const isButton = element instanceof jQuery && element.is('button'),
			interval = setInterval(function () {
				const char = THINKING_CHARS[index % THINKING_CHARS.length];
				if (isButton) {
					element.html(char + (text ? ` ${text}` : ''));
				} else {
					element.val(char);
				}
				index++;
			}, THINKING_SPEED);

		return interval;
	}

	/**
	 * Handle successful response
	 * @param data
	 * @param $button
	 */
	function handleSuccess(data, $button) {
		const getMessageCount = $cachedAcfField.data('message-count'),
			currentMessageCount = getMessageCount ? getMessageCount() : 0,
			// Ensure at least 2 messages have been shown
			minMessages = 2,
			messagesNeeded = minMessages - currentMessageCount;

		if (messagesNeeded > 0) {
			// Wait for remaining messages before showing summary
			const waitTime = messagesNeeded * 2500; // 2.5 seconds per message
			setTimeout(function () {
				// Update ACF fields with animation
				animateText($cachedAcfField, data.summary, $button);
				$cachedGptField.val(data.summary);
			}, waitTime);
		} else {
			/*
			 * Already shown enough messages
			 * Update ACF fields with animation
			 */
			animateText($cachedAcfField, data.summary, $button);
			$cachedGptField.val(data.summary);
		}
	}

	/**
	 * Animate text typing effect - ChatGPT style
	 * @param $element
	 * @param text
	 * @param $button
	 */
	function animateText($element, text, $button) {
		let index = 0;

		// Clear any loading messages interval
		const messageInterval = $element.data('message-interval');
		if (messageInterval) {
			clearInterval(messageInterval);
			$element.removeData('message-interval');
		}

		$element.val('').prop('disabled', true);

		// Show thinking animation first
		const thinkingInterval = startThinkingAnimation($element);

		// Start typing after a brief "thinking" period
		setTimeout(function () {
			clearInterval(thinkingInterval);
			$element.val('');
			typeCharacter();
		}, 300);

		function typeCharacter() {
			if (index < text.length) {
				// Type multiple characters at once - simplified
				const charsToType = Math.floor(Math.random() * 4) + 1, // 1-4 chars
					newText = text.substr(index, charsToType);
				index += newText.length;

				$element.val($element.val() + newText);

				// Scroll to bottom if needed
				$element[0].scrollTop = $element[0].scrollHeight;

				// Simplified delay calculation
				const lastChar = text.charAt(index - 1);
				let delayConfig = TYPING_DELAYS.default;

				if ('.!?'.includes(lastChar)) {
					delayConfig = TYPING_DELAYS.sentence;
				} else if (',;'.includes(lastChar)) {
					delayConfig = TYPING_DELAYS.comma;
				} else if (lastChar === ' ') {
					delayConfig = TYPING_DELAYS.space;
				}

				const delay = delayConfig[0] + Math.random() * delayConfig[1];
				setTimeout(typeCharacter, delay);
			} else {
				// Add a subtle pulse effect when done
				$element.addClass('zw-ttvgpt-complete');
				setTimeout(function () {
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
	 * @param $button
	 * @param isLoading
	 */
	function setLoadingState($button, isLoading) {
		if (isLoading) {
			$button.prop('disabled', true).addClass('zw-ttvgpt-generating');

			// Start thinking animation
			const interval = startThinkingAnimation(
				$button,
				zwTTVGPT.strings.generating
			);
			$button.data('thinking-interval', interval);
		} else {
			// Clear thinking animation
			const thinkingInterval = $button.data('thinking-interval');
			if (thinkingInterval) {
				clearInterval(thinkingInterval);
				$button.removeData('thinking-interval');
			}

			$button
				.prop('disabled', false)
				.removeClass('zw-ttvgpt-generating')
				.text(zwTTVGPT.strings.buttonText);
		}
	}

	/**
	 * Show status message
	 * @param type
	 * @param message
	 */
	function showStatus(type, message) {
		// Create a temporary notice above the ACF field
		if (!$cachedAcfField || $cachedAcfField.length === 0) {
			return;
		}

		// Remove any existing status
		$('.zw-ttvgpt-status').remove();

		const cssClass = type === 'error' ? 'notice-error' : 'notice-success',
			$status = $(
				`<div class="notice ${cssClass} zw-ttvgpt-status" style="margin: 10px 0;"><p>${message}</p></div>`
			);

		$cachedAcfField.parent().prepend($status);
		$status.slideDown();

		// Auto-hide success messages
		if (type === 'success') {
			setTimeout(function () {
				$status.slideUp(function () {
					$(this).remove();
				});
			}, zwTTVGPT.timeouts.successMessage || 3000);
		}
	}

	/**
	 * Clear loading messages and restore ACF field
	 */
	function clearLoadingMessages() {
		const messageInterval = $cachedAcfField.data('message-interval');
		if (messageInterval) {
			clearInterval(messageInterval);
			$cachedAcfField.removeData('message-interval');
		}
		// Restore original placeholder if it had one
		$cachedAcfField
			.val('')
			.prop('disabled', false)
			.removeAttr('placeholder');
	}

	/**
	 * Show loading messages in ACF field while generating
	 */
	function showLoadingMessages() {
		if (
			!$cachedAcfField ||
			$cachedAcfField.length === 0 ||
			!zwTTVGPT.strings.loadingMessages
		) {
			return;
		}

		// Create a shuffled copy of messages
		let messages = shuffleArray(zwTTVGPT.strings.loadingMessages),
			messageIndex = 0,
			messageCount = 0;

		// Clear the field and disable it
		$cachedAcfField.prop('disabled', true).attr('placeholder', '');

		// Show first message immediately
		$cachedAcfField.val(messages[messageIndex]);
		messageIndex++;
		messageCount++;

		// Cycle through randomized messages
		const messageInterval = setInterval(function () {
			if (messageIndex >= messages.length) {
				// Reshuffle when we've shown all messages
				messages = shuffleArray(messages);
				messageIndex = 0;
			}

			// Smooth transition to next message
			const nextMessage = messages[messageIndex];
			let charIndex = 0;

			// Gradually replace current text with next message
			const transitionInterval = setInterval(function () {
				if (charIndex <= nextMessage.length) {
					$cachedAcfField.val(nextMessage.substring(0, charIndex));
					charIndex += 2; // Type 2 chars at a time for smooth transition
				} else {
					clearInterval(transitionInterval);
				}
			}, 20);

			messageIndex++;
			messageCount++;

			// Keep button disabled while generating - it will be re-enabled when typing completes
		}, 2500);

		// Store interval and count for cleanup
		$cachedAcfField.data('message-interval', messageInterval);
		$cachedAcfField.data('message-count', () => messageCount);
	}

	// Initialize when ready
	init();
})(jQuery);
