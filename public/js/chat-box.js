document.addEventListener('DOMContentLoaded', () => {
    function autoSizeTextarea(t) {
        if (!t) return;
        t.style.height = 'auto';
        t.style.height = (t.scrollHeight) + 'px';
    }

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function htmlToText(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html.replace(/<br\s*\/?>/gi, '\n');
        return tmp.textContent || tmp.innerText || '';
    }

    function downloadHandler(e) {
        const btn = e.currentTarget;
        const bubble = btn.closest('.chat-bubble');
        const contentEl = bubble ? bubble.querySelector('.bubble-content') : null;

        if (!contentEl) return;

        const html = contentEl.innerHTML || '';
        const text = htmlToText(html);

        let fmt = prompt('Enter download format: "txt" or "doc" (default txt):', 'txt');
        
        if (!fmt) fmt = 'txt';
        fmt = fmt.trim().toLowerCase();
        if (fmt !== 'txt' && fmt !== 'doc') { alert('Unsupported format — using txt.'); fmt = 'txt'; }

        const ext = fmt === 'doc' ? 'doc' : 'txt';
        const mime = 'text/plain;charset=utf-8';
        const filename = `note-${Date.now()}.${ext}`;
        const blob = new Blob([text], { type: mime });
        const a = document.createElement('a');

        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
    }

    function attachDownloadHandlerToButton(btn) {
        if (!btn) return;
        if (!btn.__hasDownloadHandler) {
            btn.addEventListener('click', downloadHandler);
            btn.__hasDownloadHandler = true;
        }
    }

    function appendBubbleTo(messagesEl, type, rawText) {
        if (!messagesEl) return;
        const text = String(rawText || '');
        const safeHtml = escapeHtml(text).replace(/\n/g, '<br>');
        const b = document.createElement('div');
        b.className = `chat-bubble ${type}`;
        b.innerHTML = `
            <div class="bubble-content">${safeHtml}</div>
            <div class="bubble-actions">
                <button type="button" class="${type === 'user' ? 'btn-action-user btn btn-sm btn-outline-secondary' : 'download-btn btn btn-sm btn-outline-light'}" title="${type === 'user' ? 'Edit' : 'Download'}">
                <i class="fas fa-${type === 'user' ? 'edit' : 'download'}"></i>
                </button>
            </div>
        `;
        messagesEl.appendChild(b);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        if (type === 'system') {
            attachDownloadHandlerToButton(b.querySelector('.download-btn'));
        }
    }

    const openChatBtn = document.getElementById('openChatModal');
    const chatModalEl = document.getElementById('chatModal');

    if (openChatBtn && chatModalEl) {
        const bsModal = new bootstrap.Modal(chatModalEl);
        openChatBtn.addEventListener('click', (e) => {
            e.preventDefault();
            bsModal.show();
        });
    }

    const form = document.getElementById('chatForm');
    if (!form) return;

    const promptInput = document.getElementById('promptInput');
    const fileInput = document.getElementById('fileInput');
    const filePromptInput = document.getElementById('filePromptInput');
    const fileOriginalNameInput = document.getElementById('fileOriginalNameInput');
    const uploadedFileInfo = document.getElementById('uploadedFileInfo');
    const uploadedFileName = document.getElementById('uploadedFileName');
    const uploadedFileSnippet = document.getElementById('uploadedFileSnippet');
    const submitBtn = document.getElementById('submitBtn');
    const statusEl = document.getElementById('chatStatus');
    const messagesEl = document.getElementById('chatMessages');
    const modeInput = document.getElementById('modeInput');

    if (promptInput) {
        autoSizeTextarea(promptInput);
        promptInput.addEventListener('input', () => autoSizeTextarea(promptInput));
    }

    document.querySelectorAll('.download-btn').forEach(btn => attachDownloadHandlerToButton(btn));

    function togglePromptHint() {
        if (!modeInput || !promptInput) return;
        promptInput.placeholder = (modeInput.value === 'prompt')
        ? '(Required) Enter your custom prompt here…'
        : '(Optional) Add a short instruction or leave blank for Direct Note…';
    }
    if (modeInput) { modeInput.addEventListener('change', togglePromptHint); togglePromptHint(); }

    async function fetchJson(url, options = {}) {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {})
        };

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrf && !headers['X-CSRF-TOKEN']) headers['X-CSRF-TOKEN'] = csrf;

        const res = await fetch(url, { credentials: 'same-origin', ...options, headers });

        const ct = res.headers.get('content-type') || '';

        if (ct.includes('application/json')) {
            const json = await res.json();
            return { ok: res.ok, status: res.status, json, text: null, redirected: res.redirected, url: res.url };
        }

        const text = await res.text();

        return { ok: res.ok, status: res.status, json: null, text, redirected: res.redirected, url: res.url };
    }

    function isMostlyPrintableText(s) {

        if (!s || typeof s !== 'string') return false;

        const total = s.length;

        if (total === 0) return false;

        let nonPrintable = 0;

        const limit = Math.min(total, 500);

        for (let i = 0; i < limit; i++) {
            const code = s.charCodeAt(i);
            if ((code >= 32 && code <= 126) || code === 9 || code === 10 || code === 13 || code > 127) {
                
            } else {
                nonPrintable++;
            }
            if (nonPrintable / (i + 1) > 0.4) return false;
        }
        return (total - nonPrintable) > 5;
    }

    function showUploadedFileInfo(name, mime, size) {
        if (!uploadedFileInfo) return;
        if (uploadedFileName) uploadedFileName.textContent = name || 'uploaded file';

        if (uploadedFileSnippet) {
            uploadedFileSnippet.style.display = 'none';
            uploadedFileSnippet.textContent = '';
        }

        const ext = (name || '').split('.').pop()?.toLowerCase();
        let iconClass = 'fa-file';
        if (/pdf/.test(mime) || ext === 'pdf') iconClass = 'fa-file-pdf';
        else if (/word|msword|officedocument/.test(mime) || ['doc','docx'].includes(ext)) iconClass = 'fa-file-word';
        else if (/image/.test(mime) || ['jpg','jpeg','png','gif'].includes(ext)) iconClass = 'fa-file-image';
        else if (/text/.test(mime) || ['txt','md'].includes(ext)) iconClass = 'fa-file-lines';

        let iconEl = uploadedFileInfo.querySelector('.uploaded-file-icon');
        if (!iconEl) {
            iconEl = document.createElement('i');
            iconEl.className = `uploaded-file-icon fas ${iconClass} me-2`;
            uploadedFileInfo.insertBefore(iconEl, uploadedFileInfo.firstChild);
        } else {
            iconEl.className = `uploaded-file-icon fas ${iconClass} me-2`;
        }

        let sizeText = '';
        if (size && !isNaN(size)) {
            const kb = size / 1024;
            if (kb < 1024) sizeText = `${Math.round(kb)} KB`;
            else sizeText = `${(kb/1024).toFixed(2)} MB`;
        }
        let metaEl = uploadedFileInfo.querySelector('.uploaded-file-meta');
        if (!metaEl) {
            metaEl = document.createElement('small');
            metaEl.className = 'uploaded-file-meta d-block text-muted';
            uploadedFileInfo.appendChild(metaEl);
        }
        metaEl.textContent = sizeText ? `Size: ${sizeText}` : '';

        uploadedFileInfo.style.display = 'block';
    }

    if (fileInput) {
        fileInput.addEventListener('change', async (ev) => {
            const f = fileInput.files[0];
            if (!f) return;

            if (promptInput) {
                promptInput.value = `[File: ${f.name}]`;
                autoSizeTextarea(promptInput);
            }

            if (statusEl) statusEl.innerHTML = '<span class="text-muted">Uploading file…</span>';

            const fd = new FormData();
            fd.append('file', f);

            try {
                const res = await fetch('/upload', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) {
                    let errText = await res.text().catch(() => null);
                    try {
                        const j = JSON.parse(errText);
                        if (j && j.errors) {
                            errText = JSON.stringify(j.errors);
                        }
                    } catch(e) {}
                    if (statusEl) statusEl.innerHTML = `<span class="text-danger">Upload failed: ${escapeHtml(errText || res.statusText)}</span>`;
                    console.error('Upload failed', res.status, errText);
                    return;
                }

                const json = await res.json();

                if (filePromptInput) filePromptInput.value = json.path || '';
                if (fileOriginalNameInput) fileOriginalNameInput.value = json.original_name || f.name;

                if (uploadedFileName) uploadedFileName.textContent = json.original_name || f.name;

                const mime = json.mime || f.type || '';
                const size = json.size || f.size || 0;

                const snippetText = (json.snippet || '').toString();

                if (snippetText && isMostlyPrintableText(snippetText)) {
                    if (uploadedFileSnippet) {
                        const safeSnippet = snippetText.length > 2000 ? snippetText.slice(0,2000) + '...' : snippetText;
                        uploadedFileSnippet.textContent = safeSnippet;
                        uploadedFileSnippet.style.display = 'block';
                    }
                    if (uploadedFileInfo) uploadedFileInfo.style.display = 'block';
                } else {
                    if (uploadedFileSnippet) {
                        uploadedFileSnippet.textContent = '';
                        uploadedFileSnippet.style.display = 'none';
                    }
                    
                    showUploadedFileInfo(json.original_name || f.name, json.mime || f.type || '', json.size || f.size || 0);
                }

                if (statusEl) statusEl.innerHTML = `<span class="text-success">File uploaded</span>`;

            } catch (err) {
                console.error('Upload error', err);
                if (statusEl) statusEl.innerHTML = `<span class="text-danger">Upload failed</span>`;
            }
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (statusEl) statusEl.innerHTML = '';
        if (submitBtn) submitBtn.disabled = true;
        const prevLabel = submitBtn ? submitBtn.innerHTML : '';

        const mode = modeInput ? modeInput.value : 'direct';
        const text = promptInput ? promptInput.value.trim() : '';
        const hasFileLocal = fileInput && fileInput.files && fileInput.files.length > 0;
        const hasAjaxUploadedFile = filePromptInput && filePromptInput.value;

        if (mode === 'prompt' && !text && !hasFileLocal && !hasAjaxUploadedFile) {
            if (statusEl) statusEl.innerHTML = '<span class="text-danger">Custom prompt required when Mode = Custom Prompt (or upload a file).</span>';
            if (submitBtn) submitBtn.disabled = false;
            return;
        }
        if (!text && !hasFileLocal && !hasAjaxUploadedFile) {
            if (statusEl) statusEl.innerHTML = '<span class="text-danger">Please type a prompt or attach a file.</span>';
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        if (messagesEl) {
            const userText = text || (hasFileLocal ? `[File: ${fileInput.files[0].name}]` : (hasAjaxUploadedFile ? `[File: ${fileOriginalNameInput.value || 'uploaded file'}]` : ''));
            const b = document.createElement('div');
            b.className = 'chat-bubble user';
            b.innerHTML = `<div class="bubble-content">${escapeHtml(userText)}</div>`;
            messagesEl.appendChild(b);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        const fd = new FormData(form);
        fd.set('prompt', text);

        if (filePromptInput && filePromptInput.value) {
            if (fd.has('file')) {
                fd.delete('file');
            }
        }

        if (submitBtn) submitBtn.innerHTML = 'Generating…';
        if (statusEl) statusEl.innerHTML = '<span class="text-muted">Contacting AI — this may take a few seconds.</span>';

        try {
            const resp = await fetchJson(form.action, {
                method: form.method || 'POST',
                body: fd
            });

            if (!resp.ok) {
                let msg = 'Server error';
                if (resp.json) msg = resp.json.error || resp.json.message || JSON.stringify(resp.json);
                else if (resp.text) msg = resp.text;
                if (statusEl) statusEl.innerHTML = `<span class="text-danger">${escapeHtml(msg).slice(0,500)}</span>`;
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = prevLabel; }
                return;
            }

            const json = resp.json;
            if (json) {
                const replyText = json.reply || json.ai_response || 'No response.';
                if (messagesEl) {
                    const b = document.createElement('div');
                    b.className = 'chat-bubble system';
                    b.innerHTML = `<div class="bubble-content">${escapeHtml(replyText).replace(/\n/g,'<br>')}</div>
                                    <div class="bubble-actions"><button type="button" class="download-btn btn btn-sm btn-outline-light" title="Download reply"><i class="fas fa-download"></i></button></div>`;
                    messagesEl.appendChild(b);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                    attachDownloadHandlerToButton(b.querySelector('.download-btn'));
                }

                const sugText = json.study_suggestions_text || json.suggestions_text || null;
                const sugJson = json.study_suggestions_json || json.suggestions_json || null;

                if (sugText || sugJson) {
                    let sugHtml = '';
                    if (sugJson && typeof sugJson === 'object') {
                        sugHtml += `<div class="sugg-title">Study Note Suggestion</div>`;
                        if (Array.isArray(sugJson.focus_points) && sugJson.focus_points.length) {
                            sugHtml += `<div class="sugg-section"><strong>Focus Points</strong><ul>`;
                            sugJson.focus_points.forEach(p => { sugHtml += `<li>${escapeHtml(p)}</li>`; });
                            sugHtml += `</ul></div>`;
                        }
                        if (Array.isArray(sugJson.related_topics) && sugJson.related_topics.length) {
                            sugHtml += `<div class="sugg-section"><strong>Related Topics</strong><ul>`;
                            sugJson.related_topics.forEach(rt => {
                                const t = escapeHtml(rt.topic || rt.title || '');
                                const n = escapeHtml(rt.note || rt.description || '');
                                sugHtml += `<li><strong>${t}:</strong> ${n}</li>`;
                            });
                            sugHtml += `</ul></div>`;
                        }
                        if (Array.isArray(sugJson.study_plan) && sugJson.study_plan.length) {
                            sugHtml += `<div class="sugg-section"><strong>Study Plan</strong><ol>`;
                            sugJson.study_plan.forEach(s => { sugHtml += `<li>${escapeHtml(s)}</li>`; });
                            sugHtml += `</ol></div>`;
                        }
                    } else {
                        sugHtml = escapeHtml(sugText || '').replace(/\n/g,'<br>');
                    }

                    const sb = document.createElement('div');
                    sb.className = 'chat-bubble system';
                    sb.innerHTML = `<div class="bubble-content">${sugHtml}</div>
                                    <div class="bubble-actions"><button type="button" class="download-btn btn btn-sm btn-outline-light" title="Download reply"><i class="fas fa-download"></i></button></div>`;
                    messagesEl.appendChild(sb);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                    attachDownloadHandlerToButton(sb.querySelector('.download-btn'));
                }

                if (json.redirect) {
                    window.location.href = json.redirect;
                    return;
                } else if (json.session_id) {
                    window.location.href = `/chat/${json.session_id}`;
                    return;
                }
            } else {
                console.warn('Server returned non-JSON response:', resp.text);
                if (statusEl) statusEl.innerHTML = '<span class="text-danger">Unexpected server response — check server logs.</span>';
            }
        } catch (err) {
            console.error(err);
            if (statusEl) statusEl.innerHTML = '<span class="text-danger">Network or server error.</span>';
            else alert('Network or server error.');
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = prevLabel; }
            try { form.reset(); if (promptInput) autoSizeTextarea(promptInput); } catch(e) {}
        }
    });

    document.querySelectorAll('.download-btn').forEach(btn => attachDownloadHandlerToButton(btn));
});
