<?php

use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentParticipantController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Admin\RootController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// --- API ROUTES (legacy localStorage-style; wajib login agar tidak bocor
// data lintas admin / publik — R21) ---
Route::middleware('auth')->group(function () {
    Route::get('/api/data', [TournamentController::class, 'getData']);
    Route::post('/api/save', [TournamentController::class, 'saveAll']);
});

// --- AUTH ROUTES ---
// Gunakan satu saja untuk menampilkan form login
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
// R21 — throttle untuk cegah brute-force (10 percobaan / menit per IP).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// R21 — registrasi admin baru (email + password)
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

// R21 — Google OAuth (Socialite)
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// --- PROTECTED ROUTES (Hanya untuk yang sudah login) ---
// R21 — 'owns' menjaga setiap route {tournament}/{team} hanya bisa diakses
// oleh admin pemiliknya (created_by). Route tanpa binding tsb tidak terdampak.
Route::middleware(['auth', 'owns'])->group(function () {
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
    // R15 — penempatan grup manual
    Route::patch('/tournaments/{tournament}/participants/{participant}/group', [TournamentParticipantController::class, 'assignGroupManually'])->name('tournaments.participants.assignGroup');
    // R16 — undian / spin tim ke grup
    Route::get('/tournaments/{tournament}/group-draw', [TournamentController::class, 'groupDraw'])->name('tournaments.groupDraw');
    Route::post('/tournaments/{tournament}/group-draw', [TournamentController::class, 'performGroupDraw'])->name('tournaments.performGroupDraw');

    // Tournament Management Dashboard
    Route::get('/tournaments/{tournament}/manage', [TournamentController::class, 'manage'])->name('tournaments.manage');
    
    // Tournament Settings
    Route::get('/tournaments/{tournament}/settings', [TournamentController::class, 'settings'])->name('tournaments.settings');
    Route::get('/tournaments/{tournament}/manage-schedule', [TournamentController::class, 'manageSchedule'])->name('tournaments.manageSchedule');
    Route::patch('/tournaments/{tournament}/matches/{match}', [TournamentController::class, 'updateMatch'])->name('tournaments.matches.update');
    // N5 — Edit khusus Skor; N6 — Jadwal khusus tanggal/waktu
    Route::patch('/tournaments/{tournament}/matches/{match}/score', [TournamentController::class, 'updateScore'])->name('tournaments.matches.score');
    Route::patch('/tournaments/{tournament}/matches/{match}/schedule', [TournamentController::class, 'updateSchedule'])->name('tournaments.matches.schedule');
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

    // N12 — Manajemen Pemain / Statistik turnamen (sisi Admin)
    Route::get('/tournaments/{tournament}/statistics', [TournamentController::class, 'statistics'])->name('tournaments.statistics');

    // Tournament Document Verification
    Route::get('/tournaments/{tournament}/verification', [TournamentController::class, 'verification'])->name('tournaments.verification');
    Route::patch('/tournaments/{tournament}/participants/{participant}/verify', [TournamentController::class, 'verifyParticipant'])->name('tournaments.participants.verify');
    // R18 — berkas verifikasi per tim
    Route::post('/tournaments/{tournament}/participants/{participant}/documents', [TournamentController::class, 'uploadVerificationDocument'])->name('tournaments.participants.documents.upload');
    Route::delete('/tournaments/{tournament}/participants/{participant}/documents/{document}', [TournamentController::class, 'deleteVerificationDocument'])->name('tournaments.participants.documents.delete');

    // R22 — Paket langganan (admin) + upgrade dengan bukti transfer
    Route::get('/subscription', [SubscriptionController::class, 'showPlans'])->name('subscription.plans');
    Route::post('/subscription/upgrade', [SubscriptionController::class, 'requestUpgrade'])
        ->middleware('throttle:6,1')->name('subscription.upgrade');

    Route::post('/logout', function () {
        Auth::logout();
        return redirect('/login');
    })->name('logout');
});

// R22 — Area admin ROOT: tinjau & ACC pembayaran upgrade paket
Route::middleware(['auth', 'root'])->prefix('root')->name('root.')->group(function () {
    Route::get('/requests', [RootController::class, 'requests'])->name('requests');
    Route::get('/requests/{subscriptionRequest}/proof', [RootController::class, 'proof'])->name('requests.proof');
    Route::post('/requests/{subscriptionRequest}/approve', [RootController::class, 'approve'])->name('requests.approve');
    Route::post('/requests/{subscriptionRequest}/reject', [RootController::class, 'reject'])->name('requests.reject');
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

// N13 — Statistik turnamen view-only untuk Tamu/Visitor (tanpa login)
Route::get('/public/statistics', [App\Http\Controllers\PublicStatisticsController::class, 'index'])->name('public.statistics.index');
Route::get('/public/tournaments/{tournament}/statistics', [App\Http\Controllers\PublicStatisticsController::class, 'show'])->name('public.statistics.show');

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
    // N4 — Official/Manager dapat melihat bagan/bracket (read-only)
    Route::get('/official/bracket', [App\Http\Controllers\OfficialBracketController::class, 'index'])->name('official.bracket');
    // N13 — Statistik view-only untuk Manager
    Route::get('/official/statistics', [App\Http\Controllers\OfficialAuthController::class, 'statistics'])->name('official.statistics');
});