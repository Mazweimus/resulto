@extends('layouts.app')

@section('title', 'Vytvořit nový turnaj')

@push('styles')
<style>
    /* Styly pro vybraného hráče v pravém sidebaru */
    .selected-player-item {
        background: #ffffff;
        border: 1px solid #e7eaf3;
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 8px;
        transition: all 0.2s ease-in-out;
    }
    .selected-player-item:hover {
        border-color: #b66dff;
        background: #fafbff;
    }

    /* Dokonalé odsazení radio buttonů pro volbu pohlaví */
    .gender-radio-group .form-check {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .gender-radio-group .form-check-input {
        margin: 0 !important;
        padding: 0 !important;
        float: none !important;
        position: static !important;
        cursor: pointer !important;
    }
    .gender-radio-group .form-check-label {
        margin: 0 !important;
        padding: 0 !important;
        cursor: pointer !important;
        line-height: 1.2 !important;
        white-space: nowrap !important;
    }

    /* Řádky tabulky hráčů */
    .player-table-row {
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }
    .player-table-row:hover {
        background-color: #f7f4fd !important;
    }
    .player-table-row.is-selected {
        background-color: #f0eaff !important;
    }

    /* Sticky pravý sidebar */
    .sticky-sidebar {
        position: -webkit-sticky;
        position: sticky;
        top: 85px;
        z-index: 10;
    }

    /* Rychlá editace tlačítko */
    .btn-edit-player {
        opacity: 0.85;
        transition: transform 0.15s ease-in-out, opacity 0.15s ease-in-out;
    }
    .btn-edit-player:hover {
        opacity: 1;
        transform: scale(1.1);
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-trophy-variant"></i>
        </span> Vytvořit nový turnaj
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Turnaje</a></li>
            <li class="breadcrumb-item active" aria-current="page">Nový turnaj</li>
        </ul>
    </nav>
</div>

<div class="row">
    {{-- LEVÝ SLOUPEC (col-lg-8): ŘÁDEK 1, ŘÁDEK 2, ŘÁDEK 3 --}}
    <div class="col-lg-8">

        {{-- ŘÁDEK 1: KOMPAKTNÍ ZÁKLADNÍ ÚDAJE TURNAJE (1 ŘÁDEK) --}}
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-body py-3">
                <form action="{{ route('tournaments.store') }}" method="POST" id="tournament-form">
                    @csrf
                    {{-- Dynamicky generované skryté inputy s ID vybraných hráčů --}}
                    <div id="hidden-player-inputs"></div>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <label for="tournament-name" class="form-label fw-bold mb-1 text-dark">
                                Název turnaje <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-trophy text-primary"></i></span>
                                <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="tournament-name" name="name" placeholder="např. Czech Darts Masters 2026" value="{{ old('name') }}" required autofocus>
                            </div>
                            @error('name')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-5">
                            <label for="tournament-format" class="form-label fw-bold mb-1 text-dark">
                                Systém turnaje <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-sitemap text-primary"></i></span>
                                <select name="format" class="form-select form-select-lg" id="tournament-format">
                                    <option value="double_elimination" {{ old('format', 'double_elimination') === 'double_elimination' ? 'selected' : '' }}>Double Elimination (s rozlosováním)</option>
                                    <option value="free_oneway_elimination" {{ old('format') === 'free_oneway_elimination' ? 'selected' : '' }}>FREE Oneway Elimination (Single + o 3. místo)</option>
                                    <option value="free_double_elimination" {{ old('format') === 'free_double_elimination' ? 'selected' : '' }}>FREE Double Elimination (s Pavoukem B a Finále)</option>
                                    <option value="single_elimination" {{ old('format') === 'single_elimination' ? 'selected' : '' }}>KO pavouk</option>
                                    <option value="groups" {{ old('format') === 'groups' ? 'selected' : '' }}>Skupiny</option>
                                </select>
                            </div>
                        </div>

                        {{-- DROPDOWN PRO VOLBU VELIKOSTI PAVOUKA (POUZE PRO FREE FORMÁTY) --}}
                        <div class="col-12 mt-2 {{ in_array(old('format'), ['free_oneway_elimination', 'free_double_elimination']) ? '' : 'd-none' }}" id="free-bracket-size-wrapper">
                            <label for="bracket-size-select" class="form-label fw-bold mb-1 text-dark">
                                Velikost pavouka <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-numeric text-primary"></i></span>
                                <select name="bracket_size" class="form-select form-select-lg" id="bracket-size-select">
                                    <option value="8" {{ old('bracket_size', '8') == 8 ? 'selected' : '' }}>8 hráčů (4 zápasy v 1. kole, celkem 7 zápasů)</option>
                                    <option value="16" {{ old('bracket_size') == 16 ? 'selected' : '' }}>16 hráčů (8 zápasů v 1. kole, celkem 15 zápasů)</option>
                                    <option value="32" {{ old('bracket_size') == 32 ? 'selected' : '' }}>32 hráčů (16 zápasů v 1. kole, celkem 31 zápasů)</option>
                                </select>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                <i class="mdi mdi-information-outline me-1"></i> Vygeneruje prázdný pavouk dané velikosti. Hráče budete do jednotlivých slotů dosazovat přímo v bracketu.
                            </small>
                            @error('bracket_size')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- NASTAVENÍ ROČNÍKU A ZAPOČÍTÁVÁNÍ DO ŽEBŘÍČKU --}}
                        <div class="col-md-5 mt-2">
                            <label for="season-year" class="form-label fw-bold mb-1 text-dark">
                                Ročník turnaje <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-calendar-text text-primary"></i></span>
                                <input type="number" class="form-control form-control-lg @error('season_year') is-invalid @enderror" id="season-year" name="season_year" value="{{ old('season_year', date('Y')) }}" min="2020" max="2100" required>
                            </div>
                            @error('season_year')
                                <div class="text-danger text-small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7 mt-2 pt-md-4">
                            <div class="form-check form-switch ps-0 d-flex align-items-center gap-2">
                                <input type="hidden" name="counts_for_ranking" value="0">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="counts_for_ranking" value="1" id="counts-for-ranking" {{ old('counts_for_ranking', '1') == '1' ? 'checked' : '' }} style="width: 2.4em; height: 1.25em; cursor: pointer;">
                                <div>
                                    <label class="form-check-label fw-bold text-dark mb-0" for="counts-for-ranking" style="cursor: pointer;">
                                        <i class="mdi mdi-trophy-award text-warning me-1"></i> Započítávat do ročníkového žebříčku
                                    </label>
                                    <small class="text-muted d-block" style="font-size: 0.78rem;">Při vypnutí se body z turnaje nezapočítají do ročníkového ani celkového žebříčku.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    @error('player_ids')
                        <div class="alert alert-danger py-2 mt-3 mb-0">
                            <i class="mdi mdi-alert-circle me-1"></i> {{ $message }}
                        </div>
                    @enderror
                    @error('players')
                        <div class="alert alert-danger py-2 mt-3 mb-0">
                            <i class="mdi mdi-alert-circle me-1"></i> {{ $message }}
                        </div>
                    @enderror
                    @error('slots')
                        <div class="alert alert-danger py-2 mt-3 mb-0">
                            <i class="mdi mdi-alert-circle me-1"></i> {{ $message }}
                        </div>
                    @enderror
                </form>
            </div>
        </div>

        {{-- NAŠEPTÁVAČ PRO EXISTUJÍCÍ HRÁČE --}}
        <datalist id="players-datalist">
            @foreach($players as $p)
                <option value="{{ $p['name'] }}" data-id="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['gender_label'] }})</option>
            @endforeach
        </datalist>

        {{-- INFORMAČNÍ KARTA PRO FREE FORMÁTY --}}
        <div id="free-elimination-info-card" class="card mb-3 shadow-sm border border-primary border-1 {{ in_array(old('format'), ['free_oneway_elimination', 'free_double_elimination']) ? '' : 'd-none' }}">
            <div class="card-body py-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; min-width: 52px;">
                        <i class="mdi mdi-sitemap fs-3"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="text-primary fw-bold mb-1" id="free-info-title">FREE Elimination — Interaktivní nasazení v pavouku</h4>
                        <p class="text-muted mb-3" id="free-info-desc">
                            Ve formátu FREE Elimination hráče nepřiřazujete předem. Zvolte velikost pavouka (8, 16 nebo 32 pozic) v horním formuláři a klikněte na tlačítko <strong>„Vytvořit turnaj a přejít k nasazení“</strong>. Hráče budete do jednotlivých slotů 1. kola dosazovat interaktivně přímo v rozhraní pavouka s automatickým vyhodnocením volných losů (BYE).
                        </p>
                        <button type="submit" form="tournament-form" class="btn btn-gradient-success btn-lg fw-bold px-4" id="btn-submit-free-direct">
                            <i class="mdi mdi-play-circle-outline me-1"></i> Vytvořit turnaj a přejít k nasazení
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="standard-player-selection-section" class="{{ in_array(old('format'), ['free_oneway_elimination', 'free_double_elimination']) ? 'd-none' : '' }}">
        {{-- ŘÁDEK 2: RYCHLÉ PŘIDÁNÍ NOVÉHO HRÁČE NA JEDEN ŘÁDEK --}}
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-body py-3">
                <div id="quick-add-error-alert" class="alert alert-danger py-2 mb-2 d-none">
                    <i class="mdi mdi-alert-circle me-1"></i> <span id="quick-add-error-text"></span>
                </div>
                <form id="quick-add-player-form">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="mdi mdi-account-plus text-primary"></i></span>
                                <input type="text" class="form-control" id="quick-player-name" name="name" placeholder="Jméno a příjmení nového hráče" required autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="gender-radio-group d-flex align-items-center justify-content-center gap-3 border rounded px-3 py-2 bg-light">
                                <div class="form-check d-flex align-items-center gap-1 m-0 p-0">
                                    <input class="form-check-input m-0" type="radio" name="quick_gender" id="quick_gender_male" value="male" checked>
                                    <label class="form-check-label m-0 p-0 fw-bold text-dark" for="quick_gender_male">
                                        <i class="mdi mdi-gender-male text-primary"></i> Muž
                                    </label>
                                </div>
                                <div class="form-check d-flex align-items-center gap-1 m-0 p-0">
                                    <input class="form-check-input m-0" type="radio" name="quick_gender" id="quick_gender_female" value="female">
                                    <label class="form-check-label m-0 p-0 fw-bold text-dark" for="quick_gender_female">
                                        <i class="mdi mdi-gender-female text-danger"></i> Žena
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-gradient-info w-100 py-2 fw-bold" id="btn-quick-add-player">
                                <i class="mdi mdi-plus-circle me-1"></i> Vytvořit a přidat do turnaje
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ŘÁDEK 3: VELKÁ TABULKA EXISTUJÍCÍCH HRÁČŮ S RYCHLÝM VYHLEDÁVÁNÍM A EDITACÍ --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h4 class="card-title text-primary mb-1">
                            <i class="mdi mdi-account-group me-1"></i> Seznam hráčů v databázi
                        </h4>
                        <p class="card-description mb-0">Kliknutím na řádek přidáte hráče do turnaje. Kliknutím na tužku upravíte údaje.</p>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-quick-8" title="Vybrat prvních 8 hráčů">Vybrat 8</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-quick-16" title="Vybrat prvních 16 hráčů">Vybrat 16</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-quick-32" title="Vybrat prvních 32 hráčů">Vybrat 32</button>
                    </div>
                </div>

                {{-- Hledací pole pro okamžitou filtraci --}}
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white"><i class="mdi mdi-magnify text-primary"></i></span>
                    <input type="text" id="player-table-search" class="form-control" placeholder="Rychlé fulltext hledání hráče podle jména..." autocomplete="off">
                    <button type="button" class="btn btn-light border" id="btn-clear-table-search" title="Vymazat filtr">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>

                {{-- Tabulka hráčů --}}
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="players-table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Jméno a příjmení</th>
                                <th class="text-center" style="width: 140px;">Úspěšnost</th>
                                <th class="text-center" style="width: 130px;">Stav</th>
                                <th class="text-end" style="width: 90px;">Akce</th>
                            </tr>
                        </thead>
                        <tbody id="players-table-body">
                            {{-- Dynamicky generováno JavaScriptem --}}
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center text-muted text-small mt-2 pt-2 border-top">
                    <span id="table-total-count">Celkem v databázi: {{ count($players) }} hráčů</span>
                    <span id="table-visible-count" class="badge bg-light text-muted">Zobrazeno: {{ count($players) }}</span>
                </div>
            </div>
        </div>
        </div>

    </div>

    {{-- PRAVÝ SLOUPEC (col-lg-4): STICKY SIDEBAR VYBRANÝCH HRÁČŮ DO TURNAJE --}}
    <div class="col-lg-4">
        <div class="card shadow-sm sticky-sidebar border-0">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                    <div>
                        <h5 class="card-title mb-0 text-dark">
                            <i class="mdi mdi-account-multiple-check text-primary me-1"></i> Vybraní hráči do turnaje
                        </h5>
                        <div class="text-muted fw-bold mt-1" id="sidebar-count-text" style="font-size: 0.85rem;">Počet hráčů: 0</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="sidebar-count-badge" class="badge badge-gradient-primary">0</span>
                        <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2" id="btn-clear-selection" style="display: none;" title="Zrušit celý výběr">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>

                {{-- Seznam vybraných hráčů --}}
                <div id="sidebar-players-list" class="sidebar-list-container" style="max-height: 480px; overflow-y: auto;">
                    {{-- Dynamicky generováno JavaScriptem --}}
                </div>

                {{-- Tlačítko pro založení turnaje navázané na formulář vlevo přes form="tournament-form" --}}
                <div class="pt-3 mt-3 border-top">
                    <button type="submit" form="tournament-form" class="btn btn-gradient-success w-100 py-3 fw-bold fs-6" id="submit-btn-right">
                        <i class="mdi mdi-play-circle-outline me-1"></i> Založit turnaj a rozlosovat
                    </button>
                    <small class="text-muted d-block text-center mt-2" id="sidebar-submit-hint" style="font-size: 0.78rem;">
                        Pro turnaj vyberte alespoň 2 hráče (ideálně 8, 16 nebo 32).
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODÁLNÍ OKNO PRO RYCHLOU EDITACI HRÁČE --}}
<div class="modal fade" id="quickEditPlayerModal" tabindex="-1" aria-labelledby="quickEditPlayerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title text-primary fw-bold" id="quickEditPlayerModalLabel">
                    <i class="mdi mdi-account-edit me-1"></i> Upravit údaje hráče
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
            </div>
            <form id="quick-edit-player-form">
                <div class="modal-body p-4">
                    <div id="quick-edit-error-alert" class="alert alert-danger py-2 d-none mb-3">
                        <i class="mdi mdi-alert-circle me-1"></i> <span id="quick-edit-error-text"></span>
                    </div>

                    <input type="hidden" id="edit-player-id" name="id">

                    <div class="mb-3">
                        <label for="edit-player-name" class="form-label fw-bold text-dark">
                            Jméno a příjmení hráče <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="mdi mdi-account text-primary"></i></span>
                            <input type="text" class="form-control" id="edit-player-name" name="name" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold d-block text-dark">Pohlaví <span class="text-danger">*</span></label>
                        <div class="gender-radio-group d-flex align-items-center gap-4 border rounded p-2 px-3 bg-light">
                            <div class="form-check d-flex align-items-center gap-2 m-0 p-0">
                                <input class="form-check-input m-0" type="radio" name="edit_gender" id="edit_gender_male" value="male">
                                <label class="form-check-label m-0 p-0 fw-bold text-dark" for="edit_gender_male">
                                    <i class="mdi mdi-gender-male text-primary me-1"></i> Muž
                                </label>
                            </div>
                            <div class="form-check d-flex align-items-center gap-2 m-0 p-0">
                                <input class="form-check-input m-0" type="radio" name="edit_gender" id="edit_gender_female" value="female">
                                <label class="form-check-label m-0 p-0 fw-bold text-dark" for="edit_gender_female">
                                    <i class="mdi mdi-gender-female text-danger me-1"></i> Žena
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-gradient-primary px-4 fw-bold" id="btn-save-edit-player">
                        <i class="mdi mdi-check me-1"></i> Uložit změny
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Data ze serveru
        const allPlayers = @json($players);
        const initialSelected = @json(old('players', old('player_ids', [])));
        let selectedPlayerIds = new Set(initialSelected.map(id => parseInt(id)));

        let searchQuery = '';

        // Prvky DOM
        const tableBody = document.getElementById('players-table-body');
        const searchInput = document.getElementById('player-table-search');
        const clearSearchBtn = document.getElementById('btn-clear-table-search');
        const tableTotalCount = document.getElementById('table-total-count');
        const tableVisibleCount = document.getElementById('table-visible-count');

        const sidebarPlayersList = document.getElementById('sidebar-players-list');
        const sidebarCountText = document.getElementById('sidebar-count-text');
        const sidebarCountBadge = document.getElementById('sidebar-count-badge');
        const clearSelectionBtn = document.getElementById('btn-clear-selection');
        const hiddenInputsContainer = document.getElementById('hidden-player-inputs');
        const submitBtnRight = document.getElementById('submit-btn-right');

        // Prvky pro FREE Elimination
        const tournamentFormatSelect = document.getElementById('tournament-format');
        const freeBracketSizeWrapper = document.getElementById('free-bracket-size-wrapper');
        const freeBracketSizeSelect = document.getElementById('bracket-size-select');
        const freeEliminationInfoCard = document.getElementById('free-elimination-info-card');
        const standardPlayerSelectionSection = document.getElementById('standard-player-selection-section');
        const sidebarSubmitHint = document.getElementById('sidebar-submit-hint');
        const playersDatalist = document.getElementById('players-datalist');

        const quickAddPlayerForm = document.getElementById('quick-add-player-form');
        const quickPlayerNameInput = document.getElementById('quick-player-name');
        const quickAddErrorAlert = document.getElementById('quick-add-error-alert');
        const quickAddErrorText = document.getElementById('quick-add-error-text');
        const btnQuickAddPlayer = document.getElementById('btn-quick-add-player');

        // Modální okno pro editaci
        const quickEditModalEl = document.getElementById('quickEditPlayerModal');
        const quickEditForm = document.getElementById('quick-edit-player-form');
        const editPlayerIdInput = document.getElementById('edit-player-id');
        const editPlayerNameInput = document.getElementById('edit-player-name');
        const quickEditErrorAlert = document.getElementById('quick-edit-error-alert');
        const quickEditErrorText = document.getElementById('quick-edit-error-text');
        const btnSaveEditPlayer = document.getElementById('btn-save-edit-player');

        function showEditModal() {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(quickEditModalEl).show();
            } else if (window.$ && $.fn.modal) {
                $(quickEditModalEl).modal('show');
            }
        }

        function hideEditModal() {
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(quickEditModalEl).hide();
            } else if (window.$ && $.fn.modal) {
                $(quickEditModalEl).modal('hide');
            }
        }

        // 1. Filtrování hráčů pro tabulku
        function getFilteredPlayers() {
            if (!searchQuery.trim()) {
                return allPlayers;
            }
            const q = searchQuery.toLowerCase().trim();
            return allPlayers.filter(p => (p.name || '').toLowerCase().includes(q));
        }

        // 2. Vykreslení tabulky hráčů (Řádek 3)
        // Sloupec pohlaví je zde striktně skrytý
        function renderTable() {
            const filtered = getFilteredPlayers();
            tableBody.innerHTML = '';

            tableTotalCount.textContent = `Celkem v databázi: ${allPlayers.length} hráčů`;
            tableVisibleCount.textContent = `Zobrazeno: ${filtered.length}`;

            if (filtered.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td colspan="4" class="text-center py-4 text-muted">
                        <i class="mdi mdi-account-off fs-2 d-block mb-1 text-muted"></i>
                        Žádný hráč neodpovídá výrazu "${searchQuery}".
                    </td>
                `;
                tableBody.appendChild(tr);
                return;
            }

            filtered.forEach(p => {
                const isSelected = selectedPlayerIds.has(p.id);
                const tr = document.createElement('tr');
                tr.className = `player-table-row ${isSelected ? 'table-primary is-selected' : ''}`;
                tr.setAttribute('data-id', p.id);

                const winRate = typeof p.win_rate !== 'undefined' ? p.win_rate : 0;

                tr.innerHTML = `
                    <td>
                        <span class="fw-bold text-dark">${escapeHtml(p.name)}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge ${winRate >= 50 ? 'bg-success' : 'bg-secondary'} px-2 py-1" style="font-size: 0.8rem;">
                            ${winRate} %
                        </span>
                    </td>
                    <td class="text-center">
                        ${isSelected
                            ? `<span class="badge bg-success text-white py-1 px-2"><i class="mdi mdi-check"></i> V turnaji</span>`
                            : `<span class="badge bg-light text-primary border py-1 px-2"><i class="mdi mdi-plus"></i> Vybrat</span>`
                        }
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm p-1 px-2 btn-edit-player" data-id="${p.id}" title="Upravit hráče">
                            <i class="mdi mdi-pencil text-primary"></i>
                        </button>
                    </td>
                `;

                // Kliknutí na řádek tabulky (kromě tlačítka pro editaci)
                tr.addEventListener('click', function (e) {
                    if (e.target.closest('.btn-edit-player')) {
                        return;
                    }
                    togglePlayerSelection(p.id);
                });

                // Kliknutí na tlačítko tužky pro rychlou editaci
                const editBtn = tr.querySelector('.btn-edit-player');
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openEditModal(p.id);
                });

                tableBody.appendChild(tr);
            });
        }

        // 3. Vykreslení pravého sidebaru s vybranými hráči
        function renderSidebar() {
            const count = selectedPlayerIds.size;

            sidebarCountText.textContent = `Počet hráčů: ${count}`;
            sidebarCountBadge.textContent = count;

            if (count === 8 || count === 16 || count === 32) {
                sidebarCountBadge.className = 'badge badge-gradient-success fs-6';
            } else if (count >= 2) {
                sidebarCountBadge.className = 'badge badge-gradient-info fs-6';
            } else {
                sidebarCountBadge.className = 'badge badge-gradient-primary fs-6';
            }

            // Aktualizace skrytých inputů formuláře pro odeslání (players[] i player_ids[])
            hiddenInputsContainer.innerHTML = '';
            selectedPlayerIds.forEach(id => {
                const hiddenInput1 = document.createElement('input');
                hiddenInput1.type = 'hidden';
                hiddenInput1.name = 'players[]';
                hiddenInput1.value = id;
                hiddenInputsContainer.appendChild(hiddenInput1);

                const hiddenInput2 = document.createElement('input');
                hiddenInput2.type = 'hidden';
                hiddenInput2.name = 'player_ids[]';
                hiddenInput2.value = id;
                hiddenInputsContainer.appendChild(hiddenInput2);
            });

            sidebarPlayersList.innerHTML = '';

            if (count === 0) {
                clearSelectionBtn.style.display = 'none';
                sidebarPlayersList.innerHTML = `
                    <div class="text-center py-5 text-muted" id="sidebar-empty-state">
                        <i class="mdi mdi-account-group-outline fs-1 d-block mb-2 text-muted"></i>
                        <div class="fw-bold">Zatím nebyli vybráni žádní hráči</div>
                        <small class="text-muted">Klikněte na hráče v tabulce nebo vytvořte nového.</small>
                    </div>
                `;
            } else {
                clearSelectionBtn.style.display = 'inline-block';

                selectedPlayerIds.forEach(id => {
                    const player = allPlayers.find(p => p.id === id);
                    if (player) {
                        const genderLabel = player.gender === 'female' ? 'Žena' : 'Muž';
                        const genderBadgeClass = player.gender === 'female' ? 'bg-danger text-white' : 'bg-primary text-white';
                        const genderIcon = player.gender === 'female' ? 'mdi-gender-female' : 'mdi-gender-male';

                        const item = document.createElement('div');
                        item.className = 'selected-player-item d-flex justify-content-between align-items-center';
                        item.innerHTML = `
                            <div class="d-flex align-items-center text-truncate me-2">
                                <span class="fw-bold text-dark text-truncate me-2" title="${escapeHtml(player.name)}">${escapeHtml(player.name)}</span>
                                <span class="badge ${genderBadgeClass}" style="font-size: 0.7rem; padding: 2px 6px;">
                                    <i class="mdi ${genderIcon} me-1"></i>${genderLabel}
                                </span>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 px-2 btn-remove-player" data-id="${player.id}" title="Odebrat z turnaje">
                                <i class="mdi mdi-close"></i>
                            </button>
                        `;

                        item.querySelector('.btn-remove-player').addEventListener('click', function (e) {
                            e.stopPropagation();
                            removePlayerFromTournament(player.id);
                        });

                        sidebarPlayersList.appendChild(item);
                    }
                });
            }
        }

        // 4. Správa výběru hráčů
        function addPlayerToTournament(id) {
            id = parseInt(id);
            selectedPlayerIds.add(id);
            renderSidebar();
            renderTable();
        }

        function removePlayerFromTournament(id) {
            id = parseInt(id);
            selectedPlayerIds.delete(id);
            renderSidebar();
            renderTable();
        }

        function togglePlayerSelection(id) {
            id = parseInt(id);
            if (selectedPlayerIds.has(id)) {
                selectedPlayerIds.delete(id);
            } else {
                selectedPlayerIds.add(id);
            }
            renderSidebar();
            renderTable();
        }

        // 5. Rychlý výběr prvních N hráčů
        function selectFirstN(n) {
            selectedPlayerIds.clear();
            allPlayers.slice(0, n).forEach(p => selectedPlayerIds.add(p.id));
            renderSidebar();
            renderTable();
        }

        document.getElementById('btn-quick-8')?.addEventListener('click', () => selectFirstN(8));
        document.getElementById('btn-quick-16')?.addEventListener('click', () => selectFirstN(16));
        document.getElementById('btn-quick-32')?.addEventListener('click', () => selectFirstN(32));

        // 6. Vyhledávání v reálném čase pro tabulku
        searchInput.addEventListener('input', function () {
            searchQuery = this.value;
            renderTable();
        });

        clearSearchBtn.addEventListener('click', function () {
            searchInput.value = '';
            searchQuery = '';
            renderTable();
            searchInput.focus();
        });

        // 7. Zrušení celého výběru
        clearSelectionBtn.addEventListener('click', function () {
            if (confirm('Opravdu chcete odebrat všechny vybrané hráče z turnaje?')) {
                selectedPlayerIds.clear();
                renderSidebar();
                renderTable();
            }
        });

        // 8. Řádek 2: Odeslání formuláře pro rychlé přidání nového hráče (AJAX POST)
        quickAddPlayerForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const name = quickPlayerNameInput.value.trim();
            const gender = document.querySelector('input[name="quick_gender"]:checked')?.value || 'male';

            if (!name) {
                quickAddErrorAlert.classList.remove('d-none');
                quickAddErrorText.textContent = 'Zadejte prosím jméno hráče.';
                quickPlayerNameInput.focus();
                return;
            }

            quickAddErrorAlert.classList.add('d-none');
            btnQuickAddPlayer.disabled = true;
            btnQuickAddPlayer.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Ukládám...';

            try {
                const response = await fetch("{{ route('players.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ name: name, gender: gender })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Chyba při ukládání hráče.');
                    quickAddErrorAlert.classList.remove('d-none');
                    quickAddErrorText.textContent = errorMsg;
                    btnQuickAddPlayer.disabled = false;
                    btnQuickAddPlayer.innerHTML = '<i class="mdi mdi-plus-circle me-1"></i> Vytvořit a přidat do turnaje';
                    return;
                }

                const newPlayer = data.player;

                // Přidání do pole všech hráčů a seřazení podle abecedy
                allPlayers.push(newPlayer);
                allPlayers.sort((a, b) => a.name.localeCompare(b.name, 'cs'));

                // Aktualizace datalistu
                if (playersDatalist) {
                    const opt = document.createElement('option');
                    opt.value = newPlayer.name;
                    opt.textContent = `${newPlayer.name} (${newPlayer.gender_label || 'Muž'})`;
                    playersDatalist.appendChild(opt);
                }

                selectedPlayerIds.add(newPlayer.id);

                // Reset a vyčištění formuláře pro další zadání
                quickAddPlayerForm.reset();
                quickAddErrorAlert.classList.add('d-none');

                // Aktualizace UI (tabulky i sidebaru)
                renderSidebar();
                renderTable();

            } catch (err) {
                console.error(err);
                quickAddErrorAlert.classList.remove('d-none');
                quickAddErrorText.textContent = 'Došlo k chybě při komunikaci se serverem.';
            } finally {
                btnQuickAddPlayer.disabled = false;
                btnQuickAddPlayer.innerHTML = '<i class="mdi mdi-plus-circle me-1"></i> Vytvořit a přidat do turnaje';
            }
        });

        // 9. Otevření modálu pro rychlou editaci hráče
        function openEditModal(playerId) {
            const player = allPlayers.find(p => p.id === playerId);
            if (!player) return;

            editPlayerIdInput.value = player.id;
            editPlayerNameInput.value = player.name;
            if (player.gender === 'female') {
                document.getElementById('edit_gender_female').checked = true;
            } else {
                document.getElementById('edit_gender_male').checked = true;
            }

            quickEditErrorAlert.classList.add('d-none');
            showEditModal();
        }

        // 10. Uložení rychlé editace hráče (AJAX PUT)
        quickEditForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const playerId = editPlayerIdInput.value;
            const name = editPlayerNameInput.value.trim();
            const gender = document.querySelector('input[name="edit_gender"]:checked')?.value || 'male';

            if (!name) {
                quickEditErrorAlert.classList.remove('d-none');
                quickEditErrorText.textContent = 'Zadejte prosím jméno hráče.';
                editPlayerNameInput.focus();
                return;
            }

            quickEditErrorAlert.classList.add('d-none');
            btnSaveEditPlayer.disabled = true;
            btnSaveEditPlayer.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Ukládám...';

            try {
                const response = await fetch(`/players/${playerId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ name: name, gender: gender })
                });

                const data = await response.json();

                if (!response.ok) {
                    const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Chyba při ukládání hráče.');
                    quickEditErrorAlert.classList.remove('d-none');
                    quickEditErrorText.textContent = errorMsg;
                    btnSaveEditPlayer.disabled = false;
                    btnSaveEditPlayer.innerHTML = '<i class="mdi mdi-check me-1"></i> Uložit změny';
                    return;
                }

                // Aktualizace v allPlayers
                const player = allPlayers.find(p => p.id === parseInt(playerId));
                if (player) {
                    player.name = data.player.name;
                    player.gender = data.player.gender;
                    player.gender_label = data.player.gender_label;
                }

                allPlayers.sort((a, b) => a.name.localeCompare(b.name, 'cs'));

                // Zavření modálu a přerendrování
                hideEditModal();
                renderSidebar();
                renderTable();

            } catch (err) {
                console.error(err);
                quickEditErrorAlert.classList.remove('d-none');
                quickEditErrorText.textContent = 'Došlo k neočekávané chybě při ukládání změn.';
            } finally {
                btnSaveEditPlayer.disabled = false;
                btnSaveEditPlayer.innerHTML = '<i class="mdi mdi-check me-1"></i> Uložit změny';
            }
        });

        // --- LOGIKA PRO FREE FORMÁTY ---
        const freeFormats = ['free_oneway_elimination', 'free_double_elimination'];

        function handleFormatChange() {
            if (!tournamentFormatSelect) return;
            const currentFormat = tournamentFormatSelect.value;
            const isFree = freeFormats.includes(currentFormat);

            if (isFree) {
                if (freeBracketSizeWrapper) freeBracketSizeWrapper.classList.remove('d-none');
                if (freeEliminationInfoCard) freeEliminationInfoCard.classList.remove('d-none');
                if (standardPlayerSelectionSection) standardPlayerSelectionSection.classList.add('d-none');
                if (submitBtnRight) submitBtnRight.innerHTML = '<i class="mdi mdi-play-circle-outline me-1"></i> Vytvořit turnaj a přejít k nasazení';
                if (sidebarSubmitHint) sidebarSubmitHint.textContent = 'Ve formátu FREE budete hráče nasazovat přímo v pavouku.';
                if (sidebarCountText) sidebarCountText.textContent = 'FREE formát';
                if (sidebarCountBadge && freeBracketSizeSelect) sidebarCountBadge.textContent = freeBracketSizeSelect.value;

                // Dynamická úprava textu v info kartě dle zvoleného typu
                const infoTitle = document.getElementById('free-info-title');
                const infoDesc = document.getElementById('free-info-desc');
                if (currentFormat === 'free_oneway_elimination') {
                    if (infoTitle) infoTitle.textContent = 'FREE Oneway Elimination — Pavouk + Zápas o 3. a 4. místo';
                    if (infoDesc) infoDesc.innerHTML = 'Ve formátu <strong>FREE Oneway Elimination</strong> zvolte velikost pavouka (8, 16 nebo 32 pozic) a klikněte na „Vytvořit turnaj a přejít k nasazení“. Sloty 1. kola obsadíte přímo v pavouku. Poražení semifinalisté se automaticky utkají v povinném <strong>zápase o 3. a 4. místo</strong>.';
                } else if (currentFormat === 'free_double_elimination') {
                    if (infoTitle) infoTitle.textContent = 'FREE Double Elimination — Pavouk A, Pavouk B & Grand Finále';
                    if (infoDesc) infoDesc.innerHTML = 'Ve formátu <strong>FREE Double Elimination</strong> zvolte velikost pavouka (8, 16 nebo 32 pozic) a klikněte na „Vytvořit turnaj a přejít k nasazení“. Sloty 1. kola obsadíte přímo v pavouku. Zahrnuje Pavouka A (Winners), Pavouka B (Losers), automatické volné losy (BYE) v LB a esport <strong>Grand Finále s případným Resetem</strong>.';
                } else {
                    if (infoTitle) infoTitle.textContent = 'FREE Elimination — Interaktivní nasazení v pavouku';
                    if (infoDesc) infoDesc.innerHTML = 'Ve formátu <strong>FREE Elimination</strong> hráče nepřiřazujete předem. Zvolte velikost pavouka (8, 16 nebo 32 pozic) v horním formuláři a klikněte na tlačítko <strong>„Vytvořit turnaj a přejít k nasazení“</strong>. Hráče budete do jednotlivých slotů 1. kola dosazovat interaktivně přímo v rozhraní pavouka s automatickým vyhodnocením volných losů (BYE).';
                }
            } else {
                if (freeBracketSizeWrapper) freeBracketSizeWrapper.classList.add('d-none');
                if (freeEliminationInfoCard) freeEliminationInfoCard.classList.add('d-none');
                if (standardPlayerSelectionSection) standardPlayerSelectionSection.classList.remove('d-none');
                if (submitBtnRight) submitBtnRight.innerHTML = '<i class="mdi mdi-play-circle-outline me-1"></i> Založit turnaj a rozlosovat';
                if (sidebarSubmitHint) sidebarSubmitHint.textContent = 'Pro turnaj vyberte alespoň 2 hráče (ideálně 8, 16 nebo 32).';
                renderSidebar();
            }
        }

        tournamentFormatSelect?.addEventListener('change', handleFormatChange);

        freeBracketSizeSelect?.addEventListener('change', function () {
            if (tournamentFormatSelect && freeFormats.includes(tournamentFormatSelect.value)) {
                if (sidebarCountBadge) sidebarCountBadge.textContent = this.value;
            }
        });

        // Validace při odeslání hlavního formuláře turnaje
        document.getElementById('tournament-form').addEventListener('submit', function (e) {
            if (tournamentFormatSelect && freeFormats.includes(tournamentFormatSelect.value)) {
                hiddenInputsContainer.innerHTML = '';
                return true;
            } else {
                if (selectedPlayerIds.size < 2) {
                    e.preventDefault();
                    alert('Pro založení turnaje musíte vybrat minimálně 2 hráče.');
                    return false;
                }
            }
        });

        // Pomocná funkce pro bezpečný výpis HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Úvodní vykreslení
        handleFormatChange();
        renderSidebar();
        renderTable();
    });
</script>
@endpush
