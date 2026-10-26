@extends('layouts.app')

@section('title', 'Založit turnaj se základními skupinami')

@push('styles')
<style>
    /* Styly pro interaktivní karty skupin a Drag & Drop */
    .group-drop-zone {
        min-height: 140px;
        border: 2px dashed #e2e8f0;
        border-radius: 8px;
        padding: 10px;
        background-color: #fafbfc;
        transition: all 0.2s ease-in-out;
    }
    .group-drop-zone.drag-over {
        background-color: #f3e8ff !important;
        border-color: #b66dff !important;
        box-shadow: 0 0 10px rgba(182, 109, 255, 0.2);
    }
    .player-draggable {
        cursor: grab;
        user-select: none;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 8px;
        transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .player-draggable:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.06);
        border-color: #b66dff;
    }
    .player-draggable:active,
    .player-draggable.dragging {
        cursor: grabbing;
        opacity: 0.45;
        transform: scale(0.98);
        border-color: #7922ff;
    }
    .player-select-card {
        cursor: pointer;
        transition: all 0.15s ease;
        border: 1px solid #e7eaf3;
        border-radius: 8px;
    }
    .player-select-card:hover {
        background-color: #f8f7ff;
        border-color: #b66dff;
    }
    .player-select-card.selected {
        background-color: #f1e9ff;
        border-color: #7922ff;
        font-weight: 600;
    }
    .group-card {
        border-radius: 10px;
        border: 1px solid #e7eaf3;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.2s ease;
    }
    .group-card.has-error {
        border-color: #dc3545 !important;
        background-color: #fff8f8;
    }
    .drag-handle {
        color: #94a3b8;
        cursor: grab;
        font-size: 1.1rem;
    }
    .step-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
    }
    .step-pill.active {
        background: #b66dff;
        color: #ffffff;
    }
    .step-pill.completed {
        background: #1bcfb4;
        color: #ffffff;
    }
    .step-pill.pending {
        background: #e2e8f0;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-view-grid-plus"></i>
        </span> Rozdělení hráčů do základních skupin
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Turnaje</a></li>
            <li class="breadcrumb-item active" aria-current="page">Skupinový turnaj</li>
        </ul>
    </nav>
</div>

{{-- PRŮVODCE KROKY --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="step-pill active" id="step-1-pill"><i class="mdi mdi-numeric-1-circle"></i> 1. Výběr a parametry</span>
                        <i class="mdi mdi-chevron-right text-muted"></i>
                        <span class="step-pill pending" id="step-2-pill"><i class="mdi mdi-numeric-2-circle"></i> 2. Náhodný los a Drag & Drop</span>
                        <i class="mdi mdi-chevron-right text-muted"></i>
                        <span class="step-pill pending" id="step-3-pill"><i class="mdi mdi-numeric-3-circle"></i> 3. Uložení turnaje</span>
                    </div>
                    <div>
                        <a href="{{ route('tournaments.create') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-arrow-left me-1"></i> Běžný KO turnaj
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- HLAVNÍ FORMULÁŘ TURNAJE --}}
<form id="group-tournament-form" action="{{ route('tournaments.groups.store') }}" method="POST">
    @csrf

    {{-- KROK 1: ZÁKLADNÍ ÚDAJE TURNAJE --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title text-primary fw-bold mb-0">
                        <i class="mdi mdi-trophy-variant text-warning me-2"></i> Základní parametry turnaje
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="tournament-name" class="form-label fw-bold text-dark">
                                Název turnaje <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-trophy text-primary"></i></span>
                                <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="tournament-name" name="name" value="{{ old('name', 'Turnaj skupin ' . date('Y')) }}" placeholder="např. Premier League Skupiny 2026" required autofocus>
                            </div>
                            @error('name')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="tournament-date" class="form-label fw-bold text-dark">
                                Datum konání <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-calendar text-primary"></i></span>
                                <input type="date" class="form-control form-control-lg @error('date') is-invalid @enderror" id="tournament-date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                            </div>
                            @error('date')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label for="season-year" class="form-label fw-bold text-dark">
                                Ročník turnaje <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-calendar-text text-primary"></i></span>
                                <input type="number" class="form-control form-control-lg @error('season_year') is-invalid @enderror" id="season-year" name="season_year" value="{{ old('season_year', date('Y')) }}" min="2020" max="2100" required>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label for="best-of-legs" class="form-label fw-bold text-dark">
                                Počet legů (BO) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-numeric text-primary"></i></span>
                                <input type="number" class="form-control form-control-lg @error('best_of_legs') is-invalid @enderror" id="best-of-legs" name="best_of_legs" value="{{ old('best_of_legs', 3) }}" min="1" max="21" required>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="form-check form-switch ps-0 d-flex align-items-center gap-2">
                                <input type="hidden" name="counts_for_ranking" value="0">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="counts_for_ranking" value="1" id="counts-for-ranking" {{ old('counts_for_ranking', '1') == '1' ? 'checked' : '' }} style="width: 2.4em; height: 1.25em; cursor: pointer;">
                                <div>
                                    <label class="form-check-label fw-bold text-dark mb-0" for="counts-for-ranking" style="cursor: pointer;">
                                        <i class="mdi mdi-trophy-award text-warning me-1"></i> Započítávat výsledky turnaje do žebříčku
                                    </label>
                                    <small class="text-muted d-block" style="font-size: 0.8rem;">Při vypnutí se body nezapočítají do ročníkového žebříčku hráčů.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KROK 2: VÝBĚR HRÁČŮ A NASTAVENÍ POČTU SKUPIN --}}
    <div class="row mb-4">
        {{-- LEVÁ ČÁST: SEZNAM HRÁČŮ --}}
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title text-primary fw-bold mb-0">
                            <i class="mdi mdi-account-multiple-check text-success me-2"></i> Výběr hráčů do turnaje
                        </h5>
                        <small class="text-muted">Kliknutím na hráče ho vyberte nebo odeberte</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-select-all">
                            <i class="mdi mdi-check-all me-1"></i> Vybrat všechny
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-deselect-all">
                            <i class="mdi mdi-close me-1"></i> Zrušit výběr
                        </button>
                    </div>
                </div>

                <div class="card-body p-3">
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="mdi mdi-magnify text-primary"></i></span>
                            <input type="text" class="form-control" id="player-search-input" placeholder="Rychlé vyhledání hráče podle jména...">
                        </div>
                    </div>

                    <div class="row g-2" id="players-list-grid" style="max-height: 380px; overflow-y: auto;">
                        @foreach($players as $p)
                            <div class="col-md-6 player-item-wrapper" data-name="{{ mb_strtolower($p['name']) }}">
                                <div class="p-2 player-select-card d-flex align-items-center justify-content-between" data-id="{{ $p['id'] }}">
                                    <div class="d-flex align-items-center text-truncate me-2">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 32px; height: 32px; min-width: 32px; font-size: 0.8rem;">
                                            {{ mb_substr($p['name'], 0, 1) }}
                                        </div>
                                        <div class="text-truncate">
                                            <div class="player-name-label text-truncate text-dark" title="{{ $p['name'] }}">{{ $p['name'] }}</div>
                                            <small class="text-muted" style="font-size: 0.72rem;">
                                                <i class="mdi {{ $p['gender'] === 'female' ? 'mdi-gender-female text-danger' : 'mdi-gender-male text-primary' }}"></i>
                                                {{ $p['gender_label'] }}
                                            </small>
                                        </div>
                                    </div>
                                    <div class="form-check m-0 p-0">
                                        <input class="form-check-input player-checkbox" type="checkbox" value="{{ $p['id'] }}" data-name="{{ $p['name'] }}" data-gender="{{ $p['gender'] }}" data-gender-label="{{ $p['gender_label'] }}" style="pointer-events: none;">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
                    <span class="text-muted fw-bold">
                        Vybráno: <span id="selected-count-badge" class="badge bg-primary fs-6">0</span> hráčů
                    </span>
                    <span class="text-muted text-small">
                        Minimum pro skupiny: <strong>4 hráči</strong>
                    </span>
                </div>
            </div>
        </div>

        {{-- PRAVÁ ČÁST: NASTAVENÍ SKUPIN A TLAČÍTKO PRO LOS --}}
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title text-primary fw-bold mb-0">
                        <i class="mdi mdi-dice-multiple text-warning me-2"></i> Konfigurace rozlosování
                    </h5>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="mb-4">
                            <label for="groups-count-input" class="form-label fw-bold text-dark fs-6">
                                Počet základních skupin <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg">
                                <button type="button" class="btn btn-outline-secondary px-3" id="btn-dec-groups"><i class="mdi mdi-minus"></i></button>
                                <input type="number" id="groups-count-input" class="form-control text-center fw-bold fs-4" value="2" min="1" max="26">
                                <button type="button" class="btn btn-outline-secondary px-3" id="btn-inc-groups"><i class="mdi mdi-plus"></i></button>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="mdi mdi-information-outline text-primary me-1"></i> Vyberte 1 až 26 skupin (A, B, C...).
                            </small>
                        </div>

                        {{-- DYNAMICKÝ NÁHLED BALANCOVÁNÍ --}}
                        <div class="p-3 bg-light rounded border mb-4">
                            <div class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                                <span><i class="mdi mdi-scale-balance text-primary me-1"></i> Rozložení hráčů do skupin:</span>
                                <span class="badge bg-success" id="balance-status-badge">Připraveno</span>
                            </div>
                            <p class="text-muted text-small mb-2" id="balance-explanation">
                                Vyberte hráče a zvolte počet skupin pro náhled rovnoměrného rozložení.
                            </p>
                            <div class="d-flex flex-wrap gap-2" id="balance-preview-chips">
                                {{-- Dynamicky generované chipsy --}}
                            </div>
                        </div>

                        {{-- CHYBOVÉ HLÁŠENÍ PRO LOSOVÁNÍ --}}
                        <div class="alert alert-danger py-2 d-none" id="draw-error-alert">
                            <i class="mdi mdi-alert-circle me-1"></i> <span id="draw-error-text"></span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-gradient-primary btn-lg fw-bold py-3 shadow-sm" id="btn-draw-groups">
                            <i class="mdi mdi-dice-multiple me-1"></i> Rozlosovat do skupin (Náhodný los)
                        </button>
                        <small class="text-muted text-center">
                            Algoritmus Fisher-Yates náhodně a rovnoměrně rozdělí hráče do zvoleného počtu skupin.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KROK 3: VIZUÁLNÍ KARTY SKUPIN S DRAG & DROP --}}
    <div class="row d-none" id="groups-result-section">
        <div class="col-12">
            <div class="card shadow-sm border-0 border-top border-primary border-3">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="card-title text-primary fw-bold mb-1 fs-4">
                            <i class="mdi mdi-view-grid text-warning me-2"></i> Složení skupin a manuální úprava (Drag & Drop)
                        </h4>
                        <p class="card-description mb-0 text-muted">
                            Hráče můžete libovolně přetahovat myší mezi skupinami nebo přesunout tlačítkem před finálním uložením.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-warning text-dark fw-bold" id="btn-reroll-groups">
                            <i class="mdi mdi-refresh me-1"></i> Přelosovat znovu (Re-roll)
                        </button>
                        <button type="submit" class="btn btn-gradient-success btn-lg fw-bold px-4" id="btn-save-tournament">
                            <i class="mdi mdi-check-circle-outline me-1"></i> Uložit turnaj a vytvořit zápasy
                        </button>
                    </div>
                </div>

                <div class="card-body p-4">
                    {{-- UPOZORNĚNÍ POKUD NĚKTERÁ SKUPINA MÁ MÉNĚ NEŽ 2 HRÁČE --}}
                    <div class="alert alert-warning py-2 mb-3 d-none" id="groups-warning-alert">
                        <i class="mdi mdi-alert me-1"></i> <strong>Pozor:</strong> <span id="groups-warning-text"></span>
                    </div>

                    {{-- DYNAMICKÝ GRID KARET SKUPIN --}}
                    <div class="row g-4" id="groups-grid-container">
                        {{-- Karty skupin se vykreslí dynamicky přes JavaScript --}}
                    </div>
                </div>

                <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                    <span class="text-muted">
                        <i class="mdi mdi-gesture-swipe me-1"></i> Přetáhněte kartu hráče do jiné skupiny pro změnu nasazení.
                    </span>
                    <button type="submit" class="btn btn-gradient-success fw-bold px-4">
                        <i class="mdi mdi-content-save-outline me-1"></i> Uložit turnaj
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Reference na prvky DOM
    const playersGrid = document.getElementById('players-list-grid');
    const playerSearchInput = document.getElementById('player-search-input');
    const btnSelectAll = document.getElementById('btn-select-all');
    const btnDeselectAll = document.getElementById('btn-deselect-all');
    const selectedCountBadge = document.getElementById('selected-count-badge');
    const groupsCountInput = document.getElementById('groups-count-input');
    const btnIncGroups = document.getElementById('btn-inc-groups');
    const btnDecGroups = document.getElementById('btn-dec-groups');
    const balanceExplanation = document.getElementById('balance-explanation');
    const balancePreviewChips = document.getElementById('balance-preview-chips');
    const balanceStatusBadge = document.getElementById('balance-status-badge');
    const btnDrawGroups = document.getElementById('btn-draw-groups');
    const drawErrorAlert = document.getElementById('draw-error-alert');
    const drawErrorText = document.getElementById('draw-error-text');
    const groupsResultSection = document.getElementById('groups-result-section');
    const groupsGridContainer = document.getElementById('groups-grid-container');
    const btnRerollGroups = document.getElementById('btn-reroll-groups');
    const btnSaveTournament = document.getElementById('btn-save-tournament');
    const groupsWarningAlert = document.getElementById('groups-warning-alert');
    const groupsWarningText = document.getElementById('groups-warning-text');
    const tournamentForm = document.getElementById('group-tournament-form');

    // Kroky v hlavičce
    const step1Pill = document.getElementById('step-1-pill');
    const step2Pill = document.getElementById('step-2-pill');
    const step3Pill = document.getElementById('step-3-pill');

    // 2. Stav aplikace
    let currentGroups = []; // Array of group objects: { order, letter, name, players: [ { id, name, gender, gender_label } ] }
    let draggedPlayerId = null;
    let draggedFromGroupIdx = null;

    // 3. Pomocná funkce pro písmena skupin (0 => A, 1 => B ... 25 => Z, 26 => AA ...)
    function getGroupLetter(index) {
        let letters = '';
        let i = index + 1;
        while (i > 0) {
            let remainder = (i - 1) % 26;
            letters = String.fromCharCode(65 + remainder) + letters;
            i = Math.floor((i - 1) / 26);
        }
        return letters;
    }

    // 4. Získání vybraných hráčů
    function getSelectedPlayers() {
        const checkboxes = document.querySelectorAll('.player-checkbox:checked');
        return Array.from(checkboxes).map(cb => ({
            id: parseInt(cb.value),
            name: cb.dataset.name,
            gender: cb.dataset.gender,
            genderLabel: cb.dataset.genderLabel
        }));
    }

    // 5. Aktualizace náhledu vyvážení (Balance Preview)
    function updateBalancePreview() {
        const players = getSelectedPlayers();
        const total = players.length;
        const groupsCount = parseInt(groupsCountInput.value) || 1;

        selectedCountBadge.textContent = total;

        balancePreviewChips.innerHTML = '';

        if (total === 0) {
            balanceExplanation.textContent = 'Vyberte hráče a zvolte počet skupin pro náhled rovnoměrného rozložení.';
            balanceStatusBadge.textContent = 'Nevybráni hráči';
            balanceStatusBadge.className = 'badge bg-secondary';
            return;
        }

        if (groupsCount > total) {
            balanceExplanation.innerHTML = `<span class="text-danger fw-bold">Chyba:</span> Počet skupin (${groupsCount}) nemůže být vyšší než počet hráčů (${total}).`;
            balanceStatusBadge.textContent = 'Příliš mnoho skupin';
            balanceStatusBadge.className = 'badge bg-danger';
            return;
        }

        const minNeeded = groupsCount * 2;
        if (total < minNeeded) {
            balanceExplanation.innerHTML = `<span class="text-danger fw-bold">Nedostatek hráčů:</span> Každá skupina musí mít alespoň 2 hráče. Pro ${groupsCount} skupin potřebujete alespoň ${minNeeded} hráčů (chybí ${minNeeded - total}).`;
            balanceStatusBadge.textContent = 'Minimálně 2 na skupinu';
            balanceStatusBadge.className = 'badge bg-warning text-dark';
            return;
        }

        // Výpočet rovnoměrného rozdělení zbytků
        const base = Math.floor(total / groupsCount);
        const remainder = total % groupsCount;
        const counts = [];

        for (let i = 0; i < groupsCount; i++) {
            const size = base + (i < remainder ? 1 : 0);
            counts.push(size);

            const chip = document.createElement('span');
            chip.className = 'badge bg-white text-dark border px-2 py-1 shadow-sm';
            chip.innerHTML = `<strong>Skupina ${getGroupLetter(i)}:</strong> ${size} hráčů`;
            balancePreviewChips.appendChild(chip);
        }

        balanceExplanation.innerHTML = `<strong>${total} hráčů</strong> bude rozděleno do <strong>${groupsCount} skupin</strong> (poměr: ${counts.join(', ')}).`;
        balanceStatusBadge.textContent = 'Optimální rozložení';
        balanceStatusBadge.className = 'badge bg-success';
    }

    // 6. Klikání na karty hráčů pro výběr
    playersGrid.addEventListener('click', function (e) {
        const card = e.target.closest('.player-select-card');
        if (!card) return;

        const cb = card.querySelector('.player-checkbox');
        cb.checked = !cb.checked;
        card.classList.toggle('selected', cb.checked);
        updateBalancePreview();
    });

    // 7. Vyhledávání hráčů v reálném čase
    playerSearchInput.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();
        const wrappers = document.querySelectorAll('.player-item-wrapper');

        wrappers.forEach(wrap => {
            const name = wrap.dataset.name;
            wrap.style.display = name.includes(query) ? '' : 'none';
        });
    });

    // 8. Tlačítka Vybrat vše / Zrušit výběr
    btnSelectAll.addEventListener('click', function () {
        document.querySelectorAll('.player-item-wrapper').forEach(wrap => {
            if (wrap.style.display !== 'none') {
                const card = wrap.querySelector('.player-select-card');
                const cb = wrap.querySelector('.player-checkbox');
                cb.checked = true;
                card.classList.add('selected');
            }
        });
        updateBalancePreview();
    });

    btnDeselectAll.addEventListener('click', function () {
        document.querySelectorAll('.player-select-card').forEach(card => {
            const cb = card.querySelector('.player-checkbox');
            cb.checked = false;
            card.classList.remove('selected');
        });
        updateBalancePreview();
    });

    // 9. Tlačítka + a - pro počet skupin
    btnIncGroups.addEventListener('click', function () {
        let val = parseInt(groupsCountInput.value) || 1;
        if (val < 26) groupsCountInput.value = val + 1;
        updateBalancePreview();
    });

    btnDecGroups.addEventListener('click', function () {
        let val = parseInt(groupsCountInput.value) || 1;
        if (val > 1) groupsCountInput.value = val - 1;
        updateBalancePreview();
    });

    groupsCountInput.addEventListener('input', updateBalancePreview);

    // 10. Algoritmus Fisher-Yates pro náhodné zamíchání pole
    function fisherYatesShuffle(array) {
        const arr = [...array];
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }

    // 11. Rozlosování do skupin (Frontendová / AJAX generace)
    function executeGroupDraw() {
        drawErrorAlert.classList.add('d-none');

        const players = getSelectedPlayers();
        const groupsCount = parseInt(groupsCountInput.value) || 1;

        if (players.length < 2) {
            showDrawError('Vyberte prosím minimálně 2 hráče.');
            return;
        }

        if (groupsCount > players.length) {
            showDrawError(`Počet skupin (${groupsCount}) nemůže být vyšší než počet vybraných hráčů (${players.length}).`);
            return;
        }

        if (players.length < (groupsCount * 2)) {
            showDrawError(`Každá skupina musí obsahovat minimálně 2 hráče. Pro ${groupsCount} skupin je potřeba alespoň ${groupsCount * 2} hráčů.`);
            return;
        }

        // Náhodný los Fisher-Yates
        const shuffled = fisherYatesShuffle(players);
        const base = Math.floor(shuffled.length / groupsCount);
        const remainder = shuffled.length % groupsCount;

        currentGroups = [];
        let offset = 0;

        for (let i = 0; i < groupsCount; i++) {
            const size = base + (i < remainder ? 1 : 0);
            const groupPlayers = shuffled.slice(offset, offset + size);
            offset += size;

            currentGroups.push({
                order: i + 1,
                letter: getGroupLetter(i),
                name: 'Skupina ' + getGroupLetter(i),
                players: groupPlayers
            });
        }

        renderGroupCards();

        // Zobrazení výsledné sekce a posun
        groupsResultSection.classList.remove('d-none');
        step1Pill.className = 'step-pill completed';
        step2Pill.className = 'step-pill active';
        step3Pill.className = 'step-pill active';

        groupsResultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showDrawError(msg) {
        drawErrorText.textContent = msg;
        drawErrorAlert.classList.remove('d-none');
    }

    btnDrawGroups.addEventListener('click', executeGroupDraw);
    btnRerollGroups.addEventListener('click', executeGroupDraw);

    // 12. Vykreslení karet skupin s Drag & Drop a Quick Move
    function renderGroupCards() {
        groupsGridContainer.innerHTML = '';

        let hasInvalidGroup = false;
        let invalidGroupNames = [];

        currentGroups.forEach((group, gIdx) => {
            const pCount = group.players.length;
            const isInvalid = pCount < 2;

            if (isInvalid) {
                hasInvalidGroup = true;
                invalidGroupNames.push(group.name);
            }

            const col = document.createElement('div');
            col.className = 'col-md-6 col-xl-4';

            col.innerHTML = `
                <div class="card group-card h-100 ${isInvalid ? 'has-error' : ''}" data-group-index="${gIdx}">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6">${group.letter}</span>
                            <h5 class="fw-bold text-dark mb-0">${group.name}</h5>
                        </div>
                        <span class="badge ${isInvalid ? 'bg-danger' : 'bg-success'} group-count-badge">
                            ${pCount} ${pCount === 1 ? 'hráč' : (pCount >= 2 && pCount <= 4 ? 'hráči' : 'hráčů')}
                        </span>
                    </div>

                    <div class="card-body p-2">
                        <div class="group-drop-zone" data-group-index="${gIdx}">
                            ${group.players.map((p, pIdx) => `
                                <div class="player-draggable" draggable="true" data-player-id="${p.id}" data-group-index="${gIdx}">
                                    <div class="d-flex align-items-center text-truncate me-2">
                                        <i class="mdi mdi-drag-vertical drag-handle me-1" title="Chytit a přetáhnout"></i>
                                        <div class="rounded-circle bg-gradient-primary text-white d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 26px; height: 26px; min-width: 26px; font-size: 0.75rem;">
                                            ${p.name.charAt(0)}
                                        </div>
                                        <span class="fw-bold text-dark text-truncate text-small" title="${p.name}">${p.name}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-xs p-1 px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Přesunout do jiné skupiny">
                                                Přesunout
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                ${currentGroups.map((tg, targetGIdx) => targetGIdx !== gIdx ? `
                                                    <li>
                                                        <a class="dropdown-item btn-quick-move" href="#" data-player-id="${p.id}" data-from-group="${gIdx}" data-to-group="${targetGIdx}">
                                                            <i class="mdi mdi-arrow-right text-primary me-1"></i> Do ${tg.name}
                                                        </a>
                                                    </li>
                                                ` : '').join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}

                            ${pCount === 0 ? `
                                <div class="text-center py-4 text-muted fst-italic">
                                    <i class="mdi mdi-tray-arrow-down fs-3 d-block mb-1"></i>
                                    Sem přetáhněte hráče
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;

            groupsGridContainer.appendChild(col);
        });

        // Kontrola varování
        if (hasInvalidGroup) {
            groupsWarningText.textContent = `Skupiny [${invalidGroupNames.join(', ')}] mají méně než 2 hráče. Každá skupina musí obsahovat minimálně 2 hráče. Přetáhněte hráče do těchto skupin.`;
            groupsWarningAlert.classList.remove('d-none');
            btnSaveTournament.disabled = true;
        } else {
            groupsWarningAlert.classList.add('d-none');
            btnSaveTournament.disabled = false;
        }

        // Aktivace Drag & Drop listenerů
        setupDragAndDrop();

        // Aktivace Quick Move dropdownů
        setupQuickMove();

        // Aktualizace skrytých formulářových polí
        syncFormInputs();
    }

    // 13. Nastavení HTML5 Drag and Drop událostí
    function setupDragAndDrop() {
        const draggables = document.querySelectorAll('.player-draggable');
        const dropZones = document.querySelectorAll('.group-drop-zone');

        draggables.forEach(draggable => {
            draggable.addEventListener('dragstart', function (e) {
                draggedPlayerId = parseInt(this.dataset.playerId);
                draggedFromGroupIdx = parseInt(this.dataset.groupIndex);
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedPlayerId);
            });

            draggable.addEventListener('dragend', function () {
                this.classList.remove('dragging');
                dropZones.forEach(zone => zone.classList.remove('drag-over'));
            });
        });

        dropZones.forEach(zone => {
            zone.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });

            zone.addEventListener('dragleave', function () {
                this.classList.remove('drag-over');
            });

            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                this.classList.remove('drag-over');

                const targetGroupIdx = parseInt(this.dataset.groupIndex);
                if (isNaN(draggedPlayerId) || isNaN(draggedFromGroupIdx) || targetGroupIdx === draggedFromGroupIdx) {
                    return;
                }

                movePlayerBetweenGroups(draggedPlayerId, draggedFromGroupIdx, targetGroupIdx);
            });
        });
    }

    // 14. Nastavení klikacích Quick Move odkazů pro snadný přesun
    function setupQuickMove() {
        document.querySelectorAll('.btn-quick-move').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const pId = parseInt(this.dataset.playerId);
                const fromIdx = parseInt(this.dataset.fromGroup);
                const toIdx = parseInt(this.dataset.toGroup);

                movePlayerBetweenGroups(pId, fromIdx, toIdx);
            });
        });
    }

    // 15. Přesun hráče mezi skupinami v datovém modelu
    function movePlayerBetweenGroups(playerId, fromGroupIdx, toGroupIdx) {
        const fromGroup = currentGroups[fromGroupIdx];
        const toGroup = currentGroups[toGroupIdx];

        if (!fromGroup || !toGroup) return;

        const playerIdx = fromGroup.players.findIndex(p => p.id === playerId);
        if (playerIdx === -1) return;

        const [player] = fromGroup.players.splice(playerIdx, 1);
        toGroup.players.push(player);

        renderGroupCards();
    }

    // 16. Synchronizace datových struktur do skrytých inputů formuláře
    function syncFormInputs() {
        // Odstraníme staré skryté vstupy skupin
        const existingInputs = tournamentForm.querySelectorAll('.hidden-group-input');
        existingInputs.forEach(el => el.remove());

        currentGroups.forEach((group, gIdx) => {
            // Název skupiny
            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.className = 'hidden-group-input';
            nameInput.name = `groups[${gIdx}][name]`;
            nameInput.value = group.name;
            tournamentForm.appendChild(nameInput);

            // ID hráčů ve skupině
            group.players.forEach((player, pIdx) => {
                const pInput = document.createElement('input');
                pInput.type = 'hidden';
                pInput.className = 'hidden-group-input';
                pInput.name = `groups[${gIdx}][player_ids][]`;
                pInput.value = player.id;
                tournamentForm.appendChild(pInput);
            });
        });
    }

    // 17. Ošetření odeslání formuláře
    tournamentForm.addEventListener('submit', function (e) {
        if (currentGroups.length === 0) {
            e.preventDefault();
            alert('Nejprve prosím rozlosujte hráče do skupin.');
            return;
        }

        for (const g of currentGroups) {
            if (g.players.length < 2) {
                e.preventDefault();
                alert(`Skupina "${g.name}" obsahuje méně než 2 hráče. Každá skupina musí obsahovat alespoň 2 hráče.`);
                return;
            }
        }

        syncFormInputs();
    });

    // Výchozí inicializace
    // Automaticky předvybereme prvních 8 nebo 10 hráčů, pokud existují
    const firstCheckboxes = document.querySelectorAll('.player-checkbox');
    if (firstCheckboxes.length >= 4) {
        const defaultCount = Math.min(firstCheckboxes.length, 10);
        for (let i = 0; i < defaultCount; i++) {
            firstCheckboxes[i].checked = true;
            firstCheckboxes[i].closest('.player-select-card').classList.add('selected');
        }
    }
    updateBalancePreview();
});
</script>
@endpush
