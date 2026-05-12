<?php
/**
 * Settings Page class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
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
 * Settings Page class.
 *
 * Handles settings page rendering and form processing.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class SettingsPage {
	/**
	 * Select value used to reveal the legacy fine-tuned model input.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string LEGACY_FINE_TUNED_SELECT_VALUE = 'legacy-fine-tuned';

	/**
	 * Initializes the settings page and registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger Logger instance for debugging.
	 */
	public function __construct( private readonly Logger $logger ) {
		add_action( 'admin_init', $this->register_settings( ... ) );
		add_action( 'admin_init', $this->migrate_invalid_model_setting( ... ) );
	}

	/**
	 * Retrieves the settings field name attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_key Field key within settings array.
	 * @return string Name attribute value.
	 */
	private function get_field_name( string $field_key ): string {
		return Constants::SETTINGS_OPTION_NAME . '[' . $field_key . ']';
	}

	/**
	 * Registers plugin settings with the WordPress Settings API.
	 *
	 * @since 1.0.0
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
	 * Migrates unsupported stored model settings on admin requests.
	 *
	 * @since 1.0.0
	 */
	public function migrate_invalid_model_setting(): void {
		SettingsManager::migrate_invalid_model_if_needed( $this->logger );
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_model_migration_notice(); ?>
			<?php settings_errors(); ?>

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
	 * Renders a one-shot notice when an unsupported stored model has been migrated.
	 *
	 * @since 1.0.0
	 */
	private function render_model_migration_notice(): void {
		$notice = SettingsManager::get_model_migration_notice();
		if ( null === $notice ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: 1: Previous model name, 2: New model name */
					esc_html__( 'Het opgeslagen OpenAI-model "%1$s" wordt niet meer ondersteund en is automatisch vervangen door "%2$s".', 'zw-ttvgpt' ),
					esc_html( $notice['old_model'] ),
					esc_html( $notice['new_model'] )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the API section description.
	 *
	 * @since 1.0.0
	 */
	public function render_api_section(): void {
		echo '<p>' . esc_html__( 'Vul je OpenAI gegevens in om te beginnen.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Renders the summary section description.
	 *
	 * @since 1.0.0
	 */
	public function render_summary_section(): void {
		echo '<p>' . esc_html__( 'Bepaal hoe je samenvattingen eruit zien.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Renders the debug section description.
	 *
	 * @since 1.0.0
	 */
	public function render_debug_section(): void {
		echo '<p>' . esc_html__( 'Voor ontwikkelaars en probleemoplossing.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Renders the API key field.
	 *
	 * @since 1.0.0
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
	 * Renders the model selection field.
	 *
	 * @since 1.0.0
	 */
	public function render_model_field(): void {
		$current_model        = SettingsManager::get_model();
		$field_name           = $this->get_field_name( 'model' );
		$is_legacy_fine_tuned = Constants::is_legacy_fine_tuned_model( $current_model );
		?>
		<select id="zw_ttvgpt_model_select" <?php echo $is_legacy_fine_tuned ? '' : 'name="' . esc_attr( $field_name ) . '"'; ?>>
			<?php foreach ( Constants::SUPPORTED_BASE_MODELS as $model ) : ?>
				<option value="<?php echo esc_attr( $model ); ?>" <?php selected( ! $is_legacy_fine_tuned && $current_model === $model ); ?>>
					<?php echo esc_html( $model ); ?>
				</option>
			<?php endforeach; ?>
			<option value="<?php echo esc_attr( self::LEGACY_FINE_TUNED_SELECT_VALUE ); ?>" <?php selected( $is_legacy_fine_tuned ); ?>>
				<?php esc_html_e( 'Legacy ft:-model...', 'zw-ttvgpt' ); ?>
			</option>
		</select>

		<div id="zw_ttvgpt_legacy_fine_tuned_wrapper" style="margin-top: 8px; <?php echo $is_legacy_fine_tuned ? '' : 'display: none;'; ?>">
			<input type="text"
				id="zw_ttvgpt_legacy_fine_tuned_model"
				<?php echo $is_legacy_fine_tuned ? 'name="' . esc_attr( $field_name ) . '"' : ''; ?>
				value="<?php echo $is_legacy_fine_tuned ? esc_attr( $current_model ) : ''; ?>"
				class="regular-text"
				<?php echo $is_legacy_fine_tuned ? 'required' : ''; ?>
				pattern="<?php echo esc_attr( Constants::LEGACY_FINE_TUNED_MODEL_PATTERN ); ?>"
				title="<?php esc_attr_e( 'Gebruik een bestaande legacy ft:-model-ID uit de GPT-4.1 familie.', 'zw-ttvgpt' ); ?>"
				placeholder="ft:gpt-4.1:org:suffix:id" />
			<p class="description">
				<?php
				esc_html_e(
					'Alleen voor bestaande legacy ft:-model-ID\'s. De plugin ondersteunt geen fine-tuning exports of nieuwe fine-tuning workflows meer.',
					'zw-ttvgpt'
				);
				?>
			</p>
		</div>

		<p class="description">
			<?php esc_html_e( 'Aanbevolen: gpt-5.5 (beste kwaliteit)', 'zw-ttvgpt' ); ?>
		</p>
		<?php
	}

	/**
	 * Returns the inline JS config consumed by the settings ES module.
	 *
	 * @since 1.0.0
	 *
	 * @return array{fieldName: string, legacyOptionValue: string}
	 */
	public function get_legacy_model_toggle_config(): array {
		return array(
			'fieldName'         => $this->get_field_name( 'model' ),
			'legacyOptionValue' => self::LEGACY_FINE_TUNED_SELECT_VALUE,
		);
	}

	/**
	 * Renders the word limit field.
	 *
	 * @since 1.0.0
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
	 * Renders the system prompt field.
	 *
	 * @since 1.0.0
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
	 * Renders the debug mode field.
	 *
	 * @since 1.0.0
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
	 * Gets the appropriate error message for an invalid model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model Invalid model identifier.
	 * @return string Translated error message.
	 */
	private function get_model_error_message( string $model ): string {
		$supported_list = implode( ', ', Constants::SUPPORTED_BASE_MODELS );
		$legacy_list    = implode( ', ', Constants::LEGACY_FINE_TUNED_BASE_MODELS );

		if ( str_starts_with( strtolower( $model ), 'ft:' ) ) {
			/* translators: %s: List of supported legacy fine-tuned base models */
			return sprintf( __( 'Legacy ft:-model moet gebaseerd zijn op: %s', 'zw-ttvgpt' ), $legacy_list );
		}

		return sprintf(
			/* translators: 1: Invalid model name, 2: List of supported models */
			__( 'Model "%1$s" wordt niet ondersteund. Kies uit: %2$s of gebruik een bestaande legacy ft:-model-ID.', 'zw-ttvgpt' ),
			$model,
			$supported_list
		);
	}

	/**
	 * Sanitizes and validates plugin settings.
	 *
	 * @since 1.0.0
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
				$this->logger->error(
					'Invalid OpenAI API key submitted in settings',
					array( 'user_id' => get_current_user_id() )
				);
				add_settings_error(
					Constants::SETTINGS_OPTION_NAME,
					'invalid_api_key',
					__( 'API-sleutel moet beginnen met "sk-". De ongeldige waarde is niet opgeslagen.', 'zw-ttvgpt' )
				);
				$api_key = '';
			}
			$sanitized['api_key'] = $api_key;
		}

		// Model.
		if ( isset( $input['model'] ) ) {
			$model = sanitize_text_field( $input['model'] );
			if ( empty( $model ) ) {
				$this->logger->error(
					'Empty OpenAI model submitted in settings',
					array( 'user_id' => get_current_user_id() )
				);
				add_settings_error(
					Constants::SETTINGS_OPTION_NAME,
					'invalid_model',
					__( 'Model mag niet leeg zijn. Standaardmodel hersteld.', 'zw-ttvgpt' )
				);
				$model = Constants::DEFAULT_MODEL;
			} elseif ( ! Constants::is_supported_model( $model ) ) {
				$error_message = $this->get_model_error_message( $model );
				add_settings_error( Constants::SETTINGS_OPTION_NAME, 'invalid_model', $error_message );
				$this->logger->error(
					'Invalid OpenAI model submitted in settings',
					array(
						'model'   => $model,
						'user_id' => get_current_user_id(),
					)
				);
				$model = Constants::DEFAULT_MODEL;
			}
			$sanitized['model'] = $model;
		}

		// Word limit.
		if ( isset( $input['word_limit'] ) ) {
			$original_word_limit = absint( $input['word_limit'] );
			$word_limit          = $original_word_limit;
			if ( $word_limit < Constants::MIN_WORD_LIMIT || $word_limit > Constants::MAX_WORD_LIMIT ) {
				$word_limit = max( Constants::MIN_WORD_LIMIT, min( $word_limit, Constants::MAX_WORD_LIMIT ) );
				add_settings_error(
					Constants::SETTINGS_OPTION_NAME,
					'invalid_word_limit',
					sprintf(
						/* translators: 1: Original word limit entered, 2: Adjusted word limit, 3: Minimum word limit, 4: Maximum word limit */
						__( 'Woordlimiet %1$d is ongeldig. Waarde aangepast naar %2$d (bereik: %3$d-%4$d).', 'zw-ttvgpt' ),
						$original_word_limit,
						$word_limit,
						Constants::MIN_WORD_LIMIT,
						Constants::MAX_WORD_LIMIT
					),
					'warning'
				);
			}
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

		// Redact api_key before logging; debug.log can be world-readable depending on host config.
		$loggable = $sanitized;
		if ( array_key_exists( 'api_key', $loggable ) ) {
			$loggable['api_key'] = '' === $loggable['api_key'] ? '' : '[redacted]';
		}
		$this->logger->debug( 'Settings updated', $loggable );
		SettingsManager::clear_cache();

		return $sanitized;
	}
}
