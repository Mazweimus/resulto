@extends('layouts.app')

@section('title', 'Šipkařský Dashboard')

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-bullseye-arrow"></i>
        </span> Darts Dashboard
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">
                <span>Přehled Double Elimination turnajů</span> <i class="mdi mdi-trophy text-primary align-middle ms-1"></i>
            </li>
        </ul>
    </nav>
</div>

{{-- 3 HORNÍ GRADIENTNÍ KARTY --}}
<div class="row">
    {{-- Karta 1: Celkem turnajů --}}
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-primary card-img-holder text-white">
            <div class="card-body">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle-image" />
                <h4 class="font-weight-normal mb-3">
                    Celkem turnajů <i class="mdi mdi-trophy-variant mdi-24px float-end"></i>
                </h4>
                <h2 class="mb-4">{{ $totalTournaments }}</h2>
                <h6 class="card-text mb-0">
                    Odehráno: <strong>{{ $finishedTournamentsCount }}</strong> &bull; Běží: <strong>{{ $activeTournaments }}</strong>
                </h6>
            </div>
        </div>
    </div>

    {{-- Karta 2: Aktivní turnaje --}}
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-warning card-img-holder text-white">
            <div class="card-body">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle-image" />
                <h4 class="font-weight-normal mb-3">
                    Běžící turnaje <i class="mdi mdi-play-circle mdi-24px float-end"></i>
                </h4>
                <h2 class="mb-4">{{ $activeTournaments }} <span class="fs-6 fw-normal">aktivních</span></h2>
                <h6 class="card-text mb-0">
                    Systém: <strong>Double Elimination</strong>
                </h6>
            </div>
        </div>
    </div>

    {{-- Karta 3: Registrovaní hráči --}}
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-info card-img-holder text-white">
            <div class="card-body">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle-image" />
                <h4 class="font-weight-normal mb-3">
                    Registrovaní šipkaři <i class="mdi mdi-account-group mdi-24px float-end"></i>
                </h4>
                <h2 class="mb-4">{{ $totalPlayers }} <span class="fs-6 fw-normal">hráčů</span></h2>
                <h6 class="card-text mb-0">
                    <a href="{{ route('players.create') }}" class="text-white text-decoration-underline">+ Přidat nového hráče</a>
                </h6>
            </div>
        </div>
    </div>
</div>

{{-- HISTORIE POSLEDNÍCH UKONČENÝCH TURNAJŮ A PÓDIUM (1., 2., 3. MÍSTO) --}}
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="card-title text-primary mb-1">
                            <i class="mdi mdi-podium-gold me-1"></i> Historie turnajů & Medailové pódium
                        </h4>
                        <p class="card-description mb-0">Výsledky posledních ukončených turnajů s vyhodnocením 1., 2. a 3. místa v Double Elimination</p>
                    </div>
                    <div>
                        <a href="{{ route('tournaments.create') }}" class="btn btn-gradient-primary btn-sm">
                            <i class="mdi mdi-plus-circle me-1"></i> Založit nový turnaj
                        </a>
                    </div>
                </div>

                @forelse($historyTournaments as $ht)
                    <div class="border rounded p-3 mb-4 shadow-sm" style="background: #faf9ff;">
                        <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h4 class="mb-1 text-dark fw-bold">
                                    <i class="mdi mdi-trophy text-warning me-1"></i> {{ $ht->name }}
                                </h4>
                                <div class="text-muted text-small">
                                    <span class="me-3"><i class="mdi mdi-calendar"></i> {{ $ht->date->format('d.m.Y') }}</span>
                                    <span class="me-3"><i class="mdi mdi-bullseye"></i> {{ $ht->game_mode }} {{ strtoupper(str_replace('_', ' ', $ht->checkout_mode)) }}</span>
                                    <span><i class="mdi mdi-account-group"></i> {{ $ht->players->count() }} hráčů</span>
                                </div>
                            </div>
                            <div>
                                <a href="{{ route('tournaments.play', $ht->id) }}" class="btn btn-gradient-primary btn-sm">
                                    <i class="mdi mdi-sitemap me-1"></i> Zobrazit celý pavouk turnaje
                                </a>
                            </div>
                        </div>

                        {{-- PÓDIOVÁ ŘADA (1., 2. a 3. místo) --}}
                        <div class="row g-2 text-center align-items-center">
                            {{-- 2. místo --}}
                            <div class="col-md-4 col-sm-6">
                                <div class="p-3 bg-white border rounded h-100 shadow-none">
                                    <div class="fs-2 mb-1">🥈</div>
                                    <span class="badge bg-secondary text-white text-uppercase px-2 py-1 mb-2">2. Místo (Poražený finalista)</span>
                                    @if($ht->secondPlace)
                                        <div class="mt-2">
                                            <div class="fw-bold text-dark fs-6">{{ $ht->secondPlace->name }}</div>
                                            <span class="badge {{ $ht->secondPlace->gender === 'female' ? 'bg-info' : 'bg-primary' }} text-white text-small mt-1">{{ $ht->secondPlace->gender_label }}</span>
                                        </div>
                                    @else
                                        <div class="text-muted fst-italic">N/A</div>
                                    @endif
                                </div>
                            </div>

                            {{-- 1. místo --}}
                            <div class="col-md-4 col-sm-6">
                                <div class="p-3 rounded h-100 border border-warning border-2" style="background: #fffdf5;">
                                    <div class="fs-1 mb-1">🥇</div>
                                    <span class="badge bg-warning text-dark text-uppercase fw-bold px-3 py-1 mb-2">1. Místo &bull; Šampión</span>
                                    @if($ht->winner)
                                        <div class="mt-2">
                                            <div class="fw-bold text-dark fs-5">{{ $ht->winner->name }}</div>
                                            <span class="badge {{ $ht->winner->gender === 'female' ? 'bg-info' : 'bg-primary' }} text-white text-small mt-1">{{ $ht->winner->gender_label }}</span>
                                        </div>
                                    @else
                                        <div class="text-muted fst-italic">N/A</div>
                                    @endif
                                </div>
                            </div>

                            {{-- 3. místo --}}
                            <div class="col-md-4 col-sm-6">
                                <div class="p-3 bg-white border rounded h-100 shadow-none">
                                    <div class="fs-2 mb-1">🥉</div>
                                    <span class="badge bg-dark text-white text-uppercase px-2 py-1 mb-2">3. Místo (Poražený z Finále B)</span>
                                    @if($ht->thirdPlace)
                                        <div class="mt-2">
                                            <div class="fw-bold text-dark fs-6">{{ $ht->thirdPlace->name }}</div>
                                            <span class="badge {{ $ht->thirdPlace->gender === 'female' ? 'bg-info' : 'bg-primary' }} text-white text-small mt-1">{{ $ht->thirdPlace->gender_label }}</span>
                                        </div>
                                    @else
                                        <div class="text-muted fst-italic">N/A</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-trophy-outline fs-1 d-block mb-2 text-muted"></i>
                        Zatím nebyl ukončen žádný turnaj.
                        <div class="mt-2">
                            <a href="{{ route('tournaments.create') }}" class="btn btn-sm btn-gradient-primary">
                                <i class="mdi mdi-plus-circle me-1"></i> Založit nový turnaj
                            </a>
                        </div>
                    </div>
                @endforelse

            </div>
        </div>
    </div>
</div>

{{-- AKTIVNÍ TURNAJE A PŘIPRAVENÉ ZÁPASY --}}
<div class="row">
    {{-- Běžící turnaje --}}
    <div class="col-lg-5 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title text-primary mb-0">
                        <i class="mdi mdi-play-circle-outline me-1"></i> Běžící turnaje
                    </h4>
                    <span class="badge badge-gradient-warning text-dark">{{ $ongoingTournaments->count() }} aktivní</span>
                </div>
                <p class="card-description">Probíhající turnaje připravené k hraní a zápisu skóre</p>

                <div class="list-group list-group-flush">
                    @forelse($ongoingTournaments as $ot)
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                            <div>
                                <h5 class="mb-1 fw-bold text-dark">{{ $ot->name }}</h5>
                                <div class="text-muted text-small">
                                    {{ $ot->game_mode }} DO &bull; {{ $ot->players->count() }} hráčů
                                </div>
                            </div>
                            <a href="{{ route('tournaments.play', $ot->id) }}" class="btn btn-gradient-warning btn-sm text-dark fw-bold">
                                <i class="mdi mdi-sitemap me-1"></i> Hrát pavouka
                            </a>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            Žádný turnaj právě neprobíhá.
                            <div class="mt-2">
                                <a href="{{ route('tournaments.create') }}" class="btn btn-sm btn-gradient-primary">
                                    Vytvořit turnaj
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Zápasy k odehrání --}}
    <div class="col-lg-7 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title text-primary mb-0">
                        <i class="mdi mdi-bullseye me-1"></i> Připravené duely
                    </h4>
                    <span class="badge badge-gradient-primary">Čeká na odehrání</span>
                </div>
                <p class="card-description">Zápasy s určenými soupeři, kde můžete přímo zadat výsledky na legy</p>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Turnaj & Kolo</th>
                                <th>Hráč 1</th>
                                <th class="text-center">VS</th>
                                <th>Hráč 2</th>
                                <th class="text-end">Zápis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($readyMatches as $m)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-truncate" style="max-width: 140px;">{{ $m->tournament->name ?? 'Turnaj' }}</div>
                                        <small class="text-muted">{{ $m->round_name }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-truncate" style="max-width: 140px;">{{ $m->player1->name }}</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-muted border">VS</span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-truncate" style="max-width: 140px;">{{ $m->player2->name }}</div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-gradient-primary btn-sm py-1 px-2" data-bs-toggle="modal" data-bs-target="#quickMatchModal{{ $m->id }}">
                                            Zapsat
                                        </button>
                                    </td>
                                </tr>

                                {{-- Modal pro zadání výsledku legů --}}
                                <div class="modal fade" id="quickMatchModal{{ $m->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                        <div class="modal-content">
                                            <form action="{{ route('matches.update', $m->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header bg-gradient-primary text-white py-2">
                                                    <h6 class="modal-title mb-0">{{ $m->round_name }}</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-3 text-center">
                                                    <div class="row align-items-center g-2 mb-2">
                                                        <div class="col-5">
                                                            <div class="fw-bold text-small text-truncate mb-1">{{ $m->player1->name }}</div>
                                                            <input type="number" name="player1_legs" class="form-control text-center fw-bold fs-3" min="0" max="{{ $m->tournament->best_of_legs }}" value="0" required>
                                                        </div>
                                                        <div class="col-2 fs-2 fw-bold text-muted">:</div>
                                                        <div class="col-5">
                                                            <div class="fw-bold text-small text-truncate mb-1">{{ $m->player2->name }}</div>
                                                            <input type="number" name="player2_legs" class="form-control text-center fw-bold fs-3" min="0" max="{{ $m->tournament->best_of_legs }}" value="0" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer py-2 justify-content-between">
                                                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Zavřít</button>
                                                    <button type="submit" class="btn btn-gradient-primary btn-sm">Uložit výsledek</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Žádné čekající duely k odehrání.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
