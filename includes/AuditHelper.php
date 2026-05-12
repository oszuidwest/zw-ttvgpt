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
 * Provides content analysis and comparison for audit functionality.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class AuditHelper {
	/**
	 * Diff class used for content added by a human edit.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string DIFF_CLASS_ADDED = 'zw-diff-added';

	/**
	 * Diff class used for content removed from the AI version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string DIFF_CLASS_REMOVED = 'zw-diff-removed';

	/**
	 * Splits content for the WordPress diff renderer.
	 *
	 * Fails closed by returning the original content as a single chunk so the
	 * caller still renders the unsplit text instead of an empty diff.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to split.
	 * @return array<int, string> Split content chunks.
	 */
	private static function split_for_word_diff( string $content ): array {
		$parts = preg_split( '/([.!?]\s+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( false === $parts ) {
			return '' === $content ? array() : array( $content );
		}

		return $parts;
	}

	/**
	 * Removes a diff class from the combined diff HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $diff_html  Combined diff HTML.
	 * @param string $class_name Diff class to remove.
	 * @return string|null Filtered HTML, or null when PCRE failed so the caller can fall back to plain text.
	 */
	private static function remove_diff_class( string $diff_html, string $class_name ): ?string {
		$pattern = '/<span class="' . preg_quote( $class_name, '/' ) . '">.*?<\/span>/s';
		$result  = preg_replace( $pattern, '', $diff_html );

		if ( null === $result ) {
			return null;
		}

		return $result;
	}

	/**
	 * Normalizes whitespace in generated diff HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $diff_html Diff HTML to normalize.
	 * @return string Normalized HTML, or the original string if normalization fails.
	 */
	private static function normalize_diff_whitespace( string $diff_html ): string {
		$normalized = preg_replace( '/\s+/', ' ', $diff_html );
		if ( null === $normalized ) {
			return $diff_html;
		}

		return $normalized;
	}

	/**
	 * Builds a safe plain-text fallback diff when highlighting cannot be trusted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $old_clean      Original content after prefix stripping.
	 * @param string $modified_clean Modified content after prefix stripping.
	 * @return array{before: string, after: string} Safe before/after panes.
	 */
	private static function plain_diff_result( string $old_clean, string $modified_clean ): array {
		return array(
			'before' => trim( htmlspecialchars( $old_clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ),
			'after'  => trim( htmlspecialchars( $modified_clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ),
		);
	}

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

		// Limit the scan to recent posts for audit performance.
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

		$year  = (int) substr( $result, 0, 4 );
		$month = (int) substr( $result, 5, 2 );

		return array(
			'year'  => $year,
			'month' => $month,
		);
	}

	/**
	 * Retrieves months containing auditable posts.
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
	 * Retrieves published posts eligible for audit from a given month.
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
	 * Primes WordPress' meta cache for a set of posts.
	 *
	 * Call once before bulk get_post_meta() loops; subsequent reads on these
	 * posts hit the cache without extra queries.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_ids Array of post IDs.
	 *
	 * @phpstan-param array<int, int> $post_ids
	 */
	public static function prime_meta_cache( array $post_ids ): void {
		if ( empty( $post_ids ) ) {
			return;
		}

		update_meta_cache( 'post', array_map( 'intval', $post_ids ) );
	}

	/**
	 * Removes the regional location prefix from content text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to clean.
	 * @return string Cleaned content without region prefix.
	 */
	public static function strip_region_prefix( string $content ): string {
		// Match Unicode uppercase region prefixes while preserving one-letter
		// list markers such as "A - eerste optie".
		$trimmed = trim( $content );
		$result  = preg_replace( '/^\p{Lu}{2,}[\p{Lu}\s\/\-]*\s-\s/u', '', $trimmed );
		if ( null === $result ) {
			return $trimmed;
		}

		return $result;
	}

	/**
	 * Determines the audit status of a post.
	 *
	 * Callers should prime the meta cache with prime_meta_cache() before
	 * looping over many posts so get_post_meta hits the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post object to categorize.
	 * @return array Analysis result with status (AuditStatus enum), ai_content, human_content, and change_percentage.
	 *
	 * @phpstan-return PostAnalysis
	 */
	public static function categorize_post( \WP_Post $post ): array {
		$ai_content    = (string) get_post_meta( $post->ID, Constants::ACF_FIELD_AI_CONTENT, true );
		$human_content = (string) get_post_meta( $post->ID, Constants::ACF_FIELD_HUMAN_CONTENT, true );

		$ai_clean    = self::strip_region_prefix( $ai_content );
		$human_clean = self::strip_region_prefix( $human_content );

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
	 * Creates highlighted diff markup showing changes between content versions.
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
		$old_lines      = self::split_for_word_diff( $old_clean );
		$modified_lines = self::split_for_word_diff( $modified_clean );

		$text_diff = new \Text_Diff( 'auto', array( $old_lines, $modified_lines ) );
		$renderer  = new \WP_Text_Diff_Renderer_inline();
		$diff_html = $renderer->render( $text_diff );

		// WordPress uses <ins> and <del>, convert to our classes.
		$diff_html = str_replace(
			array( '<ins>', '</ins>', '<del>', '</del>' ),
			array(
				'<span class="' . self::DIFF_CLASS_ADDED . '">',
				'</span>',
				'<span class="' . self::DIFF_CLASS_REMOVED . '">',
				'</span>',
			),
			$diff_html
		);

		// Split the diff into before/after by removing the opposite tags.
		$before = self::remove_diff_class( $diff_html, self::DIFF_CLASS_ADDED );
		$after  = self::remove_diff_class( $diff_html, self::DIFF_CLASS_REMOVED );

		if ( null === $before || null === $after ) {
			return self::plain_diff_result( $old_clean, $modified_clean );
		}

		return array(
			'before' => trim( self::normalize_diff_whitespace( $before ) ),
			'after'  => trim( self::normalize_diff_whitespace( $after ) ),
		);
	}

	/**
	 * Determines the percentage difference between AI-generated and human-edited content.
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
		$max_words = max( $ai_word_count, $human_word_count );

		$common_words   = array_intersect( $ai_words, $human_words );
		$matching_words = count( $common_words );

		$similarity_ratio  = $matching_words / $max_words;
		$change_percentage = ( 1 - $similarity_ratio ) * 100;

		return round( $change_percentage, 1 );
	}
}
