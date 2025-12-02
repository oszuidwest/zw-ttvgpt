/**
 * ZW TTVGPT Admin JavaScript (ES Module)
 *
 * Manages summary generation interface with typing animations and loading states.
 * Uses native ES modules (WordPress 6.5+) with wp_enqueue_script_module.
 *
 * @package
 */

const SELECTORS = {
	contentEditor: '.wp-editor-area',
	acfSummaryField: `#${window.zwTTVGPT.acfFields.summary}`,
	acfGptField: `#${window.zwTTVGPT.acfFields.gpt_marker}`,
	regionCheckboxes: '#regiochecklist input[type="checkbox"]:checked',
};

const THINKING_CHARS = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
const THINKING_SPEED = 80;
const TYPING_DELAYS = {
	sentence: [8, 12],
	comma: [3, 5],
	space: [1, 2],
	default: [0.5, 1.5],
};

let cachedAcfField = null;
let cachedGptField = null;

/**
 * Fisher-Yates shuffle algorithm (replaces _.shuffle)
 *
 * @param {Array} array Array to shuffle.
 * @return {Array} New shuffled array.
 */
function shuffle(array) {
	const result = [...array];
	for (let i = result.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1));
		[result[i], result[j]] = [result[j], result[i]];
	}
	return result;
}

/**
 * Query selector helper
 *
 * @param {string}           selector CSS selector.
 * @param {Element|Document} context  Context element.
 * @return {Element|null} Found element or null.
 */
function $(selector, context = document) {
	return context.querySelector(selector);
}

/**
 * Query selector all helper
 *
 * @param {string}           selector CSS selector.
 * @param {Element|Document} context  Context element.
 * @return {NodeList} Found elements.
 */
function $$(selector, context = document) {
	return context.querySelectorAll(selector);
}

/**
 * Initialize plugin components and cache DOM elements.
 */
function init() {
	document.addEventListener('DOMContentLoaded', () => {
		cachedAcfField = $(SELECTORS.acfSummaryField);
		cachedGptField = $(SELECTORS.acfGptField);

		injectGenerateButton();
	});
}

/**
 * Create and inject generate button below ACF summary field.
 */
function injectGenerateButton() {
	if (!cachedAcfField) {
		return;
	}

	const postIdField = $('#post_ID');
	const button = document.createElement('button');
	button.type = 'button';
	button.className = 'button button-secondary zw-ttvgpt-inline-generate';
	button.dataset.postId = postIdField ? postIdField.value : '';
	button.textContent = window.zwTTVGPT.strings.buttonText;
	button.style.marginTop = '8px';

	cachedAcfField.parentElement.appendChild(button);
	button.addEventListener('click', handleGenerateClick);
}

/**
 * Process generate button click and initiate summary generation.
 *
 * @param {Event} e Click event.
 */
async function handleGenerateClick(e) {
	e.preventDefault();

	const button = e.currentTarget;

	if (button.disabled) {
		return;
	}

	const content = getEditorContent();
	const postId = button.dataset.postId;

	if (!content || content.trim().length === 0) {
		showStatus(
			'error',
			'Geen inhoud gevonden. Zorg dat de editor geladen is en voeg eerst tekst toe.'
		);
		return;
	}

	const regions = getSelectedRegions();

	// Debug: log content being sent to API
	if (window.zwTTVGPT.debugMode) {
		console.log('ZW TTVGPT Debug - Content wordt verstuurd naar API:');
		console.log('Post ID:', postId);
		console.log('Content lengte:', content.length, 'tekens');
		console.log("Regio's:", regions);
		console.log('Content:', content);
	}

	setLoadingState(button, true);
	button.dataset.isGenerating = 'true';
	showLoadingMessages();

	try {
		const formData = new FormData();
		formData.append('action', 'zw_ttvgpt_generate');
		formData.append('nonce', window.zwTTVGPT.nonce);
		formData.append('post_id', postId);
		formData.append('content', content);
		regions.forEach((region) => formData.append('regions[]', region));

		const response = await fetch(window.zwTTVGPT.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		});

		const data = await response.json();

		// Debug: log API response
		if (window.zwTTVGPT.debugMode) {
			console.log('ZW TTVGPT Debug - API Response:', data);
		}

		if (data.success) {
			clearLoadingMessages();
			handleSuccess(data.data, button);
		} else {
			clearLoadingMessages();
			const errorMessage =
				typeof data.data === 'string'
					? data.data
					: data.data?.message || window.zwTTVGPT.strings.error;
			showStatus('error', errorMessage);
			setLoadingState(button, false);
			button.dataset.isGenerating = 'false';
		}
	} catch (error) {
		console.error('ZW TTVGPT Error:', error);
		clearLoadingMessages();
		showStatus(
			'error',
			`${window.zwTTVGPT.strings.error}: ${error.message}`
		);
		setLoadingState(button, false);
		button.dataset.isGenerating = 'false';
	}
}

/**
 * Extract plain text from a Gutenberg block.
 *
 * @param {Object} block Block object.
 * @return {string} Plain text content.
 */
function getBlockText(block) {
	// Get inner HTML from block and strip tags using WordPress built-in
	const html = window.wp.blocks.getBlockContent(block);
	const text = window.wp.sanitize.stripTags(html);

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
 * Extract content from active editor (Block Editor, TinyMCE, or textarea).
 *
 * @return {string} Editor content as plain text.
 */
function getEditorContent() {
	let content = '';

	// Try Block Editor (Gutenberg) first
	if (
		typeof window.wp !== 'undefined' &&
		window.wp.data &&
		window.wp.data.select('core/block-editor')
	) {
		const editor = window.wp.data.select('core/block-editor');
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
		typeof window.tinyMCE !== 'undefined' &&
		window.tinyMCE.activeEditor &&
		!window.tinyMCE.activeEditor.isHidden() &&
		window.tinyMCE.activeEditor.initialized
	) {
		content = window.tinyMCE.activeEditor.getContent({ format: 'text' });
		if (content && content.trim().length > 0) {
			return content;
		}
	}

	// Fallback to textarea - strip HTML for safety
	const textarea = $(SELECTORS.contentEditor);
	if (textarea) {
		content = textarea.value;
		if (content && content.trim().length > 0) {
			return cleanupWhitespace(window.wp.sanitize.stripTags(content));
		}
	}

	return '';
}

/**
 * Clean up excessive whitespace in text.
 *
 * @param {string} text Text to clean.
 * @return {string} Text with normalized whitespace.
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
 * Check if checkbox is from Block Editor.
 *
 * @param {Element} checkbox Checkbox element.
 * @return {boolean} True if Block Editor checkbox.
 */
function isBlockEditorCheckbox(checkbox) {
	const id = checkbox.id;
	return id && id.startsWith('inspector-checkbox-control');
}

/**
 * Get label text for Block Editor checkbox.
 *
 * @param {Element} checkbox Checkbox element.
 * @return {string} Label text.
 */
function getBlockEditorLabel(checkbox) {
	const label = $(`label[for="${checkbox.id}"]`);
	return label ? label.textContent.trim() : '';
}

/**
 * Get label text for Classic Editor checkbox.
 *
 * @param {Element} checkbox Checkbox element.
 * @return {string} Label text.
 */
function getClassicEditorLabel(checkbox) {
	const parent = checkbox.parentElement;
	if (!parent) {
		return '';
	}

	// Get text nodes only
	let text = '';
	for (const node of parent.childNodes) {
		if (node.nodeType === Node.TEXT_NODE) {
			text += node.textContent;
		}
	}
	return text.trim();
}

/**
 * Extract selected region names from taxonomy checkboxes.
 * Supports both Block Editor and Classic Editor.
 *
 * @return {Array<string>} Array of selected region names.
 */
function getSelectedRegions() {
	// Try Block Editor first, fallback to Classic Editor
	let checkboxes = $$(
		'.editor-post-taxonomies__hierarchical-terms-list input[type="checkbox"]:checked'
	);

	if (checkboxes.length === 0) {
		checkboxes = $$(SELECTORS.regionCheckboxes);
	}

	if (window.zwTTVGPT.debugMode) {
		const firstCheckbox = checkboxes[0];
		console.log('ZW TTVGPT Debug - Region detection:', {
			editor:
				firstCheckbox && isBlockEditorCheckbox(firstCheckbox)
					? 'Block Editor'
					: 'Classic Editor',
			checkedCount: checkboxes.length,
		});
	}

	const regions = [];
	checkboxes.forEach((checkbox) => {
		const labelText = isBlockEditorCheckbox(checkbox)
			? getBlockEditorLabel(checkbox)
			: getClassicEditorLabel(checkbox);

		if (labelText) {
			regions.push(labelText);
		}
	});

	return regions;
}

/**
 * Start animated thinking indicator with cycling characters.
 *
 * @param {Element} element Element to animate.
 * @param {string}  text    Optional text to append after spinner.
 * @return {number} Interval ID for cleanup.
 */
function startThinkingAnimation(element, text = '') {
	let index = 0;
	const isButton = element.tagName === 'BUTTON';

	const interval = setInterval(() => {
		const char = THINKING_CHARS[index % THINKING_CHARS.length];
		if (isButton) {
			element.innerHTML = char + (text ? ` ${text}` : '');
		} else {
			element.value = char;
		}
		index++;
	}, THINKING_SPEED);

	return interval;
}

/**
 * Handle successful API response and update UI.
 *
 * @param {Object}  data   Response data containing summary.
 * @param {Element} button Generate button element.
 */
function handleSuccess(data, button) {
	// Get current message count
	const messageCountGetter = cachedAcfField._messageCount;
	const currentMessageCount =
		messageCountGetter && typeof messageCountGetter === 'function'
			? messageCountGetter()
			: 0;

	// Ensure at least 2 messages have been shown
	const minMessages = 2;
	const messagesNeeded = minMessages - currentMessageCount;

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

		setTimeout(() => {
			// Update ACF fields with animation
			animateText(cachedAcfField, data.summary, button);
			if (cachedGptField) {
				cachedGptField.value = data.summary;
			}
		}, waitTime);
	} else {
		/*
		 * Already shown enough messages
		 * Add small delay to ensure last transition completes
		 */
		setTimeout(() => {
			animateText(cachedAcfField, data.summary, button);
			if (cachedGptField) {
				cachedGptField.value = data.summary;
			}
		}, 500);
	}
}

/**
 * Animate text typing effect with ChatGPT-style character animation.
 *
 * @param {Element} element Target element to type into.
 * @param {string}  text    Text to animate.
 * @param {Element} button  Generate button to re-enable after completion.
 */
function animateText(element, text, button) {
	let index = 0;

	// Clear any loading messages interval
	if (element._messageInterval) {
		clearInterval(element._messageInterval);
		delete element._messageInterval;
	}

	// Small delay to prevent collision with loading messages
	setTimeout(() => {
		element.value = '';
		element.disabled = true;
		typeCharacter();
	}, 100);

	function typeCharacter() {
		if (index < text.length) {
			// Type multiple characters at once - faster
			const charsToType = Math.floor(Math.random() * 6) + 2; // 2-7 chars
			const newText = text.substr(index, charsToType);
			index += newText.length;

			element.value += newText;

			// Scroll to bottom if needed
			element.scrollTop = element.scrollHeight;

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
			element.disabled = false;

			// Ensure button is re-enabled when typing completes
			if (button && button.dataset.isGenerating === 'true') {
				setLoadingState(button, false);
				button.dataset.isGenerating = 'false';
			}
		}
	}
}

/**
 * Set loading state for generate button.
 *
 * @param {Element} button    Button element.
 * @param {boolean} isLoading True to enable loading state, false to disable.
 */
function setLoadingState(button, isLoading) {
	if (isLoading) {
		button.disabled = true;
		button.classList.add('zw-ttvgpt-generating');

		// Start thinking animation
		const interval = startThinkingAnimation(
			button,
			window.zwTTVGPT.strings.generating
		);
		button._thinkingInterval = interval;
	} else {
		// Clear thinking animation
		if (button._thinkingInterval) {
			clearInterval(button._thinkingInterval);
			delete button._thinkingInterval;
		}

		button.disabled = false;
		button.classList.remove('zw-ttvgpt-generating');
		button.textContent = window.zwTTVGPT.strings.buttonText;
	}
}

/**
 * Show status message to user.
 *
 * @param {string} type    Message type ('error' or 'success').
 * @param {string} message Message text to display.
 */
function showStatus(type, message) {
	// Create a temporary notice above the ACF field
	if (!cachedAcfField) {
		return;
	}

	// Remove any existing status
	const existingStatus = $('.zw-ttvgpt-status');
	if (existingStatus) {
		existingStatus.remove();
	}

	const cssClass = type === 'error' ? 'notice-error' : 'notice-success';
	const status = document.createElement('div');
	status.className = `notice ${cssClass} zw-ttvgpt-status`;
	status.style.margin = '10px 0';
	status.innerHTML = `<p>${message}</p>`;

	cachedAcfField.parentElement.insertBefore(status, cachedAcfField);

	// Auto-hide success messages
	if (type === 'success') {
		setTimeout(() => {
			status.style.transition = 'opacity 0.3s';
			status.style.opacity = '0';
			setTimeout(() => status.remove(), 300);
		}, window.zwTTVGPT.timeouts.successMessage || 3000);
	}
}

/**
 * Clear loading messages and restore ACF field.
 */
function clearLoadingMessages() {
	if (!cachedAcfField) {
		return;
	}

	if (cachedAcfField._messageInterval) {
		clearInterval(cachedAcfField._messageInterval);
		delete cachedAcfField._messageInterval;
	}

	// Clear any active transition
	if (cachedAcfField._activeTransition) {
		const activeTransition =
			typeof cachedAcfField._activeTransition === 'function'
				? cachedAcfField._activeTransition()
				: cachedAcfField._activeTransition;
		if (activeTransition) {
			clearInterval(activeTransition);
		}
		delete cachedAcfField._activeTransition;
	}

	// Clear count data
	delete cachedAcfField._messageCount;

	/*
	 * Don't clear the value or re-enable here - let animateText handle it
	 * This prevents the collision between loading messages and typed text
	 */
}

/**
 * Show loading messages in ACF field while generating.
 */
function showLoadingMessages() {
	if (!cachedAcfField || !window.zwTTVGPT.strings.loadingMessages) {
		return;
	}

	// Create a shuffled copy of messages
	let messages = shuffle(window.zwTTVGPT.strings.loadingMessages);
	let messageIndex = 0;
	let messageCount = 0;
	let activeTransition = null;

	// Clear the field and disable it
	cachedAcfField.disabled = true;
	cachedAcfField.placeholder = '';

	// Show first message immediately
	cachedAcfField.value = messages[messageIndex];
	messageIndex++;
	messageCount++;

	// Function to show next message
	function showNextMessage() {
		if (messageIndex >= messages.length) {
			// Reshuffle when we've shown all messages
			messages = shuffle(messages);
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
		activeTransition = setInterval(() => {
			if (charIndex <= nextMessage.length) {
				cachedAcfField.value = nextMessage.substring(0, charIndex);
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
	cachedAcfField._messageInterval = messageInterval;
	cachedAcfField._activeTransition = () => activeTransition;
	cachedAcfField._messageCount = () => messageCount;
}

// Initialize when ready
init();
