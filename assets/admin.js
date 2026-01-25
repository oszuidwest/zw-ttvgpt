/**
 * ZW TTVGPT Admin JavaScript (ES Module)
 *
 * Manages summary generation interface with typing animations and loading states.
 * Uses native ES modules (WordPress 6.5+) with wp_enqueue_script_module.
 *
 * @package ZW_TTVGPT
 */

const SELECTORS = {
    contentEditor: '.wp-editor-area',
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
const MESSAGE_TIMING = {
    firstMessageDelay: 1200,
    messageInterval: 2500,
    transitionSpeed: 20,
    waitForBothMessages: 1700,
    waitForSecondMessage: 700,
    transitionBuffer: 500,
    typeStartDelay: 100,
};

let cachedAcfField = null;
let cachedGptField = null;
let cachedWordCounter = null;

const elementState = new WeakMap();

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
    // ES modules are deferred, so DOMContentLoaded may have already fired
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
}

/**
 * DOM ready handler - cache elements and inject button.
 */
function onReady() {
    const config = window.zwTTVGPT;
    if (!config?.acfFields) {
        return;
    }

    cachedAcfField = $(`#${config.acfFields.summary}`);
    cachedGptField = $(`#${config.acfFields.gpt_marker}`);

    injectGenerateButton();
}

/**
 * Count words in text (matches PHP str_word_count behavior).
 *
 * @param {string} text Text to count words in.
 * @return {number} Word count.
 */
function countWords(text) {
    if (!text || typeof text !== 'string') {
        return 0;
    }
    let count = 0;
    const regex = /[\p{L}]+([-'][\p{L}]+)*/gu;
    while (regex.exec(text)) {
        count++;
    }
    return count;
}

/**
 * Update word counter display with current word count.
 */
function updateWordCounter() {
    if (!cachedWordCounter || !cachedAcfField) {
        return;
    }

    const text = cachedAcfField.value || '';
    const wordCount = countWords(text);
    const wordLimit = window.zwTTVGPT.wordLimit || 100;
    const isOverLimit = wordCount > wordLimit;

    cachedWordCounter.textContent = `${wordCount} / ${wordLimit} woorden`;
    cachedWordCounter.classList.toggle(
        'zw-ttvgpt-word-counter--over',
        isOverLimit,
    );
    cachedWordCounter.classList.toggle(
        'zw-ttvgpt-word-counter--ok',
        !isOverLimit && wordCount > 0,
    );
}

/**
 * Create and inject generate button below ACF summary field.
 */
function injectGenerateButton() {
    if (!cachedAcfField) {
        return;
    }

    // Create container for button and word counter
    const container = document.createElement('div');
    container.className = 'zw-ttvgpt-controls';
    container.style.cssText =
        'display:flex;align-items:center;gap:12px;margin-top:8px';

    const postIdField = $('#post_ID');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary zw-ttvgpt-inline-generate';
    button.dataset.postId = postIdField ? postIdField.value : '';
    button.textContent = window.zwTTVGPT.strings.buttonText;

    // Create word counter element
    cachedWordCounter = document.createElement('span');
    cachedWordCounter.className = 'zw-ttvgpt-word-counter';
    cachedWordCounter.setAttribute('aria-live', 'polite');

    container.appendChild(button);
    container.appendChild(cachedWordCounter);
    cachedAcfField.parentElement.appendChild(container);

    button.addEventListener('click', handleGenerateClick);

    // Bind input events for real-time word count updates
    cachedAcfField.addEventListener('input', updateWordCounter);
    cachedAcfField.addEventListener('change', updateWordCounter);
    cachedAcfField.addEventListener('keyup', updateWordCounter);

    // Initial word count update
    updateWordCounter();
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
            'Geen inhoud gevonden. Zorg dat de editor geladen is en voeg eerst tekst toe.',
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
        for (const region of regions) {
            formData.append('regions[]', region);
        }

        const response = await fetch(window.zwTTVGPT.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        });

        let data;
        try {
            data = await response.json();
        } catch (_parseError) {
            throw new Error(`Server error: ${response.status}`);
        }

        if (!response.ok && !data?.data?.message) {
            throw new Error(`Server error: ${response.status}`);
        }

        if (window.zwTTVGPT.debugMode) {
            console.log('ZW TTVGPT Debug - API Response:', data);
        }

        clearLoadingMessages();

        if (data.success) {
            handleSuccess(data.data, button);
        } else {
            if (window.zwTTVGPT.debugMode && data.data?.code) {
                console.error('ZW TTVGPT Error Code:', data.data.code);
            }
            showStatus(
                'error',
                data.data?.message || window.zwTTVGPT.strings.error,
            );
            setLoadingState(button, false);
            button.dataset.isGenerating = 'false';
        }
    } catch (error) {
        console.error('ZW TTVGPT Error:', error);
        clearLoadingMessages();
        showStatus(
            'error',
            `${window.zwTTVGPT.strings.error}: ${error.message}`,
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
    return id?.startsWith('inspector-checkbox-control') ?? false;
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
        '.editor-post-taxonomies__hierarchical-terms-list input[type="checkbox"]:checked',
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
            // Use textContent for security (no HTML injection)
            element.textContent = char + (text ? ` ${text}` : '');
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
    const state = elementState.get(cachedAcfField) || {};
    const messageCount = state.messageCount || 0;

    // Calculate wait time based on how many loading messages have been shown.
    // We want at least 2 messages displayed before showing the result.
    let waitTime;
    if (messageCount === 0) {
        waitTime = MESSAGE_TIMING.waitForBothMessages;
    } else if (messageCount === 1) {
        waitTime = MESSAGE_TIMING.waitForSecondMessage;
    } else {
        waitTime = MESSAGE_TIMING.transitionBuffer;
    }

    setTimeout(() => {
        animateText(cachedAcfField, data.summary, button, data.warning);
        if (cachedGptField) {
            cachedGptField.value = data.summary;
        }
    }, waitTime);
}

/**
 * Animate text typing effect with ChatGPT-style character animation.
 *
 * @param {Element}     element Target element to type into.
 * @param {string}      text    Text to animate.
 * @param {Element}     button  Generate button to re-enable after completion.
 * @param {string|null} warning Optional warning message to display after animation.
 */
function animateText(element, text, button, warning = null) {
    let index = 0;

    // Clear any loading messages interval
    const state = elementState.get(element);
    if (state?.messageInterval) {
        clearInterval(state.messageInterval);
        elementState.delete(element);
    }

    // Small delay to prevent collision with loading messages
    setTimeout(() => {
        element.value = '';
        element.disabled = true;
        typeCharacter();
    }, MESSAGE_TIMING.typeStartDelay);

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

            // Update word counter with final text
            updateWordCounter();

            // Show warning if validation failed
            if (warning) {
                showStatus('warning', warning);
            }

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
            window.zwTTVGPT.strings.generating,
        );
        elementState.set(button, { thinkingInterval: interval });
    } else {
        // Clear thinking animation
        const state = elementState.get(button);
        if (state?.thinkingInterval) {
            clearInterval(state.thinkingInterval);
            elementState.delete(button);
        }

        button.disabled = false;
        button.classList.remove('zw-ttvgpt-generating');
        button.textContent = window.zwTTVGPT.strings.buttonText;
    }
}

/**
 * Show status message to user.
 *
 * @param {string} type    Message type ('error', 'warning', or 'success').
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

    const cssClasses = {
        error: 'notice-error',
        warning: 'notice-warning',
        success: 'notice-success',
    };
    const cssClass = cssClasses[type] || 'notice-info';
    const status = document.createElement('div');
    status.className = `notice ${cssClass} zw-ttvgpt-status`;
    status.style.margin = '10px 0';

    // Create paragraph element safely without innerHTML
    const paragraph = document.createElement('p');
    paragraph.textContent = message;
    status.appendChild(paragraph);

    cachedAcfField.parentElement.insertBefore(status, cachedAcfField);

    // Auto-hide success and warning messages
    if (type === 'success' || type === 'warning') {
        const timeouts = {
            warning: 8000,
            success: window.zwTTVGPT.timeouts.successMessage || 3000,
        };
        setTimeout(() => {
            status.style.transition = 'opacity 0.3s';
            status.style.opacity = '0';
            setTimeout(() => status.remove(), 300);
        }, timeouts[type]);
    }
}

/**
 * Clear loading messages and restore ACF field.
 */
function clearLoadingMessages() {
    if (!cachedAcfField) {
        return;
    }

    const state = elementState.get(cachedAcfField);
    if (!state) {
        return;
    }

    if (state.messageInterval) {
        clearInterval(state.messageInterval);
    }

    if (state.activeTransition) {
        clearInterval(state.activeTransition);
    }

    elementState.delete(cachedAcfField);
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

    // Update state helper
    function updateState() {
        elementState.set(cachedAcfField, {
            messageInterval,
            activeTransition,
            messageCount,
        });
    }

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
                charIndex += 2;
            } else {
                clearInterval(activeTransition);
                activeTransition = null;
                updateState();
            }
        }, MESSAGE_TIMING.transitionSpeed);

        messageIndex++;
        messageCount++;
        updateState();
    }

    // Show second message quickly, then normal speed for rest
    setTimeout(showNextMessage, MESSAGE_TIMING.firstMessageDelay);

    // Then cycle through remaining messages at normal speed
    const messageInterval = setInterval(
        showNextMessage,
        MESSAGE_TIMING.messageInterval,
    );

    // Store initial state
    updateState();
}

// Initialize when ready
init();
