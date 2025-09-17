@extends('layouts.index')

@section('title','Admin Panel')

@section('hideChatBox', true)

@section('styles')
  <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container admin-dashboard">
  <h1 class="mb-4 text-white">Admin Dashboard</h1>
  <div class="row gx-4 gy-4">
    <div class="col-md-3">
      <a href="{{ route('admin.prompt-templates.index') }}" class="btn btn-lg btn-primary w-100 py-5"> Manage Prompt Templates </a>
    </div>
    <div class="col-md-3">
      <a href="{{ route('admin.logs.index') }}" class="btn btn-lg btn-secondary w-100 py-5"> View Logs & Usage </a>
    </div>
    <div class="col-md-3">
      <a href="{{ route('admin.moderation.index') }}" class="btn btn-lg btn-warning w-100 py-5 text-dark"> Moderate Content </a>
    </div>
    <div class="col-md-3">
      <a href="{{ route('admin.learned-data.index') }}" class="btn btn-lg btn-success w-100 py-5"> CRUD Learned Data </a>
    </div>
  </div>
</div>
@endsection
