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
     * Summary generator instance
     *
     * @var TTVGPTSummaryGenerator
     */
    private TTVGPTSummaryGenerator $generator;

    /**
     * Logger instance
     *
     * @var TTVGPTLogger
     */
    private TTVGPTLogger $logger;

    /**
     * Settings page slug
     *
     * @var string
     */
    // Settings page slug is now in TTVGPTConstants

    /**
     * Constructor
     *
     * @param TTVGPTSummaryGenerator $generator Summary generator instance
     * @param TTVGPTLogger           $logger    Logger instance
     */
    public function __construct(TTVGPTSummaryGenerator $generator, TTVGPTLogger $logger) {
        $this->generator = $generator;
        $this->logger = $logger;

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void {
        add_options_page(
            __('ZW Tekst TV GPT Instellingen', 'zw-ttvgpt'),
            __('ZW Tekst TV GPT', 'zw-ttvgpt'),
            TTVGPTConstants::REQUIRED_CAPABILITY,
            TTVGPTConstants::SETTINGS_PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            TTVGPTConstants::SETTINGS_GROUP,
            TTVGPTConstants::SETTINGS_OPTION_NAME,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );

        // API Settings Section
        add_settings_section(
            'zw_ttvgpt_api_section',
            __('API Instellingen', 'zw-ttvgpt'),
            array($this, 'render_api_section'),
            TTVGPTConstants::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'api_key',
            __('OpenAI API Key', 'zw-ttvgpt'),
            array($this, 'render_api_key_field'),
            TTVGPTConstants::SETTINGS_PAGE_SLUG,
            'zw_ttvgpt_api_section'
        );

        add_settings_field(
            'model',
            __('Model', 'zw-ttvgpt'),
            array($this, 'render_model_field'),
            TTVGPTConstants::SETTINGS_PAGE_SLUG,
            'zw_ttvgpt_api_section'
        );

        // Summary Settings Section
        add_settings_section(
            'zw_ttvgpt_summary_section',
            __('Samenvatting Instellingen', 'zw-ttvgpt'),
            array($this, 'render_summary_section'),
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'word_limit',
            __('Woordlimiet', 'zw-ttvgpt'),
            array($this, 'render_word_limit_field'),
            TTVGPTConstants::SETTINGS_PAGE_SLUG,
            'zw_ttvgpt_summary_section'
        );

        // Debug Settings Section
        add_settings_section(
            'zw_ttvgpt_debug_section',
            __('Debug Instellingen', 'zw-ttvgpt'),
            array($this, 'render_debug_section'),
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'debug_mode',
            __('Debug Modus', 'zw-ttvgpt'),
            array($this, 'render_debug_mode_field'),
            TTVGPTConstants::SETTINGS_PAGE_SLUG,
            'zw_ttvgpt_debug_section'
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on post edit screens
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        // Check if we're on a supported post type
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== TTVGPTConstants::SUPPORTED_POST_TYPE) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'zw-ttvgpt-admin',
            ZW_TTVGPT_URL . 'assets/admin.css',
            array(),
            ZW_TTVGPT_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'zw-ttvgpt-admin',
            ZW_TTVGPT_URL . 'assets/admin.js',
            array('jquery'),
            ZW_TTVGPT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('zw-ttvgpt-admin', 'zwTTVGPT', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zw_ttvgpt_nonce'),
            'acfFields' => TTVGPTHelper::get_acf_field_ids(),
            'animationDelay' => array(
                'min' => TTVGPTConstants::ANIMATION_DELAY_MIN,
                'max' => TTVGPTConstants::ANIMATION_DELAY_MAX,
                'space' => TTVGPTConstants::ANIMATION_DELAY_SPACE
            ),
            'timeouts' => array(
                'successMessage' => TTVGPTConstants::SUCCESS_MESSAGE_TIMEOUT
            ),
            'strings' => array(
                'generating' => __('Samenvatting genereren...', 'zw-ttvgpt'),
                'error' => __('Er is een fout opgetreden', 'zw-ttvgpt'),
                'success' => __('Samenvatting gegenereerd', 'zw-ttvgpt'),
                'buttonText' => __('Genereer Samenvatting', 'zw-ttvgpt')
            )
        ));
    }

    /**
     * Add meta box to post edit screen
     *
     * @return void
     */
    public function add_meta_box(): void {
        add_meta_box(
            'zw-ttvgpt-generator',
            __('Tekst TV Samenvatting Generator', 'zw-ttvgpt'),
            array($this, 'render_meta_box'),
            TTVGPTConstants::SUPPORTED_POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render meta box content
     *
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void {
        ?>
        <div id="zw-ttvgpt-meta-box">
            <p class="description">
                <?php esc_html_e('Genereer automatisch een samenvatting voor Tekst TV op basis van de inhoud van dit artikel.', 'zw-ttvgpt'); ?>
            </p>
            
            <div id="zw-ttvgpt-status" style="display: none;"></div>
            
            <p>
                <button type="button" id="zw-ttvgpt-generate" class="button button-primary" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Genereer Samenvatting', 'zw-ttvgpt'); ?>
                </button>
            </p>
            
            <div id="zw-ttvgpt-result" style="display: none; margin-top: 10px;">
                <label for="zw-ttvgpt-summary"><?php esc_html_e('Gegenereerde samenvatting:', 'zw-ttvgpt'); ?></label>
                <textarea id="zw-ttvgpt-summary" class="widefat" rows="5" readonly></textarea>
                <p class="description">
                    <span id="zw-ttvgpt-word-count"></span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields(TTVGPTConstants::SETTINGS_GROUP);
                do_settings_sections(self::SETTINGS_PAGE_SLUG);
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
        echo '<p>' . esc_html__('Configureer de OpenAI API instellingen.', 'zw-ttvgpt') . '</p>';
    }

    /**
     * Render summary section description
     *
     * @return void
     */
    public function render_summary_section(): void {
        echo '<p>' . esc_html__('Pas de instellingen voor samenvattingen aan.', 'zw-ttvgpt') . '</p>';
    }

    /**
     * Render debug section description
     *
     * @return void
     */
    public function render_debug_section(): void {
        echo '<p>' . esc_html__('Debug opties voor probleemoplossing.', 'zw-ttvgpt') . '</p>';
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
               name="<?php echo esc_attr(TTVGPTConstants::SETTINGS_OPTION_NAME); ?>[api_key]" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" 
               autocomplete="off" />
        <p class="description">
            <?php esc_html_e('Je OpenAI API key. Deze begint met "sk-".', 'zw-ttvgpt'); ?>
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
               name="<?php echo esc_attr(TTVGPTConstants::SETTINGS_OPTION_NAME); ?>[model]" 
               value="<?php echo esc_attr($current_model); ?>"
               class="regular-text"
               placeholder="gpt-4o" />
        <p class="description">
            <?php esc_html_e('Voer de naam van het OpenAI model in (bijv. gpt-4o, gpt-4o-mini, gpt-3.5-turbo).', 'zw-ttvgpt'); ?>
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
               name="<?php echo esc_attr(TTVGPTConstants::SETTINGS_OPTION_NAME); ?>[word_limit]" 
               value="<?php echo esc_attr($word_limit); ?>" 
               min="<?php echo esc_attr(TTVGPTConstants::MIN_WORD_LIMIT); ?>" 
               max="<?php echo esc_attr(TTVGPTConstants::MAX_WORD_LIMIT); ?>" 
               step="<?php echo esc_attr(TTVGPTConstants::WORD_LIMIT_STEP); ?>" />
        <p class="description">
            <?php esc_html_e('Maximum aantal woorden voor de samenvatting. Let op: GPT modellen zijn niet altijd precies met woordlimieten.', 'zw-ttvgpt'); ?>
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
                   name="<?php echo esc_attr(TTVGPTConstants::SETTINGS_OPTION_NAME); ?>[debug_mode]" 
                   value="1" 
                   <?php checked($debug_mode); ?> />
            <?php esc_html_e('Schakel debug logging in', 'zw-ttvgpt'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Schakel debug logging in. Debug berichten worden naar de PHP error log geschreven.', 'zw-ttvgpt'); ?>
            <br>
            <small style="color: #666;">
            <?php esc_html_e('Errors worden altijd gelogd, debug berichten alleen met deze optie aan.', 'zw-ttvgpt'); ?>
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
    public function sanitize_settings(array $input): array {
        $sanitized = array();

        // API Key
        if (isset($input['api_key'])) {
            $api_key = sanitize_text_field($input['api_key']);
            if (!empty($api_key) && !TTVGPTHelper::is_valid_api_key($api_key)) {
                add_settings_error(
                    TTVGPTConstants::SETTINGS_OPTION_NAME,
                    'invalid_api_key',
                    __('API key moet beginnen met "sk-"', 'zw-ttvgpt')
                );
            }
            $sanitized['api_key'] = $api_key;
        }

        // Model
        if (isset($input['model'])) {
            $model = sanitize_text_field($input['model']);
            // No validation - allow any model name
            $sanitized['model'] = $model;
        }

        // Word limit
        if (isset($input['word_limit'])) {
            $word_limit = absint($input['word_limit']);
            // Word limit validation is now handled by the HTML input field
            $sanitized['word_limit'] = $word_limit;
        }

        // Debug mode
        $sanitized['debug_mode'] = !empty($input['debug_mode']);

        $this->logger->debug('Settings updated', $sanitized);

        return $sanitized;
    }
}