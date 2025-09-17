<header class="navbar navbar-expand-lg navbar-dark bg-dark px-4">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark w-100">

        <button class="btn btn-outline-light me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand" href="{{ route('index') }}">Study Resource Note AI</a>

        <div class="ms-auto">
            <ul class="navbar-nav">
                @if(Auth::check())
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle btn btn-outline-light px-3 d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #1e1e1e;">
                            <i class="fas fa-user-circle"></i> 
                            <span>Hi, {{ Auth::user()->username }}</span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="{{ route('profile.index') }}" >Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>

                                <a class="dropdown-item text-danger" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    Log Out
                                </a>
                                
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>

                            </li>
                        </ul>
                    </li>
                @else

                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light px-3" href="{{ route('login') }}">Sign In</a>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
</header>
