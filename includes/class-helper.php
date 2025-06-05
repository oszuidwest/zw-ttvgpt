<?php
/**
 * Helper class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Helper class
 *
 * Common utility functions for the plugin
 */
class TTVGPTHelper {
    /**
     * Create error response array
     *
     * @param string $error_message Error message
     * @return array
     */
    public static function error_response(string $error_message): array {
        return array(
            'success' => false,
            'error' => $error_message
        );
    }
    
    /**
     * Create success response array
     *
     * @param mixed $data Response data
     * @return array
     */
    public static function success_response($data): array {
        return array(
            'success' => true,
            'data' => $data
        );
    }
    
    /**
     * Clean up database transients
     *
     * @global \wpdb $wpdb WordPress database object
     * @return void
     */
    public static function cleanup_transients(): void {
        global $wpdb;
        
        // Delete rate limit transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . TTVGPTConstants::RATE_LIMIT_PREFIX . '%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . TTVGPTConstants::RATE_LIMIT_PREFIX . '%'
            )
        );
    }
    
    /**
     * Clean up all plugin data
     *
     * @return void
     */
    public static function cleanup_plugin_data(): void {
        // Delete settings
        TTVGPTSettingsManager::delete_settings();
        
        // Clean up transients
        self::cleanup_transients();
    }
    
    /**
     * Get ACF field IDs for JavaScript localization
     *
     * @return array
     */
    public static function get_acf_field_ids(): array {
        return array(
            'summary' => 'acf-' . TTVGPTConstants::ACF_SUMMARY_FIELD,
            'gpt_marker' => 'acf-' . TTVGPTConstants::ACF_GPT_MARKER_FIELD
        );
    }
    
    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @return bool
     */
    public static function is_valid_api_key(string $api_key): bool {
        return !empty($api_key) && strpos($api_key, 'sk-') === 0;
    }
    
    /**
     * Get user-friendly error message for API status codes
     *
     * @param int $status_code HTTP status code
     * @return string
     */
    public static function get_api_error_message(int $status_code): string {
        $messages = array(
            400 => __('Ongeldige aanvraag', 'zw-ttvgpt'),
            401 => __('Ongeldige API key', 'zw-ttvgpt'),
            403 => __('Toegang geweigerd', 'zw-ttvgpt'),
            404 => __('Model niet gevonden', 'zw-ttvgpt'),
            429 => __('Te veel aanvragen, probeer later opnieuw', 'zw-ttvgpt'),
            500 => __('OpenAI server fout', 'zw-ttvgpt'),
            503 => __('OpenAI service tijdelijk niet beschikbaar', 'zw-ttvgpt')
        );

        return $messages[$status_code] ?? sprintf(
            __('API fout: HTTP %d', 'zw-ttvgpt'),
            $status_code
        );
    }
}