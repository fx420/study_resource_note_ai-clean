@extends('layouts.index')

@section('styles')
    <style>
    .hero-section, 
    .features-section {
        display: none !important;
    }

    main.container {
        background-color: transparent;
        padding-bottom: 120px;
    }

    .chat-bubble.user .bubble-content,
    .chat-bubble.system .bubble-content {
        color: white;
        text-align: left;
    }

    .ai-title {
        font-size: 1.08rem;
        font-weight: 700;
        margin-bottom: 0.35rem;
        color: #e9f1ff;
    }

    .ai-subtitle {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: #dbe9ff;
    }
    
    .bubble-content p {
        margin: 0 0 0.45rem 0;
        line-height: 1.45;
    }

    .bubble-content ul,
    .bubble-content ol {
        margin: 0.25rem 0 0.8rem 1.25rem;
    }
    .bubble-content li {
        margin-bottom: 0.25rem;
    }

    .concept-title {
        font-weight:600;
        margin-bottom:0.12rem;
    }

    .concept-def {
        margin-left:0.6rem;
    }

    .small-muted {
        color: rgba(255,255,255,0.78);
        font-size: .95rem;
        margin-bottom: .4rem;
    }

    #questionModal .modal-content {
        background-color: #000 !important;
        color: #fff !important;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 8px 30px rgba(0,0,0,0.6);
    }

    #questionModal .modal-header,
    #questionModal .modal-footer {
        border-color: rgba(255,255,255,0.06);
    }

    #questionModal .form-control,
    #questionModal .form-select,
    #questionModal input,
    #questionModal textarea {
        background-color: #0b0b0b !important;
        color: #fff !important;
        border: 1px solid rgba(255,255,255,0.12) !important;
        box-shadow: none !important;
    }
    #questionModal .form-control::placeholder {
        color: rgba(255,255,255,0.5) !important;
    }
    #questionModal .form-select {
        -webkit-appearance: none;
        appearance: none;
        background-image: linear-gradient(45deg, transparent 50%, #fff 50%), linear-gradient(135deg, #fff 50%, transparent 50%), linear-gradient(to right, rgba(255,255,255,0.06), rgba(255,255,255,0.06));
        background-position: calc(100% - 18px) calc(1em + 2px), calc(100% - 13px) calc(1em + 2px), 100% 0;
        background-size: 6px 6px, 6px 6px, 1px 1.5em;
        background-repeat: no-repeat;
        padding-right: 2.5rem;
    }

    #questionModal .form-select option {
        color: #fff;
        background-color: #000;
    }

    #questionModal .btn-primary {
        background-color: #1f6feb;
        border-color: #1f6feb;
    }

    #questionModal .btn-close {
        filter: invert(1) grayscale(1);
        opacity: 0.9;
    }

    #questionModal .modal-body { 
        padding-top: 0.75rem; 
        padding-bottom: 0.75rem; 
    }
    #questionModal .modal-footer { 
        padding: 0.75rem; 
    }

    .chat-bubble.system .bubble-content { 
        white-space: pre-wrap; 
        color: #fff; 
    }

    .keyconcepts-title, .questions-title {
        font-weight: 700;
        margin-bottom: 0.25rem;
        display: block;
    }

    .concept-item {
        margin-bottom: 0.75rem;
    }

    .concept-item .concept-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .concept-item .concept-def {
        margin-left: 0.5rem;
    }

    .question-block {
        margin-bottom: 0.75rem;
    }

    .question-block .q-label {
        font-weight: 600;
    }

    .question-block .q-answer {
        margin-top: 0.25rem;
        margin-left: 0.5rem;
    }

    .mcq-options {
        margin-top: 0.25rem;
        margin-left: 1rem;
    }

    .mcq-options li {
        list-style: none;
        margin-bottom: 0.2rem;
    }

    .small-muted {
        display:block;
        font-size: .85rem;
        color: rgba(255,255,255,0.65);
        margin-bottom: .5rem;
    }

    .chat-bubble .bubble-actions .copy-btn,
    .chat-bubble .bubble-actions .save-json-btn {
        white-space: nowrap;
    }

    .small-muted {
        display:block;
        font-size:.92rem;
        color: rgba(255,255,255,0.75);
        margin-bottom: .5rem; 
    }

    .concept-item {
        margin-bottom: .6rem;
    }

    .concept-title {
        font-weight: 600;
        margin-bottom: .15rem;
    }

    .concept-def {
        margin-left: .5rem;
        color: #e6eefc;
    }

    .question-block {
        margin-bottom: .7rem;
    }
    .q-label {
        font-weight: 600;
    }
    .q-answer {
        margin-left: .5rem;
        margin-top: .25rem;
    }
    .mcq-options {
        margin-left: 1rem;
        margin-top: .25rem;
        padding-left: 0;
    }
    .mcq-options li {
        list-style: none;
        margin-bottom: .2rem;
    }
    </style>
@endsection

@section('content')
<div class="container py-4">
    <h3 class="mb-4">{{ $session->title }}</h3>

    <div id="chatMessages" class="chat-messages mb-3" aria-live="polite" data-session-id="{{ $session->id }}">
        @foreach($session->messages as $msg)
            <div class="chat-bubble {{ $msg->sender }}">
                {{-- bubble content: preserve newlines, escape HTML --}}
                <div class="bubble-content">{!! (e($msg->message)) !!}</div>

                @if($msg->sender === 'system')
                    <div class="bubble-actions">
                        <button type="button" class="btn btn-sm btn-outline-light download-btn" title="Download reply">
                            <i class="fas fa-download"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-primary ms-2 generate-question-btn" data-response="{{ e($msg->message) }}" data-message-id="{{ $msg->id }}">
                            <i class="fas fa-question-circle"></i> Generate Questions
                        </button>

                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2 key-concepts-btn" data-message-id="{{ $msg->id }}" data-response="{{ e($msg->message) }}">
                            <i class="fas fa-lightbulb"></i> Key Concepts
                        </button>
                    </div>
                @endif

            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="questionForm">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Practice Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Number of Questions</label>
                        <input type="number" name="count" class="form-control" value="5" min="1" max="20" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" class="form-select">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <select name="type" class="form-select">
                            <option value="qa">Q&A</option>
                            <option value="mcq">MCQ</option>
                            <option value="mix">Mix</option>
                        </select>
                    </div>
                    <input type="hidden" name="context" id="questionContext">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Fixed input at bottom --}}
<div class="input-area">
    <form method="POST" action="{{ route('chat.session.submit', $session) }}" id="chatForm" enctype="multipart/form-data">
        @csrf
        <div class="input-wrapper">
            <textarea name="prompt" id="promptInput" class="form-control prompt-input" placeholder="Type your message…" rows="1" required></textarea>

            <button type="button" class="btn btn-outline-secondary btn-upload" onclick="document.getElementById('fileInput').click()">
              <i class="fas fa-paperclip"></i>
            </button>

            <input type="file" name="file" id="fileInput" class="d-none">

            <button type="submit" class="btn btn-primary btn-send"><i class="fas fa-paper-plane"></i></button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/chat-box.js') }}"></script>
<script>
    const SESSION_ID = "{{ $session->id }}";
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const SESSION_ID = document.getElementById('chatMessages')?.dataset?.sessionId || '';

        function getCsrfToken() {
            const m = document.querySelector('meta[name="csrf-token"]');
            return m ? (m.content || '') : '';
        }

        async function postJson(url, formData) {
            const token = getCsrfToken();
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            return res;
        }

        function escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function inlineFormat(raw) {
            let s = escapeHtml(raw);

            s = s.replace(/`([^`]+?)`/g, '<code>$1</code>');

            s = s.replace(/\*\*([^*]+?)\*\*/g, '<strong>$1</strong>');

            s = s.replace(/(^|[^*])\*([^*]+?)\*([^*]|$)/g, (m, p1, inner, p3) => `${p1}<em>${inner}</em>${p3}`);

            return s;
        }

        function parseAiMessageToHtml(text) {
            if (!text || !String(text).trim()) return '';

            const normalized = String(text).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
            const lines = normalized.split('\n');

            let out = '';
            let i = 0;
            while (i < lines.length) {
                let ln = lines[i].trim();

                if (ln === '') {
                    out += '<p></p>';
                    i++;
                    continue;
                }

                if (/^#{3}\s+/.test(ln)) {
                    out += `<div class="ai-title">${inlineFormat(ln.replace(/^#{3}\s+/, ''))}</div>`;
                    i++;
                    continue;
                }

                if (/^#{4}\s+/.test(ln)) {
                    out += `<div class="ai-subtitle">${inlineFormat(ln.replace(/^#{4}\s+/, ''))}</div>`;
                    i++;
                    continue;
                }

                if (/^\d+\.\s+/.test(ln)) {
                    out += '<ol>';
                    while (i < lines.length && /^\d+\.\s+/.test(lines[i].trim())) {
                        const item = lines[i].trim().replace(/^\d+\.\s+/, '');
                        out += `<li>${inlineFormat(item)}</li>`;
                        i++;
                    }
                    out += '</ol>';
                    continue;
                }

                if (/^[-\*\u2022]\s+/.test(ln)) {
                    out += '<ul>';
                    while (i < lines.length && /^[-\*\u2022]\s+/.test(lines[i].trim())) {
                        const item = lines[i].trim().replace(/^[-\*\u2022]\s+/, '');
                        out += `<li>${inlineFormat(item)}</li>`;
                        i++;
                    }
                    out += '</ul>';
                    continue;
                }

                out += `<p>${inlineFormat(ln)}</p>`;
                i++;
            }

            return out;
        }

        function processAiBubbles(root = document) {
            const bubbles = (root.querySelectorAll ? root.querySelectorAll('.chat-bubble.system .bubble-content') : []);
            bubbles.forEach(b => {
                if (b.dataset.processed) return;
                const raw = b.innerText || b.textContent || '';
                if (/<[a-z][\s\S]*>/i.test(b.innerHTML)) {
                    b.dataset.processed = '1';
                    return;
                }
                const html = parseAiMessageToHtml(raw);
                if (html) {
                    b.innerHTML = html;
                } else {
                    b.innerHTML = escapeHtml(raw).replace(/\n/g, '<br>');
                }
                b.dataset.processed = '1';
            });
        }

        function nl2brSafe(s) {
            return escapeHtml(s).replace(/\r\n|\r|\n/g, '<br>');
        }

        function tryParseJsonFromText(text, keyHint = null) {
            if (!text) return null;
            let m = text.match(/(\{[\s\S]*\}|\[[\s\S]*\])/m);
            if (m) {
                try {
                    return JSON.parse(m[0]);
                } catch (e) {
                }
            }
            if (keyHint) {
                const rx = new RegExp(keyHint.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + '\\s*:\\s*([\\s\\S]*$)', 'i');
                m = text.match(rx);
                if (m && m[1]) {
                    const candidate = m[1].trim();
                    const jmatch = candidate.match(/(\{[\s\S]*\}|\[[\s\S]*\])/m);
                    if (jmatch) {
                        try { return JSON.parse(jmatch[0]); } catch(e) {}
                    }
                }
            }
            return null;
        }

        processAiBubbles();

        function parseConceptsFromReply(text) {
            const jsonFromOutput = (function() {
                const rx = /JSON_OUTPUT\s*:\s*([\s\S]*$)/i;
                const m = text.match(rx);
                if (m && m[1]) {
                    const j = tryParseJsonFromText(m[1]);
                    if (Array.isArray(j)) return j;
                    if (j && j.concepts && Array.isArray(j.concepts)) return j.concepts;
                }
                return null;
            })();
            if (Array.isArray(jsonFromOutput)) {
                return jsonFromOutput.map(it => ({
                    concept: (it.concept || it.title || '').toString().trim(),
                    definition: (it.definition || it.def || it.description || '').toString().trim()
                })).filter(it => it.concept || it.definition);
            }

            const beforeJson = text.split(/JSON_OUTPUT|Generated Questions|Generated Questions:/i)[0];
            const lines = beforeJson.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
            const concepts = [];
            for (const ln of lines) {
                let m = ln.match(/^[\-\*\u2022]?\s*(?:\*\*?(.+?)\*\*?|(.+?))\s*[:\-]\s*(.+)$/);
                if (!m) {
                    m = ln.match(/^(.+?)\s*[:\-]\s*(.+)$/);
                }
                if (m) {
                    const concept = (m[1] || m[2] || '').replace(/^\*+|\*+$/g,'').trim();
                    const def = (m[3] || m[2] || '').trim();
                    if (concept && def) concepts.push({ concept, definition: def });
                }
            }

            return concepts;
        }

        function renderConceptsToHtml(concepts) {
            if (!concepts || concepts.length === 0) {
                return '<div class="small-muted">No key concepts found.</div>';
            }
            let out = `<div class="small-muted">Key Concepts</div>`;
            for (const c of concepts) {
                const safeTitle = escapeHtml(c.concept || '').replace(/^\*+|\*+$/g,'');
                const safeDef = escapeHtml(c.definition || '');
                out += `<div class="concept-item">` +
                    `<div class="concept-title">${safeTitle}:</div>` +
                    `<div class="concept-def">• ${safeDef}</div>` +
                    `</div>`;
            }
            return out;
        }

        function parseQuestionsFromReply(jsonOrText) {
            if (!jsonOrText) return [];

            if (typeof jsonOrText === 'object') {
                if (Array.isArray(jsonOrText.questions)) {
                    return jsonOrText.questions.map((q,i) => normalizeQuestion(q, i+1));
                }
                if (Array.isArray(jsonOrText)) {
                    return jsonOrText.map((q,i) => normalizeQuestion(q, i+1));
                }
            }

            if (typeof jsonOrText === 'string') {
                const j = tryParseJsonFromText(jsonOrText);
                if (j) return parseQuestionsFromReply(j);
                const parts = jsonOrText.split(/\n{2,}/).map(p => p.trim()).filter(p=>p);
                const extracted = [];
                for (const p of parts) {
                    const qmatch = p.match(/["']?question_text["']?\s*[:=]\s*["'](.+?)["']/i)
                        || p.match(/^Q(?:uestion)?\s*\d+\s*[:\-]\s*(.+)$/i);
                    const amatch = p.match(/["']?answer["']?\s*[:=]\s*["'](.+?)["']/i)
                        || p.match(/Answer\s*[:\-]\s*(.+)$/i);
                    if (qmatch) {
                        extracted.push({
                            question_text: (qmatch[1]||'').trim(),
                            answer: (amatch && amatch[1]) ? amatch[1].trim() : '',
                            question_type: 'qa',
                            choices: []
                        });
                    }
                }
                if (extracted.length) return extracted.map((q,i)=>normalizeQuestion(q,i+1));
            }
            return [];
        }

        function normalizeQuestion(q, number = 0) {
            const question_text = (q.question_text || q.q || q.text || '').toString().trim();
            const question_type = (q.question_type || q.type || (Array.isArray(q.choices) && q.choices.length ? 'mcq' : 'qa') || 'qa').toString();
            const choices = Array.isArray(q.choices) ? q.choices : (typeof q.choices === 'string' ? q.choices.split(/\n/).map(s=>s.trim()).filter(Boolean) : []);
            const answer = (q.answer || q.ans || q.correct || '').toString().trim();
            return { question_text, question_type, choices, answer, order: q.order || number, raw: q };
        }

        function renderQuestionsToHtml(questions) {
            if (!questions || questions.length === 0) {
                return '<div class="small-muted">No questions generated.</div>';
            }
            let out = `<div class="small-muted">Questions</div>`;
            questions.forEach((q, idx) => {
                out += `<div class="question-block">` +
                    `<div class="q-label">Question ${idx+1}: ${escapeHtml(q.question_text)}</div>`;

                if (q.question_type === 'mcq' && q.choices && q.choices.length) {
                    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    out += `<ul class="mcq-options">`;
                    q.choices.forEach((c,i) => {
                        const lab = letters[i] || String.fromCharCode(65 + i);
                        out += `<li><strong>${lab}.</strong> ${escapeHtml(c)}</li>`;
                    });
                    out += `</ul>`;
                    if (q.answer) {
                        out += `<div class="q-answer"><strong>Answer:</strong> ${escapeHtml(q.answer)}</div>`;
                    }
                } else {
                    if (q.answer) {
                        out += `<div class="q-answer"><strong>Answer:</strong> ${escapeHtml(q.answer)}</div>`;
                    } else {
                        out += `<div class="q-answer small-muted">Answer: (not provided)</div>`;
                    }
                }

                out += `</div>`;
            });
            return out;
        }

        document.addEventListener('click', async (ev) => {
            const genBtn = ev.target.closest ? ev.target.closest('.generate-question-btn') : null;
            const keyBtn = ev.target.closest ? ev.target.closest('.key-concepts-btn') : null;

            if (genBtn) {
                ev.preventDefault();
                const responseText = genBtn.getAttribute('data-response') || '';
                const mid = genBtn.getAttribute('data-message-id') || '';
                const ctx = document.getElementById('questionContext');
                if (ctx) {
                    ctx.value = responseText;
                    ctx.dataset.messageId = mid;
                }
                const form = document.getElementById('questionForm');
                if (form) form.dataset.messageId = mid;
                const modalEl = document.getElementById('questionModal');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
                return;
            }

            if (keyBtn) {
                ev.preventDefault();

                const originBubble = keyBtn.closest('.chat-bubble');
                const responseText = keyBtn.getAttribute('data-response') || '';
                const mid = keyBtn.getAttribute('data-message-id') || '';

                keyBtn.disabled = true;
                const prevHtml = keyBtn.innerHTML;
                keyBtn.innerHTML = 'Extracting…';

                try {
                    const fd = new FormData();
                    fd.append('context', responseText);

                    if (!SESSION_ID) throw new Error('Missing SESSION_ID on page');

                    const url = `/chat/${SESSION_ID}/key-concepts`;
                    const res = await postJson(url, fd);

                    if (!res.ok) {
                        const txt = await res.text().catch(()=>null);
                        console.error('Key concepts request failed', res.status, txt);
                        let message = txt || `HTTP ${res.status}`;
                        try { const j = JSON.parse(txt || '{}'); message = j.message || j.error || message; } catch(_) {}
                        throw new Error(message);
                    }

                    const json = await res.json();
                    const raw = json.concepts || json.concept_text || '';

                    const parsed = parseConceptsFromReply(raw);
                    const html = renderConceptsToHtml(parsed);

                    const newBubble = document.createElement('div');
                    newBubble.className = 'chat-bubble system';
                    newBubble.innerHTML = `<div class="bubble-content">${html}</div>
                        <div class="bubble-actions">
                            <button type="button" class="download-btn btn btn-sm btn-outline-light" title="Download"><i class="fas fa-download"></i></button>
                        </div>`;

                    if (originBubble && originBubble.parentNode) {
                        originBubble.parentNode.insertBefore(newBubble, originBubble.nextSibling);
                    } else {
                        document.getElementById('chatMessages')?.appendChild(newBubble);
                    }

                    if (typeof window.attachDownloadHandlerToButton === 'function') {
                        window.attachDownloadHandlerToButton(newBubble.querySelector('.download-btn'));
                    } else if (typeof attachDownloadHandlerToButton === 'function') {
                        attachDownloadHandlerToButton(newBubble.querySelector('.download-btn'));
                    }

                    newBubble.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                } catch (err) {
                    console.error('Key concepts failed', err);
                    alert('Failed to extract key concepts: ' + (err?.message || err));
                } finally {
                    keyBtn.disabled = false;
                    keyBtn.innerHTML = prevHtml;
                }
                return;
            }
        });

        document.getElementById('questionForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);

            const mid = form.dataset.messageId || document.getElementById('questionContext')?.dataset?.messageId;
            if (mid) fd.append('message_id', mid);

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const prevText = submitBtn.innerHTML;
                submitBtn.dataset.prev = prevText;
                submitBtn.innerHTML = 'Generating…';
            }

            try {
                if (!SESSION_ID) throw new Error('Missing SESSION_ID on page');
                const url = `/chat/${SESSION_ID}/generate-questions`;
                const res = await postJson(url, fd);

                if (!res.ok) {
                    const txt = await res.text().catch(()=>null);
                    console.error('Generate questions failed', res.status, txt);
                    let message = txt || `HTTP ${res.status}`;
                    try { const j = JSON.parse(txt || '{}'); message = j.message || j.error || message; } catch(_) {}
                    throw new Error(message);
                }

                const json = await res.json();

                let parsedQuestions = [];
                if (json.questions && Array.isArray(json.questions)) {
                    parsedQuestions = json.questions.map((q,i)=>normalizeQuestion(q, i+1));
                } else if (json.questions_text || json.questions_text === '') {
                    parsedQuestions = parseQuestionsFromReply(json.questions_text || json.questions_text === '' ? json.questions_text : '');
                } else if (json.questions) {
                    parsedQuestions = parseQuestionsFromReply(json.questions);
                } else {
                    const raw = json.questions_text || json.questions_text || (json.questions_text ? json.questions_text : (json.questions_text || json.questions_text));
                    const rawCandidate = json.questions_text || json.questions_text || json.questions_text || json.questions_text || '';
                    parsedQuestions = parseQuestionsFromReply(rawCandidate || json.questions_text || json.questions_text || (json.questions_text || ''));
                }

                if ((!parsedQuestions || parsedQuestions.length === 0) && (json.questions_text || json.questions_text === '')) {
                    parsedQuestions = parseQuestionsFromReply(json.questions_text || '');
                }
                if ((!parsedQuestions || parsedQuestions.length === 0) && json.questions_text) {
                    parsedQuestions = parseQuestionsFromReply(json.questions_text);
                }

                if ((!parsedQuestions || parsedQuestions.length === 0) && json.questions_text) {
                    parsedQuestions = parseQuestionsFromReply(json.questions_text);
                }

                if ((!parsedQuestions || parsedQuestions.length === 0)) {
                    for (const k of Object.keys(json)) {
                        if (typeof json[k] === 'string' && json[k].length > 10) {
                            const cand = parseQuestionsFromReply(json[k]);
                            if (cand && cand.length) { parsedQuestions = cand; break; }
                        }
                    }
                }

                const html = renderQuestionsToHtml(parsedQuestions);

                const newBubble = document.createElement('div');
                newBubble.className = 'chat-bubble system';
                newBubble.innerHTML = `<div class="bubble-content">${html}</div>
                    <div class="bubble-actions"><button type="button" class="download-btn btn btn-sm btn-outline-light" title="Download"><i class="fas fa-download"></i></button></div>`;

                let inserted = false;
                if (mid) {
                    const originBtn = document.querySelector(`.generate-question-btn[data-message-id="${mid}"]`);
                    const originBubble = originBtn ? originBtn.closest('.chat-bubble') : null;
                    if (originBubble && originBubble.parentNode) {
                        originBubble.parentNode.insertBefore(newBubble, originBubble.nextSibling);
                        inserted = true;
                    }
                }
                if (!inserted) document.getElementById('chatMessages')?.appendChild(newBubble);

                if (typeof window.attachDownloadHandlerToButton === 'function') {
                    window.attachDownloadHandlerToButton(newBubble.querySelector('.download-btn'));
                } else if (typeof attachDownloadHandlerToButton === 'function') {
                    attachDownloadHandlerToButton(newBubble.querySelector('.download-btn'));
                }

                const modalEl = document.getElementById('questionModal');
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();

                newBubble.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            } catch (err) {
                console.error('Generate questions failed', err);
                alert('Failed to generate questions: ' + (err?.message || err));
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.prev || 'Generate';
                }
            }
        });
    });
</script>
@endsection
