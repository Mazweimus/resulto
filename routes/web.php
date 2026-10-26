<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentGroupController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/main', function () {
    return redirect()->route('dashboard');
});

// Tournaments / Turnaje
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/groups/create', [TournamentGroupController::class, 'create'])->name('tournaments.groups.create');
Route::post('/tournaments/groups/draw', [TournamentGroupController::class, 'draw'])->name('tournaments.groups.draw');
Route::post('/tournaments/groups', [TournamentGroupController::class, 'store'])->name('tournaments.groups.store');
Route::get('/tournaments/{tournament}/groups', [TournamentGroupController::class, 'show'])->name('tournaments.groups.show');
Route::get('/tournaments/create', [TournamentController::class, 'create'])->name('tournaments.create');
Route::post('/tournaments', [TournamentController::class, 'store'])->name('tournaments.store');
Route::get('/tournaments/{tournament}/play', [TournamentController::class, 'play'])->name('tournaments.play');
Route::post('/tournaments/{tournament}/assign-slot', [TournamentController::class, 'assignSlot'])->name('tournaments.assignSlot');
Route::post('/tournaments/{tournament}/finalize-seeding', [TournamentController::class, 'finalizeSeeding'])->name('tournaments.finalizeSeeding');
Route::get('/tournaments/{tournament}/matches', function ($tournament) {
    return redirect()->route('tournaments.play', $tournament);
})->name('tournaments.matches');
Route::get('/tournaments/{tournament}/edit', [TournamentController::class, 'edit'])->name('tournaments.edit');
Route::put('/tournaments/{tournament}', [TournamentController::class, 'update'])->name('tournaments.update');
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy'])->name('tournaments.destroy');

// Aliases for backwards compatibility
Route::get('/events/create', function () {
    return redirect()->route('tournaments.create');
})->name('events.create');
Route::get('/cEvent', function () {
    return redirect()->route('tournaments.create');
});
Route::post('/events', [TournamentController::class, 'store']);

// Matches / Zápasy
Route::match(['put', 'post'], '/matches/{match}', [MatchController::class, 'update'])->name('matches.update');

// Players / Správa hráčů
Route::get('/players', [PlayerController::class, 'index'])->name('players.index');
Route::get('/players/create', [PlayerController::class, 'create'])->name('players.create');
Route::post('/players', [PlayerController::class, 'store'])->name('players.store');
Route::get('/players/{player}/edit', [PlayerController::class, 'edit'])->name('players.edit');
Route::put('/players/{player}', [PlayerController::class, 'update'])->name('players.update');
Route::delete('/players/{player}', [PlayerController::class, 'destroy'])->name('players.destroy');

// Leaderboard / Dlouhodobý žebříček hráčů
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');

// Auth / Přihlášení a registrace
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
