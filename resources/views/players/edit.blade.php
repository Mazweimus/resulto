@extends('layouts.app')

@section('title', 'Úprava hráče: ' . $player->name)

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-info text-white me-2">
            <i class="mdi mdi-account-edit"></i>
        </span> Úprava hráče
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('players.index') }}">Správa hráčů</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $player->name }}</li>
        </ul>
    </nav>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7 grid-margin stretch-card">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3 text-info shadow-sm" style="width: 48px; height: 48px;">
                            <i class="mdi mdi-account fs-3"></i>
                        </div>
                        <div>
                            <h4 class="card-title mb-0 text-info">Úprava údajů: {{ $player->name }}</h4>
                            <small class="text-muted">Pohlaví: {{ $player->gender_label }}</small>
                        </div>
                    </div>
                    <a href="{{ route('players.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-arrow-left me-1"></i> Zpět na seznam
                    </a>
                </div>

                <form action="{{ route('players.update', $player->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="name" class="fw-bold">Jméno a příjmení <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="mdi mdi-account"></i></span>
                            <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $player->name) }}" required>
                        </div>
                        @error('name')
                            <div class="text-danger text-small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="fw-bold d-block mb-2">Pohlaví <span class="text-danger">*</span></label>
                        <div class="d-flex align-items-center gap-4 gender-radio-group">
                            <div class="form-check d-flex align-items-center gap-2 m-0 p-0">
                                <input class="form-check-input m-0" type="radio" name="gender" id="gender_male" value="male" {{ old('gender', $player->gender) === 'male' ? 'checked' : '' }} style="float: none; margin-left: 0 !important; cursor: pointer;">
                                <label class="form-check-label m-0 p-0 fw-bold text-dark" for="gender_male" style="margin-left: 0 !important; cursor: pointer;">
                                    <i class="mdi mdi-gender-male text-primary me-1"></i> Muž
                                </label>
                            </div>
                            <div class="form-check d-flex align-items-center gap-2 m-0 p-0">
                                <input class="form-check-input m-0" type="radio" name="gender" id="gender_female" value="female" {{ old('gender', $player->gender) === 'female' ? 'checked' : '' }} style="float: none; margin-left: 0 !important; cursor: pointer;">
                                <label class="form-check-label m-0 p-0 fw-bold text-dark" for="gender_female" style="margin-left: 0 !important; cursor: pointer;">
                                    <i class="mdi mdi-gender-female text-danger me-1"></i> Žena
                                </label>
                            </div>
                        </div>
                        @error('gender')
                            <div class="text-danger text-small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <a href="{{ route('players.index') }}" class="btn btn-light py-3 px-4">
                            Zrušit
                        </a>
                        <button type="submit" class="btn btn-gradient-info py-3 px-5">
                            <i class="mdi mdi-check me-1"></i> Uložit změny
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
