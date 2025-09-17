@extends('layouts.index')

@section('hideChatBox', true)

@section('title','My Profile')

@section('styles')
  <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endsection

@section('scripts')
  <script src="{{ asset('js/profile.js') }}"></script>
@endsection

@section('content')
<div class="container py-5">
  <div class="card profile-card mx-auto">
    <div class="card-body">

      <h3 class="card-title text-center mb-4 text-white">My Profile</h3>
      <form id="profileForm" action="{{ route('profile.update') }}" method="POST" novalidate>
        @csrf
        @method('PUT')

        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @if (session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('info'))
          <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Username</label>
            <div id="usernameDisplay" class="text-white">
                {{ $user->username ?: 'N/A' }}
            </div>
            <input type="text" name="username" id="usernameInput" class="form-control d-none" value="{{ $user->username }}" pattern="^[^\s]+$" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <div id="emailDisplay" class="text-white">{{ $user->email ?: 'N/A'}}</div>
            <input type="email" name="email" id="emailInput" class="form-control d-none" value="{{ $user->email }}" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Gender</label>
            <div id="genderDisplay" class="text-white">{{ $user->gender ?: 'N/A'}}</div>
            <div id="genderInput" class="gender-toggle d-none">
              <button type="button" class="btn btn-outline-light" data-value="Male">Male</button>
              <button type="button" class="btn btn-outline-light" data-value="Female">Female</button>
            </div>
            <input type="hidden" name="gender" id="genderValue" value="{{ $user->gender }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">Date of Birth</label>
            <div id="dobDisplay" class="text-white">{{ $user->dob ?: 'N/A'}}</div>
            <input type="date" name="dob" id="dobInput" class="form-control d-none" value="{{ $user->dob }}" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Password</label>

            <div id="passwordDisplay" class="text-white">********</div>

            <div class="password-group d-none">
              <div class="input-group">
                <input type="password" name="password" id="passwordInput" class="form-control pw-input" placeholder="Enter new password" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-new-password" title="Show/Hide">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <div class="input-group">
                <input type="password" name="password_confirmation" id="passwordConfirm" class="form-control mt-2 pw-input" placeholder="Confirm new password" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary toggle-new-password2" title="Show/Hide">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button type="button" id="editBtn" class="btn btn-outline-light" data-target="#usernameInput">Edit Profile</button>
          <div id="saveCancelBtns" class="d-none">
            <button type="submit" class="btn btn-success">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="cancelEdit()">Cancel</button>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>
@endsection
