<?php

class TekstTVGPT_OptionsPage {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function add_plugin_page() {
        add_options_page(
            'Tekst TV GPT Settings', // page_title
            'Tekst TV GPT', // menu_title
            'manage_options', // capability
            'ttvgpt-setting-admin', // menu_slug
            array($this, 'create_admin_page') // function
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
            'ttvgpt_option_group', // option_group
            'ttvgpt_api_key', // option_name
            array($this, 'sanitize') // sanitize_callback
        );

        register_setting(
            'ttvgpt_option_group', // option_group
            'ttvgpt_word_limit', // option_name
            array($this, 'sanitize') // sanitize_callback
        );

        add_settings_section(
            'setting_section_id', // id
            'Settings', // title
            array($this, 'print_section_info'), // callback
            'ttvgpt-setting-admin' // page
        );

        add_settings_field(
            'api_key', // id
            'API Key', // title
            array($this, 'api_key_callback'), // callback
            'ttvgpt-setting-admin', // page
            'setting_section_id' // section
        );

        add_settings_field(
            'word_limit', 
            'Word Limit', 
            array($this, 'word_limit_callback'), 
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
}

if (is_admin()) {
    new TekstTVGPT_OptionsPage();
}
