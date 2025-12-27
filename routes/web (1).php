<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\TournamentController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\MatchReportController;
use App\Http\Controllers\PublicTournamentController;
use App\Http\Controllers\MyMatchesController;
use App\Http\Controllers\MatchViewController;
use App\Http\Controllers\Admin\TournamentAdminController;
use App\Http\Controllers\Admin\PlayoffController;
use App\Models\Tournament;
use App\Http\Controllers\Admin\NhlTeamAdminController;
use App\Models\MatchModel;
use App\Http\Controllers\Admin\MatchAdminController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Providers\RouteServiceProvider;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\PlayerRatingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InfoController;

/*
|--------------------------------------------------------------------------
| Главная
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Главная — дашборд игрока / лендинг
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    $user = auth()->user();

	$nextMatches = [];
	$activeTournaments = [];

    if ($user) {
        $userId = $user->id;

        // ===== Ближайший матч (первый ещё не сыгранный) =====
		$rawNextMatches = MatchModel::query()
			->with([
				'stage.tournament',
				'home.user', 'home.nhlTeam', 'home.tournament',
				'away.user', 'away.nhlTeam', 'away.tournament',
			])
			->where(function ($q) use ($userId) {
				$q->whereHas('home', function ($q2) use ($userId) {
					$q2->where('user_id', $userId);
				})->orWhereHas('away', function ($q2) use ($userId) {
					$q2->where('user_id', $userId);
				});
			})
			// только запланированные матчи (учтём и scheduled, и pending на всякий случай)
			->whereIn('status', ['scheduled', 'pending'])
			->orderBy('id')
			->take(5)
			->get();
			

if ($rawNextMatches->isNotEmpty()) {
    // helper для подписи "КОД — Игрок" (может пригодиться и дальше)
    $makeSideLabel = function ($participant) {
        if (!$participant) {
            return 'Участник';
        }

        $team = $participant->nhlTeam ?? null;
        $user = $participant->user ?? null;

        if ($team && $user) {
            return ($team->code ?? '') . ' — ' . ($user->name ?? '');
        }

        if ($team) {
            return $team->code . ' — ' . ($team->name ?? '');
        }

        if ($user) {
            return $user->name;
        }

        return 'Участник';
    };

$nextMatches = $rawNextMatches->map(function (MatchModel $match) use ($makeSideLabel) {
    $statusLabel = match ($match->status) {
        'scheduled' => 'Запланирован',
        'pending'   => 'Запланирован',
        'reported'  => 'Ожидает подтверждения',
        'confirmed' => 'Подтверждён',
        'disputed'  => 'Спорный',
        default     => null,
    };

    $stage    = $match->stage;
    $homePart = $match->home;
    $awayPart = $match->away;

    // пробуем получить турнир по всем возможным связям
$tournament =
    $stage?->tournament
    ?? $homePart?->tournament
    ?? $awayPart?->tournament
    ?? null;

// Сначала берём нормальное название турнира
$tournamentName = $tournament?->title
    ?? $tournament?->name    // на всякий случай, если где-то всё-таки name
    ?? null;

if (!$tournamentName && $tournament) {
    if (!empty($tournament->season)) {
        $tournamentName = 'Турнир сезона ' . $tournament->season;
    } else {
        $tournamentName = 'Турнир #' . $tournament->id;
    }
}

if (!$tournamentName) {
    $tournamentName = 'Турнир';
}



    $homeTeam = $homePart?->nhlTeam;
    $awayTeam = $awayPart?->nhlTeam;
    $homeUser = $homePart?->user;
    $awayUser = $awayPart?->user;

    return [
        'id'              => $match->id,
        'tournament_name' => $tournamentName,
        'stage_name'      => $stage?->name ?? 'Стадия',

        // подписи "КОД — Игрок" (если где-то пригодится)
        'home_label'      => $makeSideLabel($homePart),
        'away_label'      => $makeSideLabel($awayPart),

        'status_label'    => $statusLabel,

        // данные для отображения логотипов и текста
        'home_team_logo_url' => $homeTeam?->logo_url,
        'home_team_code'     => $homeTeam?->code,
        'home_player_name'   => $homeUser?->name,

        'away_team_logo_url' => $awayTeam?->logo_url,
        'away_team_code'     => $awayTeam?->code,
        'away_player_name'   => $awayUser?->name,
    ];
})->values()->all();

}


        // ===== Мои турниры (последние 5) =====
        $myTournaments = Tournament::query()
            ->with(['participants.nhlTeam'])
            ->whereHas('participants', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

$activeTournaments = $myTournaments->map(function (Tournament $t) use ($userId) {
    $participant = $t->participants->firstWhere('user_id', $userId);

    $teamLabel    = null;
    $teamLogoUrl  = null;
    $teamCode     = null;
    $teamName     = null;

    if ($participant && $participant->nhlTeam) {
        $team       = $participant->nhlTeam;
        $teamCode   = $team->code ?? null;
        $teamName   = $team->name ?? null;
        $teamLogoUrl = $team->logo_url ?? null;

        $teamLabel = $teamCode
            ? ($teamName ? ($teamCode . ' · ' . $teamName) : $teamCode)
            : $teamName;
    }

    $formatLabel = match ($t->format ?? null) {
        'groups'         => 'Только группы',
        'groups_playoff' => 'Группы + плей-офф',
        'playoff'        => 'Только плей-офф',
        default          => $t->format,
    };

    $statusLabel = null;
    if (property_exists($t, 'status')) {
        $statusLabel = match ($t->status) {
            'draft'    => 'Черновик',
            'active'   => 'Идёт турнир',
            'finished' => 'Завершён',
            'archived' => 'Архив',
            default    => null,
        };
    }

    return [
        'id'            => $t->id,
        'name'          => $t->title,
        'season'        => $t->season,
        'format_label'  => $formatLabel,

        'team_label'    => $teamLabel,
        'team_logo_url' => $teamLogoUrl,
        'team_code'     => $teamCode,
        'team_name'     => $teamName,

        'status_label'  => $statusLabel,
    ];
})->values()->all();

    }

    return Inertia::render('Welcome', [
        'canLogin'         => Route::has('login'),
        'canRegister'      => Route::has('register'),
        'laravelVersion'   => Application::VERSION,
        'phpVersion'       => PHP_VERSION,
        // Новые пропсы для дашборда
        'nextMatches'       => $nextMatches,
        'activeTournaments'=> $activeTournaments,
    ]);
})->name('home');



// Алиас для редиректа после логина (Breeze ожидает route('dashboard'))
Route::get('/dashboard', function () {
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');


/*
|--------------------------------------------------------------------------
| Публичные страницы турниров
|--------------------------------------------------------------------------
*/
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/{tournament}', [PublicTournamentController::class, 'show'])->name('tournaments.show');

Route::get('/tournaments/{tournament}/matches', [PublicTournamentController::class, 'matchesHistory'])
    ->name('tournaments.matches-history');
	
Route::get('/tournaments/{tournament}/matches', [PublicTournamentController::class, 'matchesHistory'])
    ->name('tournaments.matches-history');

Route::get('/rating', [PlayerRatingController::class, 'index'])->name('players.rating');

Route::get('/players/{user}', [ProfileController::class, 'showPublic'])
    ->name('players.show');
	
Route::get('/guide', [InfoController::class, 'playerGuide'])
    ->name('guide');


/*
|--------------------------------------------------------------------------
| Личный кабинет игрока (auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my/matches', [MyMatchesController::class, 'index'])->name('my.matches');
    Route::get('/matches/{match}', [MatchViewController::class, 'show'])->name('matches.show');
    Route::post('/matches/{match}/report', [MatchReportController::class, 'store'])->name('matches.report.store');

    // подтверждение/отклонение репорта соперника
    Route::post('/reports/{report}/confirm', [MatchReportController::class, 'confirm'])->name('reports.confirm');

    // (опционально) отклонить
    Route::post('/reports/{report}/reject',  [MatchReportController::class, 'reject'])->name('reports.reject');

    // Игрок сам записывается / снимается
    Route::post('/tournaments/{tournament}/register',  [ParticipantController::class, 'register'])
        ->name('tournaments.register');
    Route::delete('/tournaments/{tournament}/register', [ParticipantController::class, 'unregister'])
        ->name('tournaments.unregister');

});

/*
|--------------------------------------------------------------------------
| Админка (auth)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/tournaments', [TournamentAdminController::class, 'index'])->name('tournaments.index');
    Route::get('/tournaments/{tournament}/edit', [TournamentAdminController::class, 'edit'])->name('tournaments.edit');

    // Создать турнир (быстрое создание из кнопки)
    Route::post('/tournaments', [TournamentAdminController::class, 'store'])->name('tournaments.store');

    // --- Fallback для старых ссылок вида /admin/tournaments/{id} ---
    // Редиректим на страницу редактирования, чтобы избежать 404
    Route::get('/tournaments/{tournament}', function (Tournament $tournament) {
        return redirect()->route('admin.tournaments.edit', $tournament);
    })->name('tournaments.show'); // опциональное имя

    // генерация круговой стадии
    Route::post('/tournaments/stages/{stage}/generate-round-robin', [StageController::class, 'generateRoundRobin'])
        ->name('stages.generate_round_robin');

    // генерация плей-офф
    Route::post('/tournaments/{tournament}/playoff/generate', [PlayoffController::class, 'generate'])
        ->name('playoff.generate');

	    // ==== Турнир: сохранить / удалить ====
    Route::put('/tournaments/{tournament}', [TournamentAdminController::class, 'update'])
        ->name('tournaments.update');
    Route::delete('/tournaments/{tournament}', [TournamentAdminController::class, 'destroy'])
        ->name('tournaments.destroy');

    // ==== Стадии: создать / удалить ====
    Route::post('/tournaments/{tournament}/stages', [StageController::class, 'store'])
        ->name('stages.store');
    Route::delete('/tournaments/stages/{stage}', [StageController::class, 'destroy'])
        ->name('stages.destroy');

    // ==== Плей-офф: принудительно продвинуть ====
    Route::post('/tournaments/stages/{stage}/advance-playoff', [PlayoffController::class, 'advance'])
        ->name('stages.advance_playoff');

    // ==== Участники: добавить / удалить ====
    Route::post('/tournaments/{tournament}/participants', [ParticipantController::class, 'store'])
        ->name('participants.store');
    Route::delete('/tournaments/{tournament}/participants/{participant}', [ParticipantController::class, 'destroy'])
        ->name('participants.destroy');

	    // ==== Участники: назначение NHL-команды + рандом ====
    Route::post('/tournaments/{tournament}/participants/{participant}/team', [TournamentAdminController::class, 'updateParticipantTeam'])
        ->name('participants.updateTeam');

    Route::post('/tournaments/{tournament}/participants/randomize-teams', [TournamentAdminController::class, 'randomizeTeams'])
        ->name('participants.randomizeTeams');


    // ==== Реестр команд NHL ====
    Route::get('/nhl-teams', [NhlTeamAdminController::class, 'index'])
        ->name('nhl-teams.index');

    Route::post('/nhl-teams', [NhlTeamAdminController::class, 'store'])
        ->name('nhl-teams.store');

    Route::post('/nhl-teams/{team}', [NhlTeamAdminController::class, 'update'])
        ->name('nhl-teams.update');

    Route::delete('/nhl-teams/{team}', [NhlTeamAdminController::class, 'destroy'])
        ->name('nhl-teams.destroy');
		
		
	// Админка (матчи)
    Route::get('/matches', [MatchAdminController::class, 'index'])->name('matches.index');

    Route::put('/matches/{match}', [MatchAdminController::class, 'update'])
        ->name('matches.update');

    Route::delete('/matches/{match}', [MatchAdminController::class, 'destroy'])
        ->name('matches.destroy');

    Route::delete('/matches/{match}/reports/{report}', [MatchAdminController::class, 'destroyReport'])
        ->name('matches.reports.destroy');

    Route::delete('/matches/{match}/reports/{report}/attachments/{index}', [MatchAdminController::class, 'destroyAttachment'])
        ->name('matches.reports.attachments.destroy');
		
	// 🔹 НОВЫЙ маршрут подтверждения репорта
    Route::post('/matches/{match}/reports/{report}/confirm', [MatchAdminController::class, 'confirmReport'])
        ->name('matches.reports.confirm');
		
    // ==== Админка: пользователи ====
    Route::get('/users', [UserAdminController::class, 'index'])
        ->name('admin.users.index');

    Route::post('/users', [UserAdminController::class, 'store'])
        ->name('admin.users.store');

    Route::post('/users/{user}/verify', [UserAdminController::class, 'verify'])
        ->name('admin.users.verify');

    Route::delete('/users/{user}', [UserAdminController::class, 'destroy'])
        ->name('admin.users.destroy');

    // Массовые действия
    Route::post('/users/bulk-verify', [UserAdminController::class, 'bulkVerify'])
        ->name('admin.users.bulkVerify');

    Route::post('/users/bulk-delete', [UserAdminController::class, 'bulkDelete'])
        ->name('admin.users.bulkDelete');
	
});

/*
|--------------------------------------------------------------------------
| Breeze / Auth routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';

Route::middleware('auth', 'verified')->group(function () {
    // Страница профиля
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Обновить профиль (имя, email, psn, аватар)
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Сменить пароль
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Удалить аккаунт
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});