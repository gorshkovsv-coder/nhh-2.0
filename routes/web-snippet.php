<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\MatchReportController;
use App\Http\Controllers\PublicTournamentController;
use App\Http\Controllers\MyMatchesController;
use App\Http\Controllers\MatchViewController;

Route::middleware(['auth'])->group(function () {
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::post('/tournaments/{t}/participants', [ParticipantController::class, 'store']);
    Route::post('/stages/{stage}/generate/round-robin', [StageController::class, 'generateRoundRobin']);

    Route::get('/my/matches', [MyMatchesController::class, 'index']);
    Route::get('/matches/{match}', [MatchViewController::class, 'show']);

    Route::post('/matches/{match}/report', [MatchReportController::class, 'store']);
    Route::post('/reports/{report}/confirm', [MatchReportController::class, 'confirm']);
    Route::post('/reports/{report}/dispute', [MatchReportController::class, 'dispute']);
});
Route::get('/tournaments/{tournament}', [PublicTournamentController::class, 'show']);
