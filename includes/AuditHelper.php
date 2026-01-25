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

		return array_map(
			static fn( $row ) => array(
				'year'  => (int) $row->year,
				'month' => (int) $row->month,
			),
			$results
		);
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
	 * Retrieves metadata for multiple posts in a single query.
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

		$post_ids = array_map( 'intval', $post_ids );

		$meta_keys = array(
			Constants::ACF_FIELD_AI_CONTENT,
			Constants::ACF_FIELD_HUMAN_CONTENT,
			'_edit_last',
		);

		$id_placeholders  = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// Dynamic IN() placeholders built from array_fill, safe for interpolation.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		$meta_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ({$id_placeholders})
				AND meta_key IN ({$key_placeholders})
				ORDER BY post_id",
				...array_merge( $post_ids, $meta_keys )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders

		// Organize data by post_id for fast lookup.
		$meta_cache = array();
		foreach ( $meta_data as $meta ) {
			$meta_cache[ $meta->post_id ][ $meta->meta_key ] = $meta->meta_value;
		}

		return $meta_cache;
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
		// Remove region prefixes like "LEIDEN - ", "DEN HAAG - ", "ROOSENDAAL/OUDENBOSCH - ", "ETTEN-LEUR - ".
		// Matches: uppercase letters, spaces, forward slashes, hyphens, followed by " - ".
		return preg_replace( '/^[A-Z][A-Z\s\/\-]*\s-\s/', '', trim( $content ) ) ?? trim( $content );
	}

	/**
	 * Determines the audit status of a post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post       Post object to categorize.
	 * @param array    $meta_cache Optional. Pre-fetched meta data cache. Default empty array.
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
			'' === trim( $ai_content ) => AuditStatus::FullyHumanWritten,
			$ai_clean === $human_clean => AuditStatus::AiWrittenNotEdited,
			default                    => AuditStatus::AiWrittenEdited,
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
		$before = preg_replace( '/<span class="zw-diff-added">.*?<\/span>/s', '', $diff_html ) ?? $diff_html;
		$after  = preg_replace( '/<span class="zw-diff-removed">.*?<\/span>/s', '', $diff_html ) ?? $diff_html;

		// Clean up any double spaces.
		$before = preg_replace( '/\s+/', ' ', $before ) ?? $before;
		$after  = preg_replace( '/\s+/', ' ', $after ) ?? $after;

		return array(
			'before' => trim( $before ),
			'after'  => trim( $after ),
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

		$ai_words    = preg_split( '/\s+/', trim( $ai_content ) );
		$human_words = preg_split( '/\s+/', trim( $human_content ) );
		$ai_words    = false !== $ai_words ? $ai_words : array();
		$human_words = false !== $human_words ? $human_words : array();
		$max_words   = max( count( $ai_words ), count( $human_words ) );

		if ( 0 === $max_words ) {
			return 0.0;
		}

		$matching_words    = count( array_intersect( $ai_words, $human_words ) );
		$change_percentage = ( 1 - $matching_words / $max_words ) * 100;

		return round( $change_percentage, 1 );
	}
}
