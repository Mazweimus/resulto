@extends('layouts.app')

@section('title', 'Seznam turnajů')

@section('content')
<div class="page-header">
    <h3 class="page-title">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-trophy-variant"></i>
        </span> Turnaje v šipkách
    </h3>
    <nav aria-label="breadcrumb">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Přehled turnajů</li>
        </ul>
    </nav>
</div>

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="card-title mb-1">Evidence turnajů (Double Elimination)</h4>
                        <p class="card-description mb-0">Přehled aktivních a odehraných turnajů s vyhodnocením pódia</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('tournaments.groups.create') }}" class="btn btn-outline-primary">
                            <i class="mdi mdi-view-grid-plus me-1"></i> Skupinový turnaj
                        </a>
                        <a href="{{ route('tournaments.create') }}" class="btn btn-gradient-primary">
                            <i class="mdi mdi-plus-circle me-1"></i> Založit nový turnaj
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Název turnaje</th>
                                <th>Datum</th>
                                <th>Pravidla</th>
                                <th class="text-center">Počet hráčů</th>
                                <th class="text-center">Stav</th>
                                <th>Pódium (1. / 2. / 3. místo)</th>
                                <th class="text-end">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tournaments as $t)
                                <tr>
                                    <td>
                                        <div class="fw-bold fs-6 text-dark">{{ $t->name }}</div>
                                        <div class="text-muted text-small d-flex align-items-center flex-wrap gap-1 mt-1">
                                            <span>{{ $t->matches_count }} zápasů &bull; {{ $t->format_label }}</span>
                                            <span>&bull;</span>
                                            <span class="badge bg-light text-dark border">Ročník {{ $t->season_year }}</span>
                                            @if(!$t->counts_for_ranking)
                                                <span class="badge bg-secondary text-white">Exhibice</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <i class="mdi mdi-calendar text-muted me-1"></i> {{ $t->date->format('d.m.Y') }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $t->game_mode }} {{ strtoupper(str_replace('_', ' ', $t->checkout_mode)) }} (BO{{ $t->best_of_legs }})
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold">
                                        {{ $t->players->count() }}
                                    </td>
                                    <td class="text-center">
                                        <label class="badge {{ $t->status_badge_class }} py-2 px-3">
                                            {{ $t->status_label }}
                                        </label>
                                    </td>
                                    <td>
                                        @if($t->status === 'finished')
                                            <div class="text-small">
                                                @if($t->winner)
                                                    <div class="text-success fw-bold">🥇 {{ $t->winner->name }}</div>
                                                @endif
                                                @if($t->secondPlace)
                                                    <div class="text-muted">🥈 {{ $t->secondPlace->name }}</div>
                                                @endif
                                                @if($t->thirdPlace)
                                                    <div class="text-muted">🥉 {{ $t->thirdPlace->name }}</div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted fst-italic">Probíhá turnaj</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('tournaments.edit', $t->id) }}" class="btn btn-outline-primary btn-sm p-1 px-2" title="Upravit turnaj">
                                            <i class="mdi mdi-pencil"></i>
                                        </a>
                                        @if($t->format === 'groups')
                                            <a href="{{ route('tournaments.groups.show', $t->id) }}" class="btn btn-gradient-info btn-sm">
                                                <i class="mdi mdi-view-grid me-1"></i> Skupiny / Zápasy
                                            </a>
                                        @else
                                            <a href="{{ route('tournaments.play', $t->id) }}" class="btn btn-gradient-primary btn-sm">
                                                <i class="mdi mdi-sitemap me-1"></i> Pavouk / Zápasy
                                            </a>
                                        @endif
                                        <form action="{{ route('tournaments.destroy', $t->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Opravdu chcete smazat turnaj {{ $t->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1 px-2" title="Smazat turnaj">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="mdi mdi-trophy-broken fs-1 d-block mb-2 text-muted"></i>
                                        Zatím nebyl založen žádný turnaj.
                                        <div class="mt-2">
                                            <a href="{{ route('tournaments.create') }}" class="btn btn-gradient-primary btn-sm">
                                                <i class="mdi mdi-plus-circle me-1"></i> Založit první turnaj
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
                        Zobrazeno {{ $tournaments->firstItem() ?? 0 }} - {{ $tournaments->lastItem() ?? 0 }} z celkem {{ $tournaments->total() }} turnajů
                    </div>
                    <div>
                        {{ $tournaments->links('pagination::bootstrap-5') }}
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
