@props(['session' => null])

<form method="POST" action="{{ route('chat.session.create') }}" enctype="multipart/form-data" id="chatForm">
  @csrf

  <div class="mb-3">
    <label for="educationLevelInput" class="form-label">Education Level</label>
    <select name="education_level" id="educationLevelInput" class="form-select" required>
      <option value="" disabled selected>Select level</option>
      <option value="high_school">High School</option>
      <option value="undergraduate">Undergraduate</option>
      <option value="postgraduate">Postgraduate</option>
    </select>
  </div>

  <div class="mb-3">
    <label for="courseInput" class="form-label">Course</label>
    <input type="text" name="course" id="courseInput" class="form-control" placeholder="Enter Course" required>
  </div>

  <div class="mb-3">
    <label for="subjectInput" class="form-label">Subject</label>
    <input type="text" name="subject" id="subjectInput" class="form-control" placeholder="Enter Subject" required>
  </div>

  <div class="mb-3">
    <label for="topicInput" class="form-label">Topic</label>
    <input type="text" name="topic" id="topicInput" class="form-control" placeholder="Enter Topic" required>
  </div>

  <div class="mb-3">
    <label for="priorKnowledgeInput" class="form-label">Prior Knowledge</label>
    <input type="text" name="prior_knowledge" id="priorKnowledgeInput" class="form-control" placeholder="e.g., Basic algebra">
  </div>

  <div class="mb-3">
    <label for="learningGoalInput" class="form-label">Learning Goal</label>
    <input type="text" name="learning_goal" id="learningGoalInput" class="form-control" placeholder="e.g., Understand concepts">
  </div>

  <div class="mb-3">
    <label for="noteLevelInput" class="form-label">Difficulty Level</label>
    <select name="note_level" id="noteLevelInput" class="form-select">
      <option value="1">1 - Very Easy</option>
      <option value="2">2 - Easy</option>
      <option value="3" selected>3 - Medium</option>
      <option value="4">4 - Hard</option>
      <option value="5">5 - Very Hard</option>
    </select>
  </div>

  <div class="mb-3">
    <label for="examplesCountInput" class="form-label">Examples Count</label>
    <input type="number" name="examples_count" id="examplesCountInput" class="form-control" min="0" placeholder="Number of examples">
  </div>

  <div class="mb-3">
    <label for="contentFormatInput" class="form-label">Content Format</label>
    <select name="content_format" id="contentFormatInput" class="form-select">
      <option value="text">Text</option>
      <option value="bullet">Bullet Points</option>
      <option value="table">Table</option>
    </select>
  </div>

  <div class="mb-3">
    <label for="modeInput" class="form-label">Mode</label>
    <select name="mode" id="modeInput" class="form-select" required>
      <option value="direct">Direct Note</option>
      <option value="prompt">Custom Prompt</option>
    </select>
  </div>

  <div class="input-wrapper d-flex align-items-center gap-2 mt-4">
    <textarea name="prompt" id="promptInput" class="form-control prompt-input" placeholder="Prompt" rows="2"></textarea>

    <input type="hidden" name="file_prompt" id="filePromptInput" value="">
    <input type="hidden" name="file_original_name" id="fileOriginalNameInput" value="">

    <div id="uploadedFileInfo" class="mt-2 small text-muted d-flex align-items-start" style="display:none;">
      <i class="uploaded-file-icon fas fa-file me-2" aria-hidden="true"></i>
      <div>
        <span id="uploadedFileName" class="fw-semibold"></span>
        <div id="uploadedFileMeta" class="small text-muted"></div>
      </div>
    </div>

    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('fileInput').click()">
      <i class="fas fa-paperclip"></i>
    </button>

    <input type="file" name="file" id="fileInput" class="d-none" accept=".txt,.pdf,.docx">
    <input type="hidden" name="file_path" id="filePromptInput" value="">
    <input type="hidden" name="file_original_name" id="fileOriginalNameInput" value="">
    <input type="hidden" name="file_snippet" id="fileSnippetInput" value="">
  </div>
  
  <div class="d-flex align-items-center gap-2 mt-2">
    <button type="submit" id="submitBtn" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
    <div id="chatStatus" class="ms-2" style="min-height:1.5em;"></div>
  </div>
  
</form>

@push('scripts')
<script src="{{ asset('js/chat-box.js') }}"></script>
@endpush
