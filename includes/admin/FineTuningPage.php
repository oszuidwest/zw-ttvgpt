<?php
/**
 * Fine Tuning Page class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ZW_TTVGPT_Core\AjaxSecurity;
use ZW_TTVGPT_Core\Constants;
use ZW_TTVGPT_Core\FineTuningExport;
use ZW_TTVGPT_Core\Logger;

/**
 * Fine Tuning Page class
 *
 * Admin interface for managing OpenAI fine tuning jobs and training data export
 *
 * @package ZW_TTVGPT
 */
class FineTuningPage {
	use AjaxSecurity;

	/**
	 * Initialize fine tuning page with dependencies
	 *
	 * @param FineTuningExport $export Export functionality instance.
	 * @param Logger           $logger Logger instance for debugging.
	 */
	public function __construct(
		private readonly FineTuningExport $export,
		private readonly Logger $logger
	) {
		// Register AJAX handler.
		add_action( 'wp_ajax_zw_ttvgpt_export_training_data', $this->handle_export_ajax( ... ) );
	}

	/**
	 * Render the fine tuning administration page
	 *
	 * @return void
	 */
	public function render(): void {
		$this->validate_page_access( Constants::REQUIRED_CAPABILITY );

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
			// Export training data handler.
			$('#export-training-data').on('click', function() {
				const button = $(this);
				const originalText = button.text();
				
				button.text('<?php esc_html_e( '⏳ Bezig met exporteren...', 'zw-ttvgpt' ); ?>').prop('disabled', true);
				
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
								'<p>✅ Export succesvol voltooid</p>' +
							'</div>' +
							'<div class="export-summary">' +
								'<h4>Exportdetails</h4>' +
								'<p><strong>' + response.data.file_info.line_count + ' trainingsrecords</strong> geëxporteerd</p>' +
								<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Inline JS template string ?>
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
						'<div class="notice notice-error"><p><?php esc_html_e( 'Er is een onverwachte fout opgetreden', 'zw-ttvgpt' ); ?></p></div>'
					);
				}).always(function() {
					button.text(originalText).prop('disabled', false);
				});
			});
			
			<?php // phpcs:disable Generic.Files.LineLength.TooLong -- Inline JS template strings ?>
			function generateStatsHTML(stats) {
				return '<div class="export-stats-content">' +
					'<ul class="export-stats-list">' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Totaal artikelen', 'zw-ttvgpt' ); ?></span><span class="export-stats-value">' + stats.total_posts + '</span></li>' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Verwerkt', 'zw-ttvgpt' ); ?></span><span class="export-stats-value success">' + stats.processed + '</span></li>' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Overgeslagen', 'zw-ttvgpt' ); ?></span><span class="export-stats-value">' + stats.skipped + '</span></li>' +
					'<li><span class="export-stats-label"><?php esc_html_e( 'Fouten', 'zw-ttvgpt' ); ?></span><span class="export-stats-value' + (stats.errors > 0 ? ' error' : '') + '">' + stats.errors + '</span></li>' +
					'</ul>' +
					'</div>';
			}

			function generateFileInfoHTML(fileInfo) {
				return '<div class="file-info">' +
					'<h4><?php esc_html_e( 'Geëxporteerd bestand', 'zw-ttvgpt' ); ?></h4>' +
					'<p><strong><?php esc_html_e( 'Bestandsnaam:', 'zw-ttvgpt' ); ?></strong> <a href="' + fileInfo.file_url + '" target="_blank">' + fileInfo.filename + '</a></p>' +
					'<p><strong><?php esc_html_e( 'Records:', 'zw-ttvgpt' ); ?></strong> ' + fileInfo.line_count + '</p>' +
					'<p><strong><?php esc_html_e( 'Bestandsgrootte:', 'zw-ttvgpt' ); ?></strong> ' + Math.round(fileInfo.file_size / 1024) + ' KB</p>' +
					'</div>';
			}
			<?php // phpcs:enable Generic.Files.LineLength.TooLong ?>
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
			<h2><?php esc_html_e( 'Training data exporteren', 'zw-ttvgpt' ); ?></h2>
			<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string must not be split ?>
			<p><?php esc_html_e( 'Exporteer door AI gegenereerde en menselijk bewerkte berichten als JSONL-bestand voor DPO-finetuning. Het bestand gebruikt exact dezelfde systeemprompt en contentformattering als de productieplugin.', 'zw-ttvgpt' ); ?></p>
			
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
				<?php esc_html_e( 'Training data exporteren', 'zw-ttvgpt' ); ?>
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
			<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string must not be split ?>
			<p><?php esc_html_e( 'Nadat je het training data bestand hebt geëxporteerd, kun je het uploaden naar OpenAI en een fine-tuning job aanmaken:', 'zw-ttvgpt' ); ?></p>
			
			<ol>
				<li>
					<strong><?php esc_html_e( 'Upload naar OpenAI:', 'zw-ttvgpt' ); ?></strong>
					<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string with link ?>
					<p><?php esc_html_e( 'Ga naar het', 'zw-ttvgpt' ); ?> <a href="https://platform.openai.com/finetune" target="_blank">OpenAI-platform</a> <?php esc_html_e( 'en upload het geëxporteerde JSONL-bestand.', 'zw-ttvgpt' ); ?></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Maak een fine-tuning job aan:', 'zw-ttvgpt' ); ?></strong>
					<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string must not be split ?>
					<p><?php esc_html_e( 'Selecteer DPO (Direct Preference Optimization) als trainingsmethode en kies een basismodel (aanbevolen: gpt-4.1-mini).', 'zw-ttvgpt' ); ?></p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Gebruik het fine-tuned model:', 'zw-ttvgpt' ); ?></strong>
					<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string must not be split ?>
					<p><?php esc_html_e( 'Wanneer de training is voltooid, kun je het fine-tuned model gebruiken door de modelnaam bij te werken in de plugin-instellingen.', 'zw-ttvgpt' ); ?></p>
				</li>
			</ol>
			
			<div class="notice notice-info inline">
				<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string must not be split ?>
				<p><strong><?php esc_html_e( 'Let op:', 'zw-ttvgpt' ); ?></strong> <?php esc_html_e( 'DPO fine-tuning vereist zowel "preferred" als "non-preferred" responses. Alleen berichten waar AI-output door mensen is bewerkt komen in aanmerking voor export.', 'zw-ttvgpt' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request for exporting training data
	 *
	 * @return never
	 */
	public function handle_export_ajax(): never {
		// Nonce is verified in validate_ajax_request() method.
		$this->validate_ajax_request( 'zw_ttvgpt_fine_tuning_nonce', Constants::REQUIRED_CAPABILITY );

		// Get filters from POST data.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
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
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Generate training data.
		$this->logger->debug( 'Export training data requested with filters: ' . wp_json_encode( $filters ) );
		$result = $this->export->generate_training_data( $filters );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Export to JSONL file.
		$export_result = $this->export->export_to_jsonl( $result['data'] );

		if ( is_wp_error( $export_result ) ) {
			wp_send_json_error( array( 'message' => $export_result->get_error_message() ) );
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