<?php
/**
 * Audit Helper class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Helper class.
 *
 * Handles audit-specific functionality for content analysis.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class AuditHelper {
	/**
	 * Retrieves the most recent month that has relevant posts for audit analysis.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Array with 'year' and 'month' keys or null if no posts found.
	 *
	 * @phpstan-return MonthData|null
	 */
	public static function get_most_recent_month(): ?array {
		global $wpdb;

		// Turbo-optimized: Target only recent posts for faster scanning.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.post_date
				FROM {$wpdb->posts} p
				WHERE p.post_status = %s
				  AND p.post_type = %s
				  AND p.post_date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm1
					  WHERE pm1.post_id = p.ID
					    AND pm1.meta_key = %s
					    AND pm1.meta_value = %s
					  LIMIT 1
				  )
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm2
					  WHERE pm2.post_id = p.ID
					    AND pm2.meta_key = %s
					    AND pm2.meta_value != %s
					  LIMIT 1
				  )
				ORDER BY p.post_date DESC
				LIMIT 1",
				'publish',
				'post',
				Constants::ACF_FIELD_IN_KABELKRANT,
				'1',
				Constants::ACF_FIELD_AI_CONTENT,
				''
			)
		);

		if ( ! $result ) {
			return null;
		}

		// Extract year and month from date string.
		$year  = (int) substr( $result, 0, 4 );
		$month = (int) substr( $result, 5, 2 );

		return array(
			'year'  => $year,
			'month' => $month,
		);
	}

	/**
	 * Retrieves all months with audit data for navigation.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of arrays with 'year' and 'month' keys.
	 *
	 * @phpstan-return array<int, MonthData>
	 */
	public static function get_months(): array {
		global $wpdb;

		// Direct SQL is used for performance since WP_Query doesn't support GROUP BY efficiently.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT YEAR(p.post_date) as year, MONTH(p.post_date) as month
				FROM {$wpdb->posts} p
				WHERE p.post_status = %s
				  AND p.post_type = %s
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm1
					  WHERE pm1.post_id = p.ID
					    AND pm1.meta_key = %s
					    AND pm1.meta_value = %s
					  LIMIT 1
				  )
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm2
					  WHERE pm2.post_id = p.ID
					    AND pm2.meta_key = %s
					    AND pm2.meta_value != %s
					  LIMIT 1
				  )
				GROUP BY YEAR(p.post_date), MONTH(p.post_date)
				ORDER BY year DESC, month DESC",
				'publish',
				'post',
				Constants::ACF_FIELD_IN_KABELKRANT,
				'1',
				Constants::ACF_FIELD_AI_CONTENT,
				''
			)
		);

		// Convert database results to array format.
		$unique_months = array();
		foreach ( $results as $row ) {
			$unique_months[] = array(
				'year'  => (int) $row->year,
				'month' => (int) $row->month,
			);
		}

		return $unique_months;
	}

	/**
	 * Retrieves all posts for audit analysis from specific month and year.
	 *
	 * @since 1.0.0
	 *
	 * @param int $year  Target year.
	 * @param int $month Target month (1-12).
	 * @return array Array of WP_Post objects.
	 *
	 * @phpstan-return array<int, \WP_Post>
	 */
	public static function get_posts( int $year, int $month ): array {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'date_query'     => array(
				array(
					'year'  => $year,
					'month' => $month,
				),
			),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => Constants::ACF_FIELD_IN_KABELKRANT,
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => Constants::ACF_FIELD_AI_CONTENT,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => Constants::ACF_FIELD_HUMAN_CONTENT,
					'compare' => 'EXISTS',
				),
			),
		);

		$query = new \WP_Query( $args );

		return $query->posts;
	}


	/**
	 * Retrieves bulk meta data for multiple posts to avoid N+1 queries.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return array Associative array with post_id as key and meta_data as value.
	 *
	 * @phpstan-param array<int, int> $post_ids
	 * @phpstan-return array<int, array<string, string>>
	 */
	public static function get_bulk_meta_data( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		// Sanitize post IDs to integers only.
		$post_ids   = array_map( 'intval', $post_ids );
		$ids_string = implode( ',', $post_ids );

		// Single query to get all meta data for all posts.
		$meta_keys = array(
			Constants::ACF_FIELD_AI_CONTENT,
			Constants::ACF_FIELD_HUMAN_CONTENT,
			'_edit_last',
		);

		// Build WHERE clause for post IDs.
		$where_post_ids = array();
		foreach ( $post_ids as $id ) {
			$where_post_ids[] = $wpdb->prepare( 'post_id = %d', $id );
		}

		// Build WHERE clause for meta keys.
		$where_meta_keys = array();
		foreach ( $meta_keys as $key ) {
			$where_meta_keys[] = $wpdb->prepare( 'meta_key = %s', $key );
		}

		// Combine conditions.
		$where_post_clause = '(' . implode( ' OR ', $where_post_ids ) . ')';
		$where_key_clause  = '(' . implode( ' OR ', $where_meta_keys ) . ')';

		// Build final query.
		$query = "SELECT post_id, meta_key, meta_value 
			FROM {$wpdb->postmeta} 
			WHERE {$where_post_clause}
			AND {$where_key_clause}
			ORDER BY post_id";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built from individually prepared fragments above
		$meta_data = $wpdb->get_results( $query );

		// Organize data by post_id for fast lookup.
		$meta_cache = array();
		foreach ( $meta_data as $meta ) {
			$meta_cache[ $meta->post_id ][ $meta->meta_key ] = $meta->meta_value;
		}

		return $meta_cache;
	}

	/**
	 * Strips region prefix from content for comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to clean.
	 * @return string Cleaned content without region prefix.
	 */
	public static function strip_region_prefix( string $content ): string {
		// Remove region prefixes like "LEIDEN - ", "DEN HAAG - ", "ROOSENDAAL/OUDENBOSCH - ", "ETTEN-LEUR - ".
		// Matches: uppercase letters, spaces, forward slashes, hyphens, followed by " - ".
		$result = preg_replace( '/^[A-Z][A-Z\s\/\-]*\s-\s/', '', trim( $content ) );
		return null !== $result ? $result : trim( $content );
	}

	/**
	 * Categorizes a post based on its AI and human content.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post       Post object to categorize.
	 * @param array    $meta_cache Optional. Meta data cache to avoid N+1 queries. Default empty array.
	 * @return array Analysis result with status (AuditStatus enum), ai_content, human_content, and change_percentage.
	 *
	 * @phpstan-param array<int, array<string, string>> $meta_cache
	 * @phpstan-return PostAnalysis
	 */
	public static function categorize_post( \WP_Post $post, array $meta_cache = array() ): array {
		$ai_key        = Constants::ACF_FIELD_AI_CONTENT;
		$human_key     = Constants::ACF_FIELD_HUMAN_CONTENT;
		$ai_content    = $meta_cache[ $post->ID ][ $ai_key ] ?? get_post_meta( $post->ID, $ai_key, true );
		$human_content = $meta_cache[ $post->ID ][ $human_key ] ?? get_post_meta( $post->ID, $human_key, true );

		// Clean content for accurate comparison.
		$ai_clean    = self::strip_region_prefix( $ai_content );
		$human_clean = self::strip_region_prefix( $human_content );

		// Determine status based on content analysis using enum.
		$status = match ( true ) {
			empty( $ai_content ) || '' === trim( $ai_content ) => AuditStatus::FullyHumanWritten,
			$ai_clean === $human_clean                         => AuditStatus::AiWrittenNotEdited,
			default                                            => AuditStatus::AiWrittenEdited,
		};

		// Calculate percentage change for AI+ articles.
		$change_percentage = 0;
		if ( AuditStatus::AiWrittenEdited === $status ) {
			$change_percentage = self::calculate_change_percentage( $ai_clean, $human_clean );
		}

		return array(
			'status'            => $status,
			'ai_content'        => $ai_content,
			'human_content'     => $human_content,
			'change_percentage' => $change_percentage,
		);
	}

	/**
	 * Generates word-level diff highlighting for content comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $old      Original text.
	 * @param string $modified Modified text.
	 * @return array Array with 'before' and 'after' highlighted versions.
	 *
	 * @phpstan-return DiffResult
	 */
	public static function generate_word_diff( string $old, string $modified ): array {
		// Strip region prefixes for comparison.
		$old_clean      = self::strip_region_prefix( $old );
		$modified_clean = self::strip_region_prefix( $modified );

		// Load WordPress diff functions.
		if ( ! function_exists( 'wp_text_diff' ) ) {
			require_once ABSPATH . 'wp-admin/includes/revision.php';
		}

		// Use WordPress built-in diff with inline renderer.
		if ( ! class_exists( 'WP_Text_Diff_Renderer_inline' ) ) {
			require_once ABSPATH . 'wp-includes/wp-diff.php';
		}

		// Split into lines for WordPress diff (works better with sentences).
		$old_lines      = preg_split( '/([.!?]\s+)/', $old_clean, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$modified_lines = preg_split( '/([.!?]\s+)/', $modified_clean, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		// Create the diff.
		$text_diff = new \Text_Diff( 'auto', array( $old_lines, $modified_lines ) );
		$renderer  = new \WP_Text_Diff_Renderer_inline();
		$diff_html = $renderer->render( $text_diff );

		// WordPress uses <ins> and <del>, convert to our classes.
		$diff_html = str_replace(
			array( '<ins>', '</ins>', '<del>', '</del>' ),
			array(
				'<span class="zw-diff-added">',
				'</span>',
				'<span class="zw-diff-removed">',
				'</span>',
			),
			$diff_html
		);

		// Split the diff into before/after by removing the opposite tags.
		$before = preg_replace( '/<span class="zw-diff-added">.*?<\/span>/s', '', $diff_html );
		$after  = preg_replace( '/<span class="zw-diff-removed">.*?<\/span>/s', '', $diff_html );

		// Clean up any double spaces - handle potential null from preg_replace.
		$before = is_string( $before ) ? preg_replace( '/\s+/', ' ', $before ) : $diff_html;
		$after  = is_string( $after ) ? preg_replace( '/\s+/', ' ', $after ) : $diff_html;

		// Ensure before and after are strings before trimming.
		$before = is_string( $before ) ? $before : '';
		$after  = is_string( $after ) ? $after : '';

		return array(
			'before' => trim( $before ),
			'after'  => trim( $after ),
		);
	}

	/**
	 * Calculates percentage of change between AI and human content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ai_content    Original AI content.
	 * @param string $human_content Edited human content.
	 * @return float Percentage of change (0-100).
	 */
	public static function calculate_change_percentage( string $ai_content, string $human_content ): float {
		if ( empty( $ai_content ) && empty( $human_content ) ) {
			return 0.0;
		}

		if ( empty( $ai_content ) ) {
			return 100.0;
		}

		// Split into words for comparison.
		$ai_words_result    = preg_split( '/\s+/', trim( $ai_content ) );
		$human_words_result = preg_split( '/\s+/', trim( $human_content ) );

		$ai_words    = false !== $ai_words_result ? $ai_words_result : array();
		$human_words = false !== $human_words_result ? $human_words_result : array();

		$ai_word_count    = count( $ai_words );
		$human_word_count = count( $human_words );

		if ( 0 === $ai_word_count ) {
			return 100.0;
		}

		// Calculate similarity using simple word matching.
		$matching_words = 0;
		$max_words      = max( $ai_word_count, $human_word_count );

		// Find words that appear in both versions.
		$common_words   = array_intersect( $ai_words, $human_words );
		$matching_words = count( $common_words );

		// Calculate change percentage.
		$similarity_ratio  = $matching_words / $max_words;
		$change_percentage = ( 1 - $similarity_ratio ) * 100;

		return round( $change_percentage, 1 );
	}
}
