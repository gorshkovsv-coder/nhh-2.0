<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchModel;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use App\Models\NhlTeam;      // <-- ВАЖНО: именно так
use App\Models\Standing;

class TournamentAdminController extends Controller
{
    /** Простейшая проверка прав администратора */
    private function ensureAdmin(): void
    {
        $user = auth()->user();
        if (!$user || !($user->is_admin ?? false)) {
            abort(403, 'Only admins can access this area.');
        }
    }

    /* =========================
     *   ТУРНИРЫ: список/страницы
     * ========================= */

    public function index()
    {
        $this->ensureAdmin();

        $tournaments = Tournament::latest()->get();

        return Inertia::render('Admin/Tournaments/Index', [
            'tournaments' => $tournaments,
        ]);
    }


	public function edit(Tournament $tournament)
	{
    $this->ensureAdmin();
	
	$tournament->load('draftTeams');

    $stages = Stage::where('tournament_id', $tournament->id)
        ->orderBy('order')
        ->get();

    $participants = TournamentParticipant::where('tournament_id', $tournament->id)
		->where('is_active', true)
        ->with(['user:id,name,email', 'nhlTeam'])
        ->orderBy('id')
        ->get()
        ->map(function (TournamentParticipant $p) {
            return [
                'id'           => $p->id,
                'user_id'      => $p->user_id,
                'display_name' => $p->display_name,
                'seed'         => $p->seed,
                'nhl_team_id'  => $p->nhl_team_id,
                'user' => $p->user ? [
                    'id'    => $p->user->id,
                    'name'  => $p->user->name,
                    'email' => $p->user->email,
                ] : null,
                'nhl_team' => $p->nhlTeam ? [
                    'id'       => $p->nhlTeam->id,
                    'code'     => $p->nhlTeam->code,
                    'name'     => $p->nhlTeam->name,
                    'logo_url' => $p->nhlTeam->logo_url,
                ] : null,
            ];
        });

    $nhlTeams = NhlTeam::orderBy('code')->get()
        ->map(function (NhlTeam $t) {
            return [
                'id'       => $t->id,
                'code'     => $t->code,
                'name'     => $t->name,
                'logo_url' => $t->logo_url,
            ];
        });

    return Inertia::render('Admin/Tournaments/Edit', [
        'tournament'   => $tournament,
        'stages'       => $stages,
        'participants' => $participants,
        'nhlTeams'     => $nhlTeams,
		'draftTeamIds' => $tournament->draftTeams->pluck('id'),
    ]);
	}

	

    /* =========================
     *   ТУРНИРЫ: CRUD
     * ========================= */

    public function store(Request $request)
    {
    $data = $request->validate([
        'title'  => ['nullable','string','max:255'],
        'season' => ['nullable','integer'],
        'format' => 'required|in:groups_playoff,group_only,playoff',
		'status' => ['nullable','in:draft,registration,active,archived'],
    ]);

    $t = new Tournament();
    $t->title  = $data['title']  ?? 'Новый турнир';
    $t->season = $data['season'] ?? now()->year;
    $t->format = $data['format'] ?? 'groups_playoff';
    $t->status = $data['status'] ?? 'draft';
    $t->save();

    return redirect()->route('admin.tournaments.edit', $t)
        ->with('success', 'Турнир создан.');
    }



    public function update(\Illuminate\Http\Request $request, \App\Models\Tournament $tournament)
    {
        $data = $request->validate([
            'title'  => 'nullable|string|max:255',
            'name'   => 'nullable|string|max:255',
            'season' => 'nullable|string|max:255',
			'format' => 'required|in:groups_playoff,group_only,playoff',
			'status' => ['nullable','in:draft,registration,active,archived'],
			'status' => 'nullable|in:draft,registration,active,archived',
		]);

        // Название: поддержим обе схемы БД (title или name)
        if (\Illuminate\Support\Facades\Schema::hasColumn('tournaments', 'title')) {
            if (isset($data['title']) || isset($data['name'])) {
                $tournament->title = $data['title'] ?? $data['name'] ?? $tournament->title;
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('tournaments', 'name')) {
            if (isset($data['title']) || isset($data['name'])) {
                $tournament->name = $data['title'] ?? $data['name'] ?? $tournament->name;
            }
        }

        // Прочие поля
        if (\Illuminate\Support\Facades\Schema::hasColumn('tournaments', 'season')) {
            $tournament->season = $data['season'] ?? $tournament->season;
        }
        $tournament->format = $data['format'];
        $tournament->status = $data['status'];

        $tournament->save();

        return back(303)->with('success', 'Турнир сохранён');
    }



    public function destroy(Tournament $tournament)
    {
        $this->ensureAdmin();

        DB::transaction(function () use ($tournament) {
            $stageIds = $tournament->stages()->pluck('id');

            $matchIds = MatchModel::whereIn('stage_id', $stageIds)->pluck('id');

            // 1) отчёты по матчам (если есть таблица)
            if (Schema::hasTable('match_reports')) {
                DB::table('match_reports')->whereIn('match_id', $matchIds)->delete();
            }

            // 2) матчи
            MatchModel::whereIn('id', $matchIds)->delete();

            // 3) участники
            TournamentParticipant::where('tournament_id', $tournament->id)->delete();

            // 4) стадии
            Stage::whereIn('id', $stageIds)->delete();

            // 5) турнир
            $tournament->delete();
        });

        return redirect()->route('admin.tournaments.index')->with('ok', 'Турнир удалён');
    }
	
	public function updateParticipantTeam(Request $request, Tournament $tournament, TournamentParticipant $participant)
	{
    $this->ensureAdmin();

    if ($participant->tournament_id !== $tournament->id) {
        abort(404);
    }

    $data = $request->validate([
        'nhl_team_id' => ['nullable', 'integer', 'exists:nhl_teams,id'],
    ]);

    $teamId = $data['nhl_team_id'] ?? null;

    if ($teamId) {
        $exists = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where('nhl_team_id', $teamId)
            ->where('id', '<>', $participant->id)
            ->exists();

        if ($exists) {
            return back(303)->with('error', 'Эта команда уже занята другим участником.');
        }
    }

    $participant->nhl_team_id = $teamId;
    $participant->save();

    return back(303)->with('success', 'Команда участника обновлена.');
	}
	
	public function randomizeTeams(Request $request, Tournament $tournament)
	{
    $this->ensureAdmin();

    $participants = TournamentParticipant::where('tournament_id', $tournament->id)
        ->orderBy('id')
        ->get();

    if ($participants->isEmpty()) {
        return back(303)->with('error', 'В турнире нет участников.');
    }

    $allTeamIds = NhlTeam::orderBy('code')->pluck('id')->all();

    if (empty($allTeamIds)) {
        return back(303)->with('error', 'В реестре NHL нет команд.');
    }

    shuffle($allTeamIds);

    $usedTeamIds = $participants->pluck('nhl_team_id')->filter()->all();
    $availableTeamIds = array_values(array_diff($allTeamIds, $usedTeamIds));

    $participantsWithoutTeam = $participants->filter(fn ($p) => !$p->nhl_team_id)->values();

    $pairs = min(count($availableTeamIds), $participantsWithoutTeam->count());

    for ($i = 0; $i < $pairs; $i++) {
        $participant = $participantsWithoutTeam[$i];
        $participant->nhl_team_id = $availableTeamIds[$i];
        $participant->save();
    }

    return back(303)->with('success', 'Команды распределены случайным образом между участниками (без перезаписи уже назначенных).');
	}
	
	    /**
     * Страница интерактивной жеребьёвки команд.
     */
public function showDraft(Tournament $tournament)
{
    $this->ensureAdmin();

    // Команды, выбранные для жеребьёвки (актуальный список)
    $draftTeams = $tournament->draftTeams()
        ->orderBy('code')
        ->get();

    // Результат последнего runDraft (распределение, посчитанное на сервере)
    $assignments = session('draft_assignments', []);

    if (!empty($assignments)) {
        // Если жеребьёвка уже запущена, берём участников именно из assignments
        $participantIds = collect($assignments)
            ->pluck('participant.id')
            ->unique()
            ->all();

        $participants = $tournament->participants()
            ->whereIn('id', $participantIds)
            ->with('user')
            ->get()
            // Сохраняем порядок такой же, как в assignments
            ->sortBy(function ($p) use ($participantIds) {
                return array_search($p->id, $participantIds, true);
            })
            ->values();
    } else {
        // Первый заход на страницу — показываем активных участников без команды
        $participants = $tournament->participants()
            ->where('is_active', true)
            ->whereNull('nhl_team_id')
            ->with('user')
            ->orderBy('id')
            ->get();
    }

    // Можно очистить сессию, чтобы assignments не "липли" между разными турами,
    // но уже отрендеренная страница свои props получила.
    session()->forget('draft_assignments');

    return Inertia::render('Admin/Tournaments/Draft', [
        'tournament'   => $tournament,
        'participants' => $participants,
        'draftTeams'   => $draftTeams,
        'assignments'  => $assignments,
    ]);
}




    /**
     * Запускает жеребьёвку:
     * случайно сопоставляет участников и команды и сохраняет результат.
     * Возвращает assignments для анимации на фронте.
     */
public function runDraft(Request $request, Tournament $tournament)
{
    $this->ensureAdmin();

    // 1) Берём только активных участников без команды
    $participants = $tournament->participants()
        ->where('is_active', true)
        ->whereNull('nhl_team_id')
        ->orderBy('id')
        ->get();

    if ($participants->isEmpty()) {
        return back()->with('error', 'Нет участников без назначенной команды для жеребьёвки.');
    }

    // 2) Все команды, выбранные для этого турнира (актуальный список)
    $allTeams = $tournament->draftTeams()->get();

    // 3) Команды, которые уже кем-то заняты (чтобы не раздавать их повторно)
    $usedTeamIds = $tournament->participants()
        ->where('is_active', true)
        ->whereNotNull('nhl_team_id')
        ->pluck('nhl_team_id')
        ->unique()
        ->all();

    // 4) Свободные команды = выбранные для турнира минус занятые
    $availableTeams = $allTeams
        ->when(
            !empty($usedTeamIds),
            fn ($collection) => $collection->whereNotIn('id', $usedTeamIds),
            fn ($collection) => $collection
        )
        ->values();

    if ($availableTeams->count() < $participants->count()) {
        return back()->with(
            'error',
            'Свободных команд меньше, чем участников без команды. Добавьте команды в списке для жеребьёвки.'
        );
    }

    // 5) Фиксируем порядок участников, команды перемешиваем (рандом)
    $participants   = $participants->values();
    $shuffledTeams  = $availableTeams->shuffle()->values();

    $assignments = [];

    DB::transaction(function () use ($participants, $shuffledTeams, &$assignments) {
        foreach ($participants as $index => $participant) {
            /** @var \App\Models\TournamentParticipant $participant */
            /** @var \App\Models\NhlTeam $team */
            $team = $shuffledTeams[$index];

            // Назначаем команду участнику
            $participant->nhl_team_id = $team->id;
            $participant->save();

            $assignments[] = [
                'participant' => [
                    'id'           => $participant->id,
                    'user_id'      => $participant->user_id,
                    'display_name' => $participant->display_name,
                ],
				
				 'team' => [
					'id'       => $team->id,
					'code'     => $team->code,
					'name'     => $team->name,
					// подберите нужное поле под ваш проект:
					// logo_url / logo / logo_path и т.п.
					 'logo_url' => $team->logo_url ?? $team->logo ?? null,
				],
				
            ];
        }
    });

    // 6) Передаём результат на следующее отображение Draft.vue для анимации
    session()->put('draft_assignments', $assignments);

    return redirect()->route('admin.tournaments.draft.show', $tournament);
}




	    /**
     * Сохранить список команд, участвующих в жеребьёвке турнира.
     */
    public function updateDraftTeams(Request $request, Tournament $tournament)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'team_ids'   => ['array'],
            'team_ids.*' => ['integer', 'exists:nhl_teams,id'],
        ]);

        $teamIds = $data['team_ids'] ?? [];

        $tournament->draftTeams()->sync($teamIds);

        return back(303)->with('success', 'Список команд для жеребьёвки обновлён.');
    }


    /* =========================
     *   УЧАСТНИКИ
     * ========================= */

    // Добавить участника по e-mail (как в старой версии: display_name + user_email)
    public function storeParticipant(Request $request, Tournament $tournament)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'display_name' => 'required|string|max:255',
            'user_email'   => 'required|email|exists:users,email',
        ], [
            'user_email.required' => 'Укажите e-mail зарегистрированного пользователя.',
            'user_email.email'    => 'Некорректный e-mail.',
            'user_email.exists'   => 'Пользователь с таким e-mail не найден.',
        ]);

        $user = User::where('email', $data['user_email'])->first();

        // уже зарегистрирован?
        $exists = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return back()->with('ok', 'Этот пользователь уже зарегистрирован в турнире.');
        }

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id'       => $user->id,
            'nickname'      => $data['display_name'],
        ]);

		// --- SYNC standings for all GROUP stages of this tournament ---
		$groupStageIds = $tournament->stages()->where('type','group')->pluck('id');
		foreach ($groupStageIds as $sid) {
			Standing::updateOrCreate(
				['stage_id' => $sid, 'participant_id' => $participant->id],
				[
					'gp' => 0, 'w' => 0, 'otw' => 0, 'sow' => 0,
					'otl' => 0, 'sol' => 0, 'l' => 0,
					'gf' => 0, 'ga' => 0, 'gd' => 0,
					'points' => 0, 'tech_losses' => 0,
				]
			);
		}

        return back()->with('ok', 'Участник добавлен');
    }

	public function destroyParticipant(TournamentParticipant $participant)
	{
		$this->ensureAdmin();

		// Мягкое удаление: участник перестаёт быть активным в турнире,
		// но остаётся в БД для связей с матчами и рейтингом
		$participant->is_active = false;
		$participant->save();

		return back()->with('ok', 'Участник удалён из активного состава турнира (история матчей и рейтинг сохранены).');
	}
	

    /* =========================
     *   СТАДИИ
     * ========================= */

    public function storeStage(Request $request, Tournament $tournament)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|string|max:50', // group / playoff / etc
            'order'          => 'nullable|integer',
            'games_per_pair' => 'required|integer|min:1|max:4',
        ]);

        // приведение к диапазону и порядковый номер по умолчанию
        $data['games_per_pair'] = max(1, min(4, (int)($data['games_per_pair'] ?? 1)));

        if (!array_key_exists('order', $data) || $data['order'] === '' || $data['order'] === null) {
            $max = (int) ($tournament->stages()->max('order') ?? 0);
            $data['order'] = $max + 1;
        } else {
            $data['order'] = (int) $data['order'];
        }

        $tournament->stages()->create($data);

        return back()->with('ok', 'Стадия создана');
    }

    public function updateStage(Request $request, Stage $stage)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'type'           => 'sometimes|string|max:50',
            'order'          => 'sometimes|integer|nullable',
            'games_per_pair' => 'sometimes|integer|min:1|max:4',
        ]);

        if (array_key_exists('games_per_pair', $data)) {
            $data['games_per_pair'] = max(1, min(4, (int)$data['games_per_pair']));
        }

        if (array_key_exists('order', $data)) {
            if ($data['order'] === '' || $data['order'] === null) {
                unset($data['order']);
            } else {
                $data['order'] = (int) $data['order'];
            }
        }

        $stage->fill($data)->save();

        return back()->with('ok', 'Стадия обновлена');
    }

    public function destroyStage(Stage $stage)
    {
        $this->ensureAdmin();

        DB::transaction(function () use ($stage) {
            $matchIds = MatchModel::where('stage_id', $stage->id)->pluck('id');

            if (Schema::hasTable('match_reports')) {
                DB::table('match_reports')->whereIn('match_id', $matchIds)->delete();
            }

            MatchModel::whereIn('id', $matchIds)->delete();

            $stage->delete();
        });

        return back()->with('ok', 'Стадия удалена');
    }

    /* =========================
     *   Генерация ROUND ROBIN
     * ========================= */

public function generateRoundRobin(Stage $stage)
{
    $this->ensureAdmin();

    $participantIds = \App\Models\TournamentParticipant::where('tournament_id', $stage->tournament_id)
        ->pluck('id')
        ->values()
        ->all();

    if (count($participantIds) < 2) {
        return back()->with('ok', 'Недостаточно участников для генерации.');
    }

    $games = max(1, min(4, (int) $stage->games_per_pair)); // 1..4

    for ($i = 0, $n = count($participantIds); $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $homeId = $participantIds[$i];
            $awayId = $participantIds[$j];

            // Максимальный game_no для пары в ОБЕИХ ориентациях
            $maxNo = (int) \App\Models\MatchModel::where('stage_id', $stage->id)
                ->where(function ($q) use ($homeId, $awayId) {
                    $q->where(function ($qq) use ($homeId, $awayId) {
                        $qq->where('home_participant_id', $homeId)
                           ->where('away_participant_id', $awayId);
                    })->orWhere(function ($qq) use ($homeId, $awayId) {
                        $qq->where('home_participant_id', $awayId)
                           ->where('away_participant_id', $homeId);
                    });
                })
                ->max('game_no');

            // Дозаполняем до нужного количества игр c корректным game_no
            for ($no = $maxNo + 1; $no <= $games; $no++) {
                // чётные игры — меняем хозяина/гостя
                $h = ($no % 2 === 0) ? $awayId : $homeId;
                $a = ($no % 2 === 0) ? $homeId : $awayId;

                // На всякий случай делаем идемпотентно
                \App\Models\MatchModel::firstOrCreate([
                    'stage_id'            => $stage->id,
                    'home_participant_id' => $h,
                    'away_participant_id' => $a,
                    'game_no'             => $no,
                ], [
                    'status'              => 'scheduled',
                ]);
            }
        }
    }

    return back()->with('ok', 'Расписание Round Robin сгенерировано.');
}

// ---- NEW: методы оставляем ВНУТРИ класса ----

/**
 * Расчёт таблицы группы (очки/разница и т.д.)
 * Возвращает массив позиций, отсортированный по points desc, gd desc, gf desc, wins desc.
 */


//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

/**
 * Расчёт таблицы группы (очки/разница и т.д.)
 * Возвращает массив позиций, отсортированный по points desc, gd desc, gf desc, wins desc.
 */
public function computeStandings(\App\Models\Stage $stage): array
{
    // Дефолтные очки
    $defaults = [
        'win'      => 2,
        'loss'     => 0,
        'ot_win'   => 2,
        'ot_loss'  => 1,
        'so_win'   => 2,
        'so_loss'  => 1,
        'draw'     => 1,
    ];
    // Сливаем с конфигом (если он есть) — чтобы не было "Undefined array key"
    $cfg = config('tournament.points', []);
    if (!is_array($cfg)) $cfg = [];
    $pointsCfg = array_merge($defaults, $cfg);

    // Инициализируем строки таблицы
    $rows = [];
		
	$participantIds = \App\Models\TournamentParticipant::where('tournament_id', $stage->tournament_id)
    ->where('is_active', true)
    ->pluck('id')
    ->all();

    foreach ($participantIds as $pid) {
        $rows[$pid] = [
            'participant_id' => $pid,
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'ot_wins' => 0,
            'ot_losses' => 0,
            'so_wins' => 0,
            'so_losses' => 0,
            'gf' => 0,
            'ga' => 0,
            'gd' => 0,
            'points' => 0,
        ];
    }

    // Только подтверждённые матчи
    $matches = \App\Models\MatchModel::where('stage_id', $stage->id)
        ->where('status', 'confirmed')
        ->get(['home_participant_id','away_participant_id','score_home','score_away','ot','so']);

    foreach ($matches as $m) {
        // Пропускаем незаполненные счёты
        if ($m->score_home === null || $m->score_away === null) {
            continue;
        }

        $h = $m->home_participant_id;
        $a = $m->away_participant_id;
        $hs = (int)$m->score_home;
        $as = (int)$m->score_away;
        $isOt = (bool)$m->ot;
        $isSo = (bool)$m->so;

        // На всякий случай, если по какой-то причине участника нет в списке — инициализируем
        foreach ([$h, $a] as $pid) {
            if (!isset($rows[$pid])) {
                $rows[$pid] = [
                    'participant_id' => $pid,
                    'played' => 0, 'wins' => 0, 'losses' => 0,
                    'ot_wins' => 0, 'ot_losses' => 0,
                    'so_wins' => 0, 'so_losses' => 0,
                    'gf' => 0, 'ga' => 0, 'gd' => 0, 'points' => 0,
                ];
            }
        }

        $rows[$h]['played']++; $rows[$a]['played']++;
        $rows[$h]['gf'] += $hs; $rows[$h]['ga'] += $as;
        $rows[$a]['gf'] += $as; $rows[$a]['ga'] += $hs;

        if ($hs === $as) {
            $rows[$h]['points'] += $pointsCfg['draw'];
            $rows[$a]['points'] += $pointsCfg['draw'];
        } else {
            $homeWon = $hs > $as;
            if ($homeWon) {
                if     ($isSo) { $rows[$h]['so_wins']++; $rows[$a]['so_losses']++; $rows[$h]['points'] += $pointsCfg['so_win']; $rows[$a]['points'] += $pointsCfg['so_loss']; }
                elseif ($isOt) { $rows[$h]['ot_wins']++; $rows[$a]['ot_losses']++; $rows[$h]['points'] += $pointsCfg['ot_win']; $rows[$a]['points'] += $pointsCfg['ot_loss']; }
                else           { $rows[$h]['wins']++;   $rows[$a]['losses']++;   $rows[$h]['points'] += $pointsCfg['win'];    $rows[$a]['points'] += $pointsCfg['loss']; }
            } else {
                if     ($isSo) { $rows[$a]['so_wins']++; $rows[$h]['so_losses']++; $rows[$a]['points'] += $pointsCfg['so_win']; $rows[$h]['points'] += $pointsCfg['so_loss']; }
                elseif ($isOt) { $rows[$a]['ot_wins']++; $rows[$h]['ot_losses']++; $rows[$a]['points'] += $pointsCfg['ot_win']; $rows[$h]['points'] += $pointsCfg['ot_loss']; }
                else           { $rows[$a]['wins']++;   $rows[$h]['losses']++;   $rows[$a]['points'] += $pointsCfg['win'];    $rows[$h]['points'] += $pointsCfg['loss']; }
            }
        }
    }

    foreach ($rows as &$r) {
        $r['gd'] = $r['gf'] - $r['ga'];
    }

    $table = array_values($rows);
    usort($table, fn($x, $y) =>
        [$y['points'], $y['gd'], $y['gf'], $y['wins']]
        <=>
        [$x['points'], $x['gd'], $x['gf'], $x['wins']]
    );

    return $table;
}


//!!!!!!!!!!!!!!!!!


// POST /admin/tournaments/stages/{stage}/generate-playoff
public function generatePlayoff(\App\Models\Stage $stage, \Illuminate\Http\Request $request)
{
    if ($stage->type !== 'group') {
        return back()->with('error', 'Сетку можно сформировать только из стадии типа "Группа".');
    }

    $tournament = \App\Models\Tournament::findOrFail($stage->tournament_id);

    // Жёсткая проверка формата
    if (($tournament->format ?? 'round_robin') !== 'groups_playoff') {
        return back()->with('error', 'Формат турнира не поддерживает плей-офф.');
    }

    $table = $this->computeStandings($stage);
    if (empty($table)) {
        return back()->with('error', 'Таблица пуста: нет подтверждённых матчей в группе.');
    }

    // Сколько команд выходит в плей-офф
    $advancers = min($stage->advancers, count($table));
    if (!in_array($advancers, [4, 8, 16, 32], true)) {
        $advancers = min(8, count($table));
    }

    // До скольких поражений идёт серия (1..4)
    $L = (int) $request->input('losses_to_eliminate', $stage->settings['losses_to_eliminate'] ?? 1);
    if ($L < 1) $L = 1; if ($L > 4) $L = 4;
    $gamesPerPair = (2 * $L) - 1; // максимум возможных матчей в серии

    $seeded = array_slice($table, 0, $advancers);

    $playoff = \App\Models\Stage::firstOrCreate([
        'tournament_id' => $tournament->id,
        'type'          => 'playoff',
        'name'          => 'Плей-офф',
    ], [
        'order'         => (int)$stage->order + 1,
        'games_per_pair'=> $gamesPerPair,
        'settings'      => ['source_stage_id' => $stage->id, 'round' => 'R1', 'size' => $advancers, 'losses_to_eliminate' => $L],
    ]);

    // актуализируем настройки, если стадия уже была создана
    $playoff->games_per_pair = $gamesPerPair;
    $playoff->settings = array_merge($playoff->settings ?? [], ['losses_to_eliminate' => $L, 'size' => $advancers]);
    $playoff->save();

    // Перегенерируем матчи
    \App\Models\MatchModel::where('stage_id', $playoff->id)->delete();

    $pairs = [];
    for ($i = 0; $i < intdiv($advancers, 2); $i++) {
        $a = $seeded[$i];
        $b = $seeded[$advancers - 1 - $i];
        $pairs[] = [$a['participant_id'], $b['participant_id']];
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($playoff, $pairs, $gamesPerPair) {
        foreach ($pairs as [$homeId, $awayId]) {
            for ($n = 1; $n <= $gamesPerPair; $n++) {
                \App\Models\MatchModel::create([
                    'stage_id'            => $playoff->id,
                    'home_participant_id' => $homeId,
                    'away_participant_id' => $awayId,
                    'game_no'             => $n,
                    'status'              => 'scheduled',
                ]);
            }
        }
    });

    return back()->with('ok', "Сетка плей-офф на {$advancers} команд сгенерирована (серия: до {$L} поражений).");
}


} // <-- это ЕДИНСТВЕННАЯ закрывающая скобка класса в конце файла
