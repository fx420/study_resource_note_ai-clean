(function(){
    const KEY = 'srn_event_queue';
    const BATCH_URL = '/api/interaction/batch';

    function getQueue(){ return JSON.parse(localStorage.getItem(KEY) || '[]'); }
    function setQueue(q){ localStorage.setItem(KEY, JSON.stringify(q)); }

    async function flushQueue(){
        const q = getQueue();
        if (!q.length) return;

        const normalizeDate = (iso) => {
            try {
                const d = new Date(iso);
                if (isNaN(d)) return null;
                const pad = n => String(n).padStart(2, '0');
                const Y = d.getFullYear();
                const M = pad(d.getMonth() + 1);
                const D = pad(d.getDate());
                const hh = pad(d.getHours());
                const mm = pad(d.getMinutes());
                const ss = pad(d.getSeconds());
                return `${Y}-${M}-${D} ${hh}:${mm}:${ss}`;
            } catch (e) {
                return null;
            }
        };

        const payload = q.map(evt => {
            const ev = Object.assign({}, evt);
            if (ev.created_at && typeof ev.created_at === 'string') {
                const normalized = normalizeDate(ev.created_at);
                if (normalized) ev.created_at = normalized;
                else delete ev.created_at;
            }
            return ev;
        });

        try {
            const res = await fetch(BATCH_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin',
                body: JSON.stringify({ events: payload })
            });

            if (!res.ok) {
                const txt = await res.text().catch(()=>null);
                console.error('analytics flush failed:', res.status, txt);
                return;
            }

            setQueue([]);
        } catch (e) {
            console.warn('analytics flush failed', e);
        }
    }

    window.logEvent = function(type, metadata = {}) {
        const q = getQueue();
        q.push({ event_type: type, metadata, created_at: new Date().toISOString() });
        setQueue(q);
        void flushQueue();
    };

    window.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flushQueue(); });
    window.addEventListener('online', flushQueue);
    window.addEventListener('load', () => setTimeout(flushQueue, 1000));
})();
