<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Darts Tournament Manager') - Resulto</title>

    <!-- plugins:css -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/select2/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css') }}">

    <!-- Layout styles -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" />

    <style>
        .navbar .navbar-brand-wrapper .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #b66dff;
        }
        .darts-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .bracket-node {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            border: 1px solid #ebedf2;
            padding: 12px;
            margin-bottom: 16px;
            transition: transform 0.2s;
        }
        .bracket-node:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(182,109,255,0.15);
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .form-select, .select2-container--bootstrap {
            border-radius: 4px;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container-scroller">
        <!-- Top Navbar -->
        <nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
                <a class="navbar-brand brand-logo d-flex align-items-center text-decoration-none" href="{{ url('/') }}">
                    <i class="mdi mdi-bullseye-arrow text-primary me-2 fs-3"></i>
                    <span class="fs-4 fw-bold" style="background: linear-gradient(to right, #da8cff, #9a55ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">RESULTO</span>
                </a>
                <a class="navbar-brand brand-logo-mini" href="{{ url('/') }}">
                    <i class="mdi mdi-bullseye-arrow text-primary fs-3"></i>
                </a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-stretch">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="mdi mdi-menu"></span>
                </button>
                <div class="search-field d-none d-md-block">
                    <div class="d-flex align-items-center h-100">
                        <span class="badge badge-gradient-primary py-2 px-3">
                            <i class="mdi mdi-bullseye me-1"></i> Darts Tournament Manager
                        </span>
                    </div>
                </div>
                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" id="profileDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="nav-profile-img d-inline-flex align-items-center justify-content-center bg-gradient-primary rounded-circle text-white me-2" style="width: 32px; height: 32px;">
                                <i class="mdi mdi-account"></i>
                            </div>
                            <div class="nav-profile-text">
                                <p class="mb-1 text-black">{{ auth()->check() ? auth()->user()->name : 'Host / Šipkař' }}</p>
                            </div>
                        </a>
                        <div class="dropdown-menu navbar-dropdown" aria-labelledby="profileDropdown">
                            @auth
                                <span class="dropdown-item text-muted disabled">
                                    <i class="mdi mdi-account-circle me-2 text-info"></i> {{ auth()->user()->email }}
                                </span>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}" id="nav-logout-form">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="mdi mdi-logout me-2 text-primary"></i> Odhlásit se
                                    </button>
                                </form>
                            @else
                                <a class="dropdown-item" href="{{ route('login') }}">
                                    <i class="mdi mdi-login me-2 text-success"></i> Přihlásit se
                                </a>
                                <a class="dropdown-item" href="{{ route('register') }}">
                                    <i class="mdi mdi-account-plus me-2 text-primary"></i> Registrovat se
                                </a>
                            @endauth
                        </div>
                    </li>
                    <li class="nav-item d-none d-lg-block full-screen-link">
                        <a class="nav-link" title="Celá obrazovka">
                            <i class="mdi mdi-fullscreen" id="fullscreen-button"></i>
                        </a>
                    </li>
                    <li class="nav-item nav-logout d-none d-lg-block">
                        @auth
                            <a class="nav-link" href="javascript:void(0)" onclick="document.getElementById('nav-logout-form').submit();" title="Odhlásit se">
                                <i class="mdi mdi-power"></i>
                            </a>
                        @else
                            <a class="nav-link" href="{{ route('login') }}" title="Přihlásit se">
                                <i class="mdi mdi-login"></i>
                            </a>
                        @endauth
                    </li>
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="mdi mdi-menu"></span>
                </button>
            </div>
        </nav>

        <!-- Body Wrapper -->
        <div class="container-fluid page-body-wrapper">
            <!-- Sidebar Component -->
            <x-sidebar />

            <!-- Main Panel -->
            <div class="main-panel">
                <div class="content-wrapper">
                    {{-- Flash notifications --}}
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-circle me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    @if(isset($errors) && $errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong><i class="mdi mdi-alert-octagon me-2"></i> Pozor na chyby ve formuláři:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @yield('content')
                </div>

                <!-- Footer -->
                <footer class="footer">
                    <div class="d-sm-flex justify-content-center justify-content-sm-between">
                        <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Resulto &copy; {{ date('Y') }} — Systém pro správu šipkařských turnajů (501 / 301 Double Out)</span>
                        <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Šipky s vášní <i class="mdi mdi-bullseye-arrow text-danger"></i></span>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- plugins:js -->
    <script src="{{ asset('assets/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('assets/vendors/chart.js/chart.umd.js') }}"></script>
    <script src="{{ asset('assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/select2/select2.min.js') }}"></script>

    <!-- inject:js -->
    <script src="{{ asset('assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('assets/js/misc.js') }}"></script>
    <script src="{{ asset('assets/js/settings.js') }}"></script>
    <script src="{{ asset('assets/js/todolist.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.cookie.js') }}"></script>

    @stack('scripts')
</body>
</html>
