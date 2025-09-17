@extends('layouts.index')

@section('title', 'Login – Study Resource Note AI')
@section('hideFooter', true)
@section('hideChatBox', true)

@section('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endsection

@section('scripts')
<script src="{{ asset('js/auth/auth.js') }}"></script>
@endsection

@section('content')
<div class="d-flex justify-content-center align-items-center" style="min-height:80vh;">
  <div class="card login-card p-4" style="width: 400px; background-color:#212529;">
    <h3 class="text-center text-white mb-4">Sign In</h3>

    @if($errors->has('username'))
      <div class="alert alert-danger">
        {{ $errors->first('username') }}
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate>
      @csrf

      <div class="mb-3">
        <label for="username" class="form-label text-white">Username</label>
        <input type="text" name="username" id="username" class="form-control bg-secondary text-white @error('username') is-invalid @enderror"
          value="{{ old('username') }}" required autofocus>
        @error('username')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>

      <div class="mb-3">
        <label for="password" class="form-label text-white">Password</label>
        <div class="input-group">
          <input type="password" name="password" id="password" class="form-control bg-secondary text-white @error('password') is-invalid @enderror"
            required>
          <button class="btn btn-outline-light" type="button" id="togglePassword">
            <i class="fas fa-eye"></i>
          </button>
          @error('password')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
      </div>

      <div class="text-center">
        <button type="submit" class="btn btn-danger px-5">Login</button>
      </div>
    </form>

    <p class="mt-3 text-center text-white">
      Don’t have an account?
      <a href="{{ route('register') }}" class="text-danger">Register</a>
    </p>
  </div>
</div>
@endsection
