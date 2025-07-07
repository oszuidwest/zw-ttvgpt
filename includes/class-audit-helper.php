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
		$start_time = microtime( true );

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

		$execution_time = ( microtime( true ) - $start_time ) * 1000;
		self::log_performance( 'get_most_recent_month', $execution_time, $result ? 1 : 0 );

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
		$start_time = microtime( true );

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

		$execution_time = ( microtime( true ) - $start_time ) * 1000;
		self::log_performance( 'get_months', $execution_time, count( $months ) );

		return $months;
	}

	/**
	 * Run comprehensive benchmark of all query strategies
	 *
	 * @param int $year Target year for benchmark
	 * @param int $month Target month for benchmark
	 * @return array Benchmark results for all strategies
	 */
	public static function run_comprehensive_benchmark( int $year, int $month ): array {
		global $wpdb;

		$results    = array();
		$strategies = array( 'strategy_1', 'strategy_2', 'strategy_3', 'strategy_4', 'strategy_5' );

		// Get database info for context
		$db_info = array(
			'mysql_version'  => $wpdb->get_var( 'SELECT VERSION()' ),
			'posts_count'    => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'post',
					'publish'
				)
			),
			'postmeta_count' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ),
			'benchmark_time' => current_time( 'Y-m-d H:i:s' ),
			'server_info'    => array(
				'php_version'        => PHP_VERSION,
				'memory_limit'       => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
			),
		);

		$results['database_info'] = $db_info;
		$results['strategies']    = array();

		// Test each strategy 3 times and take average
		foreach ( $strategies as $strategy ) {
			$times         = array();
			$result_counts = array();

			for ( $run = 1; $run <= 3; $run++ ) {
				$start_time = microtime( true );

				switch ( $strategy ) {
					case 'strategy_1':
						$posts = self::get_posts_strategy_1( $year, $month );
						break;
					case 'strategy_2':
						$posts = self::get_posts_strategy_2( $year, $month );
						break;
					case 'strategy_3':
						$posts = self::get_posts_strategy_3( $year, $month );
						break;
					case 'strategy_4':
						$posts = self::get_posts_strategy_4( $year, $month );
						break;
					case 'strategy_5':
						$posts = self::get_posts_strategy_5( $year, $month );
						break;
				}

				$execution_time  = ( microtime( true ) - $start_time ) * 1000;
				$times[]         = $execution_time;
				$result_counts[] = count( $posts );
			}

			$results['strategies'][ $strategy ] = array(
				'avg_time_ms'  => round( array_sum( $times ) / count( $times ), 2 ),
				'min_time_ms'  => round( min( $times ), 2 ),
				'max_time_ms'  => round( max( $times ), 2 ),
				'result_count' => $result_counts[0], // Should be same for all runs
				'runs'         => $times,
			);
		}

		// Test supporting queries
		$start_time = microtime( true );
		self::get_most_recent_month();
		$recent_month_time = ( microtime( true ) - $start_time ) * 1000;

		$start_time = microtime( true );
		self::get_months();
		$months_time = ( microtime( true ) - $start_time ) * 1000;

		$results['supporting_queries'] = array(
			'get_most_recent_month_ms' => round( $recent_month_time, 2 ),
			'get_months_ms'            => round( $months_time, 2 ),
		);

		// Calculate winner
		$fastest_strategy = array_reduce(
			array_keys( $results['strategies'] ),
			function ( $carry, $strategy ) use ( $results ) {
				if ( ! $carry || $results['strategies'][ $strategy ]['avg_time_ms'] < $results['strategies'][ $carry ]['avg_time_ms'] ) {
					return $strategy;
				}
				return $carry;
			}
		);

		$results['analysis'] = array(
			'fastest_strategy'        => $fastest_strategy,
			'fastest_time_ms'         => $results['strategies'][ $fastest_strategy ]['avg_time_ms'],
			'total_dashboard_time_ms' => round(
				$results['strategies'][ $fastest_strategy ]['avg_time_ms'] +
				$recent_month_time +
				$months_time,
				2
			),
		);

		// Write results to file
		self::write_benchmark_results( $results );

		return $results;
	}

	/**
	 * Write benchmark results to wp-content file
	 *
	 * @param array $results Benchmark results
	 * @return void
	 */
	private static function write_benchmark_results( array $results ): void {
		$upload_dir     = wp_upload_dir();
		$benchmark_file = $upload_dir['basedir'] . '/zw-ttvgpt-benchmark.json';

		// Also create human-readable format
		$readable_file = $upload_dir['basedir'] . '/zw-ttvgpt-benchmark.txt';

		// Write JSON for programmatic access
		file_put_contents( $benchmark_file, json_encode( $results, JSON_PRETTY_PRINT ) );

		// Write human-readable format
		$readable_content  = "=== ZW TTVGPT Database Performance Benchmark ===\n\n";
		$readable_content .= 'Benchmark Time: ' . $results['database_info']['benchmark_time'] . "\n";
		$readable_content .= 'MySQL Version: ' . $results['database_info']['mysql_version'] . "\n";
		$readable_content .= 'PHP Version: ' . $results['database_info']['server_info']['php_version'] . "\n";
		$readable_content .= 'Total Posts: ' . number_format( $results['database_info']['posts_count'] ) . "\n";
		$readable_content .= 'Total Postmeta: ' . number_format( $results['database_info']['postmeta_count'] ) . "\n\n";

		$readable_content .= "=== Query Strategy Performance ===\n\n";

		foreach ( $results['strategies'] as $strategy => $data ) {
			$readable_content .= sprintf(
				"%s: %.2fms (min: %.2fms, max: %.2fms) - %d results\n",
				strtoupper( str_replace( '_', ' ', $strategy ) ),
				$data['avg_time_ms'],
				$data['min_time_ms'],
				$data['max_time_ms'],
				$data['result_count']
			);
		}

		$readable_content .= "\n=== Supporting Queries ===\n\n";
		$readable_content .= sprintf( "Get Recent Month: %.2fms\n", $results['supporting_queries']['get_most_recent_month_ms'] );
		$readable_content .= sprintf( "Get All Months: %.2fms\n", $results['supporting_queries']['get_months_ms'] );

		$readable_content .= "\n=== Analysis ===\n\n";
		$readable_content .= sprintf( "🏆 FASTEST: %s (%.2fms)\n", strtoupper( str_replace( '_', ' ', $results['analysis']['fastest_strategy'] ) ), $results['analysis']['fastest_time_ms'] );
		$readable_content .= sprintf( "📊 TOTAL DASHBOARD LOAD: %.2fms\n", $results['analysis']['total_dashboard_time_ms'] );

		if ( $results['analysis']['total_dashboard_time_ms'] < 100 ) {
			$readable_content .= "🚀 EXCELLENT! Under 100ms\n";
		} elseif ( $results['analysis']['total_dashboard_time_ms'] < 200 ) {
			$readable_content .= "✅ GOOD! Under 200ms\n";
		} elseif ( $results['analysis']['total_dashboard_time_ms'] < 500 ) {
			$readable_content .= "⚠️  ACCEPTABLE! Under 500ms\n";
		} else {
			$readable_content .= "❌ SLOW! Over 500ms - needs more optimization\n";
		}

		file_put_contents( $readable_file, $readable_content );

		// Log to error log as well
		error_log( "[ZW_TTVGPT_BENCHMARK] Results written to: {$benchmark_file}" );
		error_log( "[ZW_TTVGPT_BENCHMARK] Fastest strategy: {$results['analysis']['fastest_strategy']} ({$results['analysis']['fastest_time_ms']}ms)" );
	}

	/**
	 * Performance logging for benchmarking different query strategies
	 *
	 * @param string $method_name Name of the method being benchmarked
	 * @param float  $execution_time Execution time in milliseconds
	 * @param int    $result_count Number of results returned
	 * @return void
	 */
	private static function log_performance( string $method_name, float $execution_time, int $result_count ): void {
		// Always log performance unless explicitly disabled
		$should_log = ! ( defined( 'ZW_TTVGPT_DISABLE_BENCHMARK' ) && ZW_TTVGPT_DISABLE_BENCHMARK );
		
		if ( $should_log ) {
			error_log(
				sprintf(
					'[ZW_TTVGPT_BENCHMARK] %s: %.2fms, %d results',
					$method_name,
					$execution_time,
					$result_count
				)
			);
		}
	}

	/**
	 * Retrieve all posts for audit analysis from specific month and year
	 *
	 * @param int $year Target year
	 * @param int $month Target month (1-12)
	 * @return array Array of WP_Post objects
	 */
	public static function get_posts( int $year, int $month ): array {
		// Benchmark different query strategies
		$benchmark_method = defined( 'ZW_TTVGPT_BENCHMARK_METHOD' ) ? ZW_TTVGPT_BENCHMARK_METHOD : 'strategy_1';

		$start_time = microtime( true );

		switch ( $benchmark_method ) {
			case 'strategy_1':
				$result = self::get_posts_strategy_1( $year, $month );
				break;
			case 'strategy_2':
				$result = self::get_posts_strategy_2( $year, $month );
				break;
			case 'strategy_3':
				$result = self::get_posts_strategy_3( $year, $month );
				break;
			case 'strategy_4':
				$result = self::get_posts_strategy_4( $year, $month );
				break;
			case 'strategy_5':
				$result = self::get_posts_strategy_5( $year, $month );
				break;
			default:
				$result = self::get_posts_strategy_1( $year, $month );
		}

		$execution_time = ( microtime( true ) - $start_time ) * 1000;
		self::log_performance( $benchmark_method, $execution_time, count( $result ) );

		return $result;
	}

	/**
	 * Strategy 1: Triple INNER JOIN with date range (current optimized approach)
	 */
	private static function get_posts_strategy_1( int $year, int $month ): array {
		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$end_date   = sprintf( '%04d-%02d-01 00:00:00', $year, $month + 1 );

		if ( 12 === $month ) {
			$end_date = sprintf( '%04d-01-01 00:00:00', $year + 1 );
		}

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

		return self::convert_to_wp_posts( $posts );
	}

	/**
	 * Strategy 2: EXISTS subqueries with covering index optimization
	 */
	private static function get_posts_strategy_2( int $year, int $month ): array {
		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$end_date   = sprintf( '%04d-%02d-01 00:00:00', $year, $month + 1 );

		if ( 12 === $month ) {
			$end_date = sprintf( '%04d-01-01 00:00:00', $year + 1 );
		}

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_date, p.post_title, p.post_content
				FROM {$wpdb->posts} p
				WHERE p.post_status = %s
				  AND p.post_type = %s
				  AND p.post_date >= %s
				  AND p.post_date < %s
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm1
					  WHERE pm1.post_id = p.ID
					    AND pm1.meta_key = %s
					    AND pm1.meta_value = %s
				  )
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm2
					  WHERE pm2.post_id = p.ID
					    AND pm2.meta_key = %s
					  LIMIT 1
				  )
				  AND EXISTS (
					  SELECT 1 FROM {$wpdb->postmeta} pm3
					  WHERE pm3.post_id = p.ID
					    AND pm3.meta_key = %s
					  LIMIT 1
				  )
				ORDER BY p.post_date DESC",
				'publish',
				'post',
				$start_date,
				$end_date,
				'post_in_kabelkrant',
				'1',
				'post_kabelkrant_content_gpt',
				'post_kabelkrant_content'
			)
		);

		return self::convert_to_wp_posts( $posts );
	}

	/**
	 * Strategy 3: Two-step query with IN clause for maximum MySQL optimization
	 */
	private static function get_posts_strategy_3( int $year, int $month ): array {
		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$end_date   = sprintf( '%04d-%02d-01 00:00:00', $year, $month + 1 );

		if ( 12 === $month ) {
			$end_date = sprintf( '%04d-01-01 00:00:00', $year + 1 );
		}

		// Step 1: Get eligible post IDs using fastest possible meta query
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm1.post_id
				FROM {$wpdb->postmeta} pm1
				INNER JOIN {$wpdb->postmeta} pm2 ON (pm1.post_id = pm2.post_id AND pm2.meta_key = %s)
				INNER JOIN {$wpdb->postmeta} pm3 ON (pm1.post_id = pm3.post_id AND pm3.meta_key = %s)
				INNER JOIN {$wpdb->posts} p ON (pm1.post_id = p.ID)
				WHERE pm1.meta_key = %s
				  AND pm1.meta_value = %s
				  AND p.post_status = %s
				  AND p.post_type = %s
				  AND p.post_date >= %s
				  AND p.post_date < %s",
				'post_kabelkrant_content_gpt',
				'post_kabelkrant_content',
				'post_in_kabelkrant',
				'1',
				'publish',
				'post',
				$start_date,
				$end_date
			)
		);

		if ( empty( $post_ids ) ) {
			return array();
		}

		// Step 2: Get full post data for matched IDs
		$ids_string = implode( ',', array_map( 'intval', $post_ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Safely escaped with intval()
		$posts = $wpdb->get_results(
			"SELECT ID, post_author, post_date, post_title, post_content
			FROM {$wpdb->posts}
			WHERE ID IN ({$ids_string})
			ORDER BY post_date DESC"
		);

		return self::convert_to_wp_posts( $posts );
	}

	/**
	 * Strategy 4: Optimized LEFT JOIN with NULL checks for better performance
	 */
	private static function get_posts_strategy_4( int $year, int $month ): array {
		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$end_date   = sprintf( '%04d-%02d-01 00:00:00', $year, $month + 1 );

		if ( 12 === $month ) {
			$end_date = sprintf( '%04d-01-01 00:00:00', $year + 1 );
		}

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_date, p.post_title, p.post_content
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_kb ON (p.ID = pm_kb.post_id AND pm_kb.meta_key = %s)
				LEFT JOIN {$wpdb->postmeta} pm_gpt ON (p.ID = pm_gpt.post_id AND pm_gpt.meta_key = %s)
				LEFT JOIN {$wpdb->postmeta} pm_content ON (p.ID = pm_content.post_id AND pm_content.meta_key = %s)
				WHERE p.post_status = %s
				  AND p.post_type = %s
				  AND p.post_date >= %s
				  AND p.post_date < %s
				  AND pm_kb.meta_value = %s
				  AND pm_gpt.meta_id IS NOT NULL
				  AND pm_content.meta_id IS NOT NULL
				ORDER BY p.post_date DESC",
				'post_in_kabelkrant',
				'post_kabelkrant_content_gpt',
				'post_kabelkrant_content',
				'publish',
				'post',
				$start_date,
				$end_date,
				'1'
			)
		);

		return self::convert_to_wp_posts( $posts );
	}

	/**
	 * Strategy 5: Minimal query with post-processing (WordPress way)
	 */
	private static function get_posts_strategy_5( int $year, int $month ): array {
		global $wpdb;

		$start_date = sprintf( '%04d-%02d-01', $year, $month );
		$end_date   = sprintf( '%04d-%02d-01', $year, $month + 1 );

		if ( 12 === $month ) {
			$end_date = sprintf( '%04d-01-01', $year + 1 );
		}

		// Use WordPress built-in functions for maximum compatibility
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'date_query'     => array(
					array(
						'after'     => $start_date,
						'before'    => $end_date,
						'inclusive' => true,
					),
				),
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => 'post_in_kabelkrant',
						'value' => '1',
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
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $posts;
	}

	/**
	 * Convert raw database results to WP_Post objects
	 */
	private static function convert_to_wp_posts( array $posts ): array {
		if ( empty( $posts ) ) {
			return array();
		}

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
