<?php
/**
 * Fine Tuning Export class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fine Tuning Export class.
 *
 * Exports AI+human training data in JSONL format for OpenAI fine-tuning.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class FineTuningExport {
	/**
	 * Initializes fine tuning export with dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger     $logger      Logger instance for debugging.
	 * @param ApiHandler $api_handler API handler for reusing production logic.
	 * @param int        $word_limit  Word limit matching production settings.
	 */
	public function __construct(
		private readonly Logger $logger,
		private readonly ApiHandler $api_handler,
		private readonly int $word_limit
	) {}

	/**
	 * Initializes WordPress filesystem API.
	 *
	 * @since 1.0.0
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
	 * Generates JSONL training data for DPO fine-tuning.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters Optional. Filters for date range and post count. Default empty array.
	 * @return array|\WP_Error Training result with data and stats, or WP_Error on failure.
	 *
	 * @phpstan-param TrainingFilters $filters
	 * @phpstan-return array{message: string, data: array<int, mixed>, stats: array<string, mixed>}|\WP_Error
	 */
	public function generate_training_data( array $filters = array() ): array|\WP_Error {
		$this->logger->debug( 'Starting DPO training data generation with filters: ' . wp_json_encode( $filters ) );

		$posts = $this->get_suitable_posts( $filters );

		if ( empty( $posts ) ) {
			$this->logger->debug( 'No suitable posts found for training data generation' );
			return new \WP_Error(
				'no_posts',
				__( 'Geen geschikte berichten gevonden voor training data', 'zw-ttvgpt' )
			);
		}

		$training_data = array();
		$stats         = array(
			'total_posts' => 0,
			'processed'   => 0,
			'skipped'     => 0,
			'errors'      => 0,
			'date_range'  => array(
				'start' => '',
				'end'   => '',
			),
		);

		foreach ( $posts as $post ) {
			++$stats['total_posts'];

			try {
				$training_entry = $this->create_training_entry( $post );

				if ( $training_entry ) {
					$training_data[] = $training_entry;
					++$stats['processed'];

					// Track date range.
					$post_date = get_the_date( 'Y-m-d', $post->ID );
					if ( '' === $stats['date_range']['start'] ) {
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
	 * Retrieves posts suitable for DPO training (AI generated + human edited).
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters Filters for date range and limits.
	 * @return array Array of post objects with AI and human content.
	 *
	 * @phpstan-param TrainingFilters $filters
	 * @phpstan-return array<int, object>
	 */
	private function get_suitable_posts( array $filters ): array {
		global $wpdb;

		$date_filter  = Helper::build_date_filter_clause(
			$filters['start_date'] ?? '',
			$filters['end_date'] ?? ''
		);
		$limit_clause = '';

		// Apply limit.
		if ( ! empty( $filters['limit'] ) ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', $filters['limit'] );
		}

		// Query for posts with AI content that has been edited by humans.
		$ai_field         = Constants::ACF_FIELD_AI_CONTENT;
		$human_field      = Constants::ACF_FIELD_HUMAN_CONTENT;
		$kabelkrant_field = Constants::ACF_FIELD_IN_KABELKRANT;

		// Build base query.
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

		// Add date filter if provided.
		if ( ! empty( $date_filter ) ) {
			$base_query .= ' ' . $date_filter;
		}

		// Add order by.
		$base_query .= ' ORDER BY p.post_date DESC';

		// Add limit if provided.
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
	 * Creates a single training entry in DPO format.
	 *
	 * @since 1.0.0
	 *
	 * @param \stdClass $post Post object with ID, ai_content, human_content, and post_content properties.
	 * @return array|null Training entry array or null if content is invalid.
	 *
	 * @phpstan-return array<string, mixed>|null
	 */
	private function create_training_entry( \stdClass $post ): ?array {
		// Validate required properties exist.
		if ( ! isset( $post->ID ) || ! isset( $post->ai_content ) || ! isset( $post->human_content ) || ! isset( $post->post_content ) ) {
			return null;
		}

		// Validate content.
		if ( empty( $post->ai_content ) || empty( $post->human_content ) ) {
			$this->logger->debug( 'Skipping post ' . $post->ID . ': missing AI or human content' );
			return null;
		}

		// Strip region prefixes for comparison (reuse audit helper logic).
		$ai_clean    = AuditHelper::strip_region_prefix( $post->ai_content );
		$human_clean = AuditHelper::strip_region_prefix( $post->human_content );

		// Double-check that content is actually different.
		if ( $ai_clean === $human_clean ) {
			$this->logger->debug( 'Skipping post ' . $post->ID . ': AI and human content are identical after cleaning' );
			return null;
		}

		// Prepare content using the exact same logic as production.
		$cleaned_content = $this->api_handler->prepare_content( $post->post_content );

		// Create DPO training entry using exact production message format.
		$training_entry = array(
			'input'                => array(
				'messages'            => $this->api_handler->build_messages( $cleaned_content, $this->word_limit ),
				'tools'               => array(),
				'parallel_tool_calls' => true,
			),
			'preferred_output'     => array(
				array(
					'role'    => 'assistant',
					'content' => $human_clean, // Cleaned version without region prefix.
				),
			),
			'non_preferred_output' => array(
				array(
					'role'    => 'assistant',
					'content' => $ai_clean, // Cleaned version without region prefix.
				),
			),
		);

		return $training_entry;
	}



	/**
	 * Exports training data as JSONL file.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $training_data Array of training entries.
	 * @param string $filename      Optional. Filename (defaults to timestamped name). Default empty.
	 * @return array|\WP_Error Export result with file info, or WP_Error on failure.
	 *
	 * @phpstan-param array<int, mixed> $training_data
	 * @phpstan-return array{message: string, file_path: string, file_url: string, filename: string, line_count: int, file_size: int}|\WP_Error
	 */
	public function export_to_jsonl( array $training_data, string $filename = '' ): array|\WP_Error {
		if ( empty( $training_data ) ) {
			return new \WP_Error(
				'no_data',
				__( 'Geen training data om te exporteren', 'zw-ttvgpt' )
			);
		}

		// Generate filename if not provided.
		if ( empty( $filename ) ) {
			$filename = 'dpo_training_data_' . gmdate( 'Y-m-d_H-i-s' ) . '.jsonl';
		}

		// Ensure .jsonl extension.
		if ( ! str_ends_with( $filename, '.jsonl' ) ) {
			$filename .= '.jsonl';
		}

		// Create uploads directory if needed.
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['path'] . '/' . $filename;

		$wp_filesystem = $this->init_filesystem();

		// Build JSONL content.
		$jsonl_content = '';
		$line_count    = 0;
		foreach ( $training_data as $entry ) {
			$jsonl_content .= wp_json_encode( $entry, JSON_UNESCAPED_UNICODE ) . "\n";
			++$line_count;
		}

		// Write file using WP_Filesystem.
		if ( ! $wp_filesystem->put_contents( $file_path, $jsonl_content, FS_CHMOD_FILE ) ) {
			$this->logger->error( 'JSONL export error: Could not create file: ' . $file_path );

			return new \WP_Error(
				'export_failed',
				__( 'Fout bij exporteren van training data: kon bestand niet aanmaken', 'zw-ttvgpt' )
			);
		}

		$file_size = $wp_filesystem->size( $file_path );

		$this->logger->debug( "JSONL export completed: {$filename} ({$line_count} lines, {$file_size} bytes)" );

		return array(
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
	}
}
