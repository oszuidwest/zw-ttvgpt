<?php
// Load WordPress environment
require_once('../wp-load.php');

// Get the current year and month from the query string, defaulting to the most recent month with posts
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Get all months with relevant posts for pagination
global $wpdb;
$months_with_posts = $wpdb->get_results("
    SELECT DISTINCT YEAR(p.post_date) AS year, MONTH(p.post_date) AS month
    FROM {$wpdb->prefix}posts p
    JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
    JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id
    WHERE pm1.meta_key = 'post_in_kabelkrant' 
      AND pm1.meta_value = '1'
      AND pm2.meta_key = 'post_kabelkrant_content_gpt'
      AND pm2.meta_value != ''
      AND p.post_status = 'publish'
    ORDER BY year DESC, month DESC
");

if (empty($_GET['year']) || empty($_GET['month'])) {
    if (!empty($months_with_posts)) {
        $most_recent_month = $months_with_posts[0];
        wp_redirect("?year={$most_recent_month->year}&month={$most_recent_month->month}");
        exit;
    } else {
        echo "<p>No posts found.</p>";
        exit;
    }
}

// Fetch posts for the selected month (only published posts)
$posts = $wpdb->get_results($wpdb->prepare("
    SELECT p.ID, p.post_date, p.post_title, p.post_author, pm2.meta_value AS ai_content_gpt, pm3.meta_value AS human_content
    FROM {$wpdb->prefix}posts p
    JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
    JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id
    JOIN {$wpdb->prefix}postmeta pm3 ON p.ID = pm3.post_id
    WHERE pm1.meta_key = 'post_in_kabelkrant'
      AND pm1.meta_value = '1'
      AND pm2.meta_key = 'post_kabelkrant_content_gpt'
      AND pm3.meta_key = 'post_kabelkrant_content'
      AND p.post_status = 'publish'
      AND YEAR(p.post_date) = %d
      AND MONTH(p.post_date) = %d
    ORDER BY p.post_date DESC
", $year, $month));

// Initialize counters for each category
$counts = ['fully_human_written' => 0, 'ai_written_not_edited' => 0, 'ai_written_edited' => 0];

// Function to strip everything before and including " - "
function strip_before_dash($content) {
    if (strpos($content, ' - ') !== false) {
        return trim(substr($content, strpos($content, ' - ') + 3));
    }
    return $content; // If " - " is not found, return the original content
}

// Function to generate word-by-word diff with Longest Common Subsequence (LCS)
function generate_word_diff($old, $new) {
    $old_words = explode(' ', trim($old));
    $new_words = explode(' ', trim($new));

    // Find the Longest Common Subsequence (LCS)
    $lengths = array_fill(0, count($old_words) + 1, array_fill(0, count($new_words) + 1, 0));

    for ($i = 0; $i < count($old_words); $i++) {
        for ($j = 0; $j < count($new_words); $j++) {
            if ($old_words[$i] === $new_words[$j]) {
                $lengths[$i + 1][$j + 1] = $lengths[$i][$j] + 1;
            } else {
                $lengths[$i + 1][$j + 1] = max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }
    }

    $lcs = [];
    for ($i = count($old_words), $j = count($new_words); $i > 0 && $j > 0;) {
        if ($lengths[$i][$j] === $lengths[$i - 1][$j]) {
            $i--;
        } elseif ($lengths[$i][$j] === $lengths[$i][$j - 1]) {
            $j--;
        } else {
            array_unshift($lcs, $old_words[$i - 1]);
            $i--;
            $j--;
        }
    }

    // Generate diff output
    $diff_before = '';
    $diff_after = '';
    $i_old = $i_new = $i_lcs = 0;

    while ($i_old < count($old_words) || $i_new < count($new_words)) {
        if ($i_lcs < count($lcs) && $old_words[$i_old] === $lcs[$i_lcs] && $new_words[$i_new] === $lcs[$i_lcs]) {
            // Match found in LCS, so these words are unchanged
            $diff_before .= "{$old_words[$i_old]} ";
            $diff_after .= "{$new_words[$i_new]} ";
            $i_old++;
            $i_new++;
            $i_lcs++;
        } else {
            // Differences found
            if ($i_old < count($old_words) && ($i_lcs >= count($lcs) || $old_words[$i_old] !== $lcs[$i_lcs])) {
                $diff_before .= "<del class='text-red-500 line-through'>{$old_words[$i_old]}</del> ";
                $i_old++;
            }
            if ($i_new < count($new_words) && ($i_lcs >= count($lcs) || $new_words[$i_new] !== $lcs[$i_lcs])) {
                $diff_after .= "<ins class='text-green-600 bg-green-100'>{$new_words[$i_new]}</ins> ";
                $i_new++;
            }
        }
    }

    return [
        'before' => trim($diff_before),
        'after' => trim($diff_after)
    ];
}

// Preprocess posts and count each category
foreach ($posts as $post) {
    $ai_content = strip_before_dash(trim($post->ai_content_gpt));
    $human_content = strip_before_dash(trim($post->human_content));

    if (empty($ai_content)) {
        // This post is fully human-written
        $counts['fully_human_written']++;
    } elseif ($ai_content === $human_content) {
        // This post was AI-written but not edited by a human
        $counts['ai_written_not_edited']++;
    } else {
        // This post was AI-written and edited by a human
        $counts['ai_written_edited']++;
    }
}

// Display the dashboard
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tekst TV GPT Dashboard</title>
    <meta name="robots" content="noindex">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<div class="dashboard-container max-w-5xl mx-auto p-8">

    <!-- Stats Overview -->
    <div class="stats-overview grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <div class="stat-card bg-white p-6 rounded-lg shadow">
            <h2 class="text-4xl font-bold text-center">{$counts['fully_human_written']}</h2>
            <p class="text-center text-gray-600 mt-2">Fully Human Written</p>
        </div>
        <div class="stat-card bg-white p-6 rounded-lg shadow">
            <h2 class="text-4xl font-bold text-center">{$counts['ai_written_not_edited']}</h2>
            <p class="text-center text-gray-600 mt-2">AI Written, Not Edited</p>
        </div>
        <div class="stat-card bg-white p-6 rounded-lg shadow">
            <h2 class="text-4xl font-bold text-center">{$counts['ai_written_edited']}</h2>
            <p class="text-center text-gray-600 mt-2">AI Written, Edited</p>
        </div>
    </div>

    <!-- Post List -->
    <div class="post-list grid grid-cols-1 gap-6">
HTML;

// Display posts as cards in the post list
foreach ($posts as $post) {
    $ai_content = strip_before_dash(trim($post->ai_content_gpt));
    $human_content = strip_before_dash(trim($post->human_content));
    $author_name = get_the_author_meta('display_name', $post->post_author);

    // Get the last user who edited the post
    $last_user_id = get_post_meta($post->ID, '_edit_last', true);
    $last_editor = $last_user_id ? get_the_author_meta('display_name', $last_user_id) : 'Unknown';

    // Determine the status of the post
    if (empty($ai_content)) {
        $status_label = 'Fully Human Written';
        $status_class = 'bg-blue-100 text-blue-800';
    } elseif ($ai_content === $human_content) {
        $status_label = 'AI Written, Not Edited';
        $status_class = 'bg-red-100 text-red-800';
    } else {
        $status_label = 'AI Written, Edited';
        $status_class = 'bg-yellow-100 text-yellow-800';
    }

    // Display the card
    echo "<div class='card bg-white p-6 rounded-lg shadow'>
        <h3 class='card-title text-2xl font-bold mb-2'>" . esc_html($post->post_title) . "</h3>
        <div class='card-meta text-gray-500 text-sm mb-4'>Published on: " . date('Y-m-d', strtotime($post->post_date)) . " | Author: {$author_name} | Last edit: {$last_editor}</div>
        <span class='card-status text-xs font-bold uppercase tracking-tight inline-block px-3 py-1 rounded-full {$status_class}'>{$status_label}</span>";

    // Show the content if fully human-written or AI-written but not edited
    if ($status_label === 'Fully Human Written' || $status_label === 'AI Written, Not Edited') {
        echo "<div class='content-container mt-6'>
            <h4 class='font-bold mb-2'>Content:</h4>
            <p>{$human_content}</p>
        </div>";
    }

    // Show word-by-word diff if the post is AI Written and Edited
    if ($status_label === 'AI Written, Edited') {
        $diff = generate_word_diff($ai_content, $human_content);

        echo "<div class='diff-container mt-6'>
            <h4 class='font-bold mb-2'>Before:</h4>
            <p>{$diff['before']}</p>
            <h4 class='font-bold mt-4 mb-2'>After:</h4>
            <p>{$diff['after']}</p>
        </div>";
    }

    echo "</div>";
}

echo '</div>';

// Pagination logic
echo '<div class="pagination-links text-center mt-12">';
if (($previous_month = $months_with_posts[array_search([$year, $month], array_map(function($m) { return [$m->year, $m->month]; }, $months_with_posts)) - 1] ?? null)) {
    echo "<a href='?year={$previous_month->year}&month={$previous_month->month}' class='px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600'>Previous Month</a>";
}
if (($next_month = $months_with_posts[array_search([$year, $month], array_map(function($m) { return [$m->year, $m->month]; }, $months_with_posts)) + 1] ?? null)) {
    echo "<a href='?year={$next_month->year}&month={$next_month->month}' class='ml-4 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600'>Next Month</a>";
}
echo '</div>';

echo '</div>';

echo '</body></html>';
?>
