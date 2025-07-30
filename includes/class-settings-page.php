<?php
/**
 * Settings Page class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Settings Page class
 *
 * Handles settings page rendering and form processing
 */
class TTVGPTSettingsPage {
	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize settings page and register WordPress hooks
	 *
	 * @param TTVGPTLogger $logger Logger instance for debugging
	 */
	public function __construct( TTVGPTLogger $logger ) {
		$this->logger = $logger;

		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register all plugin settings, sections, and fields with WordPress
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			TTVGPTConstants::SETTINGS_GROUP,
			TTVGPTConstants::SETTINGS_OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'zw_ttvgpt_api_section',
			__( 'API-configuratie', 'zw-ttvgpt' ),
			array( $this, 'render_api_section' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'api_key',
			__( 'OpenAI API-sleutel', 'zw-ttvgpt' ),
			array( $this, 'render_api_key_field' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_api_section'
		);

		add_settings_field(
			'model',
			__( 'Model', 'zw-ttvgpt' ),
			array( $this, 'render_model_field' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_api_section'
		);

		add_settings_section(
			'zw_ttvgpt_summary_section',
			__( 'Samenvatting-instellingen', 'zw-ttvgpt' ),
			array( $this, 'render_summary_section' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'word_limit',
			__( 'Woordlimiet', 'zw-ttvgpt' ),
			array( $this, 'render_word_limit_field' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_summary_section'
		);

		add_settings_section(
			'zw_ttvgpt_debug_section',
			__( 'Debug-opties', 'zw-ttvgpt' ),
			array( $this, 'render_debug_section' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug-modus', 'zw-ttvgpt' ),
			array( $this, 'render_debug_mode_field' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_debug_section'
		);
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( TTVGPTConstants::SETTINGS_GROUP );
				do_settings_sections( TTVGPTConstants::SETTINGS_PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render section descriptions
	 */
	public function render_api_section(): void {
		echo '<p>' . esc_html__( 'Vul je OpenAI gegevens in om te beginnen.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Render summary section description
	 *
	 * @return void
	 */
	public function render_summary_section(): void {
		echo '<p>' . esc_html__( 'Bepaal hoe je samenvattingen eruit zien.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Render debug section description
	 *
	 * @return void
	 */
	public function render_debug_section(): void {
		echo '<p>' . esc_html__( 'Voor ontwikkelaars en probleemoplossing.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Render API key field
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$api_key = TTVGPTSettingsManager::get_api_key();
		?>
		<input type="password" 
				id="zw_ttvgpt_api_key" 
				name="<?php echo esc_attr( TTVGPTConstants::SETTINGS_OPTION_NAME ); ?>[api_key]" 
				value="<?php echo esc_attr( $api_key ); ?>" 
				class="regular-text" 
				autocomplete="off" />
		<p class="description">
			<?php esc_html_e( 'Begint met "sk-" (te vinden op platform.openai.com)', 'zw-ttvgpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render model field
	 *
	 * @return void
	 */
	public function render_model_field(): void {
		$current_model = TTVGPTSettingsManager::get_model();
		?>
		<input type="text" 
				id="zw_ttvgpt_model" 
				name="<?php echo esc_attr( TTVGPTConstants::SETTINGS_OPTION_NAME ); ?>[model]" 
				value="<?php echo esc_attr( $current_model ); ?>"
				class="regular-text"
				placeholder="gpt-4.1-mini" />
		<p class="description">
			<?php esc_html_e( 'Aanbevolen: gpt-4.1-mini (snel & goedkoop)', 'zw-ttvgpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render word limit field
	 *
	 * @return void
	 */
	public function render_word_limit_field(): void {
		$word_limit = TTVGPTSettingsManager::get_word_limit();
		?>
		<input type="number" 
				id="zw_ttvgpt_word_limit" 
				name="<?php echo esc_attr( TTVGPTConstants::SETTINGS_OPTION_NAME ); ?>[word_limit]" 
				value="<?php echo esc_attr( (string) $word_limit ); ?>" 
				min="<?php echo esc_attr( (string) TTVGPTConstants::MIN_WORD_LIMIT ); ?>" 
				max="<?php echo esc_attr( (string) TTVGPTConstants::MAX_WORD_LIMIT ); ?>" 
				step="<?php echo esc_attr( (string) TTVGPTConstants::WORD_LIMIT_STEP ); ?>" />
		<p class="description">
			<?php esc_html_e( 'Streef naar dit aantal woorden (50-500)', 'zw-ttvgpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render debug mode field
	 *
	 * @return void
	 */
	public function render_debug_mode_field(): void {
		$debug_mode = TTVGPTSettingsManager::is_debug_mode();
		?>
		<label for="zw_ttvgpt_debug_mode">
			<input type="checkbox" 
					id="zw_ttvgpt_debug_mode" 
					name="<?php echo esc_attr( TTVGPTConstants::SETTINGS_OPTION_NAME ); ?>[debug_mode]" 
					value="1" 
					<?php checked( $debug_mode ); ?> />
			<?php esc_html_e( 'Debug-logging inschakelen', 'zw-ttvgpt' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Logt technische details naar PHP error log', 'zw-ttvgpt' ); ?>
			<br>
			<small style="color: #666;">
			<?php esc_html_e( 'Alleen nodig bij problemen', 'zw-ttvgpt' ); ?>
			</small>
		</p>
		<?php
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// API Key
		if ( isset( $input['api_key'] ) ) {
			$api_key = sanitize_text_field( $input['api_key'] );
			if ( ! empty( $api_key ) && ! TTVGPTHelper::is_valid_api_key( $api_key ) ) {
				add_settings_error(
					TTVGPTConstants::SETTINGS_OPTION_NAME,
					'invalid_api_key',
					__( 'API-sleutel moet beginnen met "sk-"', 'zw-ttvgpt' )
				);
			}
			$sanitized['api_key'] = $api_key;
		}

		// Model
		if ( isset( $input['model'] ) ) {
			$model              = sanitize_text_field( $input['model'] );
			$sanitized['model'] = $model;
		}

		// Word limit
		if ( isset( $input['word_limit'] ) ) {
			$word_limit              = absint( $input['word_limit'] );
			$sanitized['word_limit'] = $word_limit;
		}

		// Debug mode
		$sanitized['debug_mode'] = ! empty( $input['debug_mode'] );

		$this->logger->debug( 'Settings updated', $sanitized );

		return $sanitized;
	}
}