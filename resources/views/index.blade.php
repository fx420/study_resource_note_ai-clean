@extends('layouts.index')

@section('title', 'Home - Study Resource Note AI')
@section('showWelcome', true)

@section('content')
<div class="hero-section">
    <div class="container text-center py-5">
        <h1 class="display-4 fw-bold">Welcome to Study Resource Note AI</h1>
        <p class="lead text">Generate personalized study notes and resources using the power of AI.</p>
            <button href="#" id="openChatModal" class="btn btn-primary btn-lg mt-3">Get Started</button >
    </div>
</div>

<div class="features-section bg-transparent py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <i class="fas fa-book fa-3x text-primary mb-3"></i>
                <h5>Upload Study Materials</h5>
                <p>Upload your notes, books or PDFs to generate summaries and key points instantly.</p>
            </div>
            <div class="col-md-4 mb-4">
                <i class="fas fa-brain fa-3x text-danger mb-3"></i>
                <h5>Powered by AI</h5>
                <p>Our system uses a Large Language Model to analyze and understand your content.</p>
            </div>
            <div class="col-md-4 mb-4">
                <i class="fas fa-lightbulb fa-3x text-warning mb-3"></i>
                <h5>Smart Suggestions</h5>
                <p>Receive personalized resource recommendations based on your uploaded content.</p>
            </div>
        </div>
    </div>
</div>

<!-- Chat Modal -->
<div id="chatModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3 p-4">
      <div class="modal-header border-0">
        <h5 class="modal-title">Chat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
        <div class="modal-body">
            <x-chat-box :session="$session ?? null" />
        </div>
    </div>
  </div>
</div>
@endsection
