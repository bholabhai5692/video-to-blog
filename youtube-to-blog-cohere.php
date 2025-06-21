<?php
/*
Plugin Name: YouTube Blogger Connector
Description: Fetch YouTube videos, use Cohere for long-form SEO posts, skip Blogger duplicates, and auto-publish as blog posts on Blogger—all in one WordPress admin SPA.
Version: 3.1
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function() { wp_enqueue_script('jquery'); });

add_action('admin_menu', function() {
    add_menu_page('YouTube Blogger Connector', 'YouTube Blogger', 'manage_options', 'ytbc', 'ytbc_render_admin_page', 'dashicons-video-alt3', 6);
});

function ytbc_render_admin_page() {
    // Load and update the channels file
    $channels_file = __DIR__ . '/channels.json';
    if (!file_exists($channels_file)) file_put_contents($channels_file, '[]');
    $channels_json = file_get_contents($channels_file);
?>
    <div class="wrap">
        <style>
            .ytbc-section { background: #fff; border-radius: 6px; margin-bottom: 24px; padding: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.12); max-width: 1100px; width: 100%; text-align: center; display: none; }
            .ytbc-section.show-section { display: block !important; animation: fadeIn 0.35s;}
            @keyframes fadeIn { 0% {opacity: 0; transform: translateY(20px);} 100% {opacity: 1; transform: translateY(0);} }
            .ytbc-btn-group button { margin-right: 8px; }
            input, textarea, button, select { padding: 10px; margin: 10px 0; font-size: 16px; width: 100%; max-width: 500px; }
            textarea { resize: vertical; min-height: 100px; }
            button { background-color: #4CAF50; color: white; border: none; cursor: pointer; }
            #youtubeResults { margin-top: 18px; font-size: 16px; width: 100%; overflow-x: auto; }
            #youtubeVideoTable { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 15px; background: #fafafa; }
            #youtubeVideoTable th, #youtubeVideoTable td { border: 1px solid #ddd; padding: 7px 8px; text-align: left; vertical-align: top; }
            #youtubeVideoTable th { background: #f0f0f0; color: #222; font-weight: 600; }
            #youtubeVideoTable img { max-width: 110px; border-radius: 4px; }
            .seo-description { background: #eeffee; border: 1px solid #aaffaa; border-radius: 5px; padding: 10px; margin-top: 8px; font-size: 15px; color: #195818; text-align: left; }
            .blogger-post-success { color: #19751b; font-size: 14px; margin-bottom: 2px; }
            .blogger-post-fail { color: #c90000; font-size: 14px; margin-bottom: 2px; }
            .ytbc-pbar-wrap { margin: 22px 0 0 0; max-width: 600px; width: 100%; background: #eee; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px #ccc inset; }
            .ytbc-pbar-inner { height: 24px; background: #4CAF50; width: 0%; border-radius: 10px; color: #fff; transition: width 0.4s; font-weight:bold; line-height:24px; text-align:center; }
            .ytbc-status-gen { color: #888; }
            .ytbc-status-pub { color: #19751b; }
            .ytbc-status-dup { color: #c90000; }
            .ytbc-channels-box { margin: 10px auto 24px auto; background:#fafafa; border-radius:8px; padding:18px 16px 6px 16px; max-width:700px; }
            .ytbc-channels-box label { display:inline-block; margin:0 10px 0 0; font-weight:bold; }
            .ytbc-channels-list { max-width:700px; margin:10px auto 10px auto; background:#f5f5f5; border-radius:6px; padding:12px; }
            .ytbc-channel-row { border-bottom:1px solid #eee; padding:7px 0; display:flex; align-items:center; justify-content:space-between;}
            .ytbc-channel-row:last-child { border-bottom:none; }
            .ytbc-ch-name { font-weight:bold; }
            .ytbc-ch-url { color:#555; font-size:13px; }
            .ytbc-ch-action { margin-left:8px; }
            .ytbc-ch-edit input {width:180px;}
            .ytbc-multi-channels-list {display:flex;flex-wrap:wrap;justify-content:center;gap:8px 24px;}
            .ytbc-multi-channels-list label{display:flex;align-items:center;gap:6px;font-weight:normal;}
        </style>

        <h1>YouTube Blogger Connector</h1>
        <h2 class="nav-tab-wrapper" style="margin-bottom:2em;">
            <a href="#" class="nav-tab ytbc-tab active" data-section="authBox">Authentication</a>
            <a href="#" class="nav-tab ytbc-tab" data-section="channelsBox">Channels</a>
            <a href="#" class="nav-tab ytbc-tab" data-section="youtubeBox">YouTube Video Fetcher</a>
            <a href="#" class="nav-tab ytbc-tab" data-section="generateBox">Post Generator</a>
            <a href="#" class="nav-tab ytbc-tab" data-section="csvBox">CSV Upload</a>
            <a href="#" class="nav-tab ytbc-tab" data-section="manualBox">Manual Post</a>
            <a href="#" class="nav-tab ytbc-tab" data-section="listBox">Published Posts</a>
        </h2>

        <div id="authBox" class="ytbc-section show-section">
            <h2>🔑 Blogger Google Authentication</h2>
            <input type="text" id="clientId" placeholder="OAuth Client ID" value="41926964648-eaen82oa869o7ef67rg1uncdi87e6r1f.apps.googleusercontent.com" />
            <input type="text" id="clientSecret" placeholder="OAuth Client Secret" value="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" />
            <input type="text" id="blogId" placeholder="Blogger Blog ID" value="8122722669281398327"/>
            <input type="text" id="blogApiKey" placeholder="Blogger API Key" value="AIzaSyATqoBHxdsF2du7Xx-3cNTYmNwyzNh4J58"/>
            <input type="text" id="blogUrl" placeholder="Blogger Blog URL" value="https://nexusbooster.blogspot.com/"/>
            <button id="authBtn">🔓 Authenticate with Google</button>
            <div id="authStatus">Not authenticated</div>
        </div>

        <div id="channelsBox" class="ytbc-section">
            <h2>📺 Manage Channels</h2>
            <div class="ytbc-channels-box">
                <form id="ytbc-add-channel-form" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <label>Add Channel:</label>
                    <input type="text" id="ytbc-new-channel-name" placeholder="Channel Name" required>
                    <input type="text" id="ytbc-new-channel-url" placeholder="Channel URL" required>
                    <button type="submit" id="ytbc-add-channel-btn">Add</button>
                </form>
                <div class="ytbc-channels-list" id="ytbc-channels-list"></div>
            </div>
        </div>

        <div id="youtubeBox" class="ytbc-section">
            <h2>📺 YouTube Video Fetcher & Publisher</h2>
            <form id="ytbc-multi-publish-form">
                <div class="ytbc-multi-channels-list" id="ytbc-multi-channels-list"></div>
                <label for="ytbc-multi-count">Number of posts per channel:</label>
                <input type="number" id="ytbc-multi-count" value="1" min="1" max="10" style="max-width:100px;">
                <input type="hidden" id="cohereApiKey" value="vzYojUZfFdEkEEK79ZKlHtKccLLq2IxNRcU8yWRO">
                <button type="submit" id="ytbc-multi-publish-btn">Publish to Selected Channels</button>
            </form>
            <div class="ytbc-pbar-wrap"><div id="ytbc-pbar" class="ytbc-pbar-inner">0%</div></div>
            <div id="youtubeResults"></div>
        </div>
        <!-- The rest remains unchanged -->
        <script>
        let ytbc_channels = <?php echo $channels_json ?>;
        function saveChannelsToServer(list, cb) {
            jQuery.post(ajaxurl, {action:'ytbc_save_channels', channels: JSON.stringify(list)}, cb);
        }
        function refreshChannelsUI() {
            // For Channels tab (edit/delete/list)
            let html = '';
            ytbc_channels.forEach((ch, idx) => {
                html += `<div class="ytbc-channel-row" data-idx="${idx}">
                    <span class="ytbc-ch-name">${ch.name}</span>
                    <span class="ytbc-ch-url">${ch.url}</span>
                    <span>
                        <button type="button" class="ytbc-ch-edit-btn ytbc-ch-action">Edit</button>
                        <button type="button" class="ytbc-ch-delete-btn ytbc-ch-action">Delete</button>
                    </span>
                </div>`;
            });
            if (document.getElementById('ytbc-channels-list')) {
                document.getElementById('ytbc-channels-list').innerHTML = html;
            }
            // For Video Fetcher tab (checkbox multi-select)
            let boxHtml = ytbc_channels.map((ch, idx)=>`<label><input type="checkbox" class="ytbc-multi-ch" value="${idx}">${ch.name}</label>`).join('');
            if (document.getElementById('ytbc-multi-channels-list')) {
                document.getElementById('ytbc-multi-channels-list').innerHTML = boxHtml;
            }
        }
        function setProgressBar(val, max) {
            let pct = Math.round((val/max)*100);
            let el = document.getElementById('ytbc-pbar');
            el.style.width = pct+'%'; el.innerText = pct+'%';
            if (val>=max) { el.innerText = 'Done!'; }
        }
        jQuery(function($){
            $('.ytbc-tab').on('click', function(e){
                e.preventDefault();
                $('.ytbc-tab').removeClass('active');
                $(this).addClass('active');
                var section = $(this).data('section');
                $('.ytbc-section').removeClass('show-section');
                $('#' + section).addClass('show-section');
                refreshChannelsUI();
            });
            // Add channel
            $(document).on('submit', '#ytbc-add-channel-form', function(e){
                e.preventDefault();
                let name = $('#ytbc-new-channel-name').val().trim();
                let url = $('#ytbc-new-channel-url').val().trim();
                if (!name||!url) return;
                ytbc_channels.push({name, url});
                saveChannelsToServer(ytbc_channels, function(){
                    refreshChannelsUI();
                    $('#ytbc-new-channel-name').val('');
                    $('#ytbc-new-channel-url').val('');
                });
            });
            // Edit/Delete channel
            $(document).on('click', '.ytbc-ch-delete-btn', function(){
                let idx = $(this).closest('.ytbc-channel-row').data('idx');
                ytbc_channels.splice(idx,1);
                saveChannelsToServer(ytbc_channels, function(){ refreshChannelsUI(); });
            });
            $(document).on('click', '.ytbc-ch-edit-btn', function(){
                let row = $(this).closest('.ytbc-channel-row');
                let idx = row.data('idx');
                let ch = ytbc_channels[idx];
                if (row.find('.ytbc-ch-edit').length) return;
                row.html(`<span class="ytbc-ch-edit"><input type="text" value="${ch.name}" id="ytbc-edit-name-${idx}"></span>
                          <span class="ytbc-ch-edit"><input type="text" value="${ch.url}" id="ytbc-edit-url-${idx}"></span>
                          <span><button type="button" class="ytbc-ch-save-btn ytbc-ch-action">Save</button>
                          <button type="button" class="ytbc-ch-cancel-btn ytbc-ch-action">Cancel</button></span>`);
                row.find('.ytbc-ch-save-btn').on('click', function(){
                    ytbc_channels[idx].name = $(`#ytbc-edit-name-${idx}`).val();
                    ytbc_channels[idx].url = $(`#ytbc-edit-url-${idx}`).val();
                    saveChannelsToServer(ytbc_channels, function(){ refreshChannelsUI(); });
                });
                row.find('.ytbc-ch-cancel-btn').on('click', function(){ refreshChannelsUI(); });
            });
            refreshChannelsUI();
        });

        // AJAX endpoint for PHP to update file
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        </script>
        <script>
        // Blogger OAuth2 logic
        window._ytbc_access_token = '';
        window._ytbc_token_expires_at = 0;
        document.getElementById("authBtn").onclick = function() {
            const clientId = document.getElementById("clientId").value.trim();
            const redirectUri = window.location.origin + window.location.pathname;
            const authUrl = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${encodeURIComponent(clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}&response_type=token&scope=https://www.googleapis.com/auth/blogger&include_granted_scopes=true&prompt=consent`;
            let win = window.open(authUrl, 'ytbc_google_oauth', 'width=500,height=600');
            let poll = setInterval(function() {
                try {
                    if (!win || win.closed) { clearInterval(poll); return; }
                    let hash = win.location.hash;
                    if (hash && hash.includes('access_token')) {
                        clearInterval(poll);
                        win.close();
                        let params = {};
                        hash.substr(1).split('&').forEach(function(kv){
                            var parts = kv.split('=');
                            params[parts[0]] = decodeURIComponent(parts[1]);
                        });
                        if (params.access_token) {
                            window._ytbc_access_token = params.access_token;
                            window._ytbc_token_expires_at = Date.now() + (parseInt(params.expires_in) || 3600) * 1000;
                            document.getElementById("authStatus").innerHTML = "✅ Authenticated! Access Token received.";
                            document.getElementById("authStatus").style.color = "green";
                        } else {
                            document.getElementById("authStatus").innerHTML = "❌ Auth failed.";
                            document.getElementById("authStatus").style.color = "crimson";
                        }
                    }
                } catch(e){}
            }, 500);
        };

        // Helper: Blogger duplicate title check, etc
        async function bloggerPostExists(title) {
            const blogId = document.getElementById("blogId").value.trim();
            const apiKey = document.getElementById("blogApiKey").value.trim();
            let accessToken = window._ytbc_access_token, expiresAt = window._ytbc_token_expires_at || 0;
            if (!accessToken || Date.now() > expiresAt) return false;
            const url = `https://www.googleapis.com/blogger/v3/blogs/${blogId}/posts/search?q=${encodeURIComponent(title)}&key=${apiKey}`;
            const resp = await fetch(url, {
                headers: { "Authorization": `Bearer ${accessToken}` }
            });
            const data = await resp.json();
            if (data.items && data.items.length) {
                for (let post of data.items) {
                    if (post.title && post.title.trim().toLowerCase() === title.trim().toLowerCase()) {
                        return true;
                    }
                }
            }
            return false;
        }
        async function getChannelIdFromUrl(channelUrl, apiKey) {
            try {
                let url;
                try { url = new URL(channelUrl); }
                catch {
                    if (channelUrl.startsWith("@")) {
                        channelUrl = "https://www.youtube.com/" + channelUrl;
                        url = new URL(channelUrl);
                    } else {
                        return null;
                    }
                }
                if (url.pathname.startsWith("/channel/")) {
                    return url.pathname.split("/")[2];
                } else if (url.pathname.startsWith("/user/")) {
                    const username = url.pathname.split("/")[2];
                    const api = `https://www.googleapis.com/youtube/v3/channels?forUsername=${encodeURIComponent(username)}&part=id&key=${apiKey}`;
                    const data = await fetch(api).then(r=>r.json());
                    if (data.items && data.items.length) return data.items[0].id;
                } else if (url.pathname.startsWith("/@")) {
                    const handle = url.pathname.replace("/", "");
                    const api = `https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&q=${encodeURIComponent(handle)}&key=${apiKey}`;
                    const data = await fetch(api).then(r=>r.json());
                    if (data.items && data.items.length) return data.items[0].snippet.channelId;
                }
            } catch (e) {}
            return null;
        }
        async function cohereLongSeoPost({title, description, focusKeyword, videoId, cohereApiKey}) {
            const prompt = `
Generate a fully SEO-optimized, 1000+ word blog post based on the YouTube video title: "${title}". The Focus Keyword is: "${focusKeyword}".
The video description is: "${description}"
Use these relevant keywords: "${focusKeyword}"
Use this image as the main image: (if available from YouTube)

Rules:
1. Use the Focus Keyword in:
   - SEO Title (beginning, with a power word and sentiment)
   - Meta Description (start, around 150–160 characters)
   - URL suggestion (short and keyword-based, slug)
   - Introduction (1st paragraph)
   - Content (at least 20 times, aim for 1% keyword density)
   - Subheadings (H2, H3, H4 — wherever relevant)
   - Image alt text
2. Structure Content With:
   - Table of Contents
   - Short, readable paragraphs (max 3 lines)
   - Bullet points and numbered lists where helpful
   - External links with DoFollow attributes
   - Internal links to other relevant posts on the same WordPress site (use example.com if live links unavailable).
   - External links to authoritative external sources.
3. On-Page SEO:
   - Use proper HTML tags: <h1> for the main title, <h2> for major sections, <h3> or <h4> for subheadings.
   - Optimize the <title> tag and meta description to include the primary keyword.
4. Use proper formatting:
   - Wrap all headings in HTML <h2>, <h3>, etc.
   - Include at least 3 <img> tags with alt="${focusKeyword}"
   - Add SEO meta tags: title, description
   - Include embedded YouTube video at the top using <iframe src="https://www.youtube.com/embed/${videoId}"></iframe>
5. Content must be human-like, valuable, and natural.
6. Do not mention 'AI', 'Cohere', or 'generated'.
7. Suggest a short, SEO-friendly slug for the URL (max 8–10 words).
8. List at least 5 keywords for the post.

Output ONLY in this format (no explanation, no extra text):

[SEO_TITLE]
Your SEO title here

[META_DESCRIPTION]
Your meta description here

[SLUG]
your-seo-friendly-slug-here

[KEYWORDS]
keyword1, keyword2, keyword3, keyword4, keyword5

[HTML_CONTENT]
<full HTML content here>
            `.trim();

            try {
                const res = await fetch("https://api.cohere.ai/v1/generate", {
                    method: "POST",
                    headers: {
                        "Authorization": `Bearer ${cohereApiKey}`,
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        model: "command-r-plus",
                        prompt,
                        max_tokens: 1800,
                        temperature: 0.6
                    })
                });
                const data = await res.json();
                if (data.generations && data.generations.length) {
                    return data.generations[0].text.trim();
                }
                if (data.message) return "❌ Cohere API error: " + data.message;
                if (data.error) return "❌ Cohere API error: " + data.error;
                return "❌ SEO description generation failed (empty response).";
            } catch (e) {
                return "❌ Cohere API error: " + e.message;
            }
        }
        async function publishToBlogger(title, htmlContent, labels) {
            try {
                let accessToken = window._ytbc_access_token, expiresAt = window._ytbc_token_expires_at || 0;
                if (!accessToken || Date.now() > expiresAt) {
                    return { error: { message: "Google OAuth2 not authenticated." }};
                }
                const blogId = document.getElementById("blogId").value.trim();
                const apiKey = document.getElementById("blogApiKey").value.trim();
                const url = `https://www.googleapis.com/blogger/v3/blogs/${blogId}/posts/?key=${apiKey}`;
                const payload = {
                    title: title,
                    content: htmlContent,
                    labels: labels ? labels.split(',').map(x => x.trim()).filter(x => x) : []
                };
                const res = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": `Bearer ${accessToken}`
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                return data;
            } catch (e) {
                return { error: { message: e.message }};
            }
        }

        // Multi-channel publish loop (checkbox, fixed for all checked channels)
        document.getElementById('ytbc-multi-publish-form').onsubmit = async function(e){
            e.preventDefault();
            let checkboxes = document.querySelectorAll('.ytbc-multi-ch:checked');
            let selIdxs = Array.from(checkboxes).map(cb => parseInt(cb.value));
            let numPosts = parseInt(document.getElementById('ytbc-multi-count').value);
            let cohereApiEl = document.getElementById("cohereApiKey");
            let cohereApiKey = cohereApiEl ? cohereApiEl.value.trim() : '';
            if (!selIdxs.length) { alert('Select at least one channel!'); return; }
            if (!cohereApiKey) { alert("Cohere API Key not found!"); return; }
            let totalLoops = selIdxs.length * numPosts;
            let done = 0;
            document.getElementById("youtubeResults").innerHTML = "";
            setProgressBar(0, totalLoops);

            // Loop through each selected channel SEQUENTIALLY
            for (let chArrIdx = 0; chArrIdx < selIdxs.length; chArrIdx++) {
                let chIdx = selIdxs[chArrIdx];
                let ch = ytbc_channels[chIdx];
                if (!ch) continue;
                let channelName = ch.name, channelUrl = ch.url;
                let apiKey = document.getElementById("blogApiKey").value.trim();
                document.getElementById("youtubeResults").innerHTML += `<h3>Channel: ${channelName}</h3>`;
                // fetch videos for this channel
                const channelId = await getChannelIdFromUrl(channelUrl, apiKey);
                if (!channelId) { document.getElementById("youtubeResults").innerHTML += `<div>❌ Channel not found: ${channelUrl}</div>`; continue;}
                const api = `https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=${encodeURIComponent(channelId)}&key=${apiKey}`;
                const data = await fetch(api).then(r=>r.json());
                if (!(data.items && data.items.length)) { document.getElementById("youtubeResults").innerHTML += `<div>❌ Channel fetch fail</div>`; continue;}
                const uploadsPlaylistId = data.items[0].contentDetails.relatedPlaylists.uploads;
                // get many videos
                let allVideoIds = [];
                let nextPageToken = "";
                let totalNeeded = numPosts * 4;
                while (allVideoIds.length < totalNeeded) {
                    let playlistApi = `https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=${uploadsPlaylistId}&key=${apiKey}`;
                    if (nextPageToken) playlistApi += `&pageToken=${nextPageToken}`;
                    const resp = await fetch(playlistApi).then(r=>r.json());
                    if (!(resp.items && resp.items.length)) break;
                    allVideoIds.push(...resp.items.map(item => item.snippet.resourceId.videoId));
                    nextPageToken = resp.nextPageToken;
                    if (!nextPageToken) break;
                }
                // Fill up to numPosts with non-duplicate
                let skipTitlesSet = new Set();
                let found = 0, vidIdx = 0;
                let html = `<table class="ytbc-multi-table" style="margin:0 auto 30px auto;"><thead>
                  <tr>
                    <th>Sr No.</th>
                    <th>Title</th>
                    <th>Description (HTML)</th>
                    <th>Keywords</th>
                    <th>Status</th>
                  </tr>
                </thead><tbody>`;
                for(let row=0; row<numPosts; row++) {
                    html += `<tr id="ytbc-row-${chIdx}-${row}">
                        <td>${row+1}</td>
                        <td id="ytbc-title-${chIdx}-${row}" class="ytbc-status-gen">Generating...</td>
                        <td id="ytbc-desc-${chIdx}-${row}" class="ytbc-status-gen">-</td>
                        <td id="ytbc-keys-${chIdx}-${row}" class="ytbc-status-gen">-</td>
                        <td id="ytbc-status-${chIdx}-${row}" class="ytbc-status-gen">Waiting...</td>
                    </tr>`;
                }
                html += `</tbody></table>`;
                document.getElementById("youtubeResults").innerHTML += html;
                let published=0;
                for(let row=0; row<numPosts; row++) {
                    while(vidIdx<allVideoIds.length) {
                        // fetch details for next
                        const videoApi = `https://www.googleapis.com/youtube/v3/videos?part=snippet&id=${allVideoIds[vidIdx]}&key=${apiKey}`;
                        const detailResp = await fetch(videoApi).then(r=>r.json());
                        vidIdx++;
                        if (!(detailResp.items && detailResp.items.length)) continue;
                        let v = detailResp.items[0];
                        let titleLower = v.snippet.title.trim().toLowerCase();
                        if (skipTitlesSet.has(titleLower)) continue;
                        // check duplicate
                        document.getElementById(`ytbc-title-${chIdx}-${row}`).innerHTML = v.snippet.title;
                        document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-gen">Checking duplicate...</span>`;
                        let isDuplicate = await bloggerPostExists(v.snippet.title);
                        if (isDuplicate) {
                            document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-dup">Skipped (duplicate)</span>`;
                            skipTitlesSet.add(titleLower);
                            continue; // try next video
                        }
                        skipTitlesSet.add(titleLower);
                        // Cohere generate
                        document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-gen">Generating with Cohere...</span>`;
                        let focusKeyword = v.snippet.title.split(" ").slice(0, 3).join(" ");
                        let cohereText = await cohereLongSeoPost({
                            title: v.snippet.title,
                            description: v.snippet.description,
                            focusKeyword: focusKeyword,
                            videoId: v.id,
                            cohereApiKey: cohereApiKey
                        });
                        // Parse Cohere output
                        let htmlContent = '', keywords = '';
                        let mDesc = cohereText.match(/\[HTML_CONTENT\]([\s\S]*)/i);
                        if (mDesc) htmlContent = mDesc[1].trim();
                        let mKeys = cohereText.match(/\[KEYWORDS\]([\s\S]*?)(?:\[|$)/i);
                        if (mKeys) keywords = mKeys[1].replace(/\n/g,'').trim();
                        document.getElementById(`ytbc-desc-${chIdx}-${row}`).innerHTML = htmlContent ? `<div style="max-height:180px;overflow:auto;">${htmlContent}</div>` : "<i>Gen fail</i>";
                        document.getElementById(`ytbc-keys-${chIdx}-${row}`).innerText = keywords ? keywords : "-";
                        document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-gen">Generating... wait 10s</span>`;
                        await new Promise(r=>setTimeout(r, 10000));
                        document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-gen">Publishing...</span>`;
                        let blogLabels = v.snippet.tags ? v.snippet.tags.slice(0, 5).join(", ") : '';
                        let bloggerPost = await publishToBlogger(
                            v.snippet.title,
                            htmlContent,
                            blogLabels
                        );
                        if (bloggerPost && bloggerPost.id) {
                            document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-pub">✅ Published <a href="${bloggerPost.url}" target="_blank">View</a></span>`;
                            published++;
                        } else {
                            document.getElementById(`ytbc-status-${chIdx}-${row}`).innerHTML = `<span class="ytbc-status-dup">❌ Publish failed: ${bloggerPost && bloggerPost.error && bloggerPost.error.message ? bloggerPost.error.message : "Unknown error"}</span>`;
                        }
                        done++;
                        setProgressBar(done, totalLoops);
                        break; // move to next row
                    }
                    setProgressBar(done, totalLoops);
                }
            }
        };
        </script>
<?php
}

// AJAX handler to save channels.json
add_action('wp_ajax_ytbc_save_channels', function() {
    $channels_file = __DIR__ . '/channels.json';
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
    $channels = json_decode(stripslashes($_POST['channels']??'[]'), true);
    if (!is_array($channels)) $channels = [];
    file_put_contents($channels_file, json_encode($channels));
    wp_send_json_success('Saved');
});
?>
