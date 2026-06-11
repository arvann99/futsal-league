<?php

use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentParticipantController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// --- API ROUTES ---
Route::get('/api/data', [TournamentController::class, 'getData']);
Route::post('/api/save', [TournamentController::class, 'saveAll']);

// --- AUTH ROUTES ---
// Gunakan satu saja untuk menampilkan form login
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// --- PROTECTED ROUTES (Hanya untuk yang sudah login) ---
Route::middleware(['auth'])->group(function () {
    // Dashboard - Redirect ke tournaments.index
    Route::get('/dashboard', [TournamentController::class, 'index'])->name('dashboard');

    // CRUD Tournaments
    Route::resource('tournaments', TournamentController::class);

    // Team Management
    Route::resource('teams', TeamController::class)->except(['show']);
    // access-manager route removed; management moved to tournament participants page
    Route::post('/teams/{team}/reset-token', [TeamController::class, 'resetToken'])->name('teams.resetToken');

    // Tournament Participant Management
    Route::get('/tournaments/{tournament}/participants', [TournamentParticipantController::class, 'index'])->name('tournaments.participants.index');
    Route::get('/tournaments/{tournament}/participants/create', [TournamentParticipantController::class, 'create'])->name('tournaments.participants.create');
    Route::post('/tournaments/{tournament}/participants', [TournamentParticipantController::class, 'store'])->name('tournaments.participants.store');
    Route::get('/tournaments/{tournament}/participants/{participant}/edit', [TournamentParticipantController::class, 'edit'])->name('tournaments.participants.edit');
    Route::put('/tournaments/{tournament}/participants/{participant}', [TournamentParticipantController::class, 'update'])->name('tournaments.participants.update');
    Route::delete('/tournaments/{tournament}/participants/{participant}', [TournamentParticipantController::class, 'destroy'])->name('tournaments.participants.destroy');
    
    // Tournament Management Dashboard
    Route::get('/tournaments/{tournament}/manage', [TournamentController::class, 'manage'])->name('tournaments.manage');
    
    // Tournament Settings
    Route::get('/tournaments/{tournament}/settings', [TournamentController::class, 'settings'])->name('tournaments.settings');
    Route::get('/tournaments/{tournament}/manage-schedule', [TournamentController::class, 'manageSchedule'])->name('tournaments.manageSchedule');
    Route::patch('/tournaments/{tournament}/matches/{match}', [TournamentController::class, 'updateMatch'])->name('tournaments.matches.update');
    Route::post('/tournaments/{tournament}/matches/{match}/live-logger', [TournamentController::class, 'openLiveMatchLogger'])->name('tournaments.matches.liveLogger');
    Route::post('/tournaments/{tournament}/matches/{match}/events', [TournamentController::class, 'storeMatchEvent'])->name('tournaments.matches.events.store');
    Route::patch('/tournaments/{tournament}/matches/{match}/end', [TournamentController::class, 'endMatch'])->name('tournaments.matches.end');
    Route::get('/tournaments/{tournament}/settings/group', [TournamentController::class, 'groupSettings'])->name('tournaments.groupSettings');
    Route::get('/tournaments/{tournament}/settings/points', [TournamentController::class, 'pointsSettings'])->name('tournaments.pointsSettings');
    Route::get('/tournaments/{tournament}/schedule', [TournamentController::class, 'schedule'])->name('tournaments.schedule');
    Route::post('/tournaments/{tournament}/settings/points', [TournamentController::class, 'updatePointSettings'])->name('tournaments.updatePointSettings');
    Route::delete('/tournaments/{tournament}/settings/points', [TournamentController::class, 'resetPointSettings'])->name('tournaments.resetPointSettings');
    Route::get('/tournaments/{tournament}/settings/bracket', [TournamentController::class, 'bracketSettings'])->name('tournaments.bracketSettings');
    Route::get('/tournaments/{tournament}/settings/bracket-admin', [TournamentController::class, 'bracketAdmin'])->name('tournaments.bracketAdmin');
    Route::post('/tournaments/{tournament}/settings/bracket-admin', [TournamentController::class, 'saveBracketAssignments'])->name('tournaments.saveBracketAssignments');
    Route::post('/tournaments/{tournament}/settings/bracket', [TournamentController::class, 'updateBracketSettings'])->name('tournaments.updateBracketSettings');
    Route::delete('/tournaments/{tournament}/settings/bracket', [TournamentController::class, 'resetBracketSettings'])->name('tournaments.resetBracketSettings');
    Route::post('/tournaments/{tournament}/settings', [TournamentController::class, 'updateSettings'])->name('tournaments.updateSettings');
    Route::delete('/tournaments/{tournament}/settings', [TournamentController::class, 'resetSettings'])->name('tournaments.resetSettings');
    Route::get('/tournaments/{tournament}/settings/data', [TournamentController::class, 'getSettings'])->name('tournaments.getSettings');
    
    // Tournament Standings
    Route::get('/tournaments/{tournament}/standings', [TournamentController::class, 'standings'])->name('tournaments.standings');

    // Tournament Document Verification
    Route::get('/tournaments/{tournament}/verification', [TournamentController::class, 'verification'])->name('tournaments.verification');
    Route::patch('/tournaments/{tournament}/participants/{participant}/verify', [TournamentController::class, 'verifyParticipant'])->name('tournaments.participants.verify');

    Route::post('/logout', function () {
        Auth::logout();
        return redirect('/login');
    })->name('logout');
});

// Portal landing page for admin, official, and public access
Route::get('/portal', function () {
    return view('public.portal');
})->name('portal');

Route::get('/', function () {
    return view('public.portal');
});

Route::get('/public/login', function () {
    return view('public.auth.login-placeholder', [
        'title' => 'Login Publik',
        'description' => 'Halaman login publik untuk peserta dan penonton.',
    ]);
})->name('public.login');

Route::get('/official/login', [App\Http\Controllers\OfficialAuthController::class, 'showLogin'])->name('official.login');
Route::post('/official/login', [App\Http\Controllers\OfficialAuthController::class, 'login'])->name('official.login.submit');
Route::post('/official/logout', [App\Http\Controllers\OfficialAuthController::class, 'logout'])->name('official.logout');
Route::get('/official/dashboard', [App\Http\Controllers\OfficialAuthController::class, 'dashboard'])
    ->middleware([App\Http\Middleware\OfficialAuth::class])
    ->name('official.dashboard');

Route::middleware([App\Http\Middleware\OfficialAuth::class])->group(function () {
    Route::get('/official/players', [App\Http\Controllers\OfficialPlayerController::class, 'index'])->name('official.players.index');
    Route::get('/official/players/create', [App\Http\Controllers\OfficialPlayerController::class, 'create'])->name('official.players.create');
    Route::post('/official/players', [App\Http\Controllers\OfficialPlayerController::class, 'store'])->name('official.players.store');
    Route::get('/official/players/{player}/edit', [App\Http\Controllers\OfficialPlayerController::class, 'edit'])->name('official.players.edit');
    Route::put('/official/players/{player}', [App\Http\Controllers\OfficialPlayerController::class, 'update'])->name('official.players.update');
    Route::delete('/official/players/{player}', [App\Http\Controllers\OfficialPlayerController::class, 'destroy'])->name('official.players.destroy');

    Route::get('/official/officials', [App\Http\Controllers\OfficialTeamOfficialController::class, 'index'])->name('official.officials.index');
    Route::get('/official/officials/create', [App\Http\Controllers\OfficialTeamOfficialController::class, 'create'])->name('official.officials.create');
    Route::post('/official/officials', [App\Http\Controllers\OfficialTeamOfficialController::class, 'store'])->name('official.officials.store');
    Route::get('/official/officials/{official}/edit', [App\Http\Controllers\OfficialTeamOfficialController::class, 'edit'])->name('official.officials.edit');
    Route::put('/official/officials/{official}', [App\Http\Controllers\OfficialTeamOfficialController::class, 'update'])->name('official.officials.update');
    Route::delete('/official/officials/{official}', [App\Http\Controllers\OfficialTeamOfficialController::class, 'destroy'])->name('official.officials.destroy');
    Route::get('/official/schedule', [App\Http\Controllers\OfficialAuthController::class, 'schedule'])->name('official.schedule');
    Route::get('/official/standings', [App\Http\Controllers\OfficialStandingsController::class, 'index'])->name('official.standings');
});