@extends('layouts.index')

@section('title','Prompt Templates')

@section('content')
<div class="container py-5">
  <h2>Prompt Templates</h2>
  <a href="{{ route('admin.prompt-templates.create') }}"
     class="btn btn-sm btn-primary mb-3">New Template</a>

  <table class="table table-dark table-striped">
    <thead>
      <tr><th>Name</th><th>Zero‑Shot</th><th>One‑Shot</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @foreach($prompt_templates as $tpl)
      <tr>
        <td>{{ $tpl->name }}</td>
        <td>{{ Str::limit($tpl->zero_shot_example,30) }}</td>
        <td>{{ Str::limit($tpl->one_shot_example,30) }}</td>
        <td>
          <a href="{{ route('admin.prompt-templates.edit',$tpl) }}" class="btn btn-sm btn-outline-light">Edit</a>
          <form action="{{ route('admin.prompt-templates.destroy',$tpl) }}" method="POST" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
