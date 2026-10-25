@php
    $isGrandFinal = $isGrandFinal ?? false;
    $isThirdPlace = $isThirdPlace ?? (bool) ($match->is_third_place ?? ($match->bracket_type === 'third_place'));
    $tournament = $tournament ?? $match->tournament;
    $isFreeFormat = $tournament && in_array($tournament->format, ['free_oneway_elimination', 'free_double_elimination']);
    $isFreeFormatSeeding = ($isFreeFormat && !$tournament->is_seeding_finalized);
    $isRound1Seeding = ($isFreeFormatSeeding && $match->bracket_type === 'winners' && $match->round === 1);
    $isBye = (bool) $match->is_bye;
    $isReady = $match->player1_id && $match->player2_id && !$isBye;
    $isResetLocked = $match->is_reset_match && !$isReady;
@endphp

<div class="match-card {{ $match->status }} {{ ($isGrandFinal || $isThirdPlace) ? 'border-2' : '' }} {{ $isThirdPlace ? 'border-warning' : '' }} {{ $isBye ? 'border-info' : '' }} {{ $isRound1Seeding ? 'border-primary' : '' }}" id="match-card-{{ $match->id }}" style="{{ ($isGrandFinal || $isThirdPlace) ? 'min-width: 290px; max-width: 330px;' : ($isRound1Seeding ? 'min-width: 260px;' : '') }}">
    <div class="d-flex justify-content-between align-items-center text-muted text-small mb-2 pb-1 border-bottom">
        <span class="fw-bold">
            @if($match->is_reset_match)
                <i class="mdi mdi-refresh text-warning me-1"></i> Finále 2 (Reset)
            @elseif($match->bracket_type === 'grand_final')
                <i class="mdi mdi-trophy text-success me-1"></i> Grand Finále 1
            @elseif($isThirdPlace)
                <i class="mdi mdi-medal text-warning me-1"></i> Zápas o 3. a 4. místo
            @elseif($isBye)
                <i class="mdi mdi-arrow-right-bold text-info me-1"></i> Zápas #{{ $match->match_number }}
            @elseif($isRound1Seeding)
                <i class="mdi mdi-bullseye-arrow text-primary me-1"></i> Zápas #{{ $match->match_number }} (1. kolo)
            @else
                Zápas #{{ $match->match_number }}
            @endif
        </span>
        <span class="badge {{ $match->status_badge_class }} py-1 px-2" style="font-size: 0.7rem;">
            @if($isRound1Seeding)
                Nasazování
            @elseif($isFreeFormatSeeding && ($match->round > 1 || $match->bracket_type !== 'winners'))
                Čeká
            @else
                {{ $match->status_label }}
            @endif
        </span>
    </div>

    @if($isRound1Seeding)
        {{-- INTERAKTIVNÍ NASAZOVÁNÍ SLOTŮ V 1. KOLE FREE FORMÁTŮ --}}
        {{-- Slot 1 --}}
        <div class="seeding-slot-container" id="slot-container-{{ $match->id }}-1" data-match-id="{{ $match->id }}" data-slot="1">
            @if($match->player1)
                <div class="d-flex align-items-center justify-content-between p-1 px-2 border rounded bg-light mb-1">
                    <div class="d-flex align-items-center text-truncate me-1" style="min-width: 0; flex: 1 1 auto;">
                        <span class="badge bg-primary me-2 flex-shrink-0" style="font-size: 0.65rem;">Slot 1</span>
                        <span class="fw-bold text-dark text-truncate text-small" title="{{ $match->player1->name }}">{{ $match->player1->name }}</span>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-xs p-0 px-1 btn-clear-seeding-slot flex-shrink-0" data-match-id="{{ $match->id }}" data-slot="1" title="Odebrat hráče">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            @else
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text bg-light text-muted fw-bold p-1 px-2" style="font-size: 0.7rem;">S1</span>
                    <input type="text" list="seeding-players-datalist" class="form-control form-control-sm seeding-slot-input" placeholder="Vyberte hráče..." data-match-id="{{ $match->id }}" data-slot="1" autocomplete="off" style="font-size: 0.8rem;">
                </div>
            @endif
        </div>

        <div class="text-center my-1 text-muted fw-bold" style="font-size: 0.65rem;">VS</div>

        {{-- Slot 2 --}}
        <div class="seeding-slot-container" id="slot-container-{{ $match->id }}-2" data-match-id="{{ $match->id }}" data-slot="2">
            @if($match->player2)
                <div class="d-flex align-items-center justify-content-between p-1 px-2 border rounded bg-light mb-1">
                    <div class="d-flex align-items-center text-truncate me-1" style="min-width: 0; flex: 1 1 auto;">
                        <span class="badge bg-primary me-2 flex-shrink-0" style="font-size: 0.65rem;">Slot 2</span>
                        <span class="fw-bold text-dark text-truncate text-small" title="{{ $match->player2->name }}">{{ $match->player2->name }}</span>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-xs p-0 px-1 btn-clear-seeding-slot flex-shrink-0" data-match-id="{{ $match->id }}" data-slot="2" title="Odebrat hráče">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            @else
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text bg-light text-muted fw-bold p-1 px-2" style="font-size: 0.7rem;">S2</span>
                    <input type="text" list="seeding-players-datalist" class="form-control form-control-sm seeding-slot-input" placeholder="Vyberte hráče..." data-match-id="{{ $match->id }}" data-slot="2" autocomplete="off" style="font-size: 0.8rem;">
                </div>
            @endif
        </div>

        {{-- Stav zápasu pro nasazování --}}
        <div class="d-grid mt-2" id="match-status-badge-{{ $match->id }}">
            @if($match->player1 && $match->player2)
                <span class="badge bg-success text-white py-1 text-center" style="font-size: 0.7rem;">
                    <i class="mdi mdi-check"></i> Připraven zápas
                </span>
            @elseif($match->player1 || $match->player2)
                <span class="badge bg-info text-white py-1 text-center" style="font-size: 0.7rem;">
                    <i class="mdi mdi-arrow-right-circle-outline"></i> 1 hráč (Volný los)
                </span>
            @else
                <span class="badge bg-light text-danger border py-1 text-center" style="font-size: 0.7rem;">
                    <i class="mdi mdi-alert-circle-outline"></i> Prázdný zápas
                </span>
            @endif
        </div>

    @elseif($isFreeFormatSeeding && ($match->round > 1 || $match->bracket_type !== 'winners'))
        {{-- ČEKÁ NA UZAVŘENÍ NASAZENÍ --}}
        <div class="player-row">
            <span class="text-muted fst-italic text-small">
                @if($isThirdPlace)
                    Poražený ze Semifinále 1
                @elseif($match->bracket_type === 'losers')
                    Poražený z Pavouka A
                @elseif($match->bracket_type === 'grand_final')
                    Vítěz Pavouka A
                @else
                    Vítěz zápasu #{{ ($match->match_number * 2) - 1 }}
                @endif
            </span>
            <span class="score-badge bg-light text-muted border">-</span>
        </div>
        <div class="player-row">
            <span class="text-muted fst-italic text-small">
                @if($isThirdPlace)
                    Poražený ze Semifinále 2
                @elseif($match->bracket_type === 'losers')
                    Poražený z Pavouka A
                @elseif($match->bracket_type === 'grand_final')
                    Vítěz Pavouka B
                @else
                    Vítěz zápasu #{{ $match->match_number * 2 }}
                @endif
            </span>
            <span class="score-badge bg-light text-muted border">-</span>
        </div>
        <div class="d-grid mt-2">
            <button type="button" class="btn btn-light btn-sm py-1 text-muted fs-7" disabled>
                Čeká na nasazení 1. kola
            </button>
        </div>

    @else
        {{-- STANDARDNÍ ZOBRAZENÍ ZÁPASU S MOŽNOSTÍ ZÁPISU SKÓRE --}}
        {{-- Hráč 1 --}}
        <div class="player-row {{ ($match->winner_id && $match->winner_id === $match->player1_id) ? 'winner' : '' }}">
            <div class="d-flex align-items-center text-truncate me-2" style="min-width: 0; flex: 1 1 auto;">
                @if($match->player1)
                    <span class="text-truncate fw-bold text-dark" style="max-width: 170px;" title="{{ $match->player1->name }}">
                        {{ $match->player1->name }}
                    </span>
                @elseif($isBye || $match->isSlotPermanentBye(1))
                    <span class="text-info fst-italic text-small fw-bold">
                        <i class="mdi mdi-arrow-right-circle-outline me-1"></i> Volný los (BYE)
                    </span>
                @else
                    <span class="text-muted fst-italic text-small">
                        @if($match->is_reset_match)
                            Vítěz z větve B
                        @elseif($isThirdPlace)
                            Poražený ze Semifinále 1
                        @else
                            Čeká na soupeře
                        @endif
                    </span>
                @endif
            </div>
            @if($isBye)
                @if($match->winner_id === $match->player1_id)
                    <span class="score-badge bg-success text-white flex-shrink-0" style="font-size: 0.75rem;">
                        <i class="mdi mdi-check"></i>
                    </span>
                @else
                    <span class="score-badge bg-light text-muted border flex-shrink-0" style="font-size: 0.75rem;">
                        BYE
                    </span>
                @endif
            @else
                <span class="score-badge {{ ($match->winner_id && $match->winner_id === $match->player1_id) ? 'bg-success text-white' : 'bg-light text-dark border' }} flex-shrink-0">
                    {{ $match->player1_legs }}
                </span>
            @endif
        </div>

        {{-- Hráč 2 --}}
        <div class="player-row {{ ($match->winner_id && $match->winner_id === $match->player2_id) ? 'winner' : '' }}">
            <div class="d-flex align-items-center text-truncate me-2" style="min-width: 0; flex: 1 1 auto;">
                @if($match->player2)
                    <span class="text-truncate fw-bold text-dark" style="max-width: 170px;" title="{{ $match->player2->name }}">
                        {{ $match->player2->name }}
                    </span>
                @elseif($isBye || $match->isSlotPermanentBye(2))
                    <span class="text-info fst-italic text-small fw-bold">
                        <i class="mdi mdi-arrow-right-circle-outline me-1"></i> Volný los (BYE)
                    </span>
                @else
                    <span class="text-muted fst-italic text-small">
                        @if($match->is_reset_match)
                            Hráč z větve A
                        @elseif($isThirdPlace)
                            Poražený ze Semifinále 2
                        @else
                            Čeká na soupeře
                        @endif
                    </span>
                @endif
            </div>
            @if($isBye)
                @if($match->winner_id === $match->player2_id)
                    <span class="score-badge bg-success text-white flex-shrink-0" style="font-size: 0.75rem;">
                        <i class="mdi mdi-check"></i>
                    </span>
                @else
                    <span class="score-badge bg-light text-muted border flex-shrink-0" style="font-size: 0.75rem;">
                        BYE
                    </span>
                @endif
            @else
                <span class="score-badge {{ ($match->winner_id && $match->winner_id === $match->player2_id) ? 'bg-success text-white' : 'bg-light text-dark border' }} flex-shrink-0">
                    {{ $match->player2_legs }}
                </span>
            @endif
        </div>

        {{-- Tlačítko akce --}}
        <div class="d-grid mt-2">
            @if($isBye)
                <div class="badge bg-light text-primary border py-2 text-center w-100" style="font-size: 0.75rem;">
                    <i class="mdi mdi-arrow-right-circle text-primary me-1"></i> Volný los (Postup)
                </div>
            @elseif($isReady)
                <button type="button" class="btn {{ $match->status === 'completed' ? 'btn-outline-primary' : ($isGrandFinal ? 'btn-gradient-success' : ($isThirdPlace ? 'btn-gradient-warning text-dark' : 'btn-gradient-primary')) }} btn-sm py-1 fs-7" data-bs-toggle="modal" data-bs-target="#scoreModal{{ $match->id }}">
                    <i class="mdi mdi-pencil me-1"></i> {{ $match->status === 'completed' ? 'Upravit výsledek' : 'Zapsat výsledek' }}
                </button>
            @elseif($isResetLocked)
                <span class="text-muted text-center py-1 fst-italic" style="font-size: 0.75rem;">
                    Hraje se pouze v případě výhry hráče z B
                </span>
            @else
                <button type="button" class="btn btn-light btn-sm py-1 text-muted fs-7" disabled>
                    Čeká na dohrání
                </button>
            @endif
        </div>
    @endif
</div>

{{-- MODAL POUZE PRO ZÁPIS SKÓRE NA LEGY --}}
@if($isReady)
<div class="modal fade" id="scoreModal{{ $match->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="{{ route('matches.update', $match->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-gradient-primary text-white py-2">
                    <h6 class="modal-title mb-0">
                        <i class="mdi mdi-bullseye me-1"></i> {{ $match->round_name }}
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 text-center">
                    <p class="text-muted text-small mb-3">Zadejte vyhrané legy obou hráčů</p>

                    <div class="row align-items-center g-2 mb-2">
                        <div class="col-5">
                            <div class="fw-bold text-truncate text-small mb-1">{{ $match->player1->name }}</div>
                            <span class="badge {{ $match->player1->gender === 'female' ? 'bg-info' : 'bg-primary' }} text-white text-small mb-2">{{ $match->player1->gender_label }}</span>
                            <input type="number" name="player1_legs" class="form-control form-control-lg text-center fw-bold fs-3" min="0" max="{{ $tournament->best_of_legs }}" value="{{ $match->player1_legs }}" required autofocus>
                        </div>

                        <div class="col-2 fs-2 fw-bold text-muted">
                            :
                        </div>

                        <div class="col-5">
                            <div class="fw-bold text-truncate text-small mb-1">{{ $match->player2->name }}</div>
                            <span class="badge {{ $match->player2->gender === 'female' ? 'bg-info' : 'bg-primary' }} text-white text-small mb-2">{{ $match->player2->gender_label }}</span>
                            <input type="number" name="player2_legs" class="form-control form-control-lg text-center fw-bold fs-3" min="0" max="{{ $tournament->best_of_legs }}" value="{{ $match->player2_legs }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Zavřít</button>
                    <button type="submit" class="btn btn-gradient-primary btn-sm px-3">
                        <i class="mdi mdi-check me-1"></i> Uložit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
