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
 */
class TTVGPTAdminMenu {
	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize admin menu and register WordPress hooks
	 *
	 * @param TTVGPTLogger $logger Logger instance for debugging
	 */
	public function __construct( TTVGPTLogger $logger ) {
		$this->logger = $logger;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add plugin settings page to WordPress admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'ZW Tekst TV GPT Instellingen', 'zw-ttvgpt' ),
			__( 'ZW Tekst TV GPT', 'zw-ttvgpt' ),
			TTVGPTConstants::REQUIRED_CAPABILITY,
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			array( new TTVGPTSettingsPage( $this->logger ), 'render' )
		);

		add_management_page(
			__( 'Tekst TV GPT Audit', 'zw-ttvgpt' ),
			__( 'Tekst TV GPT Audit', 'zw-ttvgpt' ),
			TTVGPTConstants::REQUIRED_CAPABILITY,
			'zw-ttvgpt-audit',
			array( new TTVGPTAuditPage( $this->logger ), 'render' )
		);
	}

	/**
	 * Load CSS and JavaScript assets on post edit screens
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || TTVGPTConstants::SUPPORTED_POST_TYPE !== $screen->post_type ) {
			return;
		}

		$version = ZW_TTVGPT_VERSION . ( TTVGPTSettingsManager::is_debug_mode() ? '.' . time() : '' );

		wp_enqueue_style( 'zw-ttvgpt-admin', ZW_TTVGPT_URL . 'assets/admin.css', array(), $version );
		wp_enqueue_script( 'zw-ttvgpt-admin', ZW_TTVGPT_URL . 'assets/admin.js', array( 'jquery' ), $version, true );

		wp_localize_script(
			'zw-ttvgpt-admin',
			'zwTTVGPT',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'zw_ttvgpt_nonce' ),
				'acfFields'      => TTVGPTHelper::get_acf_field_ids(),
				'animationDelay' => array(
					'min'   => 20,
					'max'   => 50,
					'space' => 30,
				),
				'timeouts'       => array( 'successMessage' => 3000 ),
				'strings'        => array(
					'generating'      => __( 'Genereren', 'zw-ttvgpt' ),
					'error'           => __( 'Er is een fout opgetreden', 'zw-ttvgpt' ),
					'success'         => __( 'Samenvatting gegenereerd', 'zw-ttvgpt' ),
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
			)
		);
	}
}
