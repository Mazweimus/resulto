@extends('layouts.app')

@section('title', 'Dlouhodobý žebříček hráčů' . ($isAllTime ? ' — All-Time' : ' — Ročník ' . $selectedYear))

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-podium-gold"></i>
        </span> Dlouhodobý žebříček hráčů
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Žebříček</li>
        </ul>
    </nav>
</div>

{{-- SOUHRNNÉ KARTY ŽEBŘÍČKU --}}
<div class="row">
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-primary card-img-holder text-white">
            <div class="card-body py-3">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle" />
                <h5 class="font-weight-normal mb-2">Bodující hráči <i class="mdi mdi-account-star float-end"></i></h5>
                <h2 class="mb-0">{{ $players->filter(fn($p) => $p->total_points > 0)->count() }}</h2>
                <small>{{ $isAllTime ? 'Všichni bodující v historii' : 'Hráčů s body v ročníku ' . $selectedYear }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-warning card-img-holder text-white">
            <div class="card-body py-3">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle" />
                <h5 class="font-weight-normal mb-2">Lídr žebříčku <i class="mdi mdi-crown float-end"></i></h5>
                <h2 class="mb-0 fs-3">{{ $players->first()?->name ?? 'Zatím nikdo' }}</h2>
                <small>{{ $players->first() ? $players->first()->total_points . ' bodů' : 'Žádná data' }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 stretch-card grid-margin">
        <div class="card bg-gradient-success card-img-holder text-white">
            <div class="card-body py-3">
                <img src="{{ asset('assets/images/dashboard/circle.svg') }}" class="card-img-absolute" alt="circle" />
                <h5 class="font-weight-normal mb-2">Celkem bodů <i class="mdi mdi-bullseye-arrow float-end"></i></h5>
                <h2 class="mb-0">{{ $players->sum(fn($p) => $p->total_points) }}</h2>
                <small>V {{ $tournamentsCount }} započítávaných turnajích</small>
            </div>
        </div>
    </div>
</div>

{{-- HLAVNÍ TABULKA ŽEBŘÍČKU --}}
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 border-bottom pb-3">
                    <div>
                        <h4 class="card-title text-primary mb-1 fs-4">
                            <i class="mdi mdi-format-list-numbered text-warning me-2"></i>
                            @if($isAllTime)
                                Celkové pořadí šipkařů Resulto — All-Time
                            @elseif($selectedYear === $currentYear)
                                Pořadí šipkařů Resulto — Aktuální ročník {{ $selectedYear }}
                            @else
                                Pořadí šipkařů Resulto — Archiv ročníku {{ $selectedYear }}
                            @endif
                        </h4>
                        <p class="card-description mb-0 text-muted">
                            @if($isAllTime)
                                Celkový dlouhodobý žebříček vypočítaný ze všech odehraných turnajů v historii.
                            @else
                                Ročníkový žebříček pro rok {{ $selectedYear }} vypočítaný z dokončených a započítávaných turnajů.
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        {{-- DROPDOWN PRO VÝBĚR ROČNÍKU / ALL-TIME --}}
                        <div class="d-flex align-items-center gap-2">
                            <label for="season-select" class="form-label mb-0 fw-bold text-dark text-nowrap d-none d-sm-inline">
                                <i class="mdi mdi-calendar-clock text-primary me-1"></i> Ročník:
                            </label>
                            <select id="season-select" class="form-select form-select-md border-primary fw-bold" style="min-width: 210px;" onchange="window.location.href = '{{ route('leaderboard') }}?season=' + this.value;">
                                <option value="{{ $currentYear }}" {{ (!$isAllTime && $selectedYear === $currentYear) ? 'selected' : '' }}>
                                    Aktuální ročník ({{ $currentYear }})
                                </option>
                                @foreach($availableYears as $year)
                                    @if($year !== $currentYear)
                                        <option value="{{ $year }}" {{ (!$isAllTime && $selectedYear === $year) ? 'selected' : '' }}>
                                            Ročník {{ $year }}
                                        </option>
                                    @endif
                                @endforeach
                                <option value="all_time" {{ $isAllTime ? 'selected' : '' }}>
                                    Celkový žebříček (All-Time)
                                </option>
                            </select>
                        </div>

                        <a href="{{ route('tournaments.create') }}" class="btn btn-gradient-primary">
                            <i class="mdi mdi-plus-circle me-1"></i> Založit nový turnaj
                        </a>
                    </div>
                </div>

                @if($players->isEmpty())
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="mdi mdi-trophy-off text-muted" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-2">Žádná turnajová data</h4>
                        <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
                            @if($isAllTime)
                                V systému zatím nebyly dokončeny žádné turnaje se započítáváním do žebříčku.
                            @else
                                V ročníku {{ $selectedYear }} zatím nebyly odehrány žádné turnaje započítávané do žebříčku.
                            @endif
                        </p>
                        <a href="{{ route('tournaments.create') }}" class="btn btn-gradient-primary">
                            <i class="mdi mdi-plus-circle me-1"></i> Založit turnaj pro ročník {{ $isAllTime ? $currentYear : $selectedYear }}
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 bg-white border rounded">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 160px;" class="fw-bold text-center">Pořadí v žebříčku</th>
                                    <th class="fw-bold">Jméno hráče</th>
                                    <th style="width: 200px;" class="fw-bold text-center">Odehrané turnaje</th>
                                    <th style="width: 180px;" class="fw-bold text-center">Celkový počet bodů</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $rank = 1; @endphp
                                @foreach($players as $player)
                                    <tr class="{{ $rank === 1 && $player->total_points > 0 ? 'table-warning bg-opacity-10' : '' }}">
                                        <td class="text-center">
                                            @if($rank === 1 && $player->total_points > 0)
                                                <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6 shadow-sm">
                                                    <i class="mdi mdi-medal me-1"></i> 1. místo
                                                </span>
                                            @elseif($rank === 2 && $player->total_points > 0)
                                                <span class="badge bg-secondary text-white fw-bold px-3 py-2 fs-6 shadow-sm">
                                                    <i class="mdi mdi-medal me-1"></i> 2. místo
                                                </span>
                                            @elseif($rank === 3 && $player->total_points > 0)
                                                <span class="badge text-white fw-bold px-3 py-2 fs-6 shadow-sm" style="background-color: #cd7f32;">
                                                    <i class="mdi mdi-medal me-1"></i> 3. místo
                                                </span>
                                            @else
                                                <span class="fw-bold text-muted fs-6">{{ $rank }}.</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-gradient-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 38px; height: 38px; min-width: 38px;">
                                                    {{ mb_substr($player->name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark fs-6">{{ $player->name }}</div>
                                                    <small class="text-muted">
                                                        <i class="mdi {{ $player->gender === 'female' ? 'mdi-gender-female text-danger' : 'mdi-gender-male text-primary' }} me-1"></i>
                                                        {{ $player->gender_label }}
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                                                <i class="mdi mdi-trophy-variant-outline text-primary me-1"></i>
                                                {{ $player->tournaments_played ?? $player->tournaments_count ?? $player->tournaments_played_count }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-gradient-primary text-white fw-bold px-3 py-2 fs-6 shadow-sm">
                                                {{ $player->total_points }} b.
                                            </span>
                                        </td>
                                    </tr>
                                    @php $rank++; @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
