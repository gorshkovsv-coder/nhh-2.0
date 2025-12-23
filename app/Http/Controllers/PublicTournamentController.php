<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\MatchModel;
use App\Models\NhlTeam;
use Illuminate\Http\Request;

class PublicTournamentController extends Controller
{
	public function show(Tournament $tournament)
{
	$tournament->load([
		'participants.nhlTeam',
		'stages' => fn ($q) => $q->orderBy('order'),
		'stages.matches' => fn ($q) => $q
			->orderBy('home_participant_id')
			->orderBy('away_participant_id')
			->orderBy('game_no'),
	]);
	
	$authUser = auth()->user();
	$isRegistered = false;
	if ($authUser) {
    $isRegistered = DB::table('tournament_participants')
        ->where('tournament_id', $tournament->id)
        ->where('user_id', $authUser->id)
		->where('is_active', 1)   // вот это ключевая строка
        ->exists();
	}

    // Участники по id для быстрых отображаемых имён
    $pById = $tournament->participants->keyBy('id');
	
	// Нормализованный участник для фронта (Игрок + Команда)
    $mapParticipant = function ($p) {
        if (!$p) {
            return null;
        }

        $team = $p->nhlTeam;

        return [
            'id'           => $p->id,
            'user_id'      => $p->user_id,
            'display_name' => $p->display_name,
            'nhl_team_id'  => $p->nhl_team_id,
            'team'         => $team ? [
                'id'       => $team->id,
                'code'     => $team->code,
                'name'     => $team->name,
                'logo_url' => $team->logo_url,
            ] : null,
        ];
    };

    $groupTables = $this->buildGroupTables($tournament, false);


    // === Сетка плей-офф (как было) ===
    $playoffStages = $tournament->stages->where('type', 'playoff')->values();
    $bracketColumns = [];

    foreach ($playoffStages as $ps) {
        $settings = (array) ($ps->settings ?? []);
        $size = (int) ($settings['size'] ?? 0);
        if (!in_array($size, [4,8,16], true)) {
            $firstRoundPairs = collect($ps->matches ?? [])
                ->filter(fn($m) => (int)($m->meta['round'] ?? 1) === 1)
                ->map(fn($m) => min($m->home_participant_id, $m->away_participant_id) . ':' . max($m->home_participant_id, $m->away_participant_id))
                ->unique()
                ->count();
            $size = max(4, min(16, $firstRoundPairs * 2 ?: 8));
        }

        $labels = match ($size) {
            16 => ['1/8', '1/4', '1/2', 'Финал'],
            8  => ['1/4', '1/2', 'Финал'],
            4  => ['1/2', 'Финал'],
            default => ['Раунд 1'],
        };

        $byRound = [];
        foreach ($ps->matches as $m) {
           // не включаем матч(и) за 3-е место в обычные раунды
            if ((bool)($m->meta['third_place'] ?? false)) {
                continue;
            }
            $r = (int) ($m->meta['round'] ?? 1);
            $pairKey = min($m->home_participant_id, $m->away_participant_id) . ':' .
                       max($m->home_participant_id, $m->away_participant_id);
            $byRound[$r][$pairKey][] = $m;
        }


        $roundsCount = count($labels);
        for ($r = 1; $r <= $roundsCount; $r++) {
            $needSeries = $size >> $r; // 16→8,4,2,1
            $col = ['title' => $labels[$r-1] ?? "Раунд {$r}", 'series' => []];

            $seriesMap = $byRound[$r] ?? [];
            if (!empty($seriesMap)) {
                foreach ($seriesMap as $games) {
                    $first = $games[0] ?? null;
                    if (!$first) continue;

                    $winsH = $winsA = $lossH = $lossA = 0;
                    foreach ($games as $g) {
                        if ($g->status !== 'confirmed') continue;
                        if ($g->score_home === null || $g->score_away === null) continue;
                        if ((int)$g->score_home > (int)$g->score_away) { $winsH++; $lossA++; }
                        elseif ((int)$g->score_home < (int)$g->score_away) { $winsA++; $lossH++; }
                    }

                    $gp = max(1, min(7, (int) ($ps->games_per_pair ?? 1)));
                    $need = intdiv($gp, 2) + 1;

					$col['series'][] = [
						'home'        => $mapParticipant($pById->get($first->home_participant_id)),
						'away'        => $mapParticipant($pById->get($first->away_participant_id)),
						'wins_home'   => $winsH,
						'wins_away'   => $winsA,
						'losses_home' => $lossH,
						'losses_away' => $lossA,
						'finished'    => (bool) (max($winsH, $winsA) >= $need),
						'games'       => array_values($games),
					];
                }
            }

            while (count($col['series']) < $needSeries) {
                $col['series'][] = [
                    'home'        => null,
                    'away'        => null,
                    'wins_home'   => 0,
                    'wins_away'   => 0,
                    'losses_home' => 0,
                    'losses_away' => 0,
                    'finished'    => false,
                    'games'       => [],
                ];
            }

            $bracketColumns[] = $col;
        }
		
		
		if ((bool) ($settings['third_place'] ?? false)) {
			$thirdGames = collect($ps->matches ?? [])
				->filter(fn($m) => (bool) ($m->meta['third_place'] ?? false))
				->values();
		
			$thirdCol = ['title' => 'Матч за 3-е место', 'series' => []];
		
			if ($thirdGames->isNotEmpty()) {
				$g1 = $thirdGames->first();
				$winsH = $winsA = $lossH = $lossA = 0;

				foreach ($thirdGames as $g) {
					if ($g->status !== 'confirmed') continue;
					if ($g->score_home === null || $g->score_away === null) continue;
					if ((int)$g->score_home > (int)$g->score_away) { $winsH++; $lossA++; }
					elseif ((int)$g->score_home < (int)$g->score_away) { $winsA++; $lossH++; }
				}

				// === НОВОЕ: считаем требуемые победы и флаг завершённости серии ===
				$gp   = max(1, min(7, (int) ($ps->games_per_pair ?? 1)));
				$need = intdiv($gp, 2) + 1;
						
				$thirdCol['series'][] = [
					'home'        => $mapParticipant($pById->get($g1->home_participant_id)),
					'away'        => $mapParticipant($pById->get($g1->away_participant_id)),
					'wins_home'   => $winsH,
					'wins_away'   => $winsA,
					'losses_home' => $lossH,
					'losses_away' => $lossA,
					'finished'    => (bool) (max($winsH, $winsA) >= $need),
					'games'       => array_values($games),
				];
				
				
			} else {
				$thirdCol['series'][] = [
					'home'        => null,
					'away'        => null,
					'wins_home'   => 0,
					'wins_away'   => 0,
					'losses_home' => 0,
					'losses_away' => 0,
					'finished'    => false,
					'games'       => [],
				];
			}
		
			$bracketColumns[] = $thirdCol;
		}
		
    }
	
	return Inertia::render('Tournament/Show', [
        'tournament'     => $tournament,
        'stages'         => $tournament->stages,
        'groupTables'    => $groupTables,   // ⬅️ добавили
        'bracketColumns' => $bracketColumns,
		'selfReg' => [
        'enabled'    => $tournament->status === 'registration',
        'registered' => $isRegistered,
    ],
]);
	
}

public function headToHead(Tournament $tournament)
{
    $tournament->load([
        'participants.nhlTeam',
        'stages' => fn ($q) => $q->orderBy('order'),
        'stages.matches' => fn ($q) => $q
            ->with(['reports' => function ($q) {
                $q->latest('created_at');
            }])
            ->orderBy('home_participant_id')
            ->orderBy('away_participant_id')
            ->orderBy('game_no'),
    ]);

    $groupTables = $this->buildGroupTables($tournament, true);

    return Inertia::render('Tournament/HeadToHead', [
        'tournament'  => $tournament,
        'groupTables' => $groupTables,
    ]);
}

private function buildGroupTables(Tournament $tournament, bool $includeHeadToHead): array
{
    // Участники по id для быстрых отображаемых имён
    $pById = $tournament->participants->keyBy('id');

    // Нормализованный участник для фронта (Игрок + Команда)
    $mapParticipant = function ($p) {
        if (!$p) {
            return null;
        }

        $team = $p->nhlTeam;

        return [
            'id'           => $p->id,
            'user_id'      => $p->user_id,
            'display_name' => $p->display_name,
            'nhl_team_id'  => $p->nhl_team_id,
            'team'         => $team ? [
                'id'       => $team->id,
                'code'     => $team->code,
                'name'     => $team->name,
                'logo_url' => $team->logo_url,
            ] : null,
        ];
    };

    // === Турнирные таблицы для всех стадий типа "group" (Регулярка) ===
    $groupStages = $tournament->stages->where('type', 'group')->values();
    $groupTables = [];

    foreach ($groupStages as $gs) {

        // 1) standings этой стадии -> по participant_id
        $stByPid = \DB::table('standings')
            ->where('stage_id', $gs->id)
            ->select('participant_id','gp','w','otw','sow','otl','sol','l','gf','ga','gd','points','tech_losses')
            ->get()
            ->keyBy('participant_id');

        // 2) Плановое количество игр для каждого участника на этой стадии (по сетке матчей)
        //    Берём все матчи этой стадии и считаем, сколько раз каждый participant_id встречается
        $plannedByPid = collect($gs->matches ?? [])
            ->flatMap(function ($m) {
                return [
                    (int) $m->home_participant_id,
                    (int) $m->away_participant_id,
                ];
            })
            ->countBy()
            ->map(fn ($cnt) => (int) $cnt);

        // 3) все участники турнира (покажем даже тех, у кого пока нет строки в standings)
        $allParticipants = $tournament->participants;

        // 4) собираем строки; если в standings нет — отдадим нули
        $rows = [];
        foreach ($allParticipants as $p) {
            $pid = (int) $p->id;
            $s   = $stByPid->get($pid);
            $mp  = $mapParticipant($p);

            $rows[] = [
                'participant_id' => $pid,
                'user_id'        => $mp['user_id'] ?? null,
                // Имя игрока
                'name'           => $mp['display_name'] ?? ('#' . $pid),
                // Команда (логотип + название)
                'team'           => $mp['team'] ?? null,

                'gp'         => (int)($s->gp         ?? 0),
                // Новое поле: сколько игр запланировано на этой стадии для участника
                'gp_planned' => (int)($plannedByPid->get($pid) ?? 0),

                'w'          => (int)($s->w          ?? 0),
                'otw'        => (int)($s->otw        ?? 0),
                'sow'        => (int)($s->sow        ?? 0),
                'otl'        => (int)($s->otl        ?? 0),
                'sol'        => (int)($s->sol        ?? 0),
                'l'          => (int)($s->l          ?? 0),
                'gf'         => (int)($s->gf         ?? 0),
                'ga'         => (int)($s->ga         ?? 0),
                'gd'         => (int)($s->gd         ?? 0),
                'points'     => (int)($s->points     ?? 0),
                'tech_losses'=> (int)($s->tech_losses ?? 0),
            ];
        }

        // 5) сортировка (очки → +/- → забитые, как раньше)
        usort($rows, fn($a, $b) =>
            [$b['points'], $b['gd'], $b['gf'], $a['participant_id']]
            <=>
            [$a['points'], $a['gd'], $a['gf'], $b['participant_id']]
        );

        // 6) назначаем позиции
        $tableRows = [];
        $pos = 1;
        foreach ($rows as $r) {
            $r['pos'] = $pos++;
            $tableRows[] = $r;
        }

        $table = [
            'stage_id'   => $gs->id,
            'stage_name' => $gs->name ?? 'Регулярка',
            'rows'       => $tableRows,
        ];

        if ($includeHeadToHead) {
            $table['head_to_head'] = $this->buildHeadToHeadMatrix($gs, $tableRows);
        }

        $groupTables[] = $table;
    }

    return $groupTables;
}

private function buildHeadToHeadMatrix($stage, array $tableRows): array
{
    if (empty($tableRows)) {
        return [
            'participants' => [],
            'rows' => [],
        ];
    }

    $participants = array_values($tableRows);
    $participantIds = array_map(fn ($row) => (int) $row['participant_id'], $participants);

    $matrix = [];
    foreach ($participantIds as $rowId) {
        foreach ($participantIds as $colId) {
            $matrix[$rowId][$colId] = null;
        }
    }

    $matches = collect($stage->matches ?? [])
        ->filter(fn ($match) => in_array($match->status, ['confirmed', 'reported', 'disputed'], true));

    foreach ($matches as $match) {
        $report = $match->reports?->first();
        $scoreHome = $match->score_home ?? $report?->score_home;
        $scoreAway = $match->score_away ?? $report?->score_away;

        if ($scoreHome === null || $scoreAway === null) {
            continue;
        }

        $homeId = (int) $match->home_participant_id;
        $awayId = (int) $match->away_participant_id;

        if (!isset($matrix[$homeId][$awayId])) {
            continue;
        }

        $homeResult = $scoreHome > $scoreAway ? 'win' : ($scoreHome < $scoreAway ? 'loss' : 'draw');
        $awayResult = $scoreAway > $scoreHome ? 'win' : ($scoreAway < $scoreHome ? 'loss' : 'draw');

        $matrix[$homeId][$awayId] = [
            'value' => "{$scoreHome}:{$scoreAway}",
            'result' => $homeResult,
        ];
        $matrix[$awayId][$homeId] = [
            'value' => "{$scoreAway}:{$scoreHome}",
            'result' => $awayResult,
        ];
    }

    $headerParticipants = array_map(function ($row) {
        return [
            'participant_id' => $row['participant_id'],
            'pos' => $row['pos'] ?? null,
            'name' => $row['name'],
            'team' => $row['team'],
        ];
    }, $participants);

    $rows = [];
    foreach ($participants as $row) {
        $rowId = (int) $row['participant_id'];
        $cells = [];

        foreach ($participants as $col) {
            $colId = (int) $col['participant_id'];

            if ($rowId === $colId) {
                $cells[] = [
                    'value' => '—',
                    'result' => 'self',
                ];
                continue;
            }

            $cells[] = $matrix[$rowId][$colId] ?? [
                'value' => '—',
                'result' => null,
            ];
        }

        $rows[] = [
            'participant' => [
                'participant_id' => $rowId,
                'pos' => $row['pos'] ?? null,
                'name' => $row['name'],
                'team' => $row['team'],
            ],
            'cells' => $cells,
        ];
    }

    return [
        'participants' => $headerParticipants,
        'rows' => $rows,
    ];
}

public function matchesHistory(Tournament $tournament, Request $request)
{
    // Текущие значения фильтров из query string
    $filters = [
        'stage_id' => $request->integer('stage_id') ?: null,
        'team_id'  => $request->integer('team_id') ?: null,
    ];

    // Базовый запрос: только подтверждённые матчи этого турнира
    $query = MatchModel::query()
        ->where('status', 'confirmed')
        ->with([
            'stage.tournament',
            'home.user',
            'home.nhlTeam',
            'away.user',
            'away.nhlTeam',
        ])
        ->whereHas('stage', function ($q) use ($tournament) {
            $q->where('tournament_id', $tournament->id);
        });

    // Фильтр по стадии
    if ($filters['stage_id']) {
        $query->where('stage_id', $filters['stage_id']);
    }

    // Фильтр по команде (NhlTeam): либо хозяева с этой командой, либо гости
    if ($filters['team_id']) {
        $teamId = $filters['team_id'];

        $query->where(function ($q) use ($teamId) {
            $q->whereHas('home.nhlTeam', function ($qq) use ($teamId) {
                $qq->where('id', $teamId);
            })->orWhereHas('away.nhlTeam', function ($qq) use ($teamId) {
                $qq->where('id', $teamId);
            });
        });
    }

    $matches = $query
        ->orderByRaw('COALESCE(scheduled_at, created_at) DESC')
        ->paginate(30)
        ->withQueryString();

    // Список стадий этого турнира (для выпадающего списка)
    $stages = $tournament->stages()
        ->orderBy('order')
        ->get(['id', 'name']);

    // Список команд, участвующих в турнире
    $teams = NhlTeam::query()
        ->whereIn('id', function ($sub) use ($tournament) {
            $sub->select('nhl_team_id')
                ->from('tournament_participants')
                ->where('tournament_id', $tournament->id)
                ->whereNotNull('nhl_team_id');
        })
        ->orderBy('name')
        ->get(['id', 'name']);

    return Inertia::render('Tournament/MatchesHistory', [
        'tournament' => $tournament,
        'matches'    => $matches,
        'stages'     => $stages,
        'teams'      => $teams,
        'filters'    => $filters,
    ]);
}



}
