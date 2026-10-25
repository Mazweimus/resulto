@extends('layouts.app')

@section('title', 'Správa hráčů')

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-account-group"></i>
        </span> Správa hráčů
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Evidence hráčů</li>
        </ul>
    </nav>
</div>

{{-- SOUHRNNÉ KARTY --}}
<div class="row">
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-primary card-img-holder text-white">
            <div class="card-body py-3">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle" />
                <h5 class="font-weight-normal mb-2">Hráčská základna <i class="mdi mdi-account-multiple float-end"></i></h5>
                <h2 class="mb-0">{{ $allPlayersCount }}</h2>
                <small>Registrovaných hráčů v databázi</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-success card-img-holder text-white">
            <div class="card-body py-3">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle" />
                <h5 class="font-weight-normal mb-2">Aktivní šipkaři <i class="mdi mdi-bullseye-arrow float-end"></i></h5>
                <h2 class="mb-0">{{ $players->where('matches_count', '>', 0)->count() }}</h2>
                <small>Hráčů s odehraným zápasem</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-warning card-img-holder text-white">
            <div class="card-body py-3">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle" />
                <h5 class="font-weight-normal mb-2">Tituly a vítězství <i class="mdi mdi-trophy float-end"></i></h5>
                <h2 class="mb-0">{{ $players->sum(fn($p) => $p->wonTournaments->count()) }}</h2>
                <small>Rozdaných turnajových trofejí</small>
            </div>
        </div>
    </div>
</div>

{{-- HLAVNÍ TABULKA HRÁČŮ S VYHLEDÁVÁNÍM --}}
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="card-title mb-1">Seznam všech registrovaných šipkařů</h4>
                        <p class="card-description mb-0">Evidence hráčů a jejich zápasová bilance</p>
                    </div>
                    <div>
                        <a href="{{ route('players.create') }}" class="btn btn-gradient-primary">
                            <i class="mdi mdi-account-plus me-1"></i> Přidat nového hráče
                        </a>
                    </div>
                </div>

                {{-- Vyhledávací formulář --}}
                <form action="{{ route('players.index') }}" method="GET" class="mb-4">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="mdi mdi-magnify text-primary"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Hledat hráče podle jména..." value="{{ $search }}">
                            </div>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="mdi mdi-filter me-1"></i> Hledat
                            </button>
                            @if(!empty($search))
                                <a href="{{ route('players.index') }}" class="btn btn-light btn-sm">
                                    <i class="mdi mdi-close me-1"></i> Zrušit filtr
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;"># ID</th>
                                <th>Jméno</th>
                                <th class="text-center" style="width: 140px;">Pohlaví</th>
                                <th class="text-center" style="width: 160px;">Turnaje</th>
                                <th class="text-end" style="width: 160px;">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($players as $p)
                                <tr>
                                    <td class="fw-bold text-muted">#{{ $p->id }}</td>
                                    <td>
                                        <div class="fw-bold fs-6 text-dark">{{ $p->name }}</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $p->gender === 'female' ? 'badge-gradient-info' : 'badge-gradient-primary' }} px-3 py-2">
                                            <i class="mdi {{ $p->gender === 'female' ? 'mdi-gender-female' : 'mdi-gender-male' }} me-1"></i>
                                            {{ $p->gender_label }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <i class="mdi mdi-trophy-variant-outline me-1"></i> {{ $p->tournaments_count ?? $p->tournaments->count() }} účastí
                                        </span>
                                        @if($p->wonTournaments->count() > 0)
                                            <span class="badge bg-warning text-dark ms-1 py-1 px-2" title="Tituly">
                                                <i class="mdi mdi-trophy"></i> {{ $p->wonTournaments->count() }}x
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('players.edit', $p->id) }}" class="btn btn-outline-info btn-sm p-1 px-2" title="Upravit hráče">
                                            <i class="mdi mdi-pencil"></i> Upravit
                                        </a>
                                        <form action="{{ route('players.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Opravdu chcete smazat hráče {{ $p->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2" title="Smazat">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="mdi mdi-account-search fs-1 d-block mb-2 text-muted"></i>
                                        Zatím nejsou zaevidováni žádní hráči.
                                        <div class="mt-2">
                                            <a href="{{ route('players.create') }}" class="btn btn-sm btn-gradient-primary">
                                                <i class="mdi mdi-plus me-1"></i> Přidat prvního hráče
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Stránkování --}}
                <div class="mt-4 d-flex justify-content-between align-items-center">
                    <div class="text-muted text-small">
                        Zobrazeno {{ $players->firstItem() ?? 0 }} - {{ $players->lastItem() ?? 0 }} z celkem {{ $players->total() }} hráčů
                    </div>
                    <div>
                        {{ $players->links('pagination::bootstrap-5') }}
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
