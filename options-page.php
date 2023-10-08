<?php

class TekstTVGPT_OptionsPage {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function add_plugin_page() {
        add_options_page(
            'Tekst TV GPT Settings',
            'Tekst TV GPT',
            'manage_options',
            'ttvgpt-setting-admin',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Tekst TV GPT Settings</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('ttvgpt_option_group');
                do_settings_sections('ttvgpt-setting-admin');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'ttvgpt_option_group',
            'ttvgpt_api_key',
            array($this, 'sanitize')
        );

        register_setting(
            'ttvgpt_option_group',
            'ttvgpt_word_limit',
            array($this, 'sanitize')
        );

        register_setting(
            'ttvgpt_option_group',
            'ttvgpt_model',
            array($this, 'sanitize')
        );

        add_settings_section(
            'setting_section_id',
            'Settings',
            array($this, 'print_section_info'),
            'ttvgpt-setting-admin'
        );

        add_settings_field(
            'api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'ttvgpt-setting-admin',
            'setting_section_id'
        );

        add_settings_field(
            'word_limit',
            'Word Limit',
            array($this, 'word_limit_callback'),
            'ttvgpt-setting-admin',
            'setting_section_id'
        );

        add_settings_field(
            'model',
            'Model',
            array($this, 'model_callback'),
            'ttvgpt-setting-admin',
            'setting_section_id'
        );
    }

    public function sanitize($input) {
        return sanitize_text_field($input);
    }

    public function print_section_info() {
        print('Enter your settings below:');
    }

    public function api_key_callback() {
        printf(
            '<input type="text" id="api_key" name="ttvgpt_api_key" value="%s" style="width: 300px;" />',
            esc_attr(get_option('ttvgpt_api_key'))
        );
    }

    public function word_limit_callback() {
        printf(
            '<input type="number" id="word_limit" name="ttvgpt_word_limit" value="%s" min="1" />',
            esc_attr(get_option('ttvgpt_word_limit', 100))
        );
    }

    public function model_callback() {
        printf(
            '<input type="text" id="model" name="ttvgpt_model" value="%s" style="width: 300px;" placeholder="gpt-4" />',
            esc_attr(get_option('ttvgpt_model', 'gpt-4'))
        );
    }
}

if (is_admin()) {
    new TekstTVGPT_OptionsPage();
}
