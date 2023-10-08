<?php
/*
Plugin Name: Tekst TV GPT
Description: Maakt met OpenAI's GPT een samenvatting van een artikel voor op Tekst TV en plaatst dit in het juiste ACF-veld
Version: 0.1
Author: Raymon Mens
*/

// Add hardcoded variables for API key and word limit
$hardcoded_api_key = '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';
$hardcoded_word_limit = 100;

function asg_enqueue_scripts($hook) {
    global $hardcoded_api_key, $hardcoded_word_limit;

    if ('post.php' != $hook && 'post-new.php' != $hook) {
        return;
    }

    wp_enqueue_script('article-summary-generator', plugin_dir_url(__FILE__) . 'asg.js', array('jquery'), '1.0', true);
    wp_localize_script('article-summary-generator', 'asg_ajax_vars', array(
        'nonce' => wp_create_nonce('asg-ajax-nonce')
    ));
}

add_action('admin_enqueue_scripts', 'asg_enqueue_scripts');

function asg_generate_summary_button() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('#acf-field_5f21a06d22c58');
            if (textarea) {
                const button = document.createElement('button');
                button.textContent = 'Genereer';
                button.className = 'generate-summary-button button button-secundary';
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

add_action('admin_footer', 'asg_generate_summary_button');

function asg_generate_summary_ajax() {
    check_ajax_referer('asg-ajax-nonce', '_ajax_nonce');

    if (isset($_POST['content'])) {
        $content = $_POST['content'];
        $summary = asg_generate_summary_using_gpt35($content);
        echo $summary;
    }

    wp_die();
}

add_action('wp_ajax_generate_summary', 'asg_generate_summary_ajax');

function asg_generate_summary_using_gpt35($content) {
    global $hardcoded_api_key, $hardcoded_word_limit;

    // Check if the word count in the content is less than 30
    if (str_word_count($content) < 30) {
        return "Te weinig woorden om een bericht te maken. Ik heb er minimaal 30 nodig...";
    }

    $api_key = $hardcoded_api_key;
    $word_limit = $hardcoded_word_limit;

    // Check if the API key is empty and return the specified message
    if (empty($api_key)) {
        return 'API Key niet ingevuld. Kan geen bericht genreren.';
    }

    $endpoint_url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'max_tokens' => 256,
        'model' => 'ft:gpt-3.5-turbo-0613:personal::871wJ7cX',
        'messages' => [
            [
                'role' => 'system',
                'content' => "Please summarize the following news article in a clear and concise manner that is easy to understand for a general audience. Use short sentences. Do it in Dutch. Ignore everything in the article that's not a Dutch word. Parse HTML. Never output English words. Use maximal " . $word_limit . " words."
            ],
            [
                'role' => 'user',
                'content' => $content
            ]
        ]
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];

    $ch = curl_init($endpoint_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $summary = $result['choices'][0]['message']['content'];

    return trim($summary);
}
