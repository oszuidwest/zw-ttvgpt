<?php
/**
 * Admin Menu class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Admin Menu class
 *
 * Handles WordPress admin menu registration and asset loading
 *
 * @package ZW_TTVGPT
 */
class TTVGPTAdminMenu {
	/**
	 * Initialize admin menu and register WordPress hooks
	 *
	 * @param TTVGPTLogger         $logger           Logger instance for debugging.
	 * @param TTVGPTFineTuningPage $fine_tuning_page Fine tuning page instance.
	 */
	public function __construct(
		private readonly TTVGPTLogger $logger,
		private readonly TTVGPTFineTuningPage $fine_tuning_page
	) {
		add_action( 'admin_menu', $this->add_admin_menu( ... ) );
		add_action( 'admin_enqueue_scripts', $this->enqueue_admin_assets( ... ) );
	}

	/**
	 * Add plugin settings page to WordPress admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'Tekst TV GPT Instellingen', 'zw-ttvgpt' ),
			__( 'Tekst TV GPT', 'zw-ttvgpt' ),
			TTVGPTConstants::REQUIRED_CAPABILITY,
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			array( new TTVGPTSettingsPage( $this->logger ), 'render' )
		);

		add_management_page(
			__( 'Tekst TV Audit', 'zw-ttvgpt' ),
			__( 'Tekst TV Audit', 'zw-ttvgpt' ),
			TTVGPTConstants::REQUIRED_CAPABILITY,
			'zw-ttvgpt-audit',
			array( new TTVGPTAuditPage(), 'render' )
		);

		add_management_page(
			__( 'Tekst TV Training Data', 'zw-ttvgpt' ),
			__( 'Tekst TV Training', 'zw-ttvgpt' ),
			TTVGPTConstants::REQUIRED_CAPABILITY,
			'zw-ttvgpt-fine-tuning',
			array( $this->fine_tuning_page, 'render' )
		);
	}

	/**
	 * Load CSS and JavaScript assets on post edit screens and audit page
	 *
	 * Uses wp_enqueue_script_module (WordPress 6.5+) for ES modules support.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$version = TTVGPTHelper::get_asset_version();

		// Enqueue audit CSS on audit page
		if ( 'tools_page_zw-ttvgpt-audit' === $hook ) {
			wp_enqueue_style( 'zw-ttvgpt-audit', ZW_TTVGPT_URL . 'assets/audit.css', array(), $version );
			return;
		}

		// Enqueue fine tuning page assets
		if ( 'tools_page_zw-ttvgpt-fine-tuning' === $hook ) {
			wp_enqueue_style( 'zw-ttvgpt-fine-tuning', ZW_TTVGPT_URL . 'assets/admin.css', array(), $version );
			return;
		}

		// Enqueue admin assets on post edit screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || TTVGPTConstants::SUPPORTED_POST_TYPE !== $screen->post_type ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style( 'zw-ttvgpt-admin', ZW_TTVGPT_URL . 'assets/admin.css', array(), $version );

		// Inline script data before module loads (wp_enqueue_script_module doesn't support wp_localize_script)
		$inline_data = array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'zw_ttvgpt_nonce' ),
			'acfFields'      => TTVGPTHelper::get_acf_field_ids(),
			'debugMode'      => TTVGPTSettingsManager::is_debug_mode(),
			'animationDelay' => array(
				'min'   => 20,
				'max'   => 50,
				'space' => 30,
			),
			'timeouts'       => array( 'successMessage' => 3000 ),
			'strings'        => array(
				'generating'      => __( 'Genereren...', 'zw-ttvgpt' ),
				'error'           => __( 'Fout opgetreden', 'zw-ttvgpt' ),
				'success'         => __( 'Klaar!', 'zw-ttvgpt' ),
				'buttonText'      => __( 'Genereer', 'zw-ttvgpt' ),
				'loadingMessages' => array(
					__( '🤔 Even nadenken...', 'zw-ttvgpt' ),
					__( '📰 Artikel aan het lezen...', 'zw-ttvgpt' ),
					__( '✨ AI magie aan het werk...', 'zw-ttvgpt' ),
					__( '🔍 De essentie aan het vinden...', 'zw-ttvgpt' ),
					__( '📝 Aan het samenvatten...', 'zw-ttvgpt' ),
					__( '🎯 Belangrijkste punten selecteren...', 'zw-ttvgpt' ),
					__( '🧠 Neuronen aan het vuren...', 'zw-ttvgpt' ),
					__( '🚀 Tekst TV klaar maken...', 'zw-ttvgpt' ),
					__( '🎨 Tekst aan het polijsten...', 'zw-ttvgpt' ),
					__( '🌟 Briljante samenvatting maken...', 'zw-ttvgpt' ),
				),
			),
		);

		// Add inline script before module (wp_add_inline_script doesn't work with modules)
		wp_register_script( 'zw-ttvgpt-data', false, array(), $version, false );
		wp_enqueue_script( 'zw-ttvgpt-data' );
		wp_add_inline_script(
			'zw-ttvgpt-data',
			'window.zwTTVGPT = ' . wp_json_encode( $inline_data ) . ';',
			'before'
		);

		// Enqueue ES module (WordPress 6.5+)
		wp_enqueue_script_module( 'zw-ttvgpt-admin', ZW_TTVGPT_URL . 'assets/admin.mjs', array(), $version );
	}
}
