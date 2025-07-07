<?php
/**
 * Audit Helper class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Audit Helper class
 *
 * Handles audit-specific functionality
 */
class TTVGPTAuditHelper {
	/**
	 * Get the most recent month that has relevant posts for audit analysis
	 *
	 * @return array|null Array with 'year' and 'month' keys or null if no posts found
	 */
	public static function get_most_recent_month(): ?array {
		global $wpdb;

		// Ultra-fast single query with INNERJOINs for maximum speed
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_kb ON (p.ID = pm_kb.post_id AND pm_kb.meta_key = %s AND pm_kb.meta_value = %s)
				INNER JOIN {$wpdb->postmeta} pm_gpt ON (p.ID = pm_gpt.post_id AND pm_gpt.meta_key = %s AND pm_gpt.meta_value != %s)
				WHERE p.post_status = %s
				  AND p.post_type = %s
				ORDER BY p.post_date DESC
				LIMIT 1",
				'post_in_kabelkrant',
				'1',
				'post_kabelkrant_content_gpt',
				'',
				'publish',
				'post'
			)
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
	public static function get_months(): array {
		global $wpdb;

		// Ultra-fast INNER JOIN with DISTINCT for optimal performance
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_kb ON (p.ID = pm_kb.post_id AND pm_kb.meta_key = %s AND pm_kb.meta_value = %s)
				INNER JOIN {$wpdb->postmeta} pm_gpt ON (p.ID = pm_gpt.post_id AND pm_gpt.meta_key = %s AND pm_gpt.meta_value != %s)
				WHERE p.post_status = %s
				  AND p.post_type = %s
				ORDER BY year DESC, month DESC",
				'post_in_kabelkrant',
				'1',
				'post_kabelkrant_content_gpt',
				'',
				'publish',
				'post'
			)
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
	public static function get_posts( int $year, int $month ): array {
		// Ultra-fast single query with date range instead of functions
		global $wpdb;

		// Calculate date range boundaries for index usage
		$start_date = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$end_date   = sprintf( '%04d-%02d-01 00:00:00', $year, $month + 1 );

		// Handle December edge case
		if ( 12 === $month ) {
			$end_date = sprintf( '%04d-01-01 00:00:00', $year + 1 );
		}

		// Single optimized query with compound conditions
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_date, p.post_title, p.post_content
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_kb ON (p.ID = pm_kb.post_id AND pm_kb.meta_key = %s AND pm_kb.meta_value = %s)
				INNER JOIN {$wpdb->postmeta} pm_gpt ON (p.ID = pm_gpt.post_id AND pm_gpt.meta_key = %s)
				INNER JOIN {$wpdb->postmeta} pm_content ON (p.ID = pm_content.post_id AND pm_content.meta_key = %s)
				WHERE p.post_status = %s
				  AND p.post_type = %s
				  AND p.post_date >= %s
				  AND p.post_date < %s
				ORDER BY p.post_date DESC",
				'post_in_kabelkrant',
				'1',
				'post_kabelkrant_content_gpt',
				'post_kabelkrant_content',
				'publish',
				'post',
				$start_date,
				$end_date
			)
		);

		if ( empty( $posts ) ) {
			return array();
		}

		// Convert to WP_Post objects without additional queries
		$wp_posts = array();
		foreach ( $posts as $post_data ) {
			$wp_posts[] = new \WP_Post( $post_data );
		}

		return $wp_posts;
	}


	/**
	 * Bulk fetch meta data for multiple posts to avoid N+1 queries
	 *
	 * @param array $post_ids Array of post IDs
	 * @return array Associative array of post meta data
	 */
	public static function get_bulk_meta_data( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		// Build safe IN clause with proper escaping
		$safe_post_ids = array_map( 'intval', $post_ids );
		$ids_string    = implode( ',', $safe_post_ids );

		// Single query to get all meta data for all posts
		$meta_data = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safely escaped with intval()
			"SELECT post_id, meta_key, meta_value 
			FROM {$wpdb->postmeta} 
			WHERE post_id IN ({$ids_string})
			AND meta_key IN ('post_kabelkrant_content_gpt', 'post_kabelkrant_content', '_edit_last')
			ORDER BY post_id"
		);

		$result = array();
		foreach ( $meta_data as $meta ) {
			$result[ $meta->post_id ][ $meta->meta_key ] = $meta->meta_value;
		}

		return $result;
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
	 * @param array    $meta_cache Meta data cache to avoid N+1 queries
	 * @return array{status: string, ai_content: string, human_content: string}
	 */
	public static function categorize_post( \WP_Post $post, array $meta_cache = array() ): array {
		// Use cached meta data if available to avoid N+1 queries
		if ( isset( $meta_cache[ $post->ID ] ) ) {
			$ai_meta_content    = $meta_cache[ $post->ID ]['post_kabelkrant_content_gpt'] ?? '';
			$human_meta_content = $meta_cache[ $post->ID ]['post_kabelkrant_content'] ?? '';
		} else {
			$ai_meta_content    = get_post_meta( $post->ID, 'post_kabelkrant_content_gpt', true );
			$human_meta_content = get_post_meta( $post->ID, 'post_kabelkrant_content', true );
		}

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
