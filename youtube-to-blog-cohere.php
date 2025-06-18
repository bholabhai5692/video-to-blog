<?php
/*
Plugin Name: YouTube to SEO Blog with Cohere AI (Pro)
Description: Converts YouTube videos into 1000+ word SEO-optimized blog posts with images, keyword focus, progress bar, and live preview, using Cohere AI.
Version: 2.1
Author: AI Copilot
*/

defined('ABSPATH') || exit;

class YTToBlogCoherePro {
    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
        add_action('admin_post_generate_blog_post_pro', [$this, 'handle_form']);
        add_action('wp_ajax_ytb_generate_preview', [$this, 'ajax_generate_preview']);
        add_action('wp_ajax_nopriv_ytb_generate_preview', [$this, 'ajax_generate_preview']);
        $this->ensure_assets_exist();
    }

    public function admin_menu() {
        add_menu_page("YT2Blog AI Pro", "YT2Blog AI Pro", "manage_options", "yt-to-blog-ai-pro", [$this, 'plugin_page'], 'dashicons-media-document', 99);
    }

    public function load_assets() {
        wp_enqueue_style('ytb-style-pro', plugin_dir_url(__FILE__) . 'css/style-pro.css');
        wp_enqueue_script('ytb-script-pro', plugin_dir_url(__FILE__) . 'js/script-pro.js', ['jquery'], null, true);
        wp_localize_script('ytb-script-pro', 'ytbProAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ytbproajaxnonce')
        ]);
    }

    public function plugin_page() {
        ?>
        <div class="wrap">
            <h1>YouTube to SEO Blog (Cohere AI Powered)</h1>
            <form id="ytb-form-pro" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="generate_blog_post_pro" />
                <label><b>YouTube URL:</b></label><br>
                <input type="url" name="youtube_url" id="youtube_url" required style="width: 60%;" /><br><br>
                <label><b>Cohere API Key:</b></label><br>
                <input type="text" name="cohere_api_key" id="cohere_api_key" required style="width: 60%;" /><br><br>
                <button type="button" id="ytb-preview-btn" class="button button-secondary">Preview Blog</button>
                <input type="submit" class="button button-primary" value="Create Blog Post" id="ytb-create-btn" disabled />
                <div id="progress-bar-pro"><div class="bar"></div></div>
            </form>
            <div id="ytb-live-preview" style="background: #fff; border: 1px solid #ccc; margin-top: 30px; padding:20px; display:none;">
                <h2>Blog Live Preview</h2>
                <div id="ytb-preview-content"></div>
            </div>
        </div>
        <?php
    }

    public function ajax_generate_preview() {
        check_ajax_referer('ytbproajaxnonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $video_url = sanitize_text_field($_POST['youtube_url']);
        $cohere_api_key = sanitize_text_field($_POST['cohere_api_key']);

        $result = $this->generate_blog_content($video_url, $cohere_api_key, true);

        if ($result['success']) {
            wp_send_json_success(['content' => $result['content']]);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    public function handle_form() {
        if (!current_user_can('manage_options')) wp_die("Unauthorized");

        $video_url = sanitize_text_field($_POST['youtube_url']);
        $cohere_api_key = sanitize_text_field($_POST['cohere_api_key']);

        $result = $this->generate_blog_content($video_url, $cohere_api_key);

        if ($result['success']) {
            $post_id = wp_insert_post([
                'post_title'   => $result['title'],
                'post_content' => $result['content'],
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'tags_input'   => [$result['title']],
                'post_category'=> [1]
            ]);
            wp_redirect(admin_url("admin.php?page=yt-to-blog-ai-pro&success=1"));
        } else {
            wp_redirect(admin_url("admin.php?page=yt-to-blog-ai-pro&error=" . urlencode($result['error'])));
        }
        exit;
    }

    private function generate_blog_content($video_url, $cohere_api_key, $preview_only = false) {
        preg_match('#(?:v=|youtu\.be/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})#', $video_url, $matches);
        $video_id = $matches[1] ?? null;
        if (!$video_id) return ['success'=>false, 'error'=>'Invalid YouTube URL'];

        $api_url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=$video_id&format=json";
        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) return ['success'=>false, 'error'=>'Failed to fetch video title'];

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $title = sanitize_text_field($data['title'] ?? 'YouTube Blog');
        $focus_keyword = $title;

        $image_urls = $this->google_images($focus_keyword, 5);

        $prompt = "Write a detailed, 1000+ word SEO-optimized blog post for this YouTube video titled: \"$title\".
Use the focus keyword \"$focus_keyword\" at least 20 times organically throughout the blog.
Make the writing human-like, highly readable and HTML-formatted (with paragraphs, h2/h3 headings, bullets, etc).
Use <img> tags for images, and make sure each image's alt attribute is \"$focus_keyword\".
Add all provided images from this list in the blog, distributing them naturally: " . implode(", ", $image_urls) . ".
Do not mention 'AI', 'Cohere', 'YouTube', or that this is AI-generated.
Focus on SEO tips, use the focus keyword, and ensure the content is unique, valuable, and engaging for readers.";

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

        if (is_wp_error($ai_response)) {
            return ['success' => false, 'error' => 'Cohere API error: ' . $ai_response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($ai_response), true);
        if (empty($body['generations'][0]['text'])) {
            return ['success'=>false, 'error'=>'Cohere AI did not return any text.'];
        }

        $description = wp_kses_post($body['generations'][0]['text']);
        $thumb = "https://img.youtube.com/vi/$video_id/hqdefault.jpg";
        $content = "<img src='$thumb' alt='$focus_keyword' style='width:100%;max-width:600px;' /><br><br>" . $description;

        return ['success'=>true, 'title'=>$title, 'content'=>$content];
    }

    private function google_images($keyword, $limit = 5) {
        $keyword = urlencode($keyword);
        $url = "https://www.google.com/search?q=$keyword&tbm=isch";
        $response = wp_remote_get($url, ['headers' => ['User-Agent' => 'Mozilla/5.0'], 'timeout' => 10]);

        if (is_wp_error($response)) return [];

        $body = wp_remote_retrieve_body($response);
        preg_match_all('/"ou":"([^"]+)"/', $body, $matches);
        return array_slice(array_unique($matches[1]), 0, $limit);
    }

    private function ensure_assets_exist() {
        $css = __DIR__ . '/css/style-pro.css';
        $js = __DIR__ . '/js/script-pro.js';

        if (!file_exists($css)) {
            wp_mkdir_p(dirname($css));
            file_put_contents($css, <<<EOT
#progress-bar-pro {width:100%;background:#f2f2f2;height:20px;border-radius:8px;overflow:hidden;margin:20px 0;display:none;}
#progress-bar-pro .bar {height:100%;width:0;background:#0a9cfc;transition:width 0.5s;}
#ytb-live-preview {margin-top:30px;}
EOT);
        }

        if (!file_exists($js)) {
            wp_mkdir_p(dirname($js));
            file_put_contents($js, <<<EOT
jQuery(document).ready(function($){
    function setProgress(pct){
        $('#progress-bar-pro').show();
        $('#progress-bar-pro .bar').css('width', pct+'%');
    }
    $('#ytb-preview-btn').click(function(e){
        e.preventDefault();
        setProgress(10);
        $('#ytb-live-preview').hide();
        $('#ytb-create-btn').prop('disabled',true);
        var url = $('#youtube_url').val();
        var key = $('#cohere_api_key').val();
        if(!url || !key) { alert('Please enter YouTube URL and Cohere API Key'); setProgress(0); return; }
        setProgress(30);
        $.ajax({
            url: ytbProAjax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ytb_generate_preview',
                youtube_url: url,
                cohere_api_key: key,
                nonce: ytbProAjax.nonce
            },
            success: function(resp){
                setProgress(100);
                if(resp.success){
                    $('#ytb-preview-content').html(resp.data.content);
                    $('#ytb-live-preview').show();
                    $('#ytb-create-btn').prop('disabled',false);
                } else {
                    $('#ytb-preview-content').html('<b style="color:red;">'+resp.data+'</b>');
                    $('#ytb-live-preview').show();
                    setProgress(0);
                }
            },
            error: function(){
                setProgress(0);
                $('#ytb-preview-content').html('<b style="color:red;">Server error. Try again later.</b>');
                $('#ytb-live-preview').show();
            }
        });
    });
    $('#ytb-form-pro').submit(function(){
        setProgress(80);
        $('#ytb-create-btn').prop('disabled',true);
    });
});
EOT);
        }
    }
}

new YTToBlogCoherePro();
