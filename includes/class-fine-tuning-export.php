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
 *
 * @package ZW_TTVGPT
 */
class TTVGPTFineTuningExport {
	/**
	 * Initialize fine tuning export with dependencies
	 *
	 * @param TTVGPTLogger     $logger      Logger instance for debugging.
	 * @param TTVGPTApiHandler $api_handler API handler for reusing production logic.
	 * @param int              $word_limit  Word limit matching production settings.
	 */
	public function __construct(
		private readonly TTVGPTLogger $logger,
		private readonly TTVGPTApiHandler $api_handler,
		private readonly int $word_limit
	) {}

	/**
	 * Initialize WordPress filesystem API
	 *
	 * @return \WP_Filesystem_Base WordPress filesystem instance.
	 */
	private function init_filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		return $wp_filesystem;
	}

	/**
	 * Generate JSONL training data for DPO fine-tuning
	 *
	 * @param array $filters Optional filters for date range and post count.
	 * @return array Array containing success status, training data, and statistics.
	 */
	public function generate_training_data( array $filters = array() ): array {
		$this->logger->debug( 'Starting DPO training data generation with filters: ' . wp_json_encode( $filters ) );

		$posts = $this->get_suitable_posts( $filters );

		if ( empty( $posts ) ) {
			$this->logger->debug( 'No suitable posts found for training data generation' );
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
			} catch ( \Exception $e ) {
				++$stats['errors'];
				$this->logger->debug( 'Error processing post ' . $post->ID . ': ' . $e->getMessage() );
			}
		}

		$this->logger->debug( 'Training data generation completed. Stats: ' . wp_json_encode( $stats ) );

		return array(
			'message' => sprintf(
				/* translators: %1$d: number of processed posts, %2$d: number of suitable posts for training */
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
	 * @param array $filters Filters for date range and limits.
	 * @return array Array of post objects with AI and human content.
	 */
	private function get_suitable_posts( array $filters ): array {
		global $wpdb;

		$date_filter  = TTVGPTHelper::build_date_filter_clause(
			$filters['start_date'] ?? '',
			$filters['end_date'] ?? ''
		);
		$limit_clause = '';

		// Apply limit
		if ( ! empty( $filters['limit'] ) && is_numeric( $filters['limit'] ) ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', absint( $filters['limit'] ) );
		}

		// Query for posts with AI content that has been edited by humans
		$ai_field         = TTVGPTConstants::ACF_FIELD_AI_CONTENT;
		$human_field      = TTVGPTConstants::ACF_FIELD_HUMAN_CONTENT;
		$kabelkrant_field = TTVGPTConstants::ACF_FIELD_IN_KABELKRANT;

		// Build base query
		$base_query = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_content, p.post_date,
			       pm1.meta_value as ai_content,
			       pm2.meta_value as human_content
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
				AND pm1.meta_key = %s
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND pm2.meta_key = %s
			INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id 
				AND pm3.meta_key = %s 
				AND pm3.meta_value = '1'
			WHERE p.post_status = 'publish'
			  AND p.post_type = 'post'
			  AND pm1.meta_value != ''
			  AND pm1.meta_value != pm2.meta_value",
			$ai_field,
			$human_field,
			$kabelkrant_field
		);

		// Add date filter if provided
		if ( ! empty( $date_filter ) ) {
			$base_query .= ' ' . $date_filter;
		}

		// Add order by
		$base_query .= ' ORDER BY p.post_date DESC';

		// Add limit if provided
		if ( ! empty( $limit_clause ) ) {
			$base_query .= ' ' . $limit_clause;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Base query is prepared above, date/limit clauses are also prepared
		$results = $wpdb->get_results( $base_query );

		if ( $wpdb->last_error ) {
			$this->logger->error( 'Database error in get_suitable_posts: ' . $wpdb->last_error );
			return array();
		}

		$this->logger->debug( 'Found ' . count( $results ) . ' suitable posts for training data' );
		return $results;
	}

	/**
	 * Create a single training entry in DPO format
	 *
	 * @param \stdClass $post Post object with ID, ai_content, human_content, and post_content properties.
	 * @return array|null Training entry array or null if content is invalid.
	 */
	private function create_training_entry( \stdClass $post ): ?array {
		// Validate required properties exist
		if ( ! isset( $post->ID ) || ! isset( $post->ai_content ) || ! isset( $post->human_content ) || ! isset( $post->post_content ) ) {
			return null;
		}

		// Validate content
		if ( empty( $post->ai_content ) || empty( $post->human_content ) ) {
			$this->logger->debug( 'Skipping post ' . $post->ID . ': missing AI or human content' );
			return null;
		}

		// Strip region prefixes for comparison (reuse audit helper logic)
		$ai_clean    = TTVGPTAuditHelper::strip_region_prefix( $post->ai_content );
		$human_clean = TTVGPTAuditHelper::strip_region_prefix( $post->human_content );

		// Double-check that content is actually different
		if ( $ai_clean === $human_clean ) {
			$this->logger->debug( 'Skipping post ' . $post->ID . ': AI and human content are identical after cleaning' );
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
					'content' => $human_clean, // Cleaned version without region prefix
				),
			),
			'non_preferred_output' => array(
				array(
					'role'    => 'assistant',
					'content' => $ai_clean, // Cleaned version without region prefix
				),
			),
		);

		return $training_entry;
	}



	/**
	 * Export training data as JSONL file
	 *
	 * @param array  $training_data Array of training entries.
	 * @param string $filename      Optional filename (defaults to timestamped name).
	 * @return array Result array with success status, file path, and statistics.
	 * @throws \Exception If upload directory cannot be created or file cannot be written.
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
			$wp_filesystem = $this->init_filesystem();

			// Build JSONL content
			$jsonl_content = '';
			$line_count    = 0;
			foreach ( $training_data as $entry ) {
				$jsonl_content .= wp_json_encode( $entry, JSON_UNESCAPED_UNICODE ) . "\n";
				++$line_count;
			}

			// Write file using WP_Filesystem
			if ( ! $wp_filesystem->put_contents( $file_path, $jsonl_content, FS_CHMOD_FILE ) ) {
				throw new \Exception( 'Could not create file: ' . $file_path );
			}

			$file_size = $wp_filesystem->size( $file_path );

			$this->logger->debug( "JSONL export completed: {$filename} ({$line_count} lines, {$file_size} bytes)" );

			return array(
				'success'    => true,
				'message'    => sprintf(
					/* translators: %1$s: filename, %2$d: number of records */
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

		} catch ( \Exception $e ) {
			$this->logger->error( 'JSONL export error: ' . $e->getMessage() );

			return array(
				'success' => false,
				'message' => __( 'Fout bij exporteren van training data: ', 'zw-ttvgpt' ) . $e->getMessage(),
			);
		}
	}

	/**
	 * Validate JSONL file format
	 *
	 * @param string $file_path Path to JSONL file.
	 * @param int    $max_lines Maximum lines to validate (0 = all).
	 * @return array Validation result with validity status and error details.
	 */
	public function validate_jsonl( string $file_path, int $max_lines = 100 ): array {
		$wp_filesystem = $this->init_filesystem();

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Bestand niet gevonden', 'zw-ttvgpt' ),
			);
		}

		$errors        = array();
		$line_count    = 0;
		$valid_entries = 0;

		$file_contents = $wp_filesystem->get_contents( $file_path );
		if ( false === $file_contents ) {
			return array(
				'valid'   => false,
				'message' => __( 'Kan bestand niet lezen', 'zw-ttvgpt' ),
			);
		}

		$lines = explode( "\n", $file_contents );
		foreach ( $lines as $line ) {
			if ( $max_lines > 0 && $line_count >= $max_lines ) {
				break;
			}
			++$line_count;
			$line = trim( $line );

			if ( empty( $line ) ) {
				continue;
			}

			// Use json_validate() for efficient validation without parsing (PHP 8.3+)
			if ( ! json_validate( $line ) ) {
				// translators: %d: line number with invalid JSON
				$errors[] = sprintf( __( 'Regel %d: Ongeldige JSON', 'zw-ttvgpt' ), $line_count );
				continue;
			}

			$data = json_decode( $line, true );

			// Validate DPO structure
			if ( ! is_array( $data ) || ! $this->validate_dpo_entry( $data ) ) {
				// translators: %d: line number with invalid DPO structure
				$errors[] = sprintf( __( 'Regel %d: Ongeldige DPO structuur', 'zw-ttvgpt' ), $line_count );
				continue;
			}

			++$valid_entries;
		}

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
	 * @param array $entry DPO entry to validate.
	 * @return bool True if entry has valid DPO structure, false otherwise.
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
			if ( 'system' === $message['role'] ) {
				$has_system = true;
			}
			if ( 'user' === $message['role'] ) {
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

			if ( 'assistant' !== $output['role'] ) {
				return false;
			}
		}

		return true;
	}
}
