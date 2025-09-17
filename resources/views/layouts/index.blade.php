<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Bootstrap CSS -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="{{ asset('css/swiper-bundle.min.css') }}">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="{{ asset('css/sweetalert2.min.css') }}">
    <!-- SweetAlert2 Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/sweetalert2-custom.css') }}">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/chat-box.css') }}">
    <link rel="stylesheet" href="{{ asset('css/library.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">

    @yield('styles')

    <title>@yield('title', 'Study Resource Note AI')</title>
</head>
<body>
    
    <!-- Sidebar Component -->
    @auth
      <x-sidebar />
    @endauth

    <!-- Header -->
    <x-header />

    <!-- Main Content -->
    <main class="container mt-4">
        @yield('content')
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('newChatBtn');
        if (!btn) return;

        btn.addEventListener('click', () => {
            Swal.fire({
            title: 'Start a new chat?',
            text:  'This will clear your current conversation. Continue?',
            icon:  'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, start new',
            cancelButtonText: 'Cancel',
            }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = btn.dataset.route;
            }
            });
        });
        });
    </script>

    <!-- jQuery-->
    <script src="{{ asset('js/jquery-3.6.0.min.js') }}"></script>
    <!-- Bootstrap JS -->
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    <!-- Swiper JS -->
    <script src="{{ asset('js/swiper-bundle.min.js') }}"></script>
    <!-- SweetAlert2 JS -->
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <!-- Vue JS -->
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
    <!-- Custom JS -->
    <script src="{{ asset('js/chat-box.js') }}"></script>

    @yield('scripts')
</body>
</html>
