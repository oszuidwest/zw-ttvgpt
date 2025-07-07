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
	 * Create standardized error response array
	 *
	 * @param string $error_message Error message to include
	 * @return array{success: false, error: string} Error response with success flag and error message
	 */
	public static function error_response( string $error_message ): array {
		return array(
			'success' => false,
			'error'   => $error_message,
		);
	}

	/**
	 * Create standardized success response array
	 *
	 * @param mixed $data Response data to include
	 * @return array{success: true, data: mixed} Success response with success flag and data
	 */
	public static function success_response( $data ): array {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Remove all plugin-related transients from database
	 *
	 * @global \wpdb $wpdb WordPress database object
	 * @return void
	 */
	public static function cleanup_transients(): void {
		global $wpdb;

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
	 * Remove all plugin data including settings and transients
	 *
	 * @return void
	 */
	public static function cleanup_plugin_data(): void {
		TTVGPTSettingsManager::delete_settings();
		self::cleanup_transients();
	}

	/**
	 * Generate ACF field selectors for JavaScript usage
	 *
	 * @return array Field selector mappings for client-side access
	 */
	public static function get_acf_field_ids(): array {
		return array(
			'summary'    => 'acf-' . TTVGPTConstants::ACF_SUMMARY_FIELD,
			'gpt_marker' => 'acf-' . TTVGPTConstants::ACF_GPT_MARKER_FIELD,
		);
	}

	/**
	 * Validate OpenAI API key format
	 *
	 * @param string $api_key API key to validate
	 * @return bool True if key format is valid (starts with 'sk-')
	 */
	public static function is_valid_api_key( string $api_key ): bool {
		return ! empty( $api_key ) && strpos( $api_key, 'sk-' ) === 0;
	}

	/**
	 * Convert HTTP status codes to user-friendly Dutch error messages
	 *
	 * @param int $status_code HTTP status code from API response
	 * @return string Localized error message for end users
	 */
	public static function get_api_error_message( int $status_code ): string {
		$messages = array(
			400 => __( 'Ongeldige aanvraag', 'zw-ttvgpt' ),
			401 => __( 'Ongeldige API key', 'zw-ttvgpt' ),
			403 => __( 'Toegang geweigerd', 'zw-ttvgpt' ),
			404 => __( 'Model niet gevonden', 'zw-ttvgpt' ),
			429 => __( 'Te veel aanvragen, probeer later opnieuw', 'zw-ttvgpt' ),
			500 => __( 'OpenAI server fout', 'zw-ttvgpt' ),
			503 => __( 'OpenAI service tijdelijk niet beschikbaar', 'zw-ttvgpt' ),
		);

		return $messages[ $status_code ] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'API fout: HTTP %d', 'zw-ttvgpt' ),
			$status_code
		);
	}

	/**
	 * Get the most recent month that has relevant posts for audit analysis
	 *
	 * @return array|null Array with 'year' and 'month' keys or null if no posts found
	 */
	public static function get_most_recent_audit_month(): ?array {
		global $wpdb;

		$result = $wpdb->get_row(
			"SELECT DISTINCT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
			WHERE pm1.meta_key = 'post_in_kabelkrant'
			  AND pm1.meta_value = '1'
			  AND pm2.meta_key = 'post_kabelkrant_content_gpt'
			  AND pm2.meta_value != ''
			  AND p.post_status = 'publish'
			ORDER BY p.post_date DESC
			LIMIT 1"
		);

		return $result ? array(
			'year'  => (int) $result->year,
			'month' => (int) $result->month,
		) : null;
	}

	/**
	 * Get all months with audit data for navigation
	 *
	 * @return array Array of arrays with 'year' and 'month' keys
	 */
	public static function get_audit_months(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT DISTINCT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
			WHERE pm1.meta_key = 'post_in_kabelkrant'
			  AND pm1.meta_value = '1'
			  AND pm2.meta_key = 'post_kabelkrant_content_gpt'
			  AND pm2.meta_value != ''
			  AND p.post_status = 'publish'
			ORDER BY year DESC, month DESC"
		);

		$months = array();
		foreach ( $results as $result ) {
			$months[] = array(
				'year'  => (int) $result->year,
				'month' => (int) $result->month,
			);
		}

		return $months;
	}

	/**
	 * Retrieve all posts for audit analysis from specific month and year
	 *
	 * @param int $year Target year
	 * @param int $month Target month (1-12)
	 * @return array Array of WP_Post objects
	 */
	public static function get_audit_posts( int $year, int $month ): array {
		$args = array(
			'date_query'     => array(
				array(
					'year'  => $year,
					'month' => $month,
				),
			),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'post_in_kabelkrant',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => 'post_kabelkrant_content_gpt',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'post_kabelkrant_content',
					'compare' => 'EXISTS',
				),
			),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'post_date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Remove region prefix from content (everything before and including ' - ')
	 *
	 * @param string $content Content that may have region prefix
	 * @return string Content with region prefix removed
	 */
	public static function strip_region_prefix( string $content ): string {
		$pos = strpos( $content, ' - ' );
		return false !== $pos ? trim( substr( $content, $pos + 3 ) ) : $content;
	}

	/**
	 * Analyze post to determine AI vs human content status
	 *
	 * @param \WP_Post $post Post to analyze
	 * @return array{status: string, ai_content: string, human_content: string}
	 */
	public static function categorize_audit_post( \WP_Post $post ): array {
		$ai_meta_content    = get_post_meta( $post->ID, 'post_kabelkrant_content_gpt', true );
		$human_meta_content = get_post_meta( $post->ID, 'post_kabelkrant_content', true );

		$ai_content    = self::strip_region_prefix( trim( is_string( $ai_meta_content ) ? $ai_meta_content : '' ) );
		$human_content = self::strip_region_prefix( trim( is_string( $human_meta_content ) ? $human_meta_content : '' ) );

		if ( empty( $ai_content ) ) {
			$status = 'fully_human_written';
		} elseif ( $ai_content === $human_content ) {
			$status = 'ai_written_not_edited';
		} else {
			$status = 'ai_written_edited';
		}

		return array(
			'status'        => $status,
			'ai_content'    => $ai_content,
			'human_content' => $human_content,
		);
	}

	/**
	 * Generate word-by-word diff using longest common subsequence algorithm
	 *
	 * @param string $old Original text
	 * @param string $modified Modified text
	 * @return array{before: string, after: string} Array with HTML diff content
	 */
	public static function generate_word_diff( string $old, string $modified ): array {
		$old_words = preg_split( '/\s+/', trim( $old ) );
		$new_words = preg_split( '/\s+/', trim( $modified ) );

		if ( false === $old_words || false === $new_words ) {
			return array(
				'before' => esc_html( $old ),
				'after'  => esc_html( $modified ),
			);
		}

		$old_count = count( $old_words );
		$new_count = count( $new_words );

		// Build LCS table
		$lcs_table = array();
		for ( $i = 0; $i <= $old_count; $i++ ) {
			$lcs_table[ $i ] = array_fill( 0, $new_count + 1, 0 );
		}

		for ( $i = $old_count - 1; $i >= 0; $i-- ) {
			for ( $j = $new_count - 1; $j >= 0; $j-- ) {
				if ( $old_words[ $i ] === $new_words[ $j ] ) {
					$lcs_table[ $i ][ $j ] = $lcs_table[ $i + 1 ][ $j + 1 ] + 1;
				} else {
					$lcs_table[ $i ][ $j ] = max( $lcs_table[ $i + 1 ][ $j ], $lcs_table[ $i ][ $j + 1 ] );
				}
			}
		}

		// Extract LCS
		$i   = 0;
		$j   = 0;
		$lcs = array();
		while ( $i < $old_count && $j < $new_count ) {
			if ( $old_words[ $i ] === $new_words[ $j ] ) {
				$lcs[] = $old_words[ $i ];
				++$i;
				++$j;
			} elseif ( $lcs_table[ $i + 1 ][ $j ] >= $lcs_table[ $i ][ $j + 1 ] ) {
				++$i;
			} else {
				++$j;
			}
		}

		// Build diff
		$diff_before = '';
		$diff_after  = '';
		$i_old       = 0;
		$i_new       = 0;
		$i_lcs       = 0;

		while ( $i_old < $old_count || $i_new < $new_count ) {
			if ( $i_lcs < count( $lcs ) && $i_old < $old_count && $i_new < $new_count && $old_words[ $i_old ] === $lcs[ $i_lcs ] && $new_words[ $i_new ] === $lcs[ $i_lcs ] ) {
				$word         = esc_html( $old_words[ $i_old ] );
				$diff_before .= "{$word} ";
				$diff_after  .= "{$word} ";
				++$i_old;
				++$i_new;
				++$i_lcs;
			} else {
				if ( $i_old < $old_count && ( $i_lcs >= count( $lcs ) || $old_words[ $i_old ] !== $lcs[ $i_lcs ] ) ) {
					$word         = esc_html( $old_words[ $i_old ] );
					$diff_before .= "<del style='color: #d32f2f; text-decoration: line-through;'>{$word}</del> ";
					++$i_old;
				}
				if ( $i_new < $new_count && ( $i_lcs >= count( $lcs ) || $new_words[ $i_new ] !== $lcs[ $i_lcs ] ) ) {
					$word        = esc_html( $new_words[ $i_new ] );
					$diff_after .= "<ins style='color: #388e3c; background-color: #e8f5e8;'>{$word}</ins> ";
					++$i_new;
				}
			}
		}

		return array(
			'before' => trim( $diff_before ),
			'after'  => trim( $diff_after ),
		);
	}
}
