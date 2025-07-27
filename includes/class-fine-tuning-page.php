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
	use TTVGPTAjaxSecurity;

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
		$this->validate_page_access( TTVGPTConstants::REQUIRED_CAPABILITY );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Trainingsdata-export', 'zw-ttvgpt' ); ?></h1>
			
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
		
		.loading {
			opacity: 0.6;
			pointer-events: none;
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
							'<div class="notice notice-success">' +
								'<p>✓ Export succesvol voltooid</p>' +
							'</div>' +
							'<div class="export-summary">' +
								'<h4>Exportdetails</h4>' +
								'<p><strong>' + response.data.file_info.line_count + ' trainingsrecords</strong> geëxporteerd</p>' +
								'<p><a href="' + response.data.file_info.file_url + '" target="_blank" class="file-download">📄 ' + response.data.file_info.filename + '</a> <span class="file-size">(' + Math.round(response.data.file_info.file_size / 1024) + ' KB)</span></p>' +
							'</div>' +
							'<details class="export-stats">' +
								'<summary>Technische gegevens</summary>' +
								generateStatsHTML(response.data.stats) +
							'</details>'
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
				return '<div class="export-stats-content">' +
					'<ul class="export-stats-list">' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Totaal berichten', 'zw-ttvgpt' ); ?></span><span class="export-stats-value">' + stats.total_posts + '</span></li>' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Verwerkt', 'zw-ttvgpt' ); ?></span><span class="export-stats-value success">' + stats.processed + '</span></li>' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Overgeslagen', 'zw-ttvgpt' ); ?></span><span class="export-stats-value">' + stats.skipped + '</span></li>' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Fouten', 'zw-ttvgpt' ); ?></span><span class="export-stats-value' + (stats.errors > 0 ? ' error' : '') + '">' + stats.errors + '</span></li>' +
					'</ul>' +
					'</div>';
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
			<h2><?php esc_html_e( 'Trainingsdata exporteren', 'zw-ttvgpt' ); ?></h2>
			<p><?php esc_html_e( 'Exporteer AI+mensbewerkte berichten als JSONL-bestand voor DPO-finetuning. Het bestand gebruikt exact dezelfde systeemprompt en contentformattering als de productieplugin.', 'zw-ttvgpt' ); ?></p>
			
			<div class="form-row">
				<label for="export-start-date"><?php esc_html_e( 'Startdatum:', 'zw-ttvgpt' ); ?></label>
				<input type="date" id="export-start-date" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-3 months' ) ) ); ?>">
			</div>
			
			<div class="form-row">
				<label for="export-end-date"><?php esc_html_e( 'Einddatum:', 'zw-ttvgpt' ); ?></label>
				<input type="date" id="export-end-date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
			</div>
			
			<div class="form-row">
				<label for="export-limit"><?php esc_html_e( 'Maximum aantal berichten:', 'zw-ttvgpt' ); ?></label>
				<input type="number" id="export-limit" value="1000" min="10" max="10000" step="10">
			</div>
			
			<button type="button" id="export-training-data" class="button button-primary">
				<?php esc_html_e( 'Trainingsdata exporteren', 'zw-ttvgpt' ); ?>
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
			<h2><?php esc_html_e( 'Volgende stappen', 'zw-ttvgpt' ); ?></h2>
			<p><?php esc_html_e( 'Nadat je het trainingsdatabestand hebt geëxporteerd, kun je het uploaden naar OpenAI en een finetuningjob aanmaken:', 'zw-ttvgpt' ); ?></p>
			
			<ol>
				<li>
					<strong><?php esc_html_e( 'Upload naar OpenAI:', 'zw-ttvgpt' ); ?></strong>
					<p><?php esc_html_e( 'Ga naar het', 'zw-ttvgpt' ); ?> <a href="https://platform.openai.com/finetune" target="_blank">OpenAI-platform</a> <?php esc_html_e( 'en upload het geëxporteerde JSONL-bestand.', 'zw-ttvgpt' ); ?></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Maak een finetuningjob aan:', 'zw-ttvgpt' ); ?></strong>
					<p><?php esc_html_e( 'Selecteer DPO (Direct Preference Optimization) als trainingsmethode en kies een basismodel (aanbevolen: gpt-4.1-mini).', 'zw-ttvgpt' ); ?></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Gebruik het gefinetuunde model:', 'zw-ttvgpt' ); ?></strong>
					<p><?php esc_html_e( 'Wanneer de training is voltooid, kun je het gefinetuunde model gebruiken door de modelnaam bij te werken in de plugin-instellingen.', 'zw-ttvgpt' ); ?></p>
				</li>
			</ol>
			
			<div class="notice notice-info inline">
				<p><strong><?php esc_html_e( 'Let op:', 'zw-ttvgpt' ); ?></strong> <?php esc_html_e( 'DPO-finetuning vereist zowel "preferred" als "non-preferred" responses. Alleen berichten waar AI-output door mensen is bewerkt komen in aanmerking voor export.', 'zw-ttvgpt' ); ?></p>
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
		// Verify nonce first
		if ( ! check_ajax_referer( 'zw_ttvgpt_fine_tuning_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Beveiligingscontrole mislukt', 'zw-ttvgpt' ) ),
				403
			);
		}

		// Check capability
		if ( ! current_user_can( TTVGPTConstants::REQUIRED_CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Onvoldoende rechten', 'zw-ttvgpt' ) ),
				403
			);
		}

		// Get filters from POST data
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

		// Generate training data
		$this->logger->debug( 'Export training data requested with filters: ' . wp_json_encode( $filters ) );
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
	}
}