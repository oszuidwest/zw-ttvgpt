<?php
/**
 * Summary Generator class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Summary Generator class
 *
 * Core functionality for generating summaries
 */
class TTVGPTSummaryGenerator {
    // Constants are now in TTVGPTConstants class

    /**
     * API handler instance
     *
     * @var TTVGPTApiHandler
     */
    private TTVGPTApiHandler $api_handler;

    /**
     * Word limit for summaries
     *
     * @var int
     */
    private int $word_limit;

    /**
     * Logger instance
     *
     * @var TTVGPTLogger
     */
    private TTVGPTLogger $logger;

    /**
     * Constructor
     *
     * @param TTVGPTApiHandler $api_handler API handler instance
     * @param int              $word_limit  Word limit for summaries
     * @param TTVGPTLogger     $logger      Logger instance
     */
    public function __construct(TTVGPTApiHandler $api_handler, int $word_limit, TTVGPTLogger $logger) {
        $this->api_handler = $api_handler;
        $this->word_limit = $word_limit;
        $this->logger = $logger;

        // Register AJAX handler
        add_action('wp_ajax_zw_ttvgpt_generate', array($this, 'handle_ajax_request'));
    }

    /**
     * Handle AJAX request for summary generation
     *
     * @return void
     */
    public function handle_ajax_request(): void {
        // Verify nonce
        if (!check_ajax_referer('zw_ttvgpt_nonce', 'nonce', false)) {
            $this->logger->error('Security check failed: invalid nonce');
            wp_send_json_error(__('Beveiligingscontrole mislukt', 'zw-ttvgpt'), 403);
        }

        // Check capabilities
        if (!current_user_can(TTVGPTConstants::EDIT_CAPABILITY)) {
            $this->logger->error('Insufficient capabilities for summary generation');
            wp_send_json_error(__('Onvoldoende rechten', 'zw-ttvgpt'), 403);
        }

        // Get and validate input
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (empty($content)) {
            wp_send_json_error(__('Geen content opgegeven', 'zw-ttvgpt'), 400);
        }

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Ongeldig bericht ID', 'zw-ttvgpt'), 400);
        }

        // Strip HTML tags for processing
        $clean_content = wp_strip_all_tags($content);

        // Validate word count
        $word_count = str_word_count($clean_content);
        if ($word_count < TTVGPTConstants::MIN_WORD_COUNT) {
            $this->logger->debug('Content too short', array(
                'word_count' => $word_count,
                'required' => TTVGPTConstants::MIN_WORD_COUNT
            ));
            wp_send_json_error(
                sprintf(
                    __('Te weinig woorden. Minimaal %d woorden vereist, %d gevonden.', 'zw-ttvgpt'),
                    TTVGPTConstants::MIN_WORD_COUNT,
                    $word_count
                ),
                400
            );
        }

        // Check rate limiting
        if ($this->is_rate_limited()) {
            wp_send_json_error(__('Te veel aanvragen. Wacht even voordat je opnieuw probeert.', 'zw-ttvgpt'), 429);
        }

        // Generate summary
        $result = $this->api_handler->generate_summary($clean_content, $this->word_limit);

        if (!$result['success']) {
            wp_send_json_error($result['error'], 500);
        }

        // Get selected regions if available
        $regions = isset($_POST['regions']) ? array_map('sanitize_text_field', (array) $_POST['regions']) : array();
        $summary = $result['data'];

        // Prepend regions to summary if provided
        if (!empty($regions)) {
            $regions_text = implode(' / ', array_map('strtoupper', $regions));
            $summary = $regions_text . ' - ' . $summary;
        }

        // Save to ACF fields if requested
        if (!empty($_POST['save_to_acf'])) {
            $this->save_to_acf($post_id, $summary);
        }

        // Update rate limiting
        $this->update_rate_limit();

        // Minimale logging bij succes
        $this->logger->debug('Summary generated for post ' . $post_id);

        wp_send_json_success(array(
            'summary' => $summary,
            'word_count' => str_word_count($summary)
        ));
    }

    /**
     * Save summary to ACF fields
     *
     * @param int    $post_id Post ID
     * @param string $summary Generated summary
     * @return void
     */
    private function save_to_acf(int $post_id, string $summary): void {
        if (!function_exists('update_field')) {
            $this->logger->error('ACF not available');
            return;
        }

        // Update summary field
        update_field(TTVGPTConstants::ACF_SUMMARY_FIELD, $summary, $post_id);
        
        // Mark as GPT-generated
        update_field(TTVGPTConstants::ACF_GPT_MARKER_FIELD, $summary, $post_id);

        $this->logger->debug('Saved summary to ACF fields', array(
            'post_id' => $post_id
        ));
    }

    /**
     * Check if user is rate limited
     *
     * @return bool
     */
    private function is_rate_limited(): bool {
        $user_id = get_current_user_id();
        $transient_key = TTVGPTConstants::get_rate_limit_key($user_id);
        $requests = get_transient($transient_key);

        return $requests >= TTVGPTConstants::RATE_LIMIT_MAX_REQUESTS;
    }

    /**
     * Update rate limit counter
     *
     * @return void
     */
    private function update_rate_limit(): void {
        $user_id = get_current_user_id();
        $transient_key = TTVGPTConstants::get_rate_limit_key($user_id);
        $requests = get_transient($transient_key);

        if ($requests === false) {
            set_transient($transient_key, 1, TTVGPTConstants::RATE_LIMIT_WINDOW);
        } else {
            set_transient($transient_key, $requests + 1, TTVGPTConstants::RATE_LIMIT_WINDOW);
        }
    }
}