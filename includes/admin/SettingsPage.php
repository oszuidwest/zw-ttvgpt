<?php
/**
 * Settings Page class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ZW_TTVGPT_Core\Constants;
use ZW_TTVGPT_Core\Helper;
use ZW_TTVGPT_Core\Logger;
use ZW_TTVGPT_Core\SettingsManager;

/**
 * Settings Page class
 *
 * Handles settings page rendering and form processing
 */
class SettingsPage {
	/**
	 * Initialize settings page and register WordPress hooks
	 *
	 * @param Logger $logger Logger instance for debugging.
	 */
	public function __construct( private readonly Logger $logger ) {
		add_action( 'admin_init', $this->register_settings( ... ) );
	}

	/**
	 * Generate settings field name attribute
	 *
	 * @param string $field_key Field key within settings array.
	 * @return string Escaped name attribute value.
	 */
	private function get_field_name( string $field_key ): string {
		return esc_attr( Constants::SETTINGS_OPTION_NAME . '[' . $field_key . ']' );
	}

	/**
	 * Register all plugin settings, sections, and fields with WordPress
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Constants::SETTINGS_GROUP,
			Constants::SETTINGS_OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'zw_ttvgpt_api_section',
			__( 'API-configuratie', 'zw-ttvgpt' ),
			array( $this, 'render_api_section' ),
			Constants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'api_key',
			__( 'OpenAI API-sleutel', 'zw-ttvgpt' ),
			array( $this, 'render_api_key_field' ),
			Constants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_api_section'
		);

		add_settings_field(
			'model',
			__( 'Model', 'zw-ttvgpt' ),
			array( $this, 'render_model_field' ),
			Constants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_api_section'
		);

		add_settings_section(
			'zw_ttvgpt_summary_section',
			__( 'Samenvatting-instellingen', 'zw-ttvgpt' ),
			array( $this, 'render_summary_section' ),
			Constants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'word_limit',
			__( 'Woordlimiet', 'zw-ttvgpt' ),
			array( $this, 'render_word_limit_field' ),
			Constants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_summary_section'
		);

		add_settings_field(
			'system_prompt',
			__( 'Prompt', 'zw-ttvgpt' ),
			array( $this, 'render_system_prompt_field' ),
			Constants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_summary_section'
		);

		add_settings_section(
			'zw_ttvgpt_debug_section',
			__( 'Debug-opties', 'zw-ttvgpt' ),
			array( $this, 'render_debug_section' ),
			Constants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug-modus', 'zw-ttvgpt' ),
			array( $this, 'render_debug_mode_field' ),
			Constants::SETTINGS_PAGE_SLUG,
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
				settings_fields( Constants::SETTINGS_GROUP );
				do_settings_sections( Constants::SETTINGS_PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render API section description
	 *
	 * @return void
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
		$api_key    = SettingsManager::get_api_key();
		$field_name = $this->get_field_name( 'api_key' );
		?>
		<input type="password"
				id="zw_ttvgpt_api_key"
				name="<?php echo esc_attr( $field_name ); ?>"
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
		$current_model = SettingsManager::get_model();
		$field_name    = $this->get_field_name( 'model' );
		?>
		<input type="text"
				id="zw_ttvgpt_model"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $current_model ); ?>"
				class="regular-text"
				placeholder="gpt-5.1" />
		<p class="description">
			<?php esc_html_e( 'Aanbevolen: gpt-5.1 (beste kwaliteit). Alternatieven: gpt-4.1, gpt-4.1-mini, gpt-4.1-nano', 'zw-ttvgpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render word limit field
	 *
	 * @return void
	 */
	public function render_word_limit_field(): void {
		$word_limit = SettingsManager::get_word_limit();
		$field_name = $this->get_field_name( 'word_limit' );
		?>
		<input type="number"
				id="zw_ttvgpt_word_limit"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( (string) $word_limit ); ?>"
				min="<?php echo esc_attr( (string) Constants::MIN_WORD_LIMIT ); ?>"
				max="<?php echo esc_attr( (string) Constants::MAX_WORD_LIMIT ); ?>"
				step="<?php echo esc_attr( (string) Constants::WORD_LIMIT_STEP ); ?>" />
		<p class="description">
			<?php esc_html_e( 'Streef naar dit aantal woorden (50-500)', 'zw-ttvgpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Render system prompt field
	 *
	 * @return void
	 */
	public function render_system_prompt_field(): void {
		$system_prompt = SettingsManager::get_system_prompt();
		$field_name    = $this->get_field_name( 'system_prompt' );
		?>
		<textarea id="zw_ttvgpt_system_prompt"
				name="<?php echo esc_attr( $field_name ); ?>"
				rows="6"
				class="large-text code"><?php echo esc_textarea( $system_prompt ); ?></textarea>
		<p class="description">
			<?php
			/* translators: %d is a placeholder for the word limit value */
			esc_html_e( 'De instructies voor het AI-model. Gebruik %d als placeholder voor de woordlimiet.', 'zw-ttvgpt' );
			?>
			<br>
			<small style="color: #666;">
			<?php esc_html_e( 'Wijzig alleen als je weet wat je doet. Standaard is geoptimaliseerd voor Nederlandse samenvattingen.', 'zw-ttvgpt' ); ?>
			</small>
		</p>
		<?php
	}

	/**
	 * Render debug mode field
	 *
	 * @return void
	 */
	public function render_debug_mode_field(): void {
		$debug_mode = SettingsManager::is_debug_mode();
		$field_name = $this->get_field_name( 'debug_mode' );
		?>
		<label for="zw_ttvgpt_debug_mode">
			<input type="checkbox"
					id="zw_ttvgpt_debug_mode"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="1"
					<?php checked( $debug_mode ); ?> />
			<?php esc_html_e( 'Debug-logging inschakelen', 'zw-ttvgpt' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Logt uitgebreide technische details en API-verzoeken naar de PHP error log en browser console', 'zw-ttvgpt' ); ?>
			<br>
			<small style="color: #666;">
			<?php esc_html_e( 'Alleen nodig bij problemen. Schakel uit in productie.', 'zw-ttvgpt' ); ?>
			</small>
		</p>
		<?php
	}

	/**
	 * Sanitize and validate all plugin settings before saving
	 *
	 * @param array $input Raw input data from settings form.
	 * @return array Sanitized settings array.
	 *
	 * @phpstan-param array<string, mixed> $input
	 * @phpstan-return PluginSettings
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// API Key.
		if ( isset( $input['api_key'] ) ) {
			$api_key = sanitize_text_field( $input['api_key'] );
			if ( ! empty( $api_key ) && ! Helper::is_valid_api_key( $api_key ) ) {
				add_settings_error(
					Constants::SETTINGS_OPTION_NAME,
					'invalid_api_key',
					__( 'API-sleutel moet beginnen met "sk-"', 'zw-ttvgpt' )
				);
			}
			$sanitized['api_key'] = $api_key;
		}

		// Model.
		if ( isset( $input['model'] ) ) {
			$model              = sanitize_text_field( $input['model'] );
			$sanitized['model'] = $model;
		}

		// Word limit.
		if ( isset( $input['word_limit'] ) ) {
			$word_limit              = absint( $input['word_limit'] );
			$sanitized['word_limit'] = $word_limit;
		}

		// System prompt.
		if ( isset( $input['system_prompt'] ) ) {
			$system_prompt = sanitize_textarea_field( $input['system_prompt'] );
			if ( empty( $system_prompt ) ) {
				$system_prompt = Constants::DEFAULT_SYSTEM_PROMPT;
				add_settings_error(
					Constants::SETTINGS_OPTION_NAME,
					'empty_system_prompt',
					__( 'Systeem prompt mag niet leeg zijn. Standaard waarde hersteld.', 'zw-ttvgpt' ),
					'warning'
				);
			}
			$sanitized['system_prompt'] = $system_prompt;
		}

		// Debug mode.
		$sanitized['debug_mode'] = ! empty( $input['debug_mode'] );

		$this->logger->debug( 'Settings updated', $sanitized );

		return $sanitized;
	}
}