<?php
/*
Plugin Name: YouTube to Blog with Cohere AI
Description: Converts YouTube videos into SEO-optimized blog posts using Cohere AI.
Version: 1.0
Author: Your Name
*/

defined('ABSPATH') || exit;

class YTToBlogCohere {
    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
        add_action('admin_post_generate_blog_post', [$this, 'handle_form']);
    }

    public function admin_menu() {
        add_menu_page("YT to Blog AI", "YT to Blog AI", "manage_options", "yt-to-blog-ai", [$this, 'plugin_page'], null, 99);
    }

    public function load_assets() {
        wp_enqueue_style('ytb-style', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('ytb-script', plugin_dir_url(__FILE__) . 'js/script.js', ['jquery'], null, true);
    }

    public function plugin_page() {
        ?>
        <div class="wrap">
            <h1>YouTube to Blog using Cohere AI</h1>
            <form id="ytb-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="generate_blog_post" />
                <label>YouTube URL:</label><br>
                <input type="text" name="youtube_url" required style="width: 50%;" /><br><br>
                <label>Cohere API Key:</label><br>
                <input type="text" name="cohere_api_key" required style="width: 50%;" /><br><br>
                <input type="submit" class="button button-primary" value="Generate Blog Post" />
                <div id="progress-bar"><div class="bar"></div></div>
            </form>
        </div>
        <?php
    }

    public function handle_form() {
        if (!current_user_can('manage_options')) wp_die("Unauthorized");

        $video_url = sanitize_text_field($_POST['youtube_url']);
        $cohere_api_key = sanitize_text_field($_POST['cohere_api_key']);

        preg_match('#(?:v=|youtu\.be/)([a-zA-Z0-9_-]{11})#', $video_url, $matches);
        $video_id = $matches[1] ?? null;

        if (!$video_id || !$cohere_api_key) {
            wp_redirect(admin_url("admin.php?page=yt-to-blog-ai&error=1"));
            exit;
        }

        // Title from YouTube
        $api_url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=$video_id&format=json";
        $response = wp_remote_get($api_url);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $title = sanitize_text_field($data['title'] ?? 'YouTube Blog');

        // Generate blog via Cohere AI
        $prompt = "Write a 1000+ word SEO-optimized blog post for this YouTube video titled: \"$title\". 
Include the focus keyword \"$title\" at least 20 times. Make it readable, human-like and HTML friendly.";

        $ai_response = wp_remote_post('https://api.cohere.ai/v1/generate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $cohere_api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'command-r-plus',
                'prompt' => $prompt,
                'max_tokens' => 3000,
                'temperature' => 0.7
            ])
        ]);

        $body = json_decode(wp_remote_retrieve_body($ai_response), true);
        $description = wp_kses_post($body['generations'][0]['text'] ?? '');

        // Get Thumbnail
        $thumb = "https://img.youtube.com/vi/$video_id/hqdefault.jpg";

        // Create Post
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => "<img src='$thumb' alt='$title' /><br><br>" . $description,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'tags_input'   => [$title],
            'post_category'=> [1]
        ]);

        wp_redirect(admin_url("admin.php?page=yt-to-blog-ai&success=1"));
        exit;
    }
}

new YTToBlogCohere();
