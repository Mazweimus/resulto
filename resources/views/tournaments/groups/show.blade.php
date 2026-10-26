@extends('layouts.app')

@section('title', 'Základní skupiny: ' . $tournament->name)

@push('styles')
<style>
    /* Styly pro Křížovou tabulku (Matrix Grid) */
    .matrix-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    .matrix-table th, .matrix-table td {
        vertical-align: middle !important;
        border-color: #e5e9f2;
    }
    .matrix-header-cell {
        background-color: #f8f9fa;
        font-weight: 700;
        white-space: nowrap;
    }
    .matrix-player-col {
        min-width: 140px;
        max-width: 170px;
    }
    .diagonal-cell {
        background: repeating-linear-gradient(
            45deg,
            #f1f3f6,
            #f1f3f6 8px,
            #e9ecf1 8px,
            #e9ecf1 16px
        ) !important;
        cursor: not-allowed;
        user-select: none;
    }
    .matrix-cell {
        transition: background-color 0.2s ease, border-color 0.2s ease;
        padding: 6px 8px !important;
        position: relative;
    }
    .matrix-cell:hover:not(.diagonal-cell) {
        background-color: #f5f3ff !important;
    }
    .matrix-score-input {
        width: 36px !important;
        height: 32px !important;
        padding: 0 !important;
        text-align: center;
        font-weight: 700;
        font-size: 0.95rem;
        border-radius: 6px;
        border: 1px solid #ced4da;
        transition: all 0.15s ease-in-out;
    }
    .matrix-score-input:focus {
        border-color: #b66dff;
        box-shadow: 0 0 0 0.2rem rgba(182, 109, 255, 0.25);
        background-color: #ffffff;
    }
    /* Odstranění šipek u číselných inputů */
    .matrix-score-input::-webkit-outer-spin-button,
    .matrix-score-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .matrix-score-input[type=number] {
        -moz-appearance: textfield;
    }
    .cell-win {
        background-color: rgba(25, 135, 84, 0.1) !important;
    }
    .cell-loss {
        background-color: rgba(220, 53, 69, 0.08) !important;
    }
    .cell-draw {
        background-color: rgba(255, 193, 7, 0.15) !important;
    }
    .save-indicator-badge {
        transition: opacity 0.3s ease-in-out;
    }

    /* Medaile a pozice v tabulce pořadí */
    .medal-badge-gold {
        background: linear-gradient(135deg, #ffd700 0%, #ffb700 100%);
        color: #000;
        box-shadow: 0 2px 4px rgba(255, 183, 0, 0.3);
    }
    .medal-badge-silver {
        background: linear-gradient(135deg, #e0e0e0 0%, #b0b0b0 100%);
        color: #212529;
        box-shadow: 0 2px 4px rgba(176, 176, 176, 0.3);
    }
    .medal-badge-bronze {
        background: linear-gradient(135deg, #e69d67 0%, #cd7f32 100%);
        color: #fff;
        box-shadow: 0 2px 4px rgba(205, 127, 50, 0.3);
    }

    /* Pulzující indikátor pro stav "Právě se hraje" */
    .pulsing-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        background-color: #212529;
        border-radius: 50%;
        animation: pulse-dot 1.2s infinite ease-in-out;
        vertical-align: middle;
    }
    @keyframes pulse-dot {
        0% { transform: scale(0.85); opacity: 0.6; }
        50% { transform: scale(1.35); opacity: 1; }
        100% { transform: scale(0.85); opacity: 0.6; }
    }

    .match-status-dropdown .dropdown-toggle::after {
        margin-left: 0.4em;
    }
    .match-card {
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .match-card:hover {
        box-shadow: 0 4px 10px rgba(0,0,0,0.06) !important;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-view-grid"></i>
        </span> Skupinový turnaj: {{ $tournament->name }}
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Turnaje</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $tournament->name }}</li>
        </ul>
    </nav>
</div>

{{-- HLAVIČKA TURNAJE (ČISTÝ DESIGN BEZ REDUNDANTNÍCH TLAČÍTEK) --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="pb-3 border-bottom mb-3">
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
                        <span><i class="mdi mdi-view-grid-outline text-info me-1"></i> {{ $tournament->groups->count() }} skupiny</span>
                        <span><i class="mdi mdi-numeric text-warning me-1"></i> Best of {{ $tournament->best_of_legs }} legs</span>
                        <span><i class="mdi mdi-account-group text-success me-1"></i> {{ $tournament->players->count() }} hráčů</span>
                    </div>
                </div>

                {{-- NAVIGAČNÍ ZÁLOŽKY PRO JEDNOTLIVÉ SKUPINY --}}
                <ul class="nav nav-tabs card-header-tabs border-0" id="groupTabs" role="tablist">
                    @foreach($tournament->groups as $index => $group)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold {{ $index === 0 ? 'active text-primary' : 'text-dark' }}" id="tab-group-{{ $group->id }}-btn" data-bs-toggle="tab" data-bs-target="#tab-group-{{ $group->id }}" type="button" role="tab" aria-controls="tab-group-{{ $group->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                <i class="mdi mdi-view-grid-outline me-1"></i> {{ $group->name }}
                                <span class="badge bg-light text-dark ms-1">{{ $group->players->count() }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- OBSAH ZÁLOŽEK SKUPIN (IZOLOVANÉ ZOBRAZENÍ JEDNOTLIVÝCH SKUPIN) --}}
<div class="tab-content" id="groupTabsContent">
    @foreach($tournament->groups as $index => $group)
        @php
            $groupPlayers = $group->players;
            $matches = $group->matches;
            $standings = $group->getStandings();
        @endphp
        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="tab-group-{{ $group->id }}" role="tabpanel" aria-labelledby="tab-group-{{ $group->id }}-btn">
            
            {{-- 1. KŘÍŽOVÁ TABULKA (MATRIX GRID) --}}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h5 class="card-title text-primary fw-bold mb-0">
                                    <i class="mdi mdi-grid text-primary me-2"></i> Křížová tabulka zápasů: {{ $group->name }}
                                </h5>
                                <small class="text-muted">
                                    Zadejte skóre ve formátu <strong>Vyhrané legy řádku : Vyhrané legy sloupce</strong>. Výsledky se automaticky zrcadlí, uloží a zápas se přepne do stavu <em>Odehráno</em>.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-muted border px-2 py-1">
                                    <i class="mdi mdi-information-outline me-1"></i> Remízy povoleny (1 bod)
                                </span>
                                <div id="group-status-badge-{{ $group->id }}" class="save-indicator-badge d-none">
                                    <span class="badge bg-success text-white py-1 px-2">
                                        <i class="mdi mdi-check me-1"></i> Uloženo &amp; Přepočítáno
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center mb-0 matrix-table" id="matrix-table-{{ $group->id }}">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="matrix-header-cell text-start p-3" style="width: 180px; min-width: 160px;">
                                                <div class="d-flex align-items-center gap-2 text-muted fw-bold">
                                                    <i class="mdi mdi-account text-primary"></i> Hráč / Soupeř
                                                </div>
                                            </th>
                                            @foreach($groupPlayers as $colIdx => $colPlayer)
                                                <th class="matrix-header-cell matrix-player-col p-2 text-center" title="{{ $colPlayer->name }}">
                                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                                        <span class="badge bg-primary text-white rounded-circle mb-1" style="width: 22px; height: 22px; line-height: 22px; padding: 0; font-size: 0.72rem;">
                                                            {{ $colIdx + 1 }}
                                                        </span>
                                                        <span class="text-truncate fw-bold text-dark d-block" style="max-width: 130px;">
                                                            {{ $colPlayer->name }}
                                                        </span>
                                                    </div>
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($groupPlayers as $rowIdx => $rowPlayer)
                                            <tr>
                                                {{-- První sloupec: Jméno hráče pro řádek --}}
                                                <td class="matrix-header-cell text-start p-2 fw-bold">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-secondary text-white rounded-circle" style="width: 22px; height: 22px; line-height: 22px; padding: 0; font-size: 0.72rem;">
                                                            {{ $rowIdx + 1 }}
                                                        </span>
                                                        <div class="text-truncate" style="max-width: 145px;" title="{{ $rowPlayer->name }}">
                                                            <div class="text-dark fw-bold text-truncate">{{ $rowPlayer->name }}</div>
                                                            <small class="text-muted fw-normal" style="font-size: 0.75rem;">{{ $rowPlayer->gender_label }}</small>
                                                        </div>
                                                    </div>
                                                </td>

                                                {{-- Buňky křížové tabulky --}}
                                                @foreach($groupPlayers as $colIdx => $colPlayer)
                                                    @if($rowPlayer->id === $colPlayer->id)
                                                        {{-- Diagonála (i = j): Hráč nemůže hrát sám se sebou --}}
                                                        <td class="diagonal-cell text-center align-middle p-2" title="{{ $rowPlayer->name }} nemůže hrát sám se sebou">
                                                            <span class="text-muted fw-bold fs-5 opacity-75">✕</span>
                                                        </td>
                                                    @else
                                                        @php
                                                            $match = $matches->first(function ($m) use ($rowPlayer, $colPlayer) {
                                                                return ($m->player1_id === $rowPlayer->id && $m->player2_id === $colPlayer->id)
                                                                    || ($m->player1_id === $colPlayer->id && $m->player2_id === $rowPlayer->id);
                                                            });
                                                            $isPlayer1 = $match && ($match->player1_id === $rowPlayer->id);
                                                            $rowLegs = $match ? ($isPlayer1 ? $match->player1_legs : $match->player2_legs) : 0;
                                                            $colLegs = $match ? ($isPlayer1 ? $match->player2_legs : $match->player1_legs) : 0;
                                                            $hasScore = $match && ($match->status === 'completed' || $rowLegs > 0 || $colLegs > 0);
                                                            $cellKey = "{$group->id}_{$rowPlayer->id}_{$colPlayer->id}";
                                                            $mirrorKey = "{$group->id}_{$colPlayer->id}_{$rowPlayer->id}";

                                                            $cellClass = '';
                                                            if ($hasScore) {
                                                                if ($rowLegs > $colLegs) $cellClass = 'cell-win';
                                                                elseif ($rowLegs < $colLegs) $cellClass = 'cell-loss';
                                                                else $cellClass = 'cell-draw';
                                                            }
                                                        @endphp
                                                        <td class="matrix-cell text-center align-middle {{ $cellClass }}"
                                                            id="cell_{{ $cellKey }}"
                                                            data-cell-id="{{ $cellKey }}"
                                                            data-mirror-id="{{ $mirrorKey }}"
                                                            data-group-id="{{ $group->id }}"
                                                            data-match-id="{{ $match ? $match->id : '' }}"
                                                            data-is-player1="{{ $isPlayer1 ? '1' : '0' }}"
                                                            data-row-name="{{ $rowPlayer->name }}"
                                                            data-col-name="{{ $colPlayer->name }}">

                                                            @if($match)
                                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                                    <input type="number" 
                                                                           min="0" 
                                                                           max="99" 
                                                                           class="form-control matrix-score-input row-score" 
                                                                           value="{{ $hasScore ? $rowLegs : '' }}" 
                                                                           placeholder="0"
                                                                           title="{{ $rowPlayer->name }}">
                                                                    <span class="text-muted fw-bold px-1">:</span>
                                                                    <input type="number" 
                                                                           min="0" 
                                                                           max="99" 
                                                                           class="form-control matrix-score-input col-score" 
                                                                           value="{{ $hasScore ? $colLegs : '' }}" 
                                                                           placeholder="0"
                                                                           title="{{ $colPlayer->name }}">
                                                                    <span class="cell-status-icon ms-1" style="width: 14px; display: inline-block;">
                                                                        @if($match->status === 'completed')
                                                                            <i class="mdi mdi-check-circle text-success" title="Zápas odehrán" style="font-size: 0.95rem;"></i>
                                                                        @elseif($match->status === 'in_progress')
                                                                            <span class="pulsing-dot" title="Právě se hraje"></span>
                                                                        @endif
                                                                    </span>
                                                                </div>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. ŘÁDEK POD KŘÍŽOVOU TABULKOU: TABULKA POŘADÍ + SEZNAM ZÁPASŮ --}}
            <div class="row g-4">
                {{-- TABULKA POŘADÍ VE SKUPINĚ S MEDAILEMI (1., 2., 3. MÍSTO) --}}
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title text-primary fw-bold mb-0">
                                <i class="mdi mdi-format-list-numbered text-warning me-2"></i> Tabulka: {{ $group->name }}
                            </h5>
                            <span class="badge bg-primary fs-7">2 body za výhru, 1 za remízu</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="standings-table-{{ $group->id }}">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 65px;" class="text-center">#</th>
                                            <th>Hráč</th>
                                            <th class="text-center" title="Odehrané zápasy">Z</th>
                                            <th class="text-center text-success" title="Výhry">V</th>
                                            <th class="text-center text-warning" title="Remízy">R</th>
                                            <th class="text-center text-danger" title="Prohry">P</th>
                                            <th class="text-center" title="Skóre legů (vyhrané : prohrané)">Legy</th>
                                            <th class="text-center" title="Rozdíl legů">+/-</th>
                                            <th class="text-center fw-bold" title="Body">Body</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($standings as $pRank => $item)
                                            @php
                                                $rank = $item['rank'] ?? ($loop->index + 1);
                                                $legDiff = $item['legs_diff'] ?? 0;
                                            @endphp
                                            <tr class="{{ $rank <= 2 ? 'table-success bg-opacity-10' : '' }}" id="standing-row-{{ $group->id }}-{{ $item['player_id'] }}">
                                                <td class="text-center fw-bold align-middle">
                                                    @if($rank === 1)
                                                        <span class="badge medal-badge-gold fw-bold px-2 py-1">
                                                            <i class="mdi mdi-trophy me-1"></i>1.
                                                        </span>
                                                    @elseif($rank === 2)
                                                        <span class="badge medal-badge-silver fw-bold px-2 py-1">
                                                            <i class="mdi mdi-medal me-1"></i>2.
                                                        </span>
                                                    @elseif($rank === 3)
                                                        <span class="badge medal-badge-bronze fw-bold px-2 py-1">
                                                            <i class="mdi mdi-medal-outline me-1"></i>3.
                                                        </span>
                                                    @else
                                                        <span class="fw-bold text-muted">{{ $rank }}.</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                                            {{ mb_substr($item['name'], 0, 1) }}
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ $item['name'] }}</div>
                                                            <small class="text-muted" style="font-size: 0.72rem;">{{ $item['gender_label'] ?? '' }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle standing-played">{{ $item['matches_played'] }}</td>
                                                <td class="text-center text-success fw-bold align-middle standing-won">{{ $item['matches_won'] }}</td>
                                                <td class="text-center text-warning fw-bold align-middle standing-drawn">{{ $item['matches_drawn'] }}</td>
                                                <td class="text-center text-danger align-middle standing-lost">{{ $item['matches_lost'] }}</td>
                                                <td class="text-center align-middle standing-legs">{{ $item['legs_won'] }} : {{ $item['legs_lost'] }}</td>
                                                <td class="text-center align-middle standing-diff {{ $legDiff > 0 ? 'text-success fw-bold' : ($legDiff < 0 ? 'text-danger' : 'text-muted') }}">
                                                    {{ $legDiff > 0 ? '+' . $legDiff : $legDiff }}
                                                </td>
                                                <td class="text-center align-middle">
                                                    <span class="badge bg-gradient-primary text-white fs-6 px-3 py-1 standing-points">
                                                        {{ $item['points'] }} b.
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="text-center py-4 text-muted">V této skupině zatím nejsou žádní hráči.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SEZNAM ZÁPASŮ VE SKUPINĚ S INTERAKTIVNÍM DROPDOWN BADGEM STAVU --}}
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="card-title text-primary fw-bold mb-0">
                                <i class="mdi mdi-bullseye-arrow text-danger me-2"></i> Zápasy: {{ $group->name }}
                            </h5>
                            <span class="badge bg-light text-dark border">{{ $matches->count() }} zápasů</span>
                        </div>
                        <div class="card-body p-3" style="max-height: 520px; overflow-y: auto;">
                            @forelse($matches as $match)
                                <div class="card border rounded p-2 mb-2 match-card {{ $match->status === 'completed' ? 'border-success' : ($match->status === 'in_progress' ? 'border-warning' : 'border-light') }} shadow-sm" id="match-card-{{ $match->id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom text-muted" style="font-size: 0.75rem;">
                                        <span class="fw-bold text-dark">#{{ $match->match_number }} &bull; {{ $match->round_name }}</span>

                                        {{-- INTERAKTIVNÍ BOOTSTRAP DROPDOWN SE STAVY ZÁPASU --}}
                                        <div class="dropdown match-status-dropdown" data-match-id="{{ $match->id }}" data-group-id="{{ $group->id }}">
                                            <button class="btn btn-sm dropdown-toggle py-0 px-2 fw-bold match-status-btn {{ $match->status_badge_class }}" 
                                                    type="button" 
                                                    id="matchStatusDropdown{{ $match->id }}" 
                                                    data-bs-toggle="dropdown" 
                                                    aria-expanded="false" 
                                                    style="font-size: 0.72rem; border-radius: 12px;">
                                                @if($match->status === 'in_progress')
                                                    <span class="pulsing-dot me-1"></span>
                                                @endif
                                                <span class="status-label-text">{{ $match->status_label }}</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm py-1" aria-labelledby="matchStatusDropdown{{ $match->id }}" style="font-size: 0.8rem; min-width: 140px;">
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 set-status-item {{ in_array($match->status, ['pending', 'scheduled']) ? 'active' : '' }}" 
                                                       href="#" data-status="pending">
                                                        <span class="badge bg-secondary rounded-circle p-1" style="width: 7px; height: 7px;"></span>
                                                        Připravuje se
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 set-status-item {{ $match->status === 'in_progress' ? 'active' : '' }}" 
                                                       href="#" data-status="in_progress">
                                                        <span class="badge bg-warning rounded-circle p-1" style="width: 7px; height: 7px;"></span>
                                                        Právě se hraje
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item d-flex align-items-center gap-2 set-status-item {{ $match->status === 'completed' ? 'active' : '' }}" 
                                                       href="#" data-status="completed">
                                                        <span class="badge bg-success rounded-circle p-1" style="width: 7px; height: 7px;"></span>
                                                        Odehráno
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center py-1">
                                        <div class="d-flex align-items-center text-truncate {{ $match->winner_id === $match->player1_id ? 'fw-bold text-success' : 'text-dark' }} match-player1-name">
                                            <span class="text-truncate" style="max-width: 140px;" title="{{ $match->player1->name ?? 'Hráč 1' }}">{{ $match->player1->name ?? 'Hráč 1' }}</span>
                                        </div>
                                        <span class="badge {{ $match->winner_id === $match->player1_id ? 'bg-success text-white' : 'bg-light text-dark border' }} px-2 py-1 fs-6 match-player1-score">
                                            {{ $match->player1_legs }}
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center py-1">
                                        <div class="d-flex align-items-center text-truncate {{ $match->winner_id === $match->player2_id ? 'fw-bold text-success' : 'text-dark' }} match-player2-name">
                                            <span class="text-truncate" style="max-width: 140px;" title="{{ $match->player2->name ?? 'Hráč 2' }}">{{ $match->player2->name ?? 'Hráč 2' }}</span>
                                        </div>
                                        <span class="badge {{ $match->winner_id === $match->player2_id ? 'bg-success text-white' : 'bg-light text-dark border' }} px-2 py-1 fs-6 match-player2-score">
                                            {{ $match->player2_legs }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted">Žádné zápasy pro tuto skupinu.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = '{{ csrf_token() }}';
        const bestOfLegs = {{ (int) ($tournament->best_of_legs ?? 3) }};

        // Cache pro debounced ukládání
        const saveDebounceTimers = {};

        // 1. OBSLUHA VSTUPŮ KŘÍŽOVÉ TABULKY (MATRIX GRID)
        const matrixCells = document.querySelectorAll('.matrix-cell');

        matrixCells.forEach(cell => {
            const rowInput = cell.querySelector('.row-score');
            const colInput = cell.querySelector('.col-score');
            if (!rowInput || !colInput) return;

            const cellId = cell.getAttribute('data-cell-id');
            const mirrorId = cell.getAttribute('data-mirror-id');
            const matchId = cell.getAttribute('data-match-id');
            const isPlayer1 = cell.getAttribute('data-is-player1') === '1';
            const groupId = cell.getAttribute('data-group-id');

            function handleInputScore(triggerSource) {
                const mirrorCell = document.getElementById('cell_' + mirrorId);
                const rowVal = rowInput.value.trim();
                const colVal = colInput.value.trim();

                // Okamžité zrcadlení do protilehlé buňky
                if (mirrorCell) {
                    const mirrorRowInput = mirrorCell.querySelector('.row-score');
                    const mirrorColInput = mirrorCell.querySelector('.col-score');

                    if (mirrorRowInput && mirrorRowInput.value !== colVal) {
                        mirrorRowInput.value = colVal;
                    }
                    if (mirrorColInput && mirrorColInput.value !== rowVal) {
                        mirrorColInput.value = rowVal;
                    }

                    updateCellHighlight(mirrorCell, colVal, rowVal);
                }

                // Aktualizace vizuálního zvýraznění této buňky
                updateCellHighlight(cell, rowVal, colVal);

                // Naplánování asynchronního uložení (debounced)
                clearTimeout(saveDebounceTimers[matchId]);
                saveDebounceTimers[matchId] = setTimeout(() => {
                    executeScoreSave(cell, mirrorCell, matchId, isPlayer1, rowVal, colVal, groupId);
                }, 500);
            }

            rowInput.addEventListener('input', () => handleInputScore('row'));
            colInput.addEventListener('input', () => handleInputScore('col'));

            // Okamžité uložení při opuštění pole (blur)
            rowInput.addEventListener('blur', () => {
                clearTimeout(saveDebounceTimers[matchId]);
                const mirrorCell = document.getElementById('cell_' + mirrorId);
                executeScoreSave(cell, mirrorCell, matchId, isPlayer1, rowInput.value.trim(), colInput.value.trim(), groupId);
            });

            colInput.addEventListener('blur', () => {
                clearTimeout(saveDebounceTimers[matchId]);
                const mirrorCell = document.getElementById('cell_' + mirrorId);
                executeScoreSave(cell, mirrorCell, matchId, isPlayer1, rowInput.value.trim(), colInput.value.trim(), groupId);
            });
        });

        // 2. INTERAKTIVNÍ ZMĚNA STAVU ZÁPASU (PŘIPRAVUJE SE / PRÁVĚ SE HRAJE / ODEHRÁNO)
        document.querySelectorAll('.set-status-item').forEach(item => {
            item.addEventListener('click', async function (e) {
                e.preventDefault();

                const dropdownContainer = this.closest('.match-status-dropdown');
                if (!dropdownContainer) return;

                const matchId = dropdownContainer.getAttribute('data-match-id');
                const groupId = dropdownContainer.getAttribute('data-group-id');
                const newStatus = this.getAttribute('data-status');

                // Aktualizace aktivní položky v menu
                dropdownContainer.querySelectorAll('.set-status-item').forEach(el => el.classList.remove('active'));
                this.classList.add('active');

                // Dočasný indikátor načítání na tlačítku
                const statusBtn = dropdownContainer.querySelector('.match-status-btn');
                const oldBtnHtml = statusBtn ? statusBtn.innerHTML : '';
                if (statusBtn) {
                    statusBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width: 10px; height: 10px;" role="status"></span>';
                }

                try {
                    const response = await fetch('/matches/' + matchId, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ status: newStatus })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Aktualizace badge na kartě zápasu
                        updateMatchCardStatus(matchId, newStatus, data.match?.status_label);

                        // Aktualizace buňky v křížové tabulce
                        updateMatrixCellIconForStatus(matchId, newStatus);

                        // Zobrazení badge uloženo
                        showGroupSaveBadge(groupId);

                        // Aktualizace tabulky pořadí
                        if (data.standings) {
                            updateStandingsTable(groupId, data.standings);
                        }
                    } else {
                        if (statusBtn) statusBtn.innerHTML = oldBtnHtml;
                        alert(data.message || 'Chyba při změně stavu zápasu.');
                    }
                } catch (err) {
                    console.error('Chyba změny stavu zápasu:', err);
                    if (statusBtn) statusBtn.innerHTML = oldBtnHtml;
                }
            });
        });

        // Pomocná funkce pro barevné zvýraznění buňky
        function updateCellHighlight(cellEl, rowVal, colVal) {
            cellEl.classList.remove('cell-win', 'cell-loss', 'cell-draw');
            if (rowVal === '' && colVal === '') return;

            const r = parseInt(rowVal, 10);
            const c = parseInt(colVal, 10);

            if (!isNaN(r) && !isNaN(c)) {
                if (r > c) cellEl.classList.add('cell-win');
                else if (r < c) cellEl.classList.add('cell-loss');
                else cellEl.classList.add('cell-draw');
            }
        }

        // Provedení AJAX / Fetch API uložení zápasu s automatickým nastavením na 'completed'
        async function executeScoreSave(cellEl, mirrorCellEl, matchId, isPlayer1, rowVal, colVal, groupId) {
            if (!matchId) return;

            let p1Legs = 0;
            let p2Legs = 0;
            let status = 'pending';

            if (rowVal !== '' || colVal !== '') {
                const r = parseInt(rowVal, 10) || 0;
                const c = parseInt(colVal, 10) || 0;

                if (isPlayer1) {
                    p1Legs = r;
                    p2Legs = c;
                } else {
                    p1Legs = c;
                    p2Legs = r;
                }
                // Automatické dokončení při zapsání skóre
                status = 'completed';
            }

            // Indikátor načítání v obou buňkách
            setCellStatusIcon(cellEl, 'loading');
            if (mirrorCellEl) setCellStatusIcon(mirrorCellEl, 'loading');

            try {
                const response = await fetch('/matches/' + matchId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        player1_legs: p1Legs,
                        player2_legs: p2Legs,
                        status: status
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Úspěch: stavová ikona v buňkách
                    if (status === 'completed') {
                        setCellStatusIcon(cellEl, 'success');
                        if (mirrorCellEl) setCellStatusIcon(mirrorCellEl, 'success');
                    } else {
                        setCellStatusIcon(cellEl, 'clear');
                        if (mirrorCellEl) setCellStatusIcon(mirrorCellEl, 'clear');
                    }

                    // Zobrazení badge "Uloženo & Přepočítáno"
                    showGroupSaveBadge(groupId);

                    // Aktualizace zápasové karty na pravé straně
                    updateMatchCard(matchId, p1Legs, p2Legs, status);

                    // DYNAMICKÁ AKTUALIZACE TABULKY POŘADÍ A MEDAILÍ
                    if (data.standings) {
                        updateStandingsTable(groupId, data.standings);
                    }

                } else {
                    setCellStatusIcon(cellEl, 'error', data.message || 'Chyba');
                    if (mirrorCellEl) setCellStatusIcon(mirrorCellEl, 'error', data.message || 'Chyba');
                }
            } catch (err) {
                console.error('Chyba při ukládání skóre:', err);
                setCellStatusIcon(cellEl, 'error', 'Chyba sítě');
                if (mirrorCellEl) setCellStatusIcon(mirrorCellEl, 'error', 'Chyba sítě');
            }
        }

        // Nastavení stavové ikonky buňky
        function setCellStatusIcon(cellEl, state, errorTitle) {
            if (!cellEl) return;
            const iconContainer = cellEl.querySelector('.cell-status-icon');
            if (!iconContainer) return;

            if (state === 'loading') {
                iconContainer.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" style="width: 12px; height: 12px; border-width: 2px;" role="status"></span>';
            } else if (state === 'success') {
                iconContainer.innerHTML = '<i class="mdi mdi-check-circle text-success" title="Zápas odehrán" style="font-size: 0.95rem;"></i>';
            } else if (state === 'error') {
                iconContainer.innerHTML = `<i class="mdi mdi-alert-circle text-danger" title="${errorTitle || 'Chyba při ukládání'}" style="font-size: 0.95rem;"></i>`;
            } else {
                iconContainer.innerHTML = '';
            }
        }

        // Aktualizace ikony v buňkách při změně stavu z dropdownu
        function updateMatrixCellIconForStatus(matchId, newStatus) {
            document.querySelectorAll(`.matrix-cell[data-match-id="${matchId}"]`).forEach(cell => {
                const iconContainer = cell.querySelector('.cell-status-icon');
                if (!iconContainer) return;

                if (newStatus === 'completed') {
                    iconContainer.innerHTML = '<i class="mdi mdi-check-circle text-success" title="Zápas odehrán" style="font-size: 0.95rem;"></i>';
                } else if (newStatus === 'in_progress') {
                    iconContainer.innerHTML = '<span class="pulsing-dot" title="Právě se hraje"></span>';
                } else {
                    iconContainer.innerHTML = '';
                }
            });
        }

        // Dočasný badge v hlavičce skupiny "Uloženo & Přepočítáno"
        function showGroupSaveBadge(groupId) {
            const badge = document.getElementById('group-status-badge-' + groupId);
            if (!badge) return;
            badge.classList.remove('d-none');
            setTimeout(() => {
                badge.classList.add('d-none');
            }, 2500);
        }

        // Aktualizace karty zápasu
        function updateMatchCard(matchId, p1Legs, p2Legs, status) {
            const card = document.getElementById('match-card-' + matchId);
            if (!card) return;

            const p1Score = card.querySelector('.match-player1-score');
            const p2Score = card.querySelector('.match-player2-score');

            if (p1Score) p1Score.textContent = p1Legs;
            if (p2Score) p2Score.textContent = p2Legs;

            updateMatchCardStatus(matchId, status);
        }

        // Aktualizace stavu a badge na kartě zápasu
        function updateMatchCardStatus(matchId, status, customLabel) {
            const card = document.getElementById('match-card-' + matchId);
            if (!card) return;

            const statusBtn = card.querySelector('.match-status-btn');
            const labelText = card.querySelector('.status-label-text');

            card.classList.remove('border-light', 'border-warning', 'border-success');

            if (status === 'completed') {
                card.classList.add('border-success');
                if (statusBtn) {
                    statusBtn.className = 'btn btn-sm dropdown-toggle py-0 px-2 fw-bold match-status-btn bg-success text-white';
                    statusBtn.innerHTML = `<span class="status-label-text">${customLabel || 'Odehráno'}</span>`;
                }
            } else if (status === 'in_progress') {
                card.classList.add('border-warning');
                if (statusBtn) {
                    statusBtn.className = 'btn btn-sm dropdown-toggle py-0 px-2 fw-bold match-status-btn bg-warning text-dark';
                    statusBtn.innerHTML = `<span class="pulsing-dot me-1"></span><span class="status-label-text">${customLabel || 'Právě se hraje'}</span>`;
                }
            } else {
                card.classList.add('border-light');
                if (statusBtn) {
                    statusBtn.className = 'btn btn-sm dropdown-toggle py-0 px-2 fw-bold match-status-btn bg-secondary text-white';
                    statusBtn.innerHTML = `<span class="status-label-text">${customLabel || 'Připravuje se'}</span>`;
                }
            }
        }

        // 3. DYNAMICKÝ PŘEPOČET A AKTUALIZACE TABULKY POŘADÍ VČETNĚ MEDAILÍ
        function updateStandingsTable(groupId, standings) {
            if (!standings || !Array.isArray(standings)) return;
            const tbody = document.querySelector(`#standings-table-${groupId} tbody`);
            if (!tbody) return;

            tbody.innerHTML = '';

            if (standings.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">V této skupině zatím nejsou žádní hráči.</td></tr>';
                return;
            }

            standings.forEach(item => {
                const legDiff = item.legs_diff;
                const diffText = legDiff > 0 ? `+${legDiff}` : `${legDiff}`;
                const diffClass = legDiff > 0 ? 'text-success fw-bold' : (legDiff < 0 ? 'text-danger' : 'text-muted');

                let badgeHtml = '';
                let rowClass = '';

                if (item.rank === 1) {
                    badgeHtml = '<span class="badge medal-badge-gold fw-bold px-2 py-1"><i class="mdi mdi-trophy me-1"></i>1.</span>';
                    rowClass = 'table-success bg-opacity-10';
                } else if (item.rank === 2) {
                    badgeHtml = '<span class="badge medal-badge-silver fw-bold px-2 py-1"><i class="mdi mdi-medal me-1"></i>2.</span>';
                    rowClass = 'table-success bg-opacity-10';
                } else if (item.rank === 3) {
                    badgeHtml = '<span class="badge medal-badge-bronze fw-bold px-2 py-1"><i class="mdi mdi-medal-outline me-1"></i>3.</span>';
                } else {
                    badgeHtml = `<span class="fw-bold text-muted">${item.rank}.</span>`;
                }

                const tr = document.createElement('tr');
                tr.className = rowClass;
                tr.id = `standing-row-${groupId}-${item.player_id}`;
                tr.innerHTML = `
                    <td class="text-center align-middle">${badgeHtml}</td>
                    <td class="align-middle">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                ${escapeHtml(item.name).charAt(0)}
                            </div>
                            <div>
                                <div class="fw-bold text-dark">${escapeHtml(item.name)}</div>
                                <small class="text-muted" style="font-size: 0.72rem;">${escapeHtml(item.gender_label || '')}</small>
                            </div>
                        </div>
                    </td>
                    <td class="text-center align-middle standing-played">${item.matches_played}</td>
                    <td class="text-center text-success fw-bold align-middle standing-won">${item.matches_won}</td>
                    <td class="text-center text-warning fw-bold align-middle standing-drawn">${item.matches_drawn || 0}</td>
                    <td class="text-center text-danger align-middle standing-lost">${item.matches_lost}</td>
                    <td class="text-center align-middle standing-legs">${item.legs_won} : ${item.legs_lost}</td>
                    <td class="text-center align-middle standing-diff ${diffClass}">${diffText}</td>
                    <td class="text-center align-middle">
                        <span class="badge bg-gradient-primary text-white fs-6 px-3 py-1 standing-points">
                            ${item.points} b.
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Pomocná funkce pro bezpečný výpis HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
</script>
@endpush
