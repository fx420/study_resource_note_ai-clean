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

    async function downloadHandler(e) {
        const btn = e.currentTarget;
        const bubble = btn.closest('.chat-bubble');
        const contentEl = bubble ? bubble.querySelector('.bubble-content') : null;

        const messageId = btn.dataset.messageId || btn.getAttribute('data-message-id') || null;
        const sessionId = window.SESSION_ID || document.getElementById('chatMessages')?.dataset?.sessionId || null;

        if (!contentEl) return;

        if (typeof window.logEvent === 'function') {
            try {
                window.logEvent('download', {
                    session_id: sessionId,
                    message_id: messageId,
                    format: 'pdf'
                });
            } catch (err) { console.warn('logEvent(download) failed', err); }
        }

        let fmt = prompt('Enter download format: "txt", "doc" or "pdf" (default pdf):', 'pdf');
        if (!fmt) fmt = 'pdf';
        fmt = fmt.trim().toLowerCase();
        if (fmt !== 'txt' && fmt !== 'doc' && fmt !== 'pdf') {
            alert('Unsupported format — using pdf.');
            fmt = 'pdf';
        }

        const originalHtml = contentEl.innerHTML || '';
        const text = (function(html) {
            const tmp = document.createElement('div');
            tmp.innerHTML = html.replace(/<br\s*\/?>/gi, '\n');
            return tmp.textContent || tmp.innerText || '';
        })(originalHtml);

        const timestamp = Date.now();
        const baseName = `note-${timestamp}`;

        try {
            if (fmt === 'txt' || fmt === 'doc') {
                const ext = fmt === 'doc' ? 'doc' : 'txt';
                const mime = 'text/plain;charset=utf-8';
                const blob = new Blob([text], { type: mime });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `${baseName}.${ext}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                return;
            }

            const jsPdfFactory = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF
                            : (typeof window.jsPDF === 'function' ? window.jsPDF : null);

            if (!jsPdfFactory) {
                const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `${baseName}.txt`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                alert('jsPDF not available — saved as TXT instead.');
                return;
            }

            const doc = new jsPdfFactory({ unit: 'pt', format: 'a4', compress: true });

            const isMostlyText = (function(s) {
                if (!s) return true;
                const htmlOnly = s.replace(/<[^>]+>/g, '').trim();
                return htmlOnly.length > 0 && (s.length - htmlOnly.length) < Math.max(200, s.length * 0.3);
            })(originalHtml);

            if (isMostlyText) {
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const margin = 40;
                const maxLineWidth = pageWidth - margin * 2;
                const fontSize = 12;
                doc.setFontSize(fontSize);

                const lines = doc.splitTextToSize(text, maxLineWidth);
                let cursorY = margin;
                const lineHeight = Math.ceil(fontSize * 1.25);

                for (let i = 0; i < lines.length; i++) {
                    if (cursorY + lineHeight > pageHeight - margin) {
                        doc.addPage();
                        cursorY = margin;
                    }
                    doc.text(String(lines[i]), margin, cursorY);
                    cursorY += lineHeight;
                }

                doc.save(`${baseName}.pdf`);
                return;
            }

            const clone = contentEl.cloneNode(true);
            clone.querySelectorAll && clone.querySelectorAll('.bubble-actions, .download-btn, button').forEach(n => n && n.remove());

            const wrapper = document.createElement('div');
            wrapper.style.background = '#ffffff';
            wrapper.style.padding = '10px';
            wrapper.style.width = '780px';
            wrapper.appendChild(clone);
            wrapper.style.position = 'fixed';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            document.body.appendChild(wrapper);

            const htmlOptions = {
                x: 10,
                y: 10,
                margin: [10, 10, 10, 10],
                html2canvas: {
                    scale: 1.2,
                    useCORS: true,
                    logging: false
                }
            };

            let htmlRendered = false;

            if (typeof doc.html === 'function') {
                await new Promise((resolve, reject) => {
                    try {
                        const maybePromise = doc.html(wrapper, {
                            ...htmlOptions,
                            callback: function (docInstance) {
                                htmlRendered = true;
                                try {
                                    docInstance.save(`${baseName}.pdf`);
                                } catch (err) {
                                    try { doc.save(`${baseName}.pdf`); } catch(e) {}
                                }
                                resolve();
                            }
                        });
                        if (maybePromise && typeof maybePromise.then === 'function') {
                            maybePromise.then(() => {
                                if (!htmlRendered) {
                                    try { doc.save(`${baseName}.pdf`); } catch(e) {}
                                }
                                resolve();
                            }).catch(reject);
                        }
                    } catch (err) {
                        reject(err);
                    }
                });
            } else {
                throw new Error('jsPDF html rendering not available in this build');
            }

            try { wrapper.remove(); } catch (e) {}

            return;
        } catch (err) {
            console.error('Download error', err);
            alert('Failed to generate download: ' + (err?.message || err));
        }
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

                let json = null;
                try {
                    json = await res.json();
                } catch (err) {
                    json = null;
                }

                if (!res.ok) {
                    let errText = (json && json.errors) ? JSON.stringify(json.errors) : (json && json.message) ? json.message : (await res.text().catch(()=>null));
                    if (statusEl) statusEl.innerHTML = `<span class="text-danger">Upload failed: ${escapeHtml(errText || res.statusText)}</span>`;
                    console.error('Upload failed', res.status, errText);
                    return;
                }

                if (filePromptInput) filePromptInput.value = json.path || json.storage_path || '';
                if (fileOriginalNameInput) fileOriginalNameInput.value = json.original_name || f.name;
                if (document.getElementById('fileSnippetInput')) document.getElementById('fileSnippetInput').value = json.snippet || '';
                
                if (typeof window.logEvent === 'function') {
                    try {
                        const session_id = window.SESSION_ID || (document.getElementById('chatMessages')?.dataset?.sessionId) || null;
                        window.logEvent('upload', {
                        session_id,
                        file_path: json.path || null,
                        original_file_name: json.original_name || json.originalName || (f && f.name) || null,
                        mime: json.mime || f.type || null,
                        size: json.size || f.size || null
                        });
                    } catch (e) { console.warn('logEvent(upload) failed', e); }
                }

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

    window.attachDownloadHandlerToButton = attachDownloadHandlerToButton;
    window.downloadHandler = downloadHandler;
});
