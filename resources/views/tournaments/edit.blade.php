@extends('layouts.app')

@section('title', 'Upravit turnaj: ' . $tournament->name)

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-pencil-box-outline"></i>
        </span> Upravit turnaj
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Turnaje</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tournaments.show', $tournament->id) }}">{{ $tournament->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Upravit</li>
        </ul>
    </nav>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto grid-margin stretch-card">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                    <div>
                        <h4 class="card-title text-primary mb-1">
                            <i class="mdi mdi-trophy-variant text-warning me-2"></i> Nastavení turnaje: {{ $tournament->name }}
                        </h4>
                        <p class="card-description mb-0 text-muted">
                            Upravte parametry turnaje, ročník nebo započítávání do žebříčku.
                        </p>
                    </div>
                    <span class="badge {{ $tournament->status_badge_class }} py-2 px-3">
                        {{ $tournament->status_label }}
                    </span>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger py-2 mb-4">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('tournaments.update', $tournament->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="tournament-name" class="form-label fw-bold text-dark">
                            Název turnaje <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="mdi mdi-trophy text-primary"></i></span>
                            <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="tournament-name" name="name" value="{{ old('name', $tournament->name) }}" required>
                        </div>
                        @error('name')
                            <div class="text-danger text-small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="tournament-date" class="form-label fw-bold text-dark">
                                Datum konání <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-calendar text-primary"></i></span>
                                <input type="date" class="form-control @error('date') is-invalid @enderror" id="tournament-date" name="date" value="{{ old('date', $tournament->date ? $tournament->date->format('Y-m-d') : date('Y-m-d')) }}" required>
                            </div>
                            @error('date')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="tournament-legs" class="form-label fw-bold text-dark">
                                Počet vítězných legů (Best of) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-numeric text-primary"></i></span>
                                <input type="number" class="form-control @error('best_of_legs') is-invalid @enderror" id="tournament-legs" name="best_of_legs" value="{{ old('best_of_legs', $tournament->best_of_legs) }}" min="1" max="35" required>
                            </div>
                            @error('best_of_legs')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-4 align-items-center bg-light p-3 rounded border">
                        <div class="col-md-5">
                            <label for="season-year" class="form-label fw-bold text-dark mb-1">
                                Ročník turnaje <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="mdi mdi-calendar-text text-primary"></i></span>
                                <input type="number" class="form-control @error('season_year') is-invalid @enderror" id="season-year" name="season_year" value="{{ old('season_year', $tournament->season_year ?? date('Y')) }}" min="2020" max="2100" required>
                            </div>
                            @error('season_year')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-7 pt-md-3">
                            <div class="form-check form-switch ps-0 d-flex align-items-center gap-2">
                                <input type="hidden" name="counts_for_ranking" value="0">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="counts_for_ranking" value="1" id="counts-for-ranking" {{ old('counts_for_ranking', (string)$tournament->counts_for_ranking) == '1' || old('counts_for_ranking', $tournament->counts_for_ranking) ? 'checked' : '' }} style="width: 2.4em; height: 1.25em; cursor: pointer;">
                                <div>
                                    <label class="form-check-label fw-bold text-dark mb-0" for="counts-for-ranking" style="cursor: pointer;">
                                        <i class="mdi mdi-trophy-award text-warning me-1"></i> Započítávat do ročníkového žebříčku
                                    </label>
                                    <small class="text-muted d-block" style="font-size: 0.78rem;">Při vypnutí se body z turnaje nezapočítají do ročníkového ani celkového žebříčku (např. exhibice).</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- INFORMATIVNÍ ÚDAJE O TURNAJI (NEMĚNNÉ PO VYTVOŘENÍ) --}}
                    <div class="mb-4 p-3 bg-white border rounded">
                        <div class="fw-bold text-muted text-uppercase mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            Pevná konfigurace pavouka
                        </div>
                        <div class="row g-2 text-small">
                            <div class="col-sm-4">
                                <span class="text-muted">Systém / Formát:</span>
                                <div class="fw-bold text-dark">{{ $tournament->format_label }}</div>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted">Herní mód:</span>
                                <div class="fw-bold text-dark">{{ $tournament->game_mode }} {{ strtoupper(str_replace('_', ' ', $tournament->checkout_mode)) }}</div>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted">Počet hráčů:</span>
                                <div class="fw-bold text-dark">{{ $tournament->players->count() }} hráčů</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-light px-4">
                            <i class="mdi mdi-arrow-left me-1"></i> Zpět na turnaj
                        </a>
                        <button type="submit" class="btn btn-gradient-primary px-4 fw-bold">
                            <i class="mdi mdi-content-save-outline me-1"></i> Uložit změny
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
