<?php
/**
 * Admin class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Admin class
 *
 * Handles admin interface and settings
 */
class TTVGPTAdmin {
	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize admin interface and register WordPress hooks
	 *
	 * @param TTVGPTLogger $logger Logger instance for debugging
	 */
	public function __construct( TTVGPTLogger $logger ) {
		$this->logger = $logger;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
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
			array( $this, 'render_settings_page' )
		);

		add_management_page(
			__( 'Tekst TV GPT Audit', 'zw-ttvgpt' ),
			__( 'Tekst TV GPT Audit', 'zw-ttvgpt' ),
			TTVGPTConstants::REQUIRED_CAPABILITY,
			'zw-ttvgpt-audit',
			array( $this, 'render_audit_page' )
		);
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
			__( 'API Instellingen', 'zw-ttvgpt' ),
			array( $this, 'render_api_section' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'api_key',
			__( 'OpenAI API Key', 'zw-ttvgpt' ),
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
			__( 'Samenvatting Instellingen', 'zw-ttvgpt' ),
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
			__( 'Debug Instellingen', 'zw-ttvgpt' ),
			array( $this, 'render_debug_section' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Modus', 'zw-ttvgpt' ),
			array( $this, 'render_debug_mode_field' ),
			TTVGPTConstants::SETTINGS_PAGE_SLUG,
			'zw_ttvgpt_debug_section'
		);
	}

	/**
	 * Load CSS and JavaScript assets on post edit screens with cache busting
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

		$version = ZW_TTVGPT_VERSION;
		if ( TTVGPTSettingsManager::is_debug_mode() ) {
			$version .= '.' . time();
		}

		wp_enqueue_style(
			'zw-ttvgpt-admin',
			ZW_TTVGPT_URL . 'assets/admin.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'zw-ttvgpt-admin',
			ZW_TTVGPT_URL . 'assets/admin.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'zw-ttvgpt-admin',
			'zwTTVGPT',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'zw_ttvgpt_nonce' ),
				'acfFields'      => TTVGPTHelper::get_acf_field_ids(),
				'animationDelay' => array(
					'min'   => TTVGPTConstants::ANIMATION_DELAY_MIN,
					'max'   => TTVGPTConstants::ANIMATION_DELAY_MAX,
					'space' => TTVGPTConstants::ANIMATION_DELAY_SPACE,
				),
				'timeouts'       => array(
					'successMessage' => TTVGPTConstants::SUCCESS_MESSAGE_TIMEOUT,
				),
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
						__( '📍 ZuidWest-stijl toepassen...', 'zw-ttvgpt' ),
						__( '📝 Aan het samenvatten...', 'zw-ttvgpt' ),
						__( '🎯 Belangrijkste punten selecteren...', 'zw-ttvgpt' ),
						__( '🧠 Neuronen aan het vuren...', 'zw-ttvgpt' ),
						__( '💭 In diepe gedachten...', 'zw-ttvgpt' ),
						__( '🚀 Tekst TV klaar maken...', 'zw-ttvgpt' ),
						__( '🎨 Tekst aan het polijsten...', 'zw-ttvgpt' ),
						__( '📺 Voor het scherm optimaliseren...', 'zw-ttvgpt' ),
						__( '🎪 AI kunstjes uithalen...', 'zw-ttvgpt' ),
						__( '✏️ Pepijn zou dit goedkeuren...', 'zw-ttvgpt' ),
						__( '🌟 Briljante samenvatting maken...', 'zw-ttvgpt' ),
						__( '🏃 Snel even de hoofdzaken...', 'zw-ttvgpt' ),
						__( '🎭 Drama eruit, feiten erin...', 'zw-ttvgpt' ),
						__( '🔧 Laatste aanpassingen...', 'zw-ttvgpt' ),
						__( '🎬 Perfecte TV-tekst regisseren...', 'zw-ttvgpt' ),
						__( '🌈 Kleurtje aan het geven...', 'zw-ttvgpt' ),
						__( '🎸 Rock \'n roll met AI...', 'zw-ttvgpt' ),
						__( '🍕 Pizza bestellen... grapje!', 'zw-ttvgpt' ),
						__( '👩‍💼 Anne\'s goedkeuring afwachten...', 'zw-ttvgpt' ),
						__( '🦄 Eenhoorn-krachten activeren...', 'zw-ttvgpt' ),
						__( '🌊 Surfen door de tekst...', 'zw-ttvgpt' ),
						__( '🏰 Naar Bergen op Zoom sturen...', 'zw-ttvgpt' ),
						__( '🎪 Circusact met woorden...', 'zw-ttvgpt' ),
						__( '🚁 Helikopterview nemen...', 'zw-ttvgpt' ),
						__( '🎯 Bullseye raken...', 'zw-ttvgpt' ),
						__( '🎪 De show moet doorgaan...', 'zw-ttvgpt' ),
						__( '🎸 Sweet dreams are made of AI...', 'zw-ttvgpt' ),
						__( '🚂 Volle kracht vooruit...', 'zw-ttvgpt' ),
						__( '🏢 ZuidWest-kwaliteit garanderen...', 'zw-ttvgpt' ),
						__( '🌟 Sterren van de hemel schrijven...', 'zw-ttvgpt' ),
						__( '🎪 Jongleren met woorden...', 'zw-ttvgpt' ),
						__( '🌹 Roosendaal-waardig maken...', 'zw-ttvgpt' ),
						__( '🎯 Precies op het doel...', 'zw-ttvgpt' ),
						__( '🎨 Bob Ross mode: happy little words...', 'zw-ttvgpt' ),
						__( '🎸 Don\'t stop me now, I\'m having AI...', 'zw-ttvgpt' ),
						__( '🚀 Houston, we hebben een samenvatting...', 'zw-ttvgpt' ),
						__( '🎬 Lights, camera, samenvatting!', 'zw-ttvgpt' ),
						__( '🎸 Is dit het echte leven?...', 'zw-ttvgpt' ),
						__( '🎯 In de roos schieten...', 'zw-ttvgpt' ),
						__( '🌟 Twinkle twinkle little AI...', 'zw-ttvgpt' ),
						__( '🎭 To be or not to be... samengevat!', 'zw-ttvgpt' ),
						__( '🚁 Vogelperspectief activeren...', 'zw-ttvgpt' ),
						__( '🎸 Wake me up before you AI-go...', 'zw-ttvgpt' ),
						__( '🌊 Met de stroom mee...', 'zw-ttvgpt' ),
						__( '🎪 Koorddansen met zinnen...', 'zw-ttvgpt' ),
						__( '🎯 Kaarsrecht op het doel af...', 'zw-ttvgpt' ),
						__( '🎬 Take 1: De perfecte samenvatting...', 'zw-ttvgpt' ),
						__( '🌟 Fonkelende formuleringen...', 'zw-ttvgpt' ),
						__( '🎸 We will, we will... samenvatten!', 'zw-ttvgpt' ),
						__( '🎭 Het doek gaat op...', 'zw-ttvgpt' ),
						__( '🎸 Total eclipse of the tekst...', 'zw-ttvgpt' ),
						__( '🌊 Surfen op de informatiegolf...', 'zw-ttvgpt' ),
						__( '✈️ Hoogerheide hoogte bereiken...', 'zw-ttvgpt' ),
						__( '🎪 Salto\'s maken met syllables...', 'zw-ttvgpt' ),
						__( '🎯 Pijl en boog spannen...', 'zw-ttvgpt' ),
						__( '🚀 Warp-snelheid bereikt...', 'zw-ttvgpt' ),
						__( '🎬 And... action!', 'zw-ttvgpt' ),
						__( '✍️ Pepijn-proof maken...', 'zw-ttvgpt' ),
						__( '🌟 Sterrenstof strooien...', 'zw-ttvgpt' ),
						__( '🎸 Another one bites the tekst...', 'zw-ttvgpt' ),
						__( '🎭 Applaus voor de AI...', 'zw-ttvgpt' ),
						__( '🚁 Eagle eye perspectief...', 'zw-ttvgpt' ),
						__( '🌊 Zeilen op zee van tekst...', 'zw-ttvgpt' ),
						__( '🎪 Trapeze-act met taal...', 'zw-ttvgpt' ),
						__( '🎯 Laser-focus aan...', 'zw-ttvgpt' ),
						__( '🚀 Turbo-modus geactiveerd...', 'zw-ttvgpt' ),
						__( '🎬 De Oscar gaat naar...', 'zw-ttvgpt' ),
						__( '🌟 Glitter en glamour toevoegen...', 'zw-ttvgpt' ),
						__( '🎸 Stairway to samenvatting...', 'zw-ttvgpt' ),
						__( '🚁 Vanaf grote hoogte bekijken...', 'zw-ttvgpt' ),
						__( '🌊 Drijven op de datastroom...', 'zw-ttvgpt' ),
						__( '🎪 Clownerie met content...', 'zw-ttvgpt' ),
						__( '🏘️ Rondje Etten-Leur lopen...', 'zw-ttvgpt' ),
						__( '🎯 Target acquired...', 'zw-ttvgpt' ),
						__( '🚀 Hyperdrive inschakelen...', 'zw-ttvgpt' ),
						__( '🎬 Popcorn erbij pakken...', 'zw-ttvgpt' ),
						__( '🌟 Sprankje magie toevoegen...', 'zw-ttvgpt' ),
						__( '🎸 Thunderstruck door AI...', 'zw-ttvgpt' ),
						__( '🎭 Standing ovation voorbereiden...', 'zw-ttvgpt' ),
						__( '🚁 Birdseye bootcamp...', 'zw-ttvgpt' ),
						__( '🌊 Meedobberen op de info-oceaan...', 'zw-ttvgpt' ),
						__( '🎪 Goochelen met grammatica...', 'zw-ttvgpt' ),
						__( '🎯 Bulls-eye loading...', 'zw-ttvgpt' ),
						__( '👨🏻‍🎤 Plaatje aanvragen op Radio Rucphen...', 'zw-ttvgpt' ),
						__( '🚀 Countdown gestart...', 'zw-ttvgpt' ),
						__( '🎬 Silence... AI in actie!', 'zw-ttvgpt' ),
						__( '🌟 Sprankelende resultaten komen eraan...', 'zw-ttvgpt' ),
						__( '🚁 Panorama-modus aan...', 'zw-ttvgpt' ),
						__( '🎪 Balanceren op de betekenis...', 'zw-ttvgpt' ),
						__( '🎯 Doelwit in zicht...', 'zw-ttvgpt' ),
						__( '🚀 Raketwetenschap toepassen...', 'zw-ttvgpt' ),
						__( '🎬 De regisseur zegt: "Cut!"...', 'zw-ttvgpt' ),
						__( '🌟 Sterallures krijgen...', 'zw-ttvgpt' ),
						__( '🎸 Highway to tekst-hell...', 'zw-ttvgpt' ),
						__( '🎭 Bravo! Bravo! Bis!', 'zw-ttvgpt' ),
						__( '🚁 Luchtfoto\'s maken...', 'zw-ttvgpt' ),
						__( '🎸 Eye of the AI-ger...', 'zw-ttvgpt' ),
						__( '🌊 Kitesurfen door content...', 'zw-ttvgpt' ),
						__( '🎪 Vuurspuwen met feiten...', 'zw-ttvgpt' ),
						__( '🙈 Beter dan die meuk op de SLOS maken...', 'zw-ttvgpt' ),
						__( '🎯 Scherpschutter-modus...', 'zw-ttvgpt' ),
						__( '🚀 Versnellers aanzetten...', 'zw-ttvgpt' ),
						__( '🎬 De trailer maken...', 'zw-ttvgpt' ),
						__( '🌟 Glinsteren en glanzen...', 'zw-ttvgpt' ),
						__( '🎸 Smoke on the water... AI on fire!', 'zw-ttvgpt' ),
						__( '🎭 Toegift! Toegift!', 'zw-ttvgpt' ),
						__( '🚁 Helikopter-ouders mode...', 'zw-ttvgpt' ),
						__( '🎸 I want to break free... met AI!', 'zw-ttvgpt' ),
						__( '🌊 Parasailen over paragrafen...', 'zw-ttvgpt' ),
						__( '🎪 Zwaard slikken... of toch niet...', 'zw-ttvgpt' ),
						__( '🎯 Vizier scherp stellen...', 'zw-ttvgpt' ),
						__( '🚀 Booster rockets aan...', 'zw-ttvgpt' ),
						__( '🎬 Behind the scenes kijken...', 'zw-ttvgpt' ),
						__( '🌟 Bling bling toevoegen...', 'zw-ttvgpt' ),
						__( '🎸 Born to be AI\'d...', 'zw-ttvgpt' ),
						__( '🎭 Het publiek wordt wild...', 'zw-ttvgpt' ),
						__( '🚁 Quadcopter-kwaliteit...', 'zw-ttvgpt' ),
						__( '🎸 Under pressure... AI edition...', 'zw-ttvgpt' ),
						__( '🌊 Bodyboarden op bytes...', 'zw-ttvgpt' ),
						__( '🎪 Piramide bouwen met woorden...', 'zw-ttvgpt' ),
						__( '🏘️ Zoals ze in \'t Heike zeggen: alles voor ut jong eh... de samenvatting!', 'zw-ttvgpt' ),
						__( '🎯 Doelgericht denken...', 'zw-ttvgpt' ),
						__( '🚀 Naar de maan en terug...', 'zw-ttvgpt' ),
						__( '🎬 Blooper reel vermijden...', 'zw-ttvgpt' ),
						__( '👔 Eindredactie-waardig maken...', 'zw-ttvgpt' ),
						__( '🌟 Schitteren als een diamant...', 'zw-ttvgpt' ),
						__( '🎸 Whole lotta AI going on...', 'zw-ttvgpt' ),
						__( '🎭 Staande ovatie incoming...', 'zw-ttvgpt' ),
						__( '🚁 Luchtacrobatiek met letters...', 'zw-ttvgpt' ),
						__( '🌊 Waterskiën over woorden...', 'zw-ttvgpt' ),
						__( '🎪 Menselijke piramide van zinnen...', 'zw-ttvgpt' ),
						__( '🎨 Zundert-kunst met zinnen...', 'zw-ttvgpt' ),
						__( '🎯 360 no-scope samenvatting...', 'zw-ttvgpt' ),
						__( '🚀 Interstellaire intelligentie...', 'zw-ttvgpt' ),
						__( '🎬 Post-productie magic...', 'zw-ttvgpt' ),
						__( '🎯 Pepijn checkt de feiten...', 'zw-ttvgpt' ),
						__( '🌟 Fonkelen als vuurwerk...', 'zw-ttvgpt' ),
						__( '🎸 Knockin\' on heaven\'s AI...', 'zw-ttvgpt' ),
						__( '🚫 Niet naar Steenbergen sturen...', 'zw-ttvgpt' ),
						__( '🎭 Encore! Encore!', 'zw-ttvgpt' ),
						__( '🚁 Top Gun modus...', 'zw-ttvgpt' ),
						__( '🎸 Another brick in the AI...', 'zw-ttvgpt' ),
						__( '🌊 Wakeboarden op woorden...', 'zw-ttvgpt' ),
						__( '🎪 Dompteur van de data...', 'zw-ttvgpt' ),
						__( '🎯 Precisiebombardement...', 'zw-ttvgpt' ),
						__( '🚀 Mars-missie van maken...', 'zw-ttvgpt' ),
						__( '🎬 Directors cut klaarmaken...', 'zw-ttvgpt' ),
						__( '🌟 Sterrenstelsel van woorden...', 'zw-ttvgpt' ),
						__( '🎸 AI\'s just wanna have fun...', 'zw-ttvgpt' ),
						__( '📝 Anne\'s rode pen paraat...', 'zw-ttvgpt' ),
						__( '🎭 Het applaus daveren...', 'zw-ttvgpt' ),
						__( '🚁 Maverick-manoeuvres...', 'zw-ttvgpt' ),
					),
				),
			)
		);
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
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
	 * Render API section description
	 *
	 * @return void
	 */
	public function render_api_section(): void {
		echo '<p>' . esc_html__( 'Configureer de OpenAI API instellingen.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Render summary section description
	 *
	 * @return void
	 */
	public function render_summary_section(): void {
		echo '<p>' . esc_html__( 'Pas de instellingen voor samenvattingen aan.', 'zw-ttvgpt' ) . '</p>';
	}

	/**
	 * Render debug section description
	 *
	 * @return void
	 */
	public function render_debug_section(): void {
		echo '<p>' . esc_html__( 'Debug opties voor probleemoplossing.', 'zw-ttvgpt' ) . '</p>';
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
			<?php esc_html_e( 'Je OpenAI API key. Deze begint met "sk-".', 'zw-ttvgpt' ); ?>
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
				placeholder="gpt-4o" />
		<p class="description">
			<?php esc_html_e( 'Voer de naam van het OpenAI model in (bijv. gpt-4o, gpt-4o-mini, gpt-3.5-turbo).', 'zw-ttvgpt' ); ?>
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
			<?php esc_html_e( 'Maximum aantal woorden voor de samenvatting. Let op: GPT modellen zijn niet altijd precies met woordlimieten.', 'zw-ttvgpt' ); ?>
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
			<?php esc_html_e( 'Schakel debug logging in', 'zw-ttvgpt' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Schakel debug logging in. Debug berichten worden naar de PHP error log geschreven.', 'zw-ttvgpt' ); ?>
			<br>
			<small style="color: #666;">
			<?php esc_html_e( 'Errors worden altijd gelogd, debug berichten alleen met deze optie aan.', 'zw-ttvgpt' ); ?>
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
					__( 'API key moet beginnen met "sk-"', 'zw-ttvgpt' )
				);
			}
			$sanitized['api_key'] = $api_key;
		}

		// Model
		if ( isset( $input['model'] ) ) {
			$model = sanitize_text_field( $input['model'] );
			// No validation - allow any model name
			$sanitized['model'] = $model;
		}

		// Word limit
		if ( isset( $input['word_limit'] ) ) {
			$word_limit = absint( $input['word_limit'] );
			// Word limit validation is now handled by the HTML input field
			$sanitized['word_limit'] = $word_limit;
		}

		// Debug mode
		$sanitized['debug_mode'] = ! empty( $input['debug_mode'] );

		$this->logger->debug( 'Settings updated', $sanitized );

		return $sanitized;
	}

	/**
	 * Render audit analysis page
	 *
	 * @return void
	 */
	public function render_audit_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only audit page
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only audit page
		$month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : null;

		if ( ! $year || ! $month ) {
			$most_recent = TTVGPTHelper::get_most_recent_audit_month();
			if ( $most_recent ) {
				$year  = $most_recent['year'];
				$month = $most_recent['month'];
			} else {
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Tekst TV GPT Audit', 'zw-ttvgpt' ); ?></h1>
					<div class="notice notice-info">
						<p><?php esc_html_e( 'Geen posts gevonden voor audit analyse.', 'zw-ttvgpt' ); ?></p>
					</div>
				</div>
				<?php
				return;
			}
		}

		$posts  = TTVGPTHelper::get_audit_posts( $year, $month );
		$counts = array(
			'fully_human_written'   => 0,
			'ai_written_not_edited' => 0,
			'ai_written_edited'     => 0,
		);

		$categorized_posts = array();
		foreach ( $posts as $post ) {
			$analysis = TTVGPTHelper::categorize_audit_post( $post );
			++$counts[ $analysis['status'] ];
			$categorized_posts[] = array_merge( array( 'post' => $post ), $analysis );
		}

		$available_months = TTVGPTHelper::get_audit_months();
		$current_index    = null;
		foreach ( $available_months as $index => $month_data ) {
			if ( $month_data['year'] === $year && $month_data['month'] === $month ) {
				$current_index = (int) $index;
				break;
			}
		}

		$prev_month = null;
		$next_month = null;
		if ( null !== $current_index ) {
			$prev_month = isset( $available_months[ $current_index + 1 ] ) ? $available_months[ $current_index + 1 ] : null;
			$next_month = isset( $available_months[ $current_index - 1 ] ) ? $available_months[ $current_index - 1 ] : null;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tekst TV GPT Audit', 'zw-ttvgpt' ); ?></h1>
			
			<?php $this->render_audit_navigation( $year, $month, $prev_month, $next_month ); ?>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
				<?php
				$labels = array(
					'fully_human_written'   => __( 'Volledig handmatig', 'zw-ttvgpt' ),
					'ai_written_not_edited' => __( 'AI, niet bewerkt', 'zw-ttvgpt' ),
					'ai_written_edited'     => __( 'AI, bewerkt', 'zw-ttvgpt' ),
				);
				$colors = array(
					'fully_human_written'   => '#0073aa',
					'ai_written_not_edited' => '#d63638',
					'ai_written_edited'     => '#dba617',
				);
				foreach ( $counts as $status => $count ) :
					?>
					<div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
						<div style="font-size: 2em; font-weight: bold; color: <?php echo esc_attr( $colors[ $status ] ); ?>;">
							<?php echo esc_html( (string) $count ); ?>
						</div>
						<div style="margin-top: 8px; color: #666;">
							<?php echo esc_html( $labels[ $status ] ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			foreach ( $categorized_posts as $item ) :
				$post          = $item['post'];
				$status        = $item['status'];
				$ai_content    = $item['ai_content'];
				$human_content = $item['human_content'];

				$author      = get_userdata( $post->post_author );
				$edit_last   = get_post_meta( $post->ID, '_edit_last', true );
				$last_editor = is_numeric( $edit_last ) ? get_userdata( (int) $edit_last ) : false;
				?>
				<div style="background: white; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
					<h3 style="margin: 0 0 10px 0;"><?php echo esc_html( (string) get_the_title( $post->ID ) ); ?></h3>
					<div style="color: #666; font-size: 0.9em; margin-bottom: 15px;">
						<?php
						printf(
							/* translators: 1: Date, 2: Author, 3: Last editor */
							esc_html__( 'Gepubliceerd: %1$s | Auteur: %2$s | Laatste bewerking: %3$s', 'zw-ttvgpt' ),
							esc_html( (string) get_the_date( 'Y-m-d', $post->ID ) ),
							esc_html( $author ? $author->display_name : 'Onbekend' ),
							esc_html( $last_editor ? $last_editor->display_name : 'Onbekend' )
						);
						?>
					</div>
					<div style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: white; background: <?php echo esc_attr( $colors[ $status ] ); ?>;">
						<?php echo esc_html( $labels[ $status ] ); ?>
					</div>

					<?php if ( 'ai_written_edited' === $status ) : ?>
						<div style="margin-top: 20px;">
							<?php $diff = TTVGPTHelper::generate_word_diff( $ai_content, $human_content ); ?>
							<h4><?php esc_html_e( 'Voor bewerking:', 'zw-ttvgpt' ); ?></h4>
							<div style="padding: 10px; background: #f9f9f9; border-left: 4px solid #ddd; margin-bottom: 15px;">
								<?php echo wp_kses_post( $diff['before'] ); ?>
							</div>
							<h4><?php esc_html_e( 'Na bewerking:', 'zw-ttvgpt' ); ?></h4>
							<div style="padding: 10px; background: #f9f9f9; border-left: 4px solid #ddd;">
								<?php echo wp_kses_post( $diff['after'] ); ?>
							</div>
						</div>
					<?php else : ?>
						<div style="margin-top: 20px;">
							<h4><?php esc_html_e( 'Content:', 'zw-ttvgpt' ); ?></h4>
							<div style="padding: 10px; background: #f9f9f9; border-left: 4px solid #ddd;">
								<?php echo esc_html( $human_content ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render audit navigation between months
	 *
	 * @param int        $year Current year
	 * @param int        $month Current month
	 * @param array|null $prev_month Previous month data
	 * @param array|null $next_month Next month data
	 * @return void
	 */
	private function render_audit_navigation( int $year, int $month, ?array $prev_month, ?array $next_month ): void {
		?>
		<div style="background: #f1f1f1; padding: 10px 20px; margin: 20px 0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
			<?php if ( $prev_month ) : ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=zw-ttvgpt-audit&year=' . $prev_month['year'] . '&month=' . $prev_month['month'] ) ); ?>" class="button">
					← <?php esc_html_e( 'Vorige maand', 'zw-ttvgpt' ); ?>
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>

			<strong><?php echo esc_html( date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ) ); ?></strong>

			<?php if ( $next_month ) : ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=zw-ttvgpt-audit&year=' . $next_month['year'] . '&month=' . $next_month['month'] ) ); ?>" class="button">
					<?php esc_html_e( 'Volgende maand', 'zw-ttvgpt' ); ?> →
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
		</div>
		<?php
	}
}