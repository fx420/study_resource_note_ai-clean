@extends('layouts.index')

@section('title',$session->title)

@section('content')
<div class="container py-4">
  <h3 class="text-white">{{ $session->title }}</h3>
  <div id="chatMessages" class="chat-messages mb-3">
    @foreach($messages as $m)
      <div class="chat-bubble {{ $m->sender }}">
        <div class="bubble-content">{{ $m->message }}</div>
      </div>
    @endforeach
  </div>
  <form id="chatForm" action="{{ route('chat.session.submit',$session) }}" method="POST">
    @csrf
    <div class="input-wrapper">
      <textarea name="prompt" class="form-control prompt-input" rows="1"></textarea>
      <button type="submit" class="btn btn-primary btn-send"><i class="fas fa-paper-plane"></i></button>
    </div>
  </form>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('js/chat-box.js') }}"></script>
@endsection
