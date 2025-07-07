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

		// Turbo-optimized: Target only recent posts for faster scanning
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
				'post_in_kabelkrant',
				'1',
				'post_kabelkrant_content_gpt',
				''
			)
		);

		$execution_time = ( microtime( true ) - $start_time ) * 1000;
		self::log_performance( 'get_most_recent_month', $execution_time, $result ? 1 : 0 );

		if ( ! $result ) {
			return null;
		}

		// Ultra-fast PHP date extraction using substr
		$year  = (int) substr( $result, 0, 4 );
		$month = (int) substr( $result, 5, 2 );

		return array(
			'year'  => $year,
			'month' => $month,
		);
	}

	/**
	 * Get all months with audit data for navigation
	 *
	 * @return array Array of arrays with 'year' and 'month' keys
	 */
	public static function get_months(): array {
		$start_time = microtime( true );

		global $wpdb;

		// REVOLUTIONARY: Direct GROUP BY month buckets in SQL - eliminates PHP processing!
		// Shows ALL years for complete audit history access
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
				'post_in_kabelkrant',
				'1',
				'post_kabelkrant_content_gpt',
				''
			)
		);

		// Ultra-fast: Direct database results, no PHP processing needed!
		$unique_months = array();
		foreach ( $results as $row ) {
			$unique_months[] = array(
				'year'  => (int) $row->year,
				'month' => (int) $row->month,
			);
		}

		$execution_time = ( microtime( true ) - $start_time ) * 1000;
		self::log_performance( 'get_months', $execution_time, count( $unique_months ) );

		return $unique_months;
	}

	/**
	 * Run comprehensive benchmark of all query strategies with detailed bottleneck analysis
	 *
	 * @param int $year Target year for benchmark
	 * @param int $month Target month for benchmark
	 * @return array Benchmark results for all strategies
	 */
	public static function run_comprehensive_benchmark( int $year, int $month ): array {
		global $wpdb;

		$results    = array();
		$strategies = array( 'strategy_1', 'strategy_2', 'strategy_3', 'strategy_4', 'strategy_5' );

		// Enable query logging for detailed analysis
		$wpdb->save_queries  = true;
		$initial_query_count = count( $wpdb->queries );

		// Get database info for context with timing - optimized queries
		$db_start = microtime( true );

		// Parallel execution of database info queries for speed
		$mysql_version = $wpdb->get_var( 'SELECT VERSION()' );

		// Use faster approximate counts for large tables
		$posts_count = $wpdb->get_var(
			"SELECT table_rows FROM information_schema.tables 
			WHERE table_schema = DATABASE() AND table_name = '{$wpdb->posts}'"
		);

		$postmeta_count = $wpdb->get_var(
			"SELECT table_rows FROM information_schema.tables 
			WHERE table_schema = DATABASE() AND table_name = '{$wpdb->postmeta}'"
		);

		// Fallback to exact counts if schema query fails
		if ( ! $posts_count ) {
			$posts_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'post',
					'publish'
				)
			);
		}

		if ( ! $postmeta_count ) {
			$postmeta_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" );
		}

		$db_info      = array(
			'mysql_version'  => $mysql_version,
			'posts_count'    => (int) $posts_count,
			'postmeta_count' => (int) $postmeta_count,
			'benchmark_time' => current_time( 'Y-m-d H:i:s' ),
			'server_info'    => array(
				'php_version'        => PHP_VERSION,
				'memory_limit'       => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
			),
		);
		$db_info_time = ( microtime( true ) - $db_start ) * 1000;

		$results['database_info']    = $db_info;
		$results['strategies']       = array();
		$results['detailed_timings'] = array();

		// Test each strategy 3 times and take average with detailed timing
		foreach ( $strategies as $strategy ) {
			$times         = array();
			$result_counts = array();
			$detailed_runs = array();

			for ( $run = 1; $run <= 3; $run++ ) {
				$query_start_count = count( $wpdb->queries );
				$memory_start      = memory_get_usage();
				$start_time        = microtime( true );

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

				$execution_time   = ( microtime( true ) - $start_time ) * 1000;
				$memory_used      = ( memory_get_usage() - $memory_start ) / 1024 / 1024; // MB
				$queries_executed = count( $wpdb->queries ) - $query_start_count;

				$times[]         = $execution_time;
				$result_counts[] = count( $posts );

				$detailed_runs[] = array(
					'run'         => $run,
					'time_ms'     => round( $execution_time, 2 ),
					'memory_mb'   => round( $memory_used, 2 ),
					'query_count' => $queries_executed,
					'results'     => count( $posts ),
				);
			}

			$results['strategies'][ $strategy ] = array(
				'avg_time_ms'   => round( array_sum( $times ) / count( $times ), 2 ),
				'min_time_ms'   => round( min( $times ), 2 ),
				'max_time_ms'   => round( max( $times ), 2 ),
				'result_count'  => $result_counts[0], // Should be same for all runs
				'runs'          => $times,
				'detailed_runs' => $detailed_runs,
			);
		}

		// Test supporting queries with detailed breakdown
		$supporting_start  = microtime( true );
		$query_start_count = count( $wpdb->queries );
		$memory_start      = memory_get_usage();

		$recent_start      = microtime( true );
		$recent_result     = self::get_most_recent_month();
		$recent_month_time = ( microtime( true ) - $recent_start ) * 1000;
		$recent_memory     = ( memory_get_usage() - $memory_start ) / 1024 / 1024;
		$recent_queries    = count( $wpdb->queries ) - $query_start_count;

		$months_start        = microtime( true );
		$months_memory_start = memory_get_usage();
		$months_query_start  = count( $wpdb->queries );
		$months_result       = self::get_months();
		$months_time         = ( microtime( true ) - $months_start ) * 1000;
		$months_memory       = ( memory_get_usage() - $months_memory_start ) / 1024 / 1024;
		$months_queries      = count( $wpdb->queries ) - $months_query_start;

		$total_supporting_time = ( microtime( true ) - $supporting_start ) * 1000;

		$results['supporting_queries'] = array(
			'get_most_recent_month_ms' => round( $recent_month_time, 2 ),
			'get_months_ms'            => round( $months_time, 2 ),
			'total_supporting_ms'      => round( $total_supporting_time, 2 ),
			'detailed_breakdown'       => array(
				'get_most_recent_month' => array(
					'time_ms'     => round( $recent_month_time, 2 ),
					'memory_mb'   => round( $recent_memory, 2 ),
					'query_count' => $recent_queries,
					'result'      => $recent_result ? 'found' : 'not_found',
				),
				'get_months'            => array(
					'time_ms'       => round( $months_time, 2 ),
					'memory_mb'     => round( $months_memory, 2 ),
					'query_count'   => $months_queries,
					'results_count' => count( $months_result ),
				),
			),
		);

		$results['detailed_timings'] = array(
			'database_info_ms'       => round( $db_info_time, 2 ),
			'total_queries_executed' => count( $wpdb->queries ) - $initial_query_count,
			'memory_peak_mb'         => round( memory_get_peak_usage() / 1024 / 1024, 2 ),
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

		// Write human-readable format with detailed bottleneck analysis
		$readable_content  = "=== ZW TTVGPT Database Performance Benchmark ===\n\n";
		$readable_content .= 'Benchmark Time: ' . $results['database_info']['benchmark_time'] . "\n";
		$readable_content .= 'MySQL Version: ' . $results['database_info']['mysql_version'] . "\n";
		$readable_content .= 'PHP Version: ' . $results['database_info']['server_info']['php_version'] . "\n";
		$readable_content .= 'Total Posts: ' . number_format( $results['database_info']['posts_count'] ) . "\n";
		$readable_content .= 'Total Postmeta: ' . number_format( $results['database_info']['postmeta_count'] ) . "\n";

		if ( isset( $results['detailed_timings'] ) ) {
			$readable_content .= 'Memory Peak: ' . $results['detailed_timings']['memory_peak_mb'] . "MB\n";
			$readable_content .= 'Total Queries: ' . $results['detailed_timings']['total_queries_executed'] . "\n";
			$readable_content .= 'DB Info Time: ' . $results['detailed_timings']['database_info_ms'] . "ms\n";
		}
		$readable_content .= "\n";

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

			// Add detailed run information if available
			if ( isset( $data['detailed_runs'] ) ) {
				foreach ( $data['detailed_runs'] as $run_data ) {
					$readable_content .= sprintf(
						"  Run %d: %.2fms, %.2fMB memory, %d queries, %d results\n",
						$run_data['run'],
						$run_data['time_ms'],
						$run_data['memory_mb'],
						$run_data['query_count'],
						$run_data['results']
					);
				}
			}
			$readable_content .= "\n";
		}

		$readable_content .= "=== Supporting Queries - DETAILED BREAKDOWN ===\n\n";
		$readable_content .= sprintf( "Get Recent Month: %.2fms\n", $results['supporting_queries']['get_most_recent_month_ms'] );
		$readable_content .= sprintf( "Get All Months: %.2fms\n", $results['supporting_queries']['get_months_ms'] );

		if ( isset( $results['supporting_queries']['total_supporting_ms'] ) ) {
			$readable_content .= sprintf( "Total Supporting: %.2fms\n", $results['supporting_queries']['total_supporting_ms'] );
		}

		if ( isset( $results['supporting_queries']['detailed_breakdown'] ) ) {
			$breakdown         = $results['supporting_queries']['detailed_breakdown'];
			$readable_content .= "\n--- Recent Month Query Details ---\n";
			$readable_content .= sprintf( "Time: %.2fms\n", $breakdown['get_most_recent_month']['time_ms'] );
			$readable_content .= sprintf( "Memory: %.2fMB\n", $breakdown['get_most_recent_month']['memory_mb'] );
			$readable_content .= sprintf( "Queries: %d\n", $breakdown['get_most_recent_month']['query_count'] );
			$readable_content .= sprintf( "Result: %s\n", $breakdown['get_most_recent_month']['result'] );

			$readable_content .= "\n--- Get Months Query Details ---\n";
			$readable_content .= sprintf( "Time: %.2fms\n", $breakdown['get_months']['time_ms'] );
			$readable_content .= sprintf( "Memory: %.2fMB\n", $breakdown['get_months']['memory_mb'] );
			$readable_content .= sprintf( "Queries: %d\n", $breakdown['get_months']['query_count'] );
			$readable_content .= sprintf( "Results: %d months\n", $breakdown['get_months']['results_count'] );
		}

		$readable_content .= "\n=== BOTTLENECK ANALYSIS ===\n\n";

		// Identify bottlenecks
		$main_query_time   = $results['analysis']['fastest_time_ms'];
		$recent_month_time = $results['supporting_queries']['get_most_recent_month_ms'];
		$months_time       = $results['supporting_queries']['get_months_ms'];
		$total_time        = $results['analysis']['total_dashboard_time_ms'];

		$readable_content .= sprintf( "Main Query (fastest): %.2fms (%.1f%% of total)\n", $main_query_time, ( $main_query_time / $total_time ) * 100 );
		$readable_content .= sprintf( "Recent Month Query: %.2fms (%.1f%% of total)\n", $recent_month_time, ( $recent_month_time / $total_time ) * 100 );
		$readable_content .= sprintf( "Get Months Query: %.2fms (%.1f%% of total)\n", $months_time, ( $months_time / $total_time ) * 100 );

		// Identify primary bottleneck
		$bottlenecks = array(
			'main_query'   => $main_query_time,
			'recent_month' => $recent_month_time,
			'get_months'   => $months_time,
		);
		arsort( $bottlenecks );
		$primary_bottleneck = array_key_first( $bottlenecks );
		$bottleneck_time    = $bottlenecks[ $primary_bottleneck ];

		$readable_content .= sprintf(
			"\n🎯 PRIMARY BOTTLENECK: %s (%.2fms - %.1f%% of total time)\n",
			strtoupper( str_replace( '_', ' ', $primary_bottleneck ) ),
			$bottleneck_time,
			( $bottleneck_time / $total_time ) * 100
		);

		// Optimization suggestions
		if ( 'get_months' === $primary_bottleneck ) {
			$readable_content .= "💡 OPTIMIZATION: get_months query needs further optimization\n";
		} elseif ( 'recent_month' === $primary_bottleneck ) {
			$readable_content .= "💡 OPTIMIZATION: recent_month query needs index optimization\n";
		} else {
			$readable_content .= "💡 STATUS: Main query is well optimized, supporting queries dominate\n";
		}

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
	 *
	 * @param int $year Target year
	 * @param int $month Target month
	 * @return array Array of WP_Post objects
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
	 *
	 * @param int $year Target year
	 * @param int $month Target month
	 * @return array Array of WP_Post objects
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
	 *
	 * @param int $year Target year
	 * @param int $month Target month
	 * @return array Array of WP_Post objects
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
	 *
	 * @param int $year Target year
	 * @param int $month Target month
	 * @return array Array of WP_Post objects
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
	 *
	 * @param int $year Target year
	 * @param int $month Target month
	 * @return array Array of WP_Post objects
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
	 *
	 * @param array $posts Array of post data from database
	 * @return array Array of WP_Post objects
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
