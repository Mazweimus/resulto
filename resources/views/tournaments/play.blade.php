@extends('layouts.app')

@section('title', 'Double Elimination: ' . $tournament->name)

@push('styles')
<style>
    .bracket-scroll-wrapper {
        overflow-x: auto;
        overflow-y: visible !important;
        height: auto !important;
        max-height: none !important;
        padding-bottom: 25px;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
    }
    .bracket-container {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap !important;
        justify-content: flex-start;
        align-items: stretch;
        gap: 28px;
        width: max-content;
        min-width: 100%;
        margin: 0 auto;
        padding: 10px 15px;
        overflow-y: visible !important;
        height: auto !important;
        max-height: none !important;
    }
    .bracket-round {
        min-width: 260px;
        max-width: 290px;
        flex: 0 0 260px;
        display: flex;
        flex-direction: column;
        overflow-y: visible !important;
        height: auto !important;
    }
    .bracket-round-matches {
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        flex: 1;
        min-height: 100%;
        overflow-y: visible !important;
        height: auto !important;
    }
    .bracket-round-header {
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
        margin-bottom: 14px;
        padding: 6px 12px;
        border-radius: 6px;
        text-align: center;
    }
    .header-winners {
        background: #f1e9ff;
        color: #7922ff;
        border: 1px solid #d4beff;
    }
    .header-losers {
        background: #fff6e6;
        color: #e67e00;
        border: 1px solid #ffd899;
    }
    .header-gf {
        background: #e6f9f0;
        color: #009959;
        border: 1px solid #a3e9c6;
    }
    .match-card {
        background: #ffffff;
        border: 1px solid #e7ebf0;
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 16px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        position: relative;
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .match-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .match-card.completed {
        border-left: 4px solid #1bcfb4;
    }
    .match-card.scheduled {
        border-left: 4px solid #b66dff;
    }
    .player-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 8px;
        border-radius: 6px;
        margin-bottom: 2px;
        min-width: 0;
    }
    .player-row.winner {
        background-color: #f0fdf4;
        font-weight: bold;
    }
    .score-badge {
        font-weight: 700;
        font-size: 1rem;
        min-width: 30px;
        text-align: center;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .podium-box {
        background: linear-gradient(135deg, #ffffff 0%, #f9f8ff 100%);
        border: 2px solid #b66dff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 6px 20px rgba(182, 109, 255, 0.15);
    }
    .podium-card {
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .podium-1 {
        background: linear-gradient(135deg, #fff9e6 0%, #ffeed0 100%);
        border: 2px solid #f6c000;
    }
    .podium-2 {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid #adb5bd;
    }
    .podium-3 {
        background: linear-gradient(135deg, #fdf6ec 0%, #f7dfc5 100%);
        border: 2px solid #d48b48;
    }

    /* Responzivní mobilní zobrazení pro horizontální posun a dotykové terče */
    @media (max-width: 768px) {
        .bracket-scroll-wrapper {
            scroll-snap-type: x mandatory;
            padding-bottom: 30px;
        }
        .bracket-round {
            scroll-snap-align: start;
            min-width: 260px;
            max-width: 85vw;
            flex: 0 0 85vw;
        }
        .match-card {
            padding: 12px 14px;
        }
        .match-card button,
        .match-card .btn,
        .seeding-slot-container .btn-clear-seeding-slot {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }
        .seeding-slot-input {
            min-height: 42px;
            font-size: 0.9rem;
        }
        .player-row {
            min-height: 40px;
            padding: 8px 10px;
        }
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-bullseye"></i>
        </span> Turnajový pavouk: {{ $tournament->name }}
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Turnaje</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $tournament->name }}</li>
        </ul>
    </nav>
</div>

{{-- HLAVIČKA TURNAJE --}}
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 border-bottom mb-3 gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="card-title fs-3 mb-0 text-primary">{{ $tournament->name }}</h2>
                            <label class="badge {{ $tournament->status_badge_class }} py-2 px-3 fs-6">
                                {{ $tournament->status_label }}
                            </label>
                        </div>
                        <div class="text-muted d-flex flex-wrap gap-3 mt-2 align-items-center">
                            <span><i class="mdi mdi-calendar text-primary me-1"></i> {{ $tournament->date->format('d.m.Y') }}</span>
                            <span><i class="mdi mdi-calendar-text text-primary me-1"></i> Ročník {{ $tournament->season_year }}</span>
                            @if($tournament->counts_for_ranking)
                                <span class="badge bg-success text-white py-1 px-2"><i class="mdi mdi-trophy-award me-1"></i> Započítává se do žebříčku</span>
                            @else
                                <span class="badge bg-secondary text-white py-1 px-2"><i class="mdi mdi-trophy-off me-1"></i> Nezapočítává se (Exhibice)</span>
                            @endif
                            <span><i class="mdi mdi-bullseye-arrow text-danger me-1"></i> {{ $tournament->game_mode }} {{ strtoupper(str_replace('_', ' ', $tournament->checkout_mode)) }}</span>
                            <span><i class="mdi mdi-sitemap text-info me-1"></i> {{ $tournament->format_label }}</span>
                            <span><i class="mdi mdi-numeric text-warning me-1"></i> Best of {{ $tournament->best_of_legs }} legs</span>
                            <span><i class="mdi mdi-account-group text-success me-1"></i> {{ $tournament->players->count() }} hráčů</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-outline-primary btn-sm">
                            <i class="mdi mdi-pencil me-1"></i> Upravit turnaj
                        </a>
                        <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-format-list-bulleted me-1"></i> Všechny turnaje
                        </a>
                        <a href="{{ route('tournaments.create') }}" class="btn btn-gradient-primary btn-sm">
                            <i class="mdi mdi-plus-circle me-1"></i> Nový turnaj
                        </a>
                    </div>
                </div>

                {{-- VÝSLEDKOVÁ TABULKA V PŘÍPADĚ UKONČENÉHO TURNAJE --}}
                @if(in_array($tournament->status, ['finished', 'completed']))
                    <div class="card border border-primary shadow-sm mb-4" id="results-block" style="background: #faf9ff;">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">
                                <div>
                                    <h4 class="card-title text-primary mb-1 fs-4">
                                        <i class="mdi mdi-trophy-variant text-warning me-2"></i> Konečné výsledky turnaje — Konečné pořadí a body
                                    </h4>
                                    <p class="card-description mb-0">Oficiální výsledková listina hráčů a získané body v turnaji {{ $tournament->name }}</p>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-gradient-primary btn-sm" onclick="document.getElementById('all-tab').click(); document.getElementById('bracketTab').scrollIntoView({behavior: 'smooth'});">
                                        <i class="mdi mdi-pencil-outline me-1"></i> Upravit zápasy v pavouku
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0 bg-white border rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 140px;" class="fw-bold text-center">Pořadí</th>
                                            <th class="fw-bold">Hráč <span class="text-muted fw-normal">/ Jméno hráče</span></th>
                                            <th style="width: 160px;" class="fw-bold text-center">Získané body</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($standings as $s)
                                            <tr>
                                                <td class="text-center">
                                                    @if($s['rank'] === '1.' || ($s['rank_from'] ?? 0) === 1)
                                                        <span class="badge bg-warning text-dark fw-bold px-3 py-1 fs-6 shadow-sm">
                                                            <i class="mdi mdi-medal me-1"></i> 1. místo
                                                        </span>
                                                    @elseif($s['rank'] === '2.' || ($s['rank_from'] ?? 0) === 2)
                                                        <span class="badge bg-secondary text-white fw-bold px-3 py-1 fs-6 shadow-sm">
                                                            <i class="mdi mdi-medal me-1"></i> 2. místo
                                                        </span>
                                                    @elseif($s['rank'] === '3.' || (($s['rank_from'] ?? 0) === 3 && ($s['rank_to'] ?? 0) === 3))
                                                        <span class="badge text-white fw-bold px-3 py-1 fs-6 shadow-sm" style="background-color: #cd7f32;">
                                                            <i class="mdi mdi-medal me-1"></i> 3. místo
                                                        </span>
                                                    @else
                                                        <span class="fw-bold text-dark fs-6">{{ $s['rank'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="fs-6 fw-bold text-dark">
                                                    {{ $s['player_name'] }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-primary border fw-bold px-3 py-1 fs-6">
                                                        {{ $s['points'] ?? 0 }} b.
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- KARTA NASAZOVÁNÍ HRÁČŮ PRO FREE FORMÁTY --}}
                @if(in_array($tournament->format, ['free_oneway_elimination', 'free_double_elimination']) && !$tournament->is_seeding_finalized)
                    <div class="card border border-primary shadow-sm mb-4" id="seeding-control-card" style="background: #faf8ff;">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-3">
                                <div>
                                    <h4 class="card-title text-primary mb-1 fs-4">
                                        <i class="mdi mdi-sitemap text-primary me-2"></i> {{ $tournament->format_label }}: Nasazení hráčů do pavouka
                                    </h4>
                                    <p class="card-description mb-0 text-muted">
                                        Vyberte existující hráče do jednotlivých slotů 1. kola. Neobsazené pozice budou automaticky vyhodnoceny jako volný los (BYE).
                                    </p>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-gradient-success btn-lg fw-bold px-4 shadow-sm" id="btn-finalize-seeding" {{ ($seedingStatus['can_finalize'] ?? false) ? '' : 'disabled' }}>
                                        <i class="mdi mdi-check-circle-outline me-1"></i> Uzavřít nasazení a zahájit turnaj
                                    </button>
                                </div>
                            </div>

                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <div class="bg-white border rounded p-3 text-center">
                                        <span class="text-muted d-block text-small mb-1">Velikost pavouka</span>
                                        <span class="fs-4 fw-bold text-dark">{{ $tournament->bracket_size ?? 8 }} pozic</span>
                                        <small class="text-muted d-block">({{ ($tournament->bracket_size ?? 8) / 2 }} zápasů v 1. kole)</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-white border rounded p-3 text-center">
                                        <span class="text-muted d-block text-small mb-1">Nasazeno hráčů</span>
                                        <span class="fs-4 fw-bold text-primary" id="status-assigned-count">{{ $seedingStatus['assigned_count'] ?? 0 }}</span>
                                        <span class="text-muted fs-5"> / {{ $tournament->bracket_size ?? 8 }}</span>
                                        <small class="text-muted d-block" id="status-min-players">(minimálně {{ $seedingStatus['min_players'] ?? 5 }} hráčů)</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="bg-white border rounded p-3 text-center">
                                        <span class="text-muted d-block text-small mb-1">Stav validace</span>
                                        <span id="status-validation-badge" class="badge {{ ($seedingStatus['can_finalize'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }} fs-6 py-2 px-3">
                                            {{ ($seedingStatus['can_finalize'] ?? false) ? 'Připraveno k uzavření' : 'Nutno doplnit hráče' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div id="seeding-errors-alert" class="alert alert-warning py-2 px-3 mt-3 mb-0 {{ ($seedingStatus['can_finalize'] ?? false) ? 'd-none' : '' }}">
                                <i class="mdi mdi-alert-circle-outline me-1"></i>
                                <span id="seeding-errors-text">
                                    {{ implode(' ', $seedingStatus['errors'] ?? []) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- NAŠEPTÁVAČ HRÁČŮ PRO SLOTY 1. KOLA --}}
                    <datalist id="seeding-players-datalist">
                        @php
                            $assignedSet = array_flip($seedingStatus['assigned_player_ids'] ?? []);
                        @endphp
                        @foreach($allPlayers as $p)
                            @if(!isset($assignedSet[$p->id]))
                                <option value="{{ $p->name }}" data-id="{{ $p->id }}">{{ $p->name }}</option>
                            @endif
                        @endforeach
                    </datalist>
                @endif

                {{-- NAVIGAČNÍ ZÁLOŽKY PRO VĚTVE A, B A GRAND FINÁLE --}}
                <ul class="nav nav-tabs nav-tabs-bordered mb-4 justify-content-center" id="bracketTab" role="tablist">
                    @if($tournament->status === 'finished')
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-success" id="standings-tab" data-bs-toggle="tab" data-bs-target="#tab-standings" type="button" role="tab">
                            <i class="mdi mdi-format-list-numbered me-1"></i> Výsledková tabulka
                        </button>
                    </li>
                    @endif
                    @if(in_array($tournament->format, ['double_elimination', 'free_double_elimination']))
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold" id="all-tab" data-bs-toggle="tab" data-bs-target="#tab-all" type="button" role="tab">
                                <i class="mdi mdi-view-dashboard-outline me-1"></i> Kompletní zobrazení (A, B a Finále)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold text-primary" id="winners-tab" data-bs-toggle="tab" data-bs-target="#tab-winners" type="button" role="tab">
                                <i class="mdi mdi-crown-outline me-1"></i> Pavouk A (Winners)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold text-warning" id="losers-tab" data-bs-toggle="tab" data-bs-target="#tab-losers" type="button" role="tab">
                                <i class="mdi mdi-shield-refresh-outline me-1"></i> Pavouk B (Losers)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold text-success" id="grandfinal-tab" data-bs-toggle="tab" data-bs-target="#tab-grandfinal" type="button" role="tab">
                                <i class="mdi mdi-trophy-outline me-1"></i> Grand Finále
                            </button>
                        </li>
                    @else
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-primary" id="single-bracket-tab" data-bs-toggle="tab" data-bs-target="#tab-single-bracket" type="button" role="tab">
                                <i class="mdi mdi-sitemap me-1"></i> {{ $tournament->format === 'free_oneway_elimination' ? 'Vyřazovací pavouk + Zápas o 3. místo' : 'Vyřazovací pavouk' }}
                            </button>
                        </li>
                    @endif
                </ul>

                <div class="tab-content" id="bracketTabContent">
                    @if(in_array($tournament->format, ['double_elimination', 'free_double_elimination']))
                    {{-- 1. ZÁLOŽKA: VŠECHNO NA JEDNÉ STRÁNCE --}}
                    <div class="tab-pane fade show active" id="tab-all" role="tabpanel">
                        
                        {{-- GRAND FINÁLE SEKCE --}}
                        <div class="mb-5 pb-3 border-bottom text-center">
                            <h4 class="text-success fw-bold mb-3">
                                <i class="mdi mdi-trophy-variant text-warning me-1"></i> Grand Finále & Rozhodující zápas
                            </h4>
                            <p class="text-muted text-small mb-4">Vítěz Pavouka A se utká s vítězem Pavouka B. Pokud zvítězí hráč z B, hraje se rozhodující Finále 2 (Reset).</p>

                            <div class="d-flex justify-content-center flex-wrap gap-4">
                                @foreach($grandFinalMatches as $gf)
                                    @include('tournaments.partials.match-card', ['match' => $gf, 'isGrandFinal' => true])
                                @endforeach
                            </div>
                        </div>

                        {{-- PAVOUK A (WINNERS) --}}
                        <div class="mb-5 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="text-primary fw-bold mb-0">
                                    <i class="mdi mdi-crown text-primary me-1"></i> Pavouk A (Winners Bracket)
                                </h4>
                                <span class="badge badge-gradient-primary">Poražený propadá do Pavouka B</span>
                            </div>

                            <div class="bracket-scroll-wrapper">
                                <div class="bracket-container">
                                    @foreach($winnersMatches as $roundNum => $roundMatches)
                                        <div class="bracket-round">
                                            <div class="bracket-round-header header-winners">
                                                {{ $roundMatches->first()->round_name ?? "Kolo {$roundNum} (A)" }}
                                            </div>
                                            <div class="bracket-round-matches">
                                                @foreach($roundMatches as $match)
                                                    @include('tournaments.partials.match-card', ['match' => $match])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- PAVOUK B (LOSERS) --}}
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="text-warning fw-bold mb-0">
                                    <i class="mdi mdi-shield-refresh text-warning me-1"></i> Pavouk B (Losers Bracket)
                                </h4>
                                <span class="badge badge-gradient-warning text-dark">Poražený definitivně vypadává &bull; Vítěz postoupí do Grand Finále</span>
                            </div>

                            <div class="bracket-scroll-wrapper">
                                <div class="bracket-container">
                                    @foreach($losersMatches as $roundNum => $roundMatches)
                                        <div class="bracket-round">
                                            <div class="bracket-round-header header-losers">
                                                {{ $roundMatches->first()->round_name ?? "Kolo {$roundNum} (B)" }}
                                            </div>
                                            <div class="bracket-round-matches">
                                                @foreach($roundMatches as $match)
                                                    @include('tournaments.partials.match-card', ['match' => $match])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- 2. ZÁLOŽKA: POUZE PAVOUK A --}}
                    <div class="tab-pane fade" id="tab-winners" role="tabpanel">
                        <div class="bracket-scroll-wrapper">
                            <div class="bracket-container">
                                @foreach($winnersMatches as $roundNum => $roundMatches)
                                    <div class="bracket-round">
                                        <div class="bracket-round-header header-winners">
                                            {{ $roundMatches->first()->round_name ?? "Kolo {$roundNum} (A)" }}
                                        </div>
                                        <div class="bracket-round-matches">
                                            @foreach($roundMatches as $match)
                                                @include('tournaments.partials.match-card', ['match' => $match])
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- 3. ZÁLOŽKA: POUZE PAVOUK B --}}
                    <div class="tab-pane fade" id="tab-losers" role="tabpanel">
                        <div class="bracket-scroll-wrapper">
                            <div class="bracket-container">
                                @foreach($losersMatches as $roundNum => $roundMatches)
                                    <div class="bracket-round">
                                        <div class="bracket-round-header header-losers">
                                            {{ $roundMatches->first()->round_name ?? "Kolo {$roundNum} (B)" }}
                                        </div>
                                        <div class="bracket-round-matches">
                                            @foreach($roundMatches as $match)
                                                @include('tournaments.partials.match-card', ['match' => $match])
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- 4. ZÁLOŽKA: POUZE GRAND FINÁLE --}}
                    <div class="tab-pane fade" id="tab-grandfinal" role="tabpanel">
                        <div class="text-center py-4">
                            <h3 class="text-success mb-4">Grand Finále Turnaje</h3>
                            <div class="d-flex justify-content-center flex-wrap gap-4">
                                @foreach($grandFinalMatches as $gf)
                                    @include('tournaments.partials.match-card', ['match' => $gf, 'isGrandFinal' => true])
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @else
                    {{-- VYŘAZOVACÍ PAVOUK PRO FREE ELIMINATION / ONEWAY / SINGLE ELIMINATION --}}
                    <div class="tab-pane fade show active" id="tab-single-bracket" role="tabpanel">
                        <div class="bracket-scroll-wrapper">
                            <div class="bracket-container">
                                @foreach($winnersMatches as $roundNum => $roundMatches)
                                    <div class="bracket-round">
                                        <div class="bracket-round-header header-winners">
                                            {{ $roundMatches->first()->round_name ?? "Kolo {$roundNum}" }}
                                        </div>
                                        <div class="bracket-round-matches">
                                            @foreach($roundMatches as $match)
                                                @include('tournaments.partials.match-card', ['match' => $match])
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- POVINNÝ ZÁPAS O 3. A 4. MÍSTO PRO FREE ONEWAY ELIMINATION --}}
                        @if($thirdPlaceMatch)
                            <div class="mt-4 pt-4 border-top text-center">
                                <div class="d-inline-block p-3 border rounded shadow-sm bg-light" style="min-width: 330px;">
                                    <h5 class="text-warning fw-bold mb-1">
                                        <i class="mdi mdi-medal text-warning me-1"></i> Zápas o 3. a 4. místo
                                    </h5>
                                    <p class="text-muted text-small mb-3">Poražení ze semifinále se utkají o konečné 3. a 4. místo v turnaji.</p>
                                    <div class="d-flex justify-content-center">
                                        @include('tournaments.partials.match-card', ['match' => $thirdPlaceMatch, 'isThirdPlace' => true])
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif

                    @if(in_array($tournament->status, ['finished', 'completed']))
                    {{-- 5. ZÁLOŽKA: POUZE VÝSLEDKOVÁ TABULKA --}}
                    <div class="tab-pane fade" id="tab-standings" role="tabpanel">
                        <div class="py-3">
                            <h4 class="card-title text-primary mb-3">
                                <i class="mdi mdi-trophy-variant text-warning me-2"></i> Konečné pořadí a body: {{ $tournament->name }}
                            </h4>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0 bg-white border rounded">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 140px;" class="fw-bold text-center">Pořadí</th>
                                            <th class="fw-bold">Hráč <span class="text-muted fw-normal">/ Jméno hráče</span></th>
                                            <th style="width: 160px;" class="fw-bold text-center">Získané body</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($standings as $s)
                                            <tr>
                                                <td class="text-center">
                                                    @if($s['rank'] === '1.' || ($s['rank_from'] ?? 0) === 1)
                                                        <span class="badge bg-warning text-dark fw-bold px-3 py-1 fs-6 shadow-sm">
                                                            <i class="mdi mdi-medal me-1"></i> 1. místo
                                                        </span>
                                                    @elseif($s['rank'] === '2.' || ($s['rank_from'] ?? 0) === 2)
                                                        <span class="badge bg-secondary text-white fw-bold px-3 py-1 fs-6 shadow-sm">
                                                            <i class="mdi mdi-medal me-1"></i> 2. místo
                                                        </span>
                                                    @elseif($s['rank'] === '3.' || (($s['rank_from'] ?? 0) === 3 && ($s['rank_to'] ?? 0) === 3))
                                                        <span class="badge text-white fw-bold px-3 py-1 fs-6 shadow-sm" style="background-color: #cd7f32;">
                                                            <i class="mdi mdi-medal me-1"></i> 3. místo
                                                        </span>
                                                    @else
                                                        <span class="fw-bold text-dark fs-6">{{ $s['rank'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="fs-6 fw-bold text-dark">
                                                    {{ $s['player_name'] }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-primary border fw-bold px-3 py-1 fs-6">
                                                        {{ $s['points'] ?? 0 }} b.
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@if(in_array($tournament->format, ['free_oneway_elimination', 'free_double_elimination']) && !$tournament->is_seeding_finalized)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const allPlayers = @json($allPlayers->map(fn($p) => ['id' => $p->id, 'name' => $p->name]) ?? []);
    let assignedPlayerIds = @json($seedingStatus['assigned_player_ids'] ?? []);
    let seedingStatus = @json($seedingStatus ?? []);

    const datalist = document.getElementById('seeding-players-datalist');
    const statusAssignedCount = document.getElementById('status-assigned-count');
    const statusValidationBadge = document.getElementById('status-validation-badge');
    const seedingErrorsAlert = document.getElementById('seeding-errors-alert');
    const seedingErrorsText = document.getElementById('seeding-errors-text');
    const btnFinalizeSeeding = document.getElementById('btn-finalize-seeding');

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Rebuild options in datalist
    function buildDatalist() {
        if (!datalist) return;
        datalist.innerHTML = '';
        const assignedSet = new Set(assignedPlayerIds.map(id => parseInt(id)));
        allPlayers.forEach(p => {
            if (!assignedSet.has(p.id)) {
                const opt = document.createElement('option');
                opt.value = p.name;
                opt.setAttribute('data-id', p.id);
                datalist.appendChild(opt);
            }
        });
    }

    // Update the control card status
    function updateControlCard(status) {
        if (statusAssignedCount) statusAssignedCount.textContent = status.assigned_count;
        if (statusValidationBadge) {
            if (status.can_finalize) {
                statusValidationBadge.className = 'badge bg-success fs-6 py-2 px-3';
                statusValidationBadge.textContent = 'Připraveno k uzavření';
            } else {
                statusValidationBadge.className = 'badge bg-warning text-dark fs-6 py-2 px-3';
                statusValidationBadge.textContent = 'Nutno doplnit hráče';
            }
        }

        if (seedingErrorsAlert && seedingErrorsText) {
            if (status.can_finalize) {
                seedingErrorsAlert.classList.add('d-none');
            } else {
                seedingErrorsAlert.classList.remove('d-none');
                seedingErrorsText.textContent = (status.errors || []).join(' ');
            }
        }

        if (btnFinalizeSeeding) {
            btnFinalizeSeeding.disabled = !status.can_finalize;
        }
    }

    // Update bottom badge for a match card
    function updateMatchBadge(matchId, matchData) {
        const badgeEl = document.getElementById(`match-status-badge-${matchId}`);
        if (!badgeEl) return;

        if (matchData.player1 && matchData.player2) {
            badgeEl.innerHTML = `
                <span class="badge bg-success text-white py-1 text-center" style="font-size: 0.7rem;">
                    <i class="mdi mdi-check"></i> Připraven zápas
                </span>
            `;
        } else if (matchData.player1 || matchData.player2) {
            badgeEl.innerHTML = `
                <span class="badge bg-info text-white py-1 text-center" style="font-size: 0.7rem;">
                    <i class="mdi mdi-arrow-right-circle-outline"></i> 1 hráč (Volný los)
                </span>
            `;
        } else {
            badgeEl.innerHTML = `
                <span class="badge bg-light text-danger border py-1 text-center" style="font-size: 0.7rem;">
                    <i class="mdi mdi-alert-circle-outline"></i> Prázdný zápas
                </span>
            `;
        }
    }

    // Render assigned slot
    function renderSlotAssigned(matchId, slotNum, player) {
        const container = document.getElementById(`slot-container-${matchId}-${slotNum}`);
        if (!container) return;

        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-between p-1 px-2 border rounded bg-light mb-1">
                <div class="d-flex align-items-center text-truncate me-1">
                    <span class="badge bg-primary me-2" style="font-size: 0.65rem;">Slot ${slotNum}</span>
                    <span class="fw-bold text-dark text-truncate text-small" title="${escapeHtml(player.name)}">${escapeHtml(player.name)}</span>
                </div>
                <button type="button" class="btn btn-outline-danger btn-xs p-0 px-1 btn-clear-seeding-slot" data-match-id="${matchId}" data-slot="${slotNum}" title="Odebrat hráče">
                    <i class="mdi mdi-close"></i>
                </button>
            </div>
        `;

        const clearBtn = container.querySelector('.btn-clear-seeding-slot');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => handleSlotClear(clearBtn));
        }
    }

    // Render empty slot input
    function renderSlotEmpty(matchId, slotNum) {
        const container = document.getElementById(`slot-container-${matchId}-${slotNum}`);
        if (!container) return;

        container.innerHTML = `
            <div class="input-group input-group-sm mb-1">
                <span class="input-group-text bg-light text-muted fw-bold p-1 px-2" style="font-size: 0.7rem;">S${slotNum}</span>
                <input type="text" list="seeding-players-datalist" class="form-control form-control-sm seeding-slot-input" placeholder="Vyberte hráče..." data-match-id="${matchId}" data-slot="${slotNum}" autocomplete="off" style="font-size: 0.8rem;">
            </div>
        `;

        const input = container.querySelector('.seeding-slot-input');
        if (input) {
            input.addEventListener('change', () => handleSlotChange(input));
        }
    }

    // Handler when user selects a player in an input
    async function handleSlotChange(input) {
        const matchId = input.dataset.matchId;
        const slotNum = input.dataset.slot;
        const val = input.value.trim();

        if (!val) return;

        const player = allPlayers.find(p => p.name.toLowerCase() === val.toLowerCase());
        if (!player) {
            alert(`Hráč se jménem "${val}" nebyl v databázi nalezen. Vyberte prosím existujícího hráče z našeptávače.`);
            input.value = '';
            return;
        }

        if (assignedPlayerIds.includes(player.id)) {
            alert(`Hráč ${player.name} je již v tomto pavouku nasazen v jiném slotu.`);
            input.value = '';
            return;
        }

        input.disabled = true;

        try {
            const response = await fetch("{{ route('tournaments.assignSlot', $tournament->id) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    match_id: matchId,
                    slot: slotNum,
                    player_id: player.id
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                alert(data.message || 'Chyba při přiřazení hráče.');
                input.disabled = false;
                input.value = '';
                return;
            }

            assignedPlayerIds = data.seeding_status.assigned_player_ids;
            seedingStatus = data.seeding_status;

            buildDatalist();
            updateControlCard(seedingStatus);
            updateMatchBadge(matchId, data.match);
            renderSlotAssigned(matchId, slotNum, player);

        } catch (err) {
            console.error(err);
            alert('Došlo k chybě při komunikaci se serverem.');
            input.disabled = false;
            input.value = '';
        }
    }

    // Handler when user clicks clear button on assigned slot
    async function handleSlotClear(btn) {
        const matchId = btn.dataset.matchId;
        const slotNum = btn.dataset.slot;

        btn.disabled = true;

        try {
            const response = await fetch("{{ route('tournaments.assignSlot', $tournament->id) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    match_id: matchId,
                    slot: slotNum,
                    player_id: null
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                alert(data.message || 'Chyba při odebírání hráče.');
                btn.disabled = false;
                return;
            }

            assignedPlayerIds = data.seeding_status.assigned_player_ids;
            seedingStatus = data.seeding_status;

            buildDatalist();
            updateControlCard(seedingStatus);
            updateMatchBadge(matchId, data.match);
            renderSlotEmpty(matchId, slotNum);

        } catch (err) {
            console.error(err);
            alert('Došlo k chybě při komunikaci se serverem.');
            btn.disabled = false;
        }
    }

    // Initial event binding for already rendered inputs and clear buttons
    document.querySelectorAll('.seeding-slot-input').forEach(input => {
        input.addEventListener('change', () => handleSlotChange(input));
    });

    document.querySelectorAll('.btn-clear-seeding-slot').forEach(btn => {
        btn.addEventListener('click', () => handleSlotClear(btn));
    });

    // Finalize button handler
    btnFinalizeSeeding?.addEventListener('click', async function () {
        if (!confirm('Opravdu chcete uzavřít nasazení? Všechny neobsazené sloty 1. kola budou označeny jako volný los (BYE) a jednotliví hráči automaticky postoupí do 2. kola.')) {
            return;
        }

        btnFinalizeSeeding.disabled = true;
        btnFinalizeSeeding.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Uzavírám nasazení...';

        try {
            const response = await fetch("{{ route('tournaments.finalizeSeeding', $tournament->id) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                alert(data.message || 'Nepodařilo se uzavřít nasazení.');
                btnFinalizeSeeding.disabled = false;
                btnFinalizeSeeding.innerHTML = '<i class="mdi mdi-check-circle-outline me-1"></i> Uzavřít nasazení a zahájit turnaj';
                return;
            }

            // Reload to render active matches ready for play
            window.location.reload();

        } catch (err) {
            console.error(err);
            alert('Došlo k chybě při komunikaci se serverem.');
            btnFinalizeSeeding.disabled = false;
            btnFinalizeSeeding.innerHTML = '<i class="mdi mdi-check-circle-outline me-1"></i> Uzavřít nasazení a zahájit turnaj';
        }
    });

    buildDatalist();
});
</script>
@endpush
@endif
