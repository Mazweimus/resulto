<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Registrace - Resulto Darts Manager</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/font-awesome/css/font-awesome.min.css') }}">
    <!-- Layout styles -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.png') }}" />
</head>
<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth">
                <div class="row flex-grow">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-start p-5 shadow-sm rounded">
                            <div class="brand-logo text-center mb-4">
                                <a href="{{ url('/') }}" class="text-decoration-none d-inline-flex align-items-center">
                                    <i class="mdi mdi-bullseye-arrow text-primary me-2 fs-1"></i>
                                    <span class="fs-2 fw-bold" style="background: linear-gradient(to right, #da8cff, #9a55ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">RESULTO</span>
                                </a>
                                <p class="text-muted text-small mt-1 mb-0">Darts Tournament Manager</p>
                            </div>

                            <h4 class="fw-bold">Nová registrace</h4>
                            <h6 class="font-weight-light mb-4">Vytvořte si účet pro správu turnajů a hráčů.</h6>

                            @if(isset($errors) && $errors->any())
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <ul class="mb-0 ps-3">
                                        @foreach($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <form class="pt-2" method="POST" action="{{ route('register') }}">
                                @csrf
                                <div class="form-group mb-3">
                                    <label class="form-label text-small fw-bold">Jméno a příjmení</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="mdi mdi-account-outline"></i></span>
                                        <input type="text" name="name" class="form-control form-control-lg" placeholder="např. David Grey" value="{{ old('name') }}" required autofocus>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label text-small fw-bold">E-mailová adresa</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="mdi mdi-email-outline"></i></span>
                                        <input type="email" name="email" class="form-control form-control-lg" placeholder="vas.email@example.com" value="{{ old('email') }}" required>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label text-small fw-bold">Heslo</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="mdi mdi-lock-outline"></i></span>
                                        <input type="password" name="password" class="form-control form-control-lg" placeholder="Minimálně 6 znaků" required>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label text-small fw-bold">Potvrzení hesla</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="mdi mdi-lock-check-outline"></i></span>
                                        <input type="password" name="password_confirmation" class="form-control form-control-lg" placeholder="Zadejte heslo znovu" required>
                                    </div>
                                </div>

                                <div class="mt-4 d-grid gap-2">
                                    <button type="submit" class="btn btn-gradient-primary btn-lg font-weight-medium auth-form-btn py-3">
                                        <i class="mdi mdi-account-plus me-1"></i> ZAREGISTROVAT SE
                                    </button>
                                </div>

                                <div class="text-center mt-4 font-weight-light">
                                    Již máte účet? <a href="{{ route('login') }}" class="text-primary fw-bold">Přihlaste se</a>
                                </div>

                                <div class="text-center mt-3">
                                    <a href="{{ url('/') }}" class="text-muted text-small text-decoration-none">
                                        <i class="mdi mdi-arrow-left me-1"></i> Zpět na přehled dashboardu
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- plugins:js -->
    <script src="{{ asset('assets/vendors/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('assets/js/misc.js') }}"></script>
</body>
</html>
