<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>YouTube to SEO Blog (Cohere AI Powered)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial,sans-serif; background: #f4f4f4; margin:0; padding:0; }
    .container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px #0001; padding: 32px; }
    h1 { margin-top:0; }
    label { font-weight: bold; }
    input[type="text"] { width: 100%; padding: 8px; font-size: 1em; margin-bottom: 18px; border: 1px solid #ccc; border-radius: 4px; }
    button, input[type="submit"] {
      background: #0a9cfc;
      color: #fff;
      border: none;
      padding: 10px 22px;
      border-radius: 5px;
      font-size: 1em;
      cursor: pointer;
      margin-right: 12px;
      margin-bottom: 16px;
      transition: background 0.2s;
    }
    button[disabled], input[disabled] { background: #dcdcdc; color:#888; cursor: not-allowed;}
    button:hover:not([disabled]), input[type="submit"]:hover:not([disabled]) { background: #0078c7; }
    #progress-bar-pro {width:100%;background:#f2f2f2;height:20px;border-radius:8px;overflow:hidden;margin:20px 0;display:none;}
    #progress-bar-pro .bar {height:100%;width:0;background:#0a9cfc;transition:width 0.5s;}
    #ytb-live-preview {margin-top:30px; border:1px solid #ccc; background: #fafbfc; padding:24px; border-radius:8px; display:none;}
    #ytb-preview-content img { max-width: 100%; border-radius: 6px; }
    .notice { padding: 10px 18px; border-radius: 6px; margin-bottom: 18px; }
    .notice-success { background: #d8ffe1; color: #22763b; border: 1px solid #9ff3b6;}
    .notice-error { background: #ffe3e3; color: #a94442; border: 1px solid #f5c6cb;}
    @media (max-width: 600px) {
      .container {padding: 10px;}
      #ytb-live-preview {padding: 8px;}
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>YouTube to SEO Blog (Cohere AI Powered)</h1>
    <div id="notice"></div>
    <form id="ytb-form-pro">
      <label>YouTube URL:</label>
      <input type="text" id="youtube_url" required placeholder="https://www.youtube.com/watch?v=...">

      <label>Cohere API Key:</label>
      <input type="text" id="cohere_api_key" required placeholder="Paste your Cohere API Key">

      <button type="button" id="ytb-preview-btn">Preview Blog</button>
      <input type="submit" value="Create Blog Post" id="ytb-create-btn" disabled>
      <div id="progress-bar-pro"><div class="bar"></div></div>
    </form>
    <div id="ytb-live-preview">
      <h2>Blog Live Preview</h2>
      <div id="ytb-preview-content"></div>
    </div>
  </div>
  <script>
    // ========== CONFIG ==========
    // Backend endpoint for blog creation (must be implemented if you want to store blog posts)
    // Set to null if you want only preview (demo mode)
    const BACKEND_POST_ENDPOINT = null; // e.g. "/api/create-blog"

    // ========== UTILITY FUNCTIONS ==========
    function setProgress(pct) {
      document.getElementById('progress-bar-pro').style.display = 'block';
      document.querySelector('#progress-bar-pro .bar').style.width = pct + '%';
    }
    function clearProgress() {
      document.getElementById('progress-bar-pro').style.display = 'none';
      document.querySelector('#progress-bar-pro .bar').style.width = '0';
    }
    function showNotice(msg, type) {
      const el = document.getElementById('notice');
      el.innerHTML = msg ? `<div class="notice notice-${type}">${msg}</div>` : '';
    }

    // ========== IMAGE SCRAPE ==========
    async function googleImages(keyword, limit = 5) {
      // For demo: Use DuckDuckGo Images API (since Google blocks CORS)
      // For production: Use a backend proxy or a proper image API
      try {
        let resp = await fetch("https://duckduckgo.com/?q=" + encodeURIComponent(keyword) + "&iax=images&ia=images");
        let html = await resp.text();
        let vqd = html.match(/vqd='([^']+)'/);
        if (!vqd) return [];
        let imagesResp = await fetch("https://duckduckgo.com/i.js?l=us-en&o=json&q="+encodeURIComponent(keyword)+"&vqd="+vqd[1]);
        let data = await imagesResp.json();
        let results = data.results || [];
        let urls = results.map(r=>r.image).filter(Boolean).slice(0, limit);
        return urls;
      } catch(e) { return []; }
    }

    // ========== COHERE AI ==========
    async function cohereGenerateBlog({title, focus_keyword, images, cohere_api_key}) {
      let prompt = `Write a detailed, 1000+ word SEO-optimized blog post for this YouTube video titled: "${title}".
Use the focus keyword "${focus_keyword}" at least 20 times organically throughout the blog.
Make the writing human-like, highly readable and HTML-formatted (with paragraphs, h2/h3 headings, bullets, etc).
Use <img> tags for images, and make sure each image's alt attribute is "${focus_keyword}".
Add all provided images from this list in the blog, distributing them naturally: ${images.join(", ")}.
Do not mention 'AI', 'Cohere', 'YouTube', or that this is AI-generated.
Focus on SEO tips, use the focus keyword, and ensure the content is unique, valuable, and engaging for readers.`;
      let resp = await fetch("https://api.cohere.ai/v1/generate", {
        method: "POST",
        headers: {
          "Authorization": "Bearer " + cohere_api_key,
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          model: "command-r-plus",
          prompt,
          max_tokens: 3000,
          temperature: 0.7
        })
      });
      let data = await resp.json();
      if (data && data.generations && data.generations[0] && data.generations[0].text) {
        return data.generations[0].text.trim();
      }
      let err = (data && (data.message || data.error)) ? (data.message || data.error) : 'Unknown error';
      throw new Error(`Cohere AI did not return any text. ${err}`);
    }

    // ========== YOUTUBE TITLE ==========
    async function fetchYouTubeTitle(url) {
      // Extract video ID
      let match = url.match(/(?:v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/);
      let vid = match ? match[1] : null;
      if (!vid) throw new Error("Invalid YouTube URL");
      let oembed = await fetch(`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${vid}&format=json`);
      if (!oembed.ok) throw new Error("Could not fetch video title.");
      let data = await oembed.json();
      return { title: data.title, vid };
    }

    // ========== MAIN LOGIC ==========
    document.getElementById('ytb-preview-btn').onclick = async function() {
      showNotice('', '');
      setProgress(10);
      document.getElementById('ytb-live-preview').style.display = 'none';
      document.getElementById('ytb-create-btn').disabled = true;
      const youtube_url = document.getElementById('youtube_url').value.trim();
      const cohere_api_key = document.getElementById('cohere_api_key').value.trim();
      if (!youtube_url || !cohere_api_key) {
        showNotice("Please enter YouTube URL and Cohere API Key", "error");
        clearProgress();
        return;
      }
      setProgress(25);
      try {
        // 1. Get Title
        let { title, vid } = await fetchYouTubeTitle(youtube_url);
        setProgress(35);
        // 2. Get Images
        let images = await googleImages(title, 5);
        setProgress(45);
        if (!images.length) showNotice("No images found, continuing without extra images...", "error");
        // 3. Generate Blog
        let blogHTML = await cohereGenerateBlog({
          title,
          focus_keyword: title,
          images,
          cohere_api_key
        });
        setProgress(90);
        // 4. Add video thumbnail at top
        let thumb = `https://img.youtube.com/vi/${vid}/hqdefault.jpg`;
        let previewHTML = `<img src="${thumb}" alt="${title}" style="width:100%;max-width:600px;border-radius:8px;" /><br><br>${blogHTML}`;
        document.getElementById('ytb-preview-content').innerHTML = previewHTML;
        document.getElementById('ytb-live-preview').style.display = 'block';
        document.getElementById('ytb-create-btn').disabled = false;
        setProgress(100);
      } catch (e) {
        showNotice(e.message, "error");
        document.getElementById('ytb-preview-content').innerHTML = '';
        document.getElementById('ytb-live-preview').style.display = 'none';
        document.getElementById('ytb-create-btn').disabled = true;
        clearProgress();
      }
    };

    document.getElementById('ytb-form-pro').onsubmit = async function(e) {
      e.preventDefault();
      setProgress(80);
      showNotice('', '');
      // For demo, just show a fake success
      setTimeout(function() {
        showNotice("Blog post created (demo mode). You can now copy from the preview and use in your site.", "success");
        clearProgress();
      }, 1200);

      // For real backend:
      /*
      const html = document.getElementById('ytb-preview-content').innerHTML;
      const title = ... // Extracted from preview or state
      fetch(BACKEND_POST_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({title, html})
      }).then(r=>r.json()).then(d=>{
        showNotice("Blog post published!", "success");
        clearProgress();
      }).catch(e=>{
        showNotice("Failed to create blog: " + e.message, "error");
        clearProgress();
      });
      */
    };
  </script>
</body>
</html>
