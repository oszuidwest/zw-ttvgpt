<?php
/**
 * Fine Tuning Page class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Fine Tuning Page class
 *
 * Admin interface for managing OpenAI fine tuning jobs and training data export
 */
class TTVGPTFineTuningPage {
	/**
	 * Fine tuning export instance
	 *
	 * @var TTVGPTFineTuningExport
	 */
	private TTVGPTFineTuningExport $export;

	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize fine tuning page with dependencies
	 *
	 * @param TTVGPTFineTuningExport $export Export functionality
	 * @param TTVGPTLogger           $logger Logger instance
	 */
	public function __construct( TTVGPTFineTuningExport $export, TTVGPTLogger $logger ) {
		$this->export = $export;
		$this->logger = $logger;

		// Register AJAX handler
		add_action( 'wp_ajax_zw_ttvgpt_export_training_data', array( $this, 'handle_export_ajax' ) );
	}

	/**
	 * Render the fine tuning administration page
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( TTVGPTConstants::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'Je hebt geen toestemming om deze pagina te bekijken.', 'zw-ttvgpt' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Training Data Export', 'zw-ttvgpt' ); ?></h1>
			
			<div class="zw-ttvgpt-fine-tuning-container">
				<?php $this->render_export_section(); ?>
				<?php $this->render_instructions_section(); ?>
			</div>
		</div>

		<style>
		.zw-ttvgpt-fine-tuning-container {
			max-width: 1200px;
		}
		
		.fine-tuning-section {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			margin: 20px 0;
			padding: 20px;
		}
		
		.fine-tuning-section h2 {
			margin-top: 0;
			border-bottom: 1px solid #e0e0e0;
			padding-bottom: 10px;
		}
		
		.form-row {
			display: flex;
			gap: 20px;
			margin: 15px 0;
			align-items: center;
		}
		
		.form-row label {
			min-width: 120px;
			font-weight: 600;
		}
		
		.form-row input, .form-row select {
			flex: 1;
			max-width: 300px;
		}
		
		.export-stats, .job-details {
			background: #f8f9fa;
			border-left: 4px solid #007cba;
			padding: 15px;
			margin: 15px 0;
		}
		
		.export-stats h4, .job-details h4 {
			margin-top: 0;
		}
		
		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 15px;
			margin-top: 10px;
		}
		
		.stat-item {
			text-align: center;
			padding: 10px;
			background: white;
			border-radius: 4px;
			border: 1px solid #e0e0e0;
		}
		
		.stat-value {
			font-size: 24px;
			font-weight: bold;
			color: #007cba;
		}
		
		.stat-label {
			font-size: 12px;
			color: #666;
			text-transform: uppercase;
		}
		
		.jobs-table {
			width: 100%;
			border-collapse: collapse;
		}
		
		.jobs-table th,
		.jobs-table td {
			padding: 10px;
			text-align: left;
			border-bottom: 1px solid #e0e0e0;
		}
		
		.jobs-table th {
			background: #f8f9fa;
			font-weight: 600;
		}
		
		.status-badge {
			padding: 3px 8px;
			border-radius: 12px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
		}
		
		.status-running { background: #ffeaa7; color: #d63031; }
		.status-succeeded { background: #00b894; color: white; }
		.status-failed { background: #e17055; color: white; }
		.status-cancelled { background: #636e72; color: white; }
		
		.loading {
			opacity: 0.6;
			pointer-events: none;
		}
		
		.notice {
			margin: 15px 0;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Export training data handler
			$('#export-training-data').on('click', function() {
				const button = $(this);
				const originalText = button.text();
				
				button.text('<?php esc_html_e( 'Exporteren...', 'zw-ttvgpt' ); ?>').prop('disabled', true);
				
				const formData = {
					action: 'zw_ttvgpt_export_training_data',
					nonce: '<?php echo esc_js( wp_create_nonce( 'zw_ttvgpt_fine_tuning_nonce' ) ); ?>',
					start_date: $('#export-start-date').val(),
					end_date: $('#export-end-date').val(),
					limit: $('#export-limit').val()
				};
				
				$.post(ajaxurl, formData, function(response) {
					if (response.success) {
						$('#export-results').html(
							'<div class="notice notice-success"><p>' + response.data.message + '</p></div>' +
							(response.data.stats ? generateStatsHTML(response.data.stats) : '') +
							(response.data.file_info ? generateFileInfoHTML(response.data.file_info) : '')
						);
					} else {
						$('#export-results').html(
							'<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
						);
					}
				}).fail(function() {
					$('#export-results').html(
						'<div class="notice notice-error"><p><?php esc_html_e( 'Onverwachte fout opgetreden', 'zw-ttvgpt' ); ?></p></div>'
					);
				}).always(function() {
					button.text(originalText).prop('disabled', false);
				});
			});
			
			function generateStatsHTML(stats) {
				return '<div class="export-stats">' +
					'<h4><?php esc_html_e( 'Export Statistieken', 'zw-ttvgpt' ); ?></h4>' +
					'<div class="stats-grid">' +
					'<div class="stat-item"><div class="stat-value">' + stats.total_posts + '</div><div class="stat-label"><?php esc_html_e( 'Totaal Posts', 'zw-ttvgpt' ); ?></div></div>' +
					'<div class="stat-item"><div class="stat-value">' + stats.processed + '</div><div class="stat-label"><?php esc_html_e( 'Verwerkt', 'zw-ttvgpt' ); ?></div></div>' +
					'<div class="stat-item"><div class="stat-value">' + stats.skipped + '</div><div class="stat-label"><?php esc_html_e( 'Overgeslagen', 'zw-ttvgpt' ); ?></div></div>' +
					'<div class="stat-item"><div class="stat-value">' + stats.errors + '</div><div class="stat-label"><?php esc_html_e( 'Fouten', 'zw-ttvgpt' ); ?></div></div>' +
					'</div></div>';
			}
			
			function generateFileInfoHTML(fileInfo) {
				return '<div class="file-info">' +
					'<h4><?php esc_html_e( 'Geëxporteerd Bestand', 'zw-ttvgpt' ); ?></h4>' +
					'<p><strong><?php esc_html_e( 'Bestandsnaam:', 'zw-ttvgpt' ); ?></strong> <a href="' + fileInfo.file_url + '" target="_blank">' + fileInfo.filename + '</a></p>' +
					'<p><strong><?php esc_html_e( 'Records:', 'zw-ttvgpt' ); ?></strong> ' + fileInfo.line_count + '</p>' +
					'<p><strong><?php esc_html_e( 'Bestandsgrootte:', 'zw-ttvgpt' ); ?></strong> ' + Math.round(fileInfo.file_size / 1024) + ' KB</p>' +
					'</div>';
			}
		});
		</script>
		<?php
	}

	/**
	 * Render training data export section
	 *
	 * @return void
	 */
	private function render_export_section(): void {
		?>
		<div class="fine-tuning-section">
			<h2><?php esc_html_e( 'Training Data Exporteren', 'zw-ttvgpt' ); ?></h2>
			<p><?php esc_html_e( 'Exporteer AI+mens bewerkte berichten als JSONL bestand voor DPO fine-tuning. Het bestand gebruikt exact dezelfde system prompt en content formatting als de productie plugin.', 'zw-ttvgpt' ); ?></p>
			
			<div class="form-row">
				<label for="export-start-date"><?php esc_html_e( 'Start Datum:', 'zw-ttvgpt' ); ?></label>
				<input type="date" id="export-start-date" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-3 months' ) ) ); ?>">
			</div>
			
			<div class="form-row">
				<label for="export-end-date"><?php esc_html_e( 'Eind Datum:', 'zw-ttvgpt' ); ?></label>
				<input type="date" id="export-end-date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
			</div>
			
			<div class="form-row">
				<label for="export-limit"><?php esc_html_e( 'Max Records:', 'zw-ttvgpt' ); ?></label>
				<input type="number" id="export-limit" value="1000" min="10" max="10000" step="10">
			</div>
			
			<button type="button" id="export-training-data" class="button button-primary">
				<?php esc_html_e( 'Export Training Data', 'zw-ttvgpt' ); ?>
			</button>
			
			<div id="export-results"></div>
		</div>
		<?php
	}

	/**
	 * Render instructions section
	 *
	 * @return void
	 */
	private function render_instructions_section(): void {
		?>
		<div class="fine-tuning-section">
			<h2><?php esc_html_e( 'Volgende Stappen', 'zw-ttvgpt' ); ?></h2>
			<p><?php esc_html_e( 'Nadat je het training data bestand hebt geëxporteerd, kun je het uploaden naar OpenAI en een fine tuning job aanmaken:', 'zw-ttvgpt' ); ?></p>
			
			<ol>
				<li>
					<strong><?php esc_html_e( 'Upload naar OpenAI:', 'zw-ttvgpt' ); ?></strong>
					<p><?php esc_html_e( 'Ga naar de', 'zw-ttvgpt' ); ?> <a href="https://platform.openai.com/finetune" target="_blank">OpenAI Platform</a> <?php esc_html_e( 'en upload het geëxporteerde JSONL bestand.', 'zw-ttvgpt' ); ?></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Maak Fine Tuning Job aan:', 'zw-ttvgpt' ); ?></strong>
					<p><?php esc_html_e( 'Selecteer DPO (Direct Preference Optimization) als training method en kies een basis model (aanbevolen: gpt-4.1-mini).', 'zw-ttvgpt' ); ?></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Gebruik Fine-tuned Model:', 'zw-ttvgpt' ); ?></strong>
					<p><?php esc_html_e( 'Wanneer de training is voltooid, kun je het fine-tuned model gebruiken door de model naam bij te werken in de plugin instellingen.', 'zw-ttvgpt' ); ?></p>
				</li>
			</ol>
			
			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Let op:', 'zw-ttvgpt' ); ?></strong> <?php esc_html_e( 'DPO fine tuning vereist zowel "preferred" als "non-preferred" responses. Alleen berichten waar AI output door mensen is bewerkt komen in aanmerking voor export.', 'zw-ttvgpt' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request for exporting training data
	 *
	 * @return void
	 */
	public function handle_export_ajax(): void {
		// Security checks
		if ( ! check_ajax_referer( 'zw_ttvgpt_fine_tuning_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Beveiligingscontrole mislukt', 'zw-ttvgpt' ) ), 403 );
		}

		if ( ! current_user_can( TTVGPTConstants::REQUIRED_CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Onvoldoende rechten', 'zw-ttvgpt' ) ), 403 );
		}

		// Get filters
		$filters = array();
		if ( ! empty( $_POST['start_date'] ) ) {
			$filters['start_date'] = sanitize_text_field( $_POST['start_date'] );
		}
		if ( ! empty( $_POST['end_date'] ) ) {
			$filters['end_date'] = sanitize_text_field( $_POST['end_date'] );
		}
		if ( ! empty( $_POST['limit'] ) ) {
			$filters['limit'] = absint( $_POST['limit'] );
		}

		try {
			// Use export object but with fallback if it fails
			$result = $this->export->generate_training_data( $filters );
			
			if ( ! $result['success'] ) {
				wp_send_json_error( array( 'message' => $result['message'] ) );
			}

			// Export to JSONL file
			$export_result = $this->export->export_to_jsonl( $result['data'] );
			
			if ( ! $export_result['success'] ) {
				wp_send_json_error( array( 'message' => $export_result['message'] ) );
			}

			wp_send_json_success(
				array(
					'message'   => $result['message'] . ' ' . $export_result['message'],
					'stats'     => $result['stats'],
					'file_info' => array(
						'filename'   => $export_result['filename'],
						'file_url'   => $export_result['file_url'],
						'line_count' => $export_result['line_count'],
						'file_size'  => $export_result['file_size'],
					),
				)
			);

		} catch ( Exception $e ) {
			// Fallback to simple implementation if export object fails
			$training_data = $this->simple_get_training_data( $filters );
			
			if ( empty( $training_data ) ) {
				wp_send_json_error( array( 'message' => 'Geen training data gevonden' ) );
			}

			// Simple JSONL export
			$filename = 'dpo_training_data_' . gmdate( 'Y-m-d_H-i-s' ) . '.jsonl';
			$upload_dir = wp_upload_dir();
			$file_path = $upload_dir['path'] . '/' . $filename;

			$file_handle = fopen( $file_path, 'w' );
			if ( ! $file_handle ) {
				wp_send_json_error( array( 'message' => 'Kan bestand niet maken' ) );
			}

			$line_count = 0;
			foreach ( $training_data as $entry ) {
				fwrite( $file_handle, wp_json_encode( $entry, JSON_UNESCAPED_UNICODE ) . "\n" );
				$line_count++;
			}
			fclose( $file_handle );

			wp_send_json_success( array(
				'message' => "Training data geëxporteerd: {$line_count} records",
				'file_info' => array(
					'filename' => $filename,
					'file_url' => $upload_dir['url'] . '/' . $filename,
					'line_count' => $line_count,
					'file_size' => filesize( $file_path ),
				),
			) );
		}
	}

	/**
	 * Simple training data retrieval without complex objects
	 *
	 * @param array $filters Filters for data retrieval
	 * @return array Training data entries
	 */
	private function simple_get_training_data( array $filters ): array {
		global $wpdb;

		$limit_clause = '';
		if ( ! empty( $filters['limit'] ) && is_numeric( $filters['limit'] ) ) {
			$limit_clause = $wpdb->prepare( 'LIMIT %d', absint( $filters['limit'] ) );
		}

		$query = "
			SELECT p.ID, p.post_content,
			       pm1.meta_value as ai_content,
			       pm2.meta_value as human_content
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
				AND pm1.meta_key = 'post_kabelkrant_content_gpt'
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND pm2.meta_key = 'post_kabelkrant_content'
			WHERE p.post_status = 'publish'
			  AND p.post_type = 'post'
			  AND pm1.meta_value != ''
			  AND pm1.meta_value != pm2.meta_value
			ORDER BY p.post_date DESC
			{$limit_clause}
		";

		$results = $wpdb->get_results( $query );
		if ( ! $results ) {
			return array();
		}

		$training_data = array();
		foreach ( $results as $post ) {
			if ( empty( $post->ai_content ) || empty( $post->human_content ) ) {
				continue;
			}

			// Clean content
			$cleaned_content = wp_strip_all_tags( $post->post_content );
			$word_limit = 150; // Default word limit

			// Create DPO entry
			$training_entry = array(
				'input' => array(
					'messages' => array(
						array(
							'role' => 'system',
							'content' => sprintf(
								'Please summarize the following news article in a clear and concise manner that is easy to understand for a general audience. Use short sentences. Do it in Dutch. Ignore everything in the article that\'s not a Dutch word. Parse HTML. Never output English words. Use maximal %d words.',
								$word_limit
							),
						),
						array(
							'role' => 'user',
							'content' => $cleaned_content,
						),
					),
					'tools' => array(),
					'parallel_tool_calls' => true,
				),
				'preferred_output' => array(
					array(
						'role' => 'assistant',
						'content' => trim( $post->human_content ),
					),
				),
				'non_preferred_output' => array(
					array(
						'role' => 'assistant',
						'content' => trim( $post->ai_content ),
					),
				),
			);

			$training_data[] = $training_entry;
		}

		return $training_data;
	}
}