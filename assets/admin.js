/**
 * ZW TTVGPT Admin JavaScript
 *
 * Manages summary generation interface with typing animations and loading states
 * @param $ jQuery object
 */
(function ($) {
	'use strict';

	const SELECTORS = {
			contentEditor: '.wp-editor-area',
			acfSummaryField: `#${zwTTVGPT.acfFields.summary}`,
			acfGptField: `#${zwTTVGPT.acfFields.gpt_marker}`,
			regionCheckboxes: '#regiochecklist input[type="checkbox"]:checked',
		},
		THINKING_CHARS = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
		THINKING_SPEED = 80,
		TYPING_DELAYS = {
			sentence: [8, 12],
			comma: [3, 5],
			space: [1, 2],
			default: [0.5, 1.5],
		};

	let $cachedAcfField = null,
		$cachedGptField = null;

	/**
	 * Initialize plugin components and cache DOM elements
	 */
	function init() {
		$(document).ready(function () {
			$cachedAcfField = $(SELECTORS.acfSummaryField);
			$cachedGptField = $(SELECTORS.acfGptField);

			injectGenerateButton();
		});
	}

	/**
	 * Create and inject generate button below ACF summary field
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
		$button.on('click', handleGenerateClick);
	}

	/**
	 * Process generate button click and initiate summary generation
	 * @param {Event} e Click event
	 */
	function handleGenerateClick(e) {
		e.preventDefault();

		const $button = $(this);

		if ($button.prop('disabled')) {
			return;
		}

		const content = getEditorContent(),
			postId = $button.data('post-id');

		if (!content || content.trim().length === 0) {
			showStatus(
				'error',
				'Geen inhoud gevonden. Zorg dat de editor geladen is en voeg eerst tekst toe.'
			);
			return;
		}

		const regions = getSelectedRegions();

		// Debug: log content being sent to API
		if (zwTTVGPT.debugMode) {
			/* eslint-disable no-console */
			console.log('ZW TTVGPT Debug - Content wordt verstuurd naar API:');
			console.log('Post ID:', postId);
			console.log('Content lengte:', content.length, 'tekens');
			console.log("Regio's:", regions);
			console.log('Content:', content);
			/* eslint-enable no-console */
		}

		setLoadingState($button, true);
		$button.data('is-generating', true);
		showLoadingMessages();

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
				// Debug: log API response
				if (zwTTVGPT.debugMode) {
					/* eslint-disable-next-line no-console */
					console.log('ZW TTVGPT Debug - API Response:', response);
				}

				if (response.success) {
					clearLoadingMessages();
					handleSuccess(response.data, $button);
				} else {
					clearLoadingMessages();
					const errorMessage =
						typeof response.data === 'string'
							? response.data
							: response.data?.message || zwTTVGPT.strings.error;
					showStatus('error', errorMessage);
					setLoadingState($button, false);
					$button.data('is-generating', false);
				}
			},
			error(xhr, status, error) {
				console.error('ZW TTVGPT Error:', error, xhr.responseText);
				clearLoadingMessages();

				let errorMessage = zwTTVGPT.strings.error;
				try {
					const response = JSON.parse(xhr.responseText);
					if (response && response.data) {
						errorMessage =
							typeof response.data === 'string'
								? response.data
								: response.data.message || errorMessage;
					}
				} catch {
					errorMessage = error
						? `${zwTTVGPT.strings.error}: ${error}`
						: zwTTVGPT.strings.error;
				}

				showStatus('error', errorMessage);
				setLoadingState($button, false);
				$button.data('is-generating', false);
			},
		});
	}

	/**
	 * Extract plain text from a Gutenberg block
	 * @param {Object} block Block object
	 * @return {string} Plain text content
	 */
	function getBlockText(block) {
		// Get inner HTML from block and strip tags using WordPress built-in
		const html = wp.blocks.getBlockContent(block);
		const text = wp.sanitize.stripTags(html);

		// Process inner blocks recursively
		if (block.innerBlocks && block.innerBlocks.length > 0) {
			const innerText = block.innerBlocks
				.map(getBlockText)
				.filter(Boolean)
				.join('\n');
			return text + (text && innerText ? '\n' : '') + innerText;
		}

		return text;
	}

	/**
	 * Extract content from active editor (Block Editor, TinyMCE, or textarea)
	 * @return {string} Editor content as plain text
	 */
	function getEditorContent() {
		let content = '';

		// Try Block Editor (Gutenberg) first
		if (
			typeof wp !== 'undefined' &&
			wp.data &&
			wp.data.select('core/block-editor')
		) {
			const editor = wp.data.select('core/block-editor');
			const blocks = editor.getBlocks();

			if (blocks && blocks.length > 0) {
				// Extract text from each block
				const textParts = blocks.map(getBlockText).filter(Boolean);
				content = textParts.join('\n\n');

				if (content.trim().length > 0) {
					return cleanupWhitespace(content);
				}
			}
		}

		// Try TinyMCE (Classic Editor) - already returns plain text
		if (
			typeof tinyMCE !== 'undefined' &&
			tinyMCE.activeEditor &&
			!tinyMCE.activeEditor.isHidden() &&
			tinyMCE.activeEditor.initialized
		) {
			content = tinyMCE.activeEditor.getContent({ format: 'text' });
			if (content && content.trim().length > 0) {
				return content;
			}
		}

		// Fallback to textarea - strip HTML for safety
		const $textarea = $(SELECTORS.contentEditor);
		if ($textarea.length > 0) {
			content = $textarea.val();
			if (content && content.trim().length > 0) {
				return cleanupWhitespace(wp.sanitize.stripTags(content));
			}
		}

		return '';
	}

	/**
	 * Clean up excessive whitespace in text
	 * @param {string} text Text to clean
	 * @return {string} Text with normalized whitespace
	 */
	function cleanupWhitespace(text) {
		if (!text || typeof text !== 'string') {
			return '';
		}

		return text
			.replace(/\n{3,}/g, '\n\n') // Max 2 consecutive newlines
			.replace(/[ \t]+/g, ' ') // Multiple spaces/tabs to single space
			.trim(); // Remove leading/trailing whitespace
	}

	/**
	 * Extract selected region names from taxonomy checkboxes
	 * @return {Array<string>} Array of selected region names
	 */
	function getSelectedRegions() {
		const regions = [];
		$(SELECTORS.regionCheckboxes).each(function () {
			const $label = $(this).parent();
			/*
			 * Get only the direct text content, not from child elements
			 * This excludes text from Yoast's screen-reader-text spans
			 */
			const labelText = $label
				.contents()
				.filter(function () {
					return this.nodeType === 3; // Text nodes only
				})
				.text()
				.trim();

			if (labelText) {
				regions.push(labelText);
			}
		});
		return regions;
	}

	/**
	 * Randomize array order using Fisher-Yates shuffle algorithm
	 * @param {Array} array Array to shuffle
	 * @return {Array} New shuffled array
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
	 * Start animated thinking indicator with cycling characters
	 * @param {jQuery|HTMLElement} element Element to animate
	 * @param {string}             text    Optional text to append after spinner
	 * @return {number} Interval ID for cleanup
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
		// Get current message count
		const getMessageCount = $cachedAcfField.data('message-count'),
			currentMessageCount =
				getMessageCount && typeof getMessageCount === 'function'
					? getMessageCount()
					: 0;

		// Ensure at least 2 messages have been shown
		const minMessages = 2,
			messagesNeeded = minMessages - currentMessageCount;

		if (messagesNeeded > 0) {
			/*
			 * Calculate proper wait time
			 * First message shows immediately (0ms)
			 * Second message shows after 1200ms
			 */
			let waitTime = 0;
			if (currentMessageCount === 0) {
				// Need to wait for both messages
				waitTime = 1700; // 1200ms for second message + 500ms buffer
			} else if (currentMessageCount === 1) {
				// Only need to wait for second message to finish
				waitTime = 700; // Buffer for transition to complete
			}

			setTimeout(function () {
				// Update ACF fields with animation
				animateText($cachedAcfField, data.summary, $button);
				$cachedGptField.val(data.summary);
			}, waitTime);
		} else {
			/*
			 * Already shown enough messages
			 * Add small delay to ensure last transition completes
			 */
			setTimeout(function () {
				animateText($cachedAcfField, data.summary, $button);
				$cachedGptField.val(data.summary);
			}, 500);
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

		// Small delay to prevent collision with loading messages
		setTimeout(function () {
			$element.val('').prop('disabled', true);
			typeCharacter();
		}, 100);

		function typeCharacter() {
			if (index < text.length) {
				// Type multiple characters at once - faster
				const charsToType = Math.floor(Math.random() * 6) + 2, // 2-7 chars
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
				// Re-enable field immediately when done
				$element.prop('disabled', false);

				// Ensure button is re-enabled when typing completes
				if ($button && $button.data('is-generating')) {
					setLoadingState($button, false);
					$button.data('is-generating', false);
				}
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

		// Clear any active transition
		const getActiveTransition = $cachedAcfField.data('active-transition');
		if (getActiveTransition && typeof getActiveTransition === 'function') {
			const activeTransition = getActiveTransition();
			if (activeTransition) {
				clearInterval(activeTransition);
			}
			$cachedAcfField.removeData('active-transition');
		}

		// Clear count data
		$cachedAcfField.removeData('message-count');

		/*
		 * Don't clear the value or re-enable here - let animateText handle it
		 * This prevents the collision between loading messages and typed text
		 */
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
			messageCount = 0,
			activeTransition = null;

		// Clear the field and disable it
		$cachedAcfField.prop('disabled', true).attr('placeholder', '');

		// Show first message immediately
		$cachedAcfField.val(messages[messageIndex]);
		messageIndex++;
		messageCount++;

		// Function to show next message
		function showNextMessage() {
			if (messageIndex >= messages.length) {
				// Reshuffle when we've shown all messages
				messages = shuffleArray(messages);
				messageIndex = 0;
			}

			// Get next message
			const nextMessage = messages[messageIndex];
			let charIndex = 0;

			// Clear any existing transition
			if (activeTransition) {
				clearInterval(activeTransition);
			}

			// Gradually replace current text with next message
			activeTransition = setInterval(function () {
				if (charIndex <= nextMessage.length) {
					$cachedAcfField.val(nextMessage.substring(0, charIndex));
					charIndex += 2; // Type 2 chars at a time for smooth transition
				} else {
					clearInterval(activeTransition);
					activeTransition = null;
				}
			}, 20);

			messageIndex++;
			messageCount++;
		}

		// Show second message quickly, then normal speed for rest
		setTimeout(showNextMessage, 1200); // Show second message after 1.2s

		// Then cycle through remaining messages at normal speed
		const messageInterval = setInterval(showNextMessage, 2500);

		// Store intervals and count getter for cleanup
		$cachedAcfField.data('message-interval', messageInterval);
		$cachedAcfField.data('active-transition', () => activeTransition);
		$cachedAcfField.data('message-count', () => messageCount);
	}

	// Initialize when ready
	init();
})(jQuery);
