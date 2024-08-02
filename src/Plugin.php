<?php

namespace ZuidWest\TekstTVGPT;

class Plugin
{
    private $api_key;
    private $word_limit = 100;
    private $model;

    public function __construct()
    {
        $this->api_key = get_option('ttvgpt_api_key', '');
        $this->word_limit = get_option('ttvgpt_word_limit', 100);
        $this->model = get_option('ttvgpt_model', 'gpt-4');

        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'generate_summary_button'));
        add_action('wp_ajax_generate_summary', array($this, 'generate_summary_ajax'));
    }

    public function enqueue_scripts($hook)
    {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_script('ttvgpt', plugin_dir_url(__FILE__) . '../ttvgpt.js', array('jquery'), '0.5', true);
        wp_localize_script('ttvgpt', 'ttvgpt_ajax_vars', array(
            'nonce' => wp_create_nonce('ttvgpt-ajax-nonce')
        ));
    }

    public function generate_summary_button()
    {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const textarea = document.querySelector('#acf-field_5f21a06d22c58');
                if (textarea) {
                    const button = document.createElement('button');
                    button.textContent = 'Genereer';
                    button.className = 'generate-summary-button button button-secondary';
                    button.style.marginTop = '1em';
                    button.onclick = function(e) {
                        e.preventDefault();
                        if (!button.classList.contains('disabled')) {
                            generateSummary();
                        } else {
                            e.preventDefault();
                        }
                    };

                    textarea.parentElement.appendChild(button);
                }
            });
        </script>
        <?php
    }

    public function generate_summary_ajax()
    {
        check_ajax_referer('ttvgpt-ajax-nonce', '_ajax_nonce');

        if (isset($_POST['content'])) {
            $content = sanitize_text_field(wp_unslash($_POST['content']));
            $summary = $this->generate_gpt_summary($content);

            header('Content-Type: text/plain; charset=utf-8');
            echo $summary;
        }

        wp_die();
    }

    private function generate_gpt_summary($content)
    {
        if (str_word_count($content) < 100) {
            return 'Te weinig woorden om een bericht te maken. Er zijn er minimaal 100 nodig.';
        }

        if (empty($this->api_key)) {
            return 'API Key niet ingevuld. Kan geen bericht genereren.';
        }

        $endpoint_url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'max_tokens' => 2048,
            'model' => $this->model,
            'temperature' => 0.8,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Please summarize the following news article in a clear and concise manner that is easy to understand for a general audience. Use short sentences. Do it in Dutch. Ignore everything in the article that's not a Dutch word. Parse HTML. Never output English words. Use maximal " . $this->word_limit . ' words.'
                ],
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];

        $response = wp_remote_post($endpoint_url, [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['choices'][0]['message']['content'])) {
            $summary = $result['choices'][0]['message']['content'];
            return trim($summary);
        } else {
            return 'Er ging iets mis bij het maken van het bericht.';
        }
    }
}

add_action('admin_head', function () {
    echo '<style>
        .acf-field-66ad2a3105371 {
            display: none;
        }
    </style>';
});
