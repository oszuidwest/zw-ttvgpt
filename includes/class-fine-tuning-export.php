<?php
/**
 * Fine Tuning Export class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Fine Tuning Export class
 *
 * Exports AI+human training data in JSONL format for OpenAI fine-tuning
 */
class TTVGPTFineTuningExport {
	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * API handler instance for reusing production logic
	 *
	 * @var TTVGPTApiHandler
	 */
	private TTVGPTApiHandler $api_handler;

	/**
	 * Word limit for summaries (matches production settings)
	 *
	 * @var int
	 */
	private int $word_limit;

	/**
	 * Initialize fine tuning export with dependencies
	 *
	 * @param TTVGPTLogger     $logger      Logger instance for debugging
	 * @param TTVGPTApiHandler $api_handler API handler for reusing production logic
	 * @param int              $word_limit  Word limit matching production settings
	 */
	public function __construct( TTVGPTLogger $logger, TTVGPTApiHandler $api_handler, int $word_limit ) {
		$this->logger      = $logger;
		$this->api_handler = $api_handler;
		$this->word_limit  = $word_limit;
	}

	/**
	 * Generate JSONL training data for DPO fine-tuning
	 *
	 * @param array $filters Optional filters for date range and post count
	 * @return array Array containing training data and metadata
	 */
	public function generate_training_data( array $filters = array() ): array {
		error_log( 'ZW_TTVGPT: generate_training_data called with filters: ' . wp_json_encode( $filters ) );
		$this->logger->log( 'Starting DPO training data generation with filters: ' . wp_json_encode( $filters ) );

		error_log( 'ZW_TTVGPT: Calling get_suitable_posts' );
		$posts = $this->get_suitable_posts( $filters );
		error_log( 'ZW_TTVGPT: get_suitable_posts returned ' . count( $posts ) . ' posts' );

		if ( empty( $posts ) ) {
			$this->logger->log( 'No suitable posts found for training data generation' );
			return array(
				'success' => false,
				'message' => __( 'Geen geschikte berichten gevonden voor training data', 'zw-ttvgpt' ),
				'data'    => array(),
				'stats'   => array(),
			);
		}

		$training_data = array();
		$stats         = array(
			'total_posts' => 0,
			'processed'   => 0,
			'skipped'     => 0,
			'errors'      => 0,
			'date_range'  => array(),
		);

		foreach ( $posts as $post ) {
			++$stats['total_posts'];

			try {
				$training_entry = $this->create_training_entry( $post );

				if ( $training_entry ) {
					$training_data[] = $training_entry;
					++$stats['processed'];

					// Track date range
					$post_date = get_the_date( 'Y-m-d', $post->ID );
					if ( empty( $stats['date_range'] ) ) {
						$stats['date_range']['start'] = $post_date;
						$stats['date_range']['end']   = $post_date;
					} else {
						if ( $post_date < $stats['date_range']['start'] ) {
							$stats['date_range']['start'] = $post_date;
						}
						if ( $post_date > $stats['date_range']['end'] ) {
							$stats['date_range']['end'] = $post_date;
						}
					}
				} else {
					++$stats['skipped'];
				}
			} catch ( Exception $e ) {
				++$stats['errors'];
				$this->logger->log( 'Error processing post ' . $post->ID . ': ' . $e->getMessage() );
			}
		}

		$this->logger->log( 'Training data generation completed. Stats: ' . wp_json_encode( $stats ) );

		return array(
			'success' => true,
			// translators: %1$d: number of processed posts, %2$d: number of suitable posts for training
			'message' => sprintf(
				__( '%1$d berichten verwerkt, %2$d geschikt voor training', 'zw-ttvgpt' ),
				$stats['total_posts'],
				$stats['processed']
			),
			'data'    => $training_data,
			'stats'   => $stats,
		);
	}

	/**
	 * Get posts suitable for DPO training (AI generated + human edited)
	 *
	 * @param array $filters Filters for date range and limits
	 * @return array Array of post objects
	 */
	private function get_suitable_posts( array $filters ): array {
		error_log( 'ZW_TTVGPT: get_suitable_posts called' );
		global $wpdb;

		$date_filter  = '';
		$limit_clause = '';

		// Apply date filter
		if ( ! empty( $filters['start_date'] ) && ! empty( $filters['end_date'] ) ) {
			$start_date  = sanitize_text_field( $filters['start_date'] );
			$end_date    = sanitize_text_field( $filters['end_date'] );
			$date_filter = $wpdb->prepare(
				'AND p.post_date >= %s AND p.post_date <= %s',
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			);
		}

		// Apply limit
		if ( ! empty( $filters['limit'] ) && is_numeric( $filters['limit'] ) ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', absint( $filters['limit'] ) );
		}

		// Query for posts with AI content that has been edited by humans
		$query = "
			SELECT p.ID, p.post_title, p.post_content, p.post_date,
			       pm1.meta_value as ai_content,
			       pm2.meta_value as human_content
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
				AND pm1.meta_key = 'post_kabelkrant_content_gpt'
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND pm2.meta_key = 'post_kabelkrant_content'
			INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id 
				AND pm3.meta_key = 'post_in_kabelkrant' 
				AND pm3.meta_value = '1'
			WHERE p.post_status = 'publish'
			  AND p.post_type = 'post'
			  AND pm1.meta_value != ''
			  AND pm1.meta_value != pm2.meta_value
			  {$date_filter}
			ORDER BY p.post_date DESC
			{$limit_clause}
		";

		error_log( 'ZW_TTVGPT: Executing query: ' . $query );
		$results = $wpdb->get_results( $query );
		error_log( 'ZW_TTVGPT: Query executed, results: ' . ( is_array( $results ) ? count( $results ) : 'null/false' ) );

		if ( $wpdb->last_error ) {
			error_log( 'ZW_TTVGPT: Database error: ' . $wpdb->last_error );
			$this->logger->log( 'Database error in get_suitable_posts: ' . $wpdb->last_error );
			return array();
		}

		$this->logger->log( 'Found ' . count( $results ) . ' suitable posts for training data' );
		return $results;
	}

	/**
	 * Create a single training entry in DPO format
	 *
	 * @param object $post Post object with AI and human content
	 * @return array|null Training entry or null if invalid
	 */
	private function create_training_entry( object $post ): ?array {
		// Validate content
		if ( empty( $post->ai_content ) || empty( $post->human_content ) ) {
			$this->logger->log( 'Skipping post ' . $post->ID . ': missing AI or human content' );
			return null;
		}

		// Strip region prefixes for comparison (from audit helper logic)
		$ai_clean    = $this->strip_region_prefix( $post->ai_content );
		$human_clean = $this->strip_region_prefix( $post->human_content );

		// Double-check that content is actually different
		if ( $ai_clean === $human_clean ) {
			$this->logger->log( 'Skipping post ' . $post->ID . ': AI and human content are identical after cleaning' );
			return null;
		}

		// Prepare content using the exact same logic as production
		$cleaned_content = $this->api_handler->prepare_content( $post->post_content );

		// Create DPO training entry using exact production message format
		$training_entry = array(
			'input'                => array(
				'messages'            => $this->api_handler->build_messages( $cleaned_content, $this->word_limit ),
				'tools'               => array(),
				'parallel_tool_calls' => true,
			),
			'preferred_output'     => array(
				array(
					'role'    => 'assistant',
					'content' => trim( $post->human_content ),
				),
			),
			'non_preferred_output' => array(
				array(
					'role'    => 'assistant',
					'content' => trim( $post->ai_content ),
				),
			),
		);

		return $training_entry;
	}


	/**
	 * Strip region prefix from content (copied from audit helper)
	 *
	 * @param string $content Content to clean
	 * @return string Cleaned content
	 */
	private function strip_region_prefix( string $content ): string {
		return preg_replace( '/^[A-Z\s]+:\s*/', '', trim( $content ) );
	}

	/**
	 * Export training data as JSONL file
	 *
	 * @param array  $training_data Array of training entries
	 * @param string $filename Optional filename
	 * @return array Result with file path and stats
	 */
	public function export_to_jsonl( array $training_data, string $filename = '' ): array {
		if ( empty( $training_data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Geen training data om te exporteren', 'zw-ttvgpt' ),
			);
		}

		// Generate filename if not provided
		if ( empty( $filename ) ) {
			$filename = 'dpo_training_data_' . gmdate( 'Y-m-d_H-i-s' ) . '.jsonl';
		}

		// Ensure .jsonl extension
		if ( ! str_ends_with( $filename, '.jsonl' ) ) {
			$filename .= '.jsonl';
		}

		// Create uploads directory if needed
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['path'] . '/' . $filename;

		try {
			$file_handle = fopen( $file_path, 'w' );

			if ( ! $file_handle ) {
				throw new Exception( 'Could not create file: ' . $file_path );
			}

			$line_count = 0;
			foreach ( $training_data as $entry ) {
				$json_line = wp_json_encode( $entry, JSON_UNESCAPED_UNICODE ) . "\n";
				fwrite( $file_handle, $json_line );
				++$line_count;
			}

			fclose( $file_handle );

			$file_size = filesize( $file_path );

			$this->logger->log( "JSONL export completed: {$filename} ({$line_count} lines, {$file_size} bytes)" );

			return array(
				'success'    => true,
				// translators: %1$s: filename, %2$d: number of records
				'message'    => sprintf(
					__( 'Training data geëxporteerd naar %1$s (%2$d records)', 'zw-ttvgpt' ),
					$filename,
					$line_count
				),
				'file_path'  => $file_path,
				'file_url'   => $upload_dir['url'] . '/' . $filename,
				'filename'   => $filename,
				'line_count' => $line_count,
				'file_size'  => $file_size,
			);

		} catch ( Exception $e ) {
			$this->logger->log( 'JSONL export error: ' . $e->getMessage() );

			return array(
				'success' => false,
				'message' => __( 'Fout bij exporteren van training data: ', 'zw-ttvgpt' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Validate JSONL file format
	 *
	 * @param string $file_path Path to JSONL file
	 * @param int    $max_lines Maximum lines to validate (0 = all)
	 * @return array Validation result
	 */
	public function validate_jsonl( string $file_path, int $max_lines = 100 ): array {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Bestand niet gevonden', 'zw-ttvgpt' ),
			);
		}

		$errors        = array();
		$line_count    = 0;
		$valid_entries = 0;

		$file_handle = fopen( $file_path, 'r' );

		if ( ! $file_handle ) {
			return array(
				'valid'   => false,
				'message' => __( 'Kan bestand niet lezen', 'zw-ttvgpt' ),
			);
		}

		while ( ( $line = fgets( $file_handle ) ) !== false && ( $max_lines === 0 || $line_count < $max_lines ) ) {
			++$line_count;
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			$data = json_decode( $line, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				// translators: %d: line number with invalid JSON
				$errors[] = sprintf( __( 'Regel %d: Ongeldige JSON', 'zw-ttvgpt' ), $line_count );
				continue;
			}

			// Validate DPO structure
			if ( ! $this->validate_dpo_entry( $data ) ) {
				// translators: %d: line number with invalid DPO structure
				$errors[] = sprintf( __( 'Regel %d: Ongeldige DPO structuur', 'zw-ttvgpt' ), $line_count );
				continue;
			}

			++$valid_entries;
		}

		fclose( $file_handle );

		$is_valid = empty( $errors ) || count( $errors ) / $line_count < 0.1; // Allow 10% error rate

		return array(
			'valid'         => $is_valid,
			'message'       => $is_valid
				// translators: %1$d: valid entries, %2$d: total entries
				? sprintf( __( 'Bestand is geldig (%1$d/%2$d entries)', 'zw-ttvgpt' ), $valid_entries, $line_count )
				// translators: %1$d: number of errors, %2$d: total lines
				: sprintf( __( 'Bestand bevat fouten (%1$d van %2$d regels)', 'zw-ttvgpt' ), count( $errors ), $line_count ),
			'line_count'    => $line_count,
			'valid_entries' => $valid_entries,
			'errors'        => array_slice( $errors, 0, 10 ), // Show first 10 errors
			'error_count'   => count( $errors ),
		);
	}

	/**
	 * Validate single DPO entry structure
	 *
	 * @param array $entry DPO entry to validate
	 * @return bool True if valid
	 */
	private function validate_dpo_entry( array $entry ): bool {
		// Check required top-level keys
		$required_keys = array( 'input', 'preferred_output', 'non_preferred_output' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $entry[ $key ] ) ) {
				return false;
			}
		}

		// Validate input structure
		if ( ! isset( $entry['input']['messages'] ) || ! is_array( $entry['input']['messages'] ) ) {
			return false;
		}

		// Validate messages have required roles
		$messages = $entry['input']['messages'];
		if ( count( $messages ) < 2 ) {
			return false;
		}

		// Check for system and user messages
		$has_system = false;
		$has_user   = false;
		foreach ( $messages as $message ) {
			if ( ! isset( $message['role'] ) || ! isset( $message['content'] ) ) {
				return false;
			}
			if ( $message['role'] === 'system' ) {
				$has_system = true;
			}
			if ( $message['role'] === 'user' ) {
				$has_user = true;
			}
		}

		if ( ! $has_system || ! $has_user ) {
			return false;
		}

		// Validate output arrays
		foreach ( array( 'preferred_output', 'non_preferred_output' ) as $output_key ) {
			if ( ! is_array( $entry[ $output_key ] ) || empty( $entry[ $output_key ] ) ) {
				return false;
			}

			$output = $entry[ $output_key ][0] ?? null;
			if ( ! $output || ! isset( $output['role'] ) || ! isset( $output['content'] ) ) {
				return false;
			}

			if ( $output['role'] !== 'assistant' ) {
				return false;
			}
		}

		return true;
	}
}
