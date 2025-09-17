@extends('layouts.index')

@section('title', 'Library - Study Resource Note AI')

@section('styles')
    <style>
        .library-card-wrap .card {
            overflow: visible;
        }
        .library-card-wrap .card-actions .btn {
            padding: .35rem .45rem;
            border-radius: .35rem;
        }

        @media (max-width: 576px) {
            .library-card-wrap .card-actions {
                top: 8px;
                right: 8px;
            }
        }
    </style>
@endsection

@section('content')
<div class="library-page">
    <div class="container my-5 library-container">

        <h2 class="text-white mb-4">Note Library</h2>

        @if(count($notes))
        <div class="library-grid">
            @foreach($notes as $note)
            <div id="session-{{ $note['id'] }}" class="library-card-wrap position-relative mb-3">
                <div class="card library-card bg-dark border-secondary">
                    <div class="card-body position-relative">
                        <div class="card-actions position-absolute" style="top:10px; right:10px; z-index:5;">
                            <button
                                class="btn btn-sm btn-outline-danger delete-session-btn"
                                data-session-id="{{ $note['id'] }}"
                                title="Delete chat"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>

                        <h5 class="card-title text-white">{{ $note['title'] }}</h5>

                        <p class="card-text text-light">
                            {{ \Illuminate\Support\Str::limit($note['content'], 150) }}
                        </p>

                        <small class="text-muted">Generated on {{ \Carbon\Carbon::parse($note['created_at'])->format('Y-m-d') }}</small>
                    </div>

                    <div class="card-footer bg-transparent border-0 text-end">
                        <a href="data:text/plain;charset=utf-8,{{ rawurlencode($note['content']) }}"
                        download="{{ \Illuminate\Support\Str::slug($note['title'] ?: 'note') }}.txt"
                        class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-download"></i>
                        </a>

                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
            <p class="text-light">No notes found in your library.</p>
        @endif

  </div>
</div>
@endsection


@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const CSRF_TOKEN = tokenMeta ? tokenMeta.getAttribute('content') : '';

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed end-0 top-0 m-3`;
            toast.style.zIndex = '9999';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('fade');
                setTimeout(() => toast.remove(), 400);
            }, 2200);
        }

        async function deleteSession(sessionId, cardElement) {
            if (!confirm('Delete this chat? This cannot be undone.')) return;

            try {
                const res = await fetch(`/library/${sessionId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json().catch(() => ({}));

                if (res.ok) {
                    cardElement.remove();
                    showToast('Chat deleted.', 'success');
                } else {
                    console.error('Delete failed', data);
                    const msg = data?.error ?? data?.message ?? 'Delete failed';
                    showToast(msg, 'danger');
                }
            } catch (err) {
                console.error('Delete error', err);
                showToast('Network or server error.', 'danger');
            }
        }

        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.delete-session-btn');
            if (!btn) return;
            const sessionId = btn.getAttribute('data-session-id');
            if (!sessionId) return;

            const wrapper = document.getElementById('session-' + sessionId);
            deleteSession(sessionId, wrapper);
        });
    }); 
</script>
@endsection