<?php

namespace App\Services;

use App\Models\MatchModel;
use App\Models\Standing;
use App\Models\Stage;
use App\Models\TournamentParticipant;
use App\Models\User;
use Carbon\Carbon;

class PlayerStatsService
{
    /**
     * Глобальный рейтинг и статистика по всем игрокам,
     * участвовавшим хотя бы в одном подтверждённом матче.
     *
     * @return array<int,array<string,mixed>>
     */
    public function buildGlobalStats(): array
    {
        // Все участники турниров, у которых есть привязка к пользователю
        $participants = TournamentParticipant::query()
            ->whereNotNull('user_id')
            ->get(['id', 'user_id', 'tournament_id']);

        if ($participants->isEmpty()) {
            return [];
        }

        $participantToUser = $participants->pluck('user_id', 'id')->all();          // [participant_id => user_id]
        $participantToTournament = $participants->pluck('tournament_id', 'id')->all(); // [participant_id => tournament_id]

        $userIds = array_values(array_unique($participants->pluck('user_id')->all()));

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $stats = [];
        foreach ($users as $userId => $user) {
            $stats[$userId] = $this->initStatsForUser($user);
        }

        $now = Carbon::now();

        // Все подтверждённые матчи
        $matches = MatchModel::query()
            ->with([
                'stage',
                'home',
                'home.user',
                'away',
                'away.user',
            ])
            ->where('status', 'confirmed')
            ->get();

        // Для плей-офф по турнирам
        $tournamentPlayoffMatches = []; // [tournament_id => MatchModel[]]

        foreach ($matches as $match) {
            $stage = $match->stage;
            if (!$stage) {
                continue;
            }

            $tournamentId = $stage->tournament_id;

            // Дата матча
            $matchDate = $match->confirmed_at ?? $match->scheduled_at ?? $match->created_at ?? $match->updated_at;
            if ($matchDate && !$matchDate instanceof Carbon) {
                $matchDate = Carbon::parse($matchDate);
            }

            $isOT = (bool) $match->ot;
            $isSO = (bool) $match->so;
            $inExtra = $isOT || $isSO;

            // Обработка обеих сторон
            foreach (['home', 'away'] as $side) {
                $participant = $side === 'home' ? $match->home : $match->away;
                if (!$participant) {
                    continue;
                }

                $participantId = $participant->id;
                $userId = $participantToUser[$participantId] ?? null;
                if (!$userId || !isset($stats[$userId])) {
                    continue;
                }

                $gf = $side === 'home' ? $match->score_home : $match->score_away;
                $ga = $side === 'home' ? $match->score_away : $match->score_home;

                if ($gf === null || $ga === null) {
                    continue;
                }

                $s =& $stats[$userId];

                $s['matches_played']++;
                $s['goals_for'] += (int) $gf;
                $s['goals_against'] += (int) $ga;

                // Победа / поражение и очки рейтинга
                if ($gf !== $ga) {
                    $won = $gf > $ga;
                    if ($won) {
                        $s['wins']++;
                        // +2 за победу
                        $s['rating_points'] += 2;
                    } else {
                        $s['losses']++;
                        // +1 за поражение в ОТ/буллитах
                        if ($inExtra) {
                            $s['rating_points'] += 1;
                        }
                    }
                } else {
                    $won = null;
                }

                // Даты, матчи за 30 дней, список результатов для серий
                if ($matchDate) {
                    if ($s['last_match_at'] === null || $matchDate->gt($s['last_match_at'])) {
                        $s['last_match_at'] = $matchDate->copy();
                    }

                    if ($matchDate->gte($now->copy()->subDays(30))) {
                        $s['matches_last_30_days']++;
                    }

                    if ($won !== null) {
                        $s['results'][] = [
                            'date' => $matchDate->copy(),
                            'won'  => $won,
                        ];
                    }
                }

                // Отметим участие в плей-офф
                if ($stage->type === 'playoff') {
                    $s['_playoff_tournaments'][$tournamentId] = true;
                }
            }

            // Для анализа финала и чемпиона
            if ($stage->type === 'playoff') {
                $tournamentPlayoffMatches[$tournamentId][] = $match;
            }
        }

        // Плей-офф: чемпионы, финалы, выходы в плей-офф
        $this->enrichTournamentStatsFromPlayoffs($stats, $tournamentPlayoffMatches, $participantToUser);

        // Регулярка (group): среднее место и чемпионы турниров без плей-офф
        $this->enrichRegularStageStats($stats, $participantToUser, $tournamentPlayoffMatches);

        // Финализация: проценты, средние значения, серии
        foreach ($stats as $userId => &$s) {
            $gp = $s['matches_played'];

            $s['goals_diff'] = $s['goals_for'] - $s['goals_against'];

            if ($gp > 0) {
                $s['win_rate'] = round($s['wins'] / $gp * 100, 1);
                $s['goals_for_per_game'] = round($s['goals_for'] / $gp, 2);
                $s['goals_against_per_game'] = round($s['goals_against'] / $gp, 2);
            } else {
                $s['win_rate'] = 0.0;
                $s['goals_for_per_game'] = 0.0;
                $s['goals_against_per_game'] = 0.0;
            }

            // Среднее место в регулярке
            if (!empty($s['_regular_positions'])) {
                $s['regular_avg_position'] = round(array_sum($s['_regular_positions']) / count($s['_regular_positions']), 2);
            } else {
                $s['regular_avg_position'] = null;
            }

            // Серии
            $this->computeStreaks($s);

            // Очистка служебных полей
            unset($s['_playoff_tournaments'], $s['_final_tournaments'], $s['_regular_positions'], $s['results']);
        }
        unset($s);

			// Сортировка:
			// 1) по рейтингу (rating_points) по убыванию,
			// 2) затем по количеству побед,
			// 3) затем по Win%,
			// 4) затем по разнице шайб,
			// 5) затем по количеству матчей,
			// 6) затем по user_id, чтобы порядок был детерминированным.
			uasort($stats, function (array $a, array $b) {
				// 1. Рейтинг
				if ($a['rating_points'] !== $b['rating_points']) {
					return $b['rating_points'] <=> $a['rating_points'];
				}

				// 2. Победы
				if ($a['wins'] !== $b['wins']) {
					return $b['wins'] <=> $a['wins'];
				}

				// 3. Win%
				if ($a['win_rate'] !== $b['win_rate']) {
					return $b['win_rate'] <=> $a['win_rate'];
				}

				// 4. Разница шайб
				if ($a['goals_diff'] !== $b['goals_diff']) {
					return $b['goals_diff'] <=> $a['goals_diff'];
				}

				// 5. Количество матчей (больше матчей — выше)
				if ($a['matches_played'] !== $b['matches_played']) {
					return $b['matches_played'] <=> $a['matches_played'];
				}

				// 6. На всякий случай стабилизируем по user_id
				return $a['user_id'] <=> $b['user_id'];
			});

// Присвоение позиций (рангов)
// Теперь место всегда уникально: 1, 2, 3, ... без "равных мест".
$position = 0;

foreach ($stats as &$s) {
    $position++;
    $s['rank'] = $position;

    // Дата в строку для фронта
    $s['last_match_at'] = $s['last_match_at'] instanceof Carbon
        ? $s['last_match_at']->toIso8601String()
        : null;
}
unset($s);


        return array_values($stats);
    }

    /**
     * Статистика для конкретного пользователя.
     */
    public function buildStatsForUser(int $userId): ?array
    {
        $all = $this->buildGlobalStats();

        foreach ($all as $row) {
            if ((int) ($row['user_id'] ?? 0) === $userId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Инициализация структуры статистики для пользователя.
     */
    protected function initStatsForUser(User $user): array
    {
        return [
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'user_psn'  => $user->psn,
            'avatar_url'=> $user->avatar_url,

            'rating_points' => 0.0,

            'matches_played' => 0,
            'wins'           => 0,
            'losses'         => 0,
            'win_rate'       => 0.0,

            'goals_for'   => 0,
            'goals_against' => 0,
            'goals_diff'    => 0,
            'goals_for_per_game' => 0.0,
            'goals_against_per_game' => 0.0,

            'tournaments_won'      => 0,
            'playoff_appearances'  => 0,
            'final_appearances'    => 0,
            'regular_avg_position' => null,

            'last_match_at'        => null,
            'matches_last_30_days' => 0,

            'current_streak_type'   => null,
            'current_streak_length' => 0,
            'best_win_streak'       => 0,

            // служебные поля
            '_playoff_tournaments' => [],
            '_final_tournaments'   => [],
            '_regular_positions'   => [],
            'results'              => [],
        ];
    }

	    /**
     * Плей-офф: чемпионы турниров, финалисты и выходы в плей-офф/финал.
     *
     * Правила:
     * - чемпион турнира = победитель финальной серии плей-офф (этап "Финал"), причём серия ЗАВЕРШЕНА;
     * - выход в плей-офф = участие хотя бы в одном подтверждённом матче стадии type = 'playoff';
     *   (флажок _playoff_tournaments заполняется в buildGlobalStats());
     * - выход в финал = участие хотя бы в одном подтверждённом матче финального раунда (round = max, не матч за 3-е место).
     *
     * @param array<int,array<string,mixed>>               $stats
     * @param array<int,array<int,\App\Models\MatchModel>> $tournamentPlayoffMatches  [tournament_id => MatchModel[]]
     * @param array<int,int>                               $participantToUser         [participant_id => user_id]
     */
    protected function enrichTournamentStatsFromPlayoffs(array &$stats, array $tournamentPlayoffMatches, array $participantToUser): void
    {
        foreach ($tournamentPlayoffMatches as $tournamentId => $matches) {
            if (empty($matches)) {
                continue;
            }

            // Стадия плей-офф турнира (нужна games_per_pair, чтобы понять, когда серия завершена)
            $playoffStage = Stage::query()
                ->where('tournament_id', $tournamentId)
                ->where('type', 'playoff')
                ->first();

            $gp = $playoffStage ? (int) ($playoffStage->games_per_pair ?: 1) : 1;
            $gp = max(1, min(7, $gp));
            $winsNeeded = intdiv($gp, 2) + 1; // Bo1→1, Bo3→2, Bo5→3, Bo7→4

            // На всякий случай берём только матчи стадии type = 'playoff'
            $playoffMatches = [];
            foreach ($matches as $m) {
                if ($m->stage && $m->stage->type === 'playoff') {
                    $playoffMatches[] = $m;
                }
            }

            if (empty($playoffMatches)) {
                continue;
            }

            // Определяем максимальный round — это "Финал"
            $maxRound = 0;
            foreach ($playoffMatches as $m) {
                $round = (int) ($m->meta['round'] ?? 0);
                if ($round > $maxRound) {
                    $maxRound = $round;
                }
            }

            if ($maxRound <= 0) {
                continue;
            }

            // Матчи финального раунда (этап "Финал"), исключая матч за 3-е место
            $finalMatches = [];
            foreach ($playoffMatches as $m) {
                $round       = (int) ($m->meta['round'] ?? 0);
                $isThirdPlace = (bool) ($m->meta['third_place'] ?? false);

                if (
                    $round === $maxRound
                    && !$isThirdPlace
                    && $m->score_home !== null
                    && $m->score_away !== null
                ) {
                    $finalMatches[] = $m;
                }
            }

            if (empty($finalMatches)) {
                continue;
            }

            // ----- Выходы в финал -----
            foreach ($finalMatches as $m) {
                foreach ([$m->home_participant_id, $m->away_participant_id] as $pid) {
                    if (!$pid) {
                        continue;
                    }
                    $uid = $participantToUser[$pid] ?? null;
                    if ($uid && isset($stats[$uid])) {
                        $stats[$uid]['_final_tournaments'][$tournamentId] = true;
                    }
                }
            }

            // ----- Чемпион турнира (победитель завершённой финальной серии) -----
            $winsByParticipant = [];

            foreach ($finalMatches as $m) {
                if ($m->score_home === null || $m->score_away === null) {
                    continue;
                }
                if ((int) $m->score_home === (int) $m->score_away) {
                    // теоретически в финале не должно быть ничьих, но на всякий случай пропустим
                    continue;
                }

                $winnerPid = (int) (
                    (int) $m->score_home > (int) $m->score_away
                        ? $m->home_participant_id
                        : $m->away_participant_id
                );

                if ($winnerPid <= 0) {
                    continue;
                }

                $winsByParticipant[$winnerPid] = ($winsByParticipant[$winnerPid] ?? 0) + 1;
            }

            if (empty($winsByParticipant)) {
                continue;
            }

            $maxWins = max($winsByParticipant);

            // Если максимум побед меньше нужного количества — серия ещё не завершена → чемпиона НЕ считаем
            if ($maxWins < $winsNeeded) {
                continue;
            }

            // Чемпион — участник с максимальным количеством побед в финале
            $championPids = array_keys($winsByParticipant, $maxWins);
            $championPid  = (int) reset($championPids);
            $championUid  = $participantToUser[$championPid] ?? null;

            if ($championUid && isset($stats[$championUid])) {
                $stats[$championUid]['tournaments_won']++;
                // +10 рейтинговых очков за 1 место
                $stats[$championUid]['rating_points'] += 10;
            }
        }

        // Итог: количество турниров с плей-офф и финалами
        foreach ($stats as &$s) {
            // Выходы в плей-офф: турниры, где игрок играл в матчах стадии playoff (заполнено раньше)
            $s['playoff_appearances'] = !empty($s['_playoff_tournaments'])
                ? count($s['_playoff_tournaments'])
                : 0;

            // Выходы в финал: турниры, где игрок участвовал в матчах финального раунда
            $s['final_appearances'] = !empty($s['_final_tournaments'])
                ? count($s['_final_tournaments'])
                : 0;
        }
        unset($s);
    }


		    /**
     * Регулярка (group): только среднее место в группах.
     * Чемпионов здесь БОЛЬШЕ НЕ ОПРЕДЕЛЯЕМ.
     *
     * @param array<int,array<string,mixed>> $stats
     * @param array<int,int>                 $participantToUser
     * @param array<int,array>               $tournamentPlayoffMatches  (не используется, оставлен для совместимости сигнатуры)
     */
    protected function enrichRegularStageStats(array &$stats, array $participantToUser, array $tournamentPlayoffMatches): void
	{
        $groupStages = Stage::query()
            ->where('type', 'group')
            ->get();

        if ($groupStages->isEmpty()) {
            return;
        }

        $stageIds = $groupStages->pluck('id')->all();

        $standings = Standing::query()
            ->whereIn('stage_id', $stageIds)
            ->get()
            ->groupBy('stage_id');

        // Групповые стадии по турнирам
        $groupStagesByTournament = $groupStages->groupBy('tournament_id');

        foreach ($groupStagesByTournament as $tournamentId => $stagesOfTournament) {
            // Пишем позиции игроков в каждой группе, чтобы потом посчитать среднее
            foreach ($stagesOfTournament as $stage) {
                $rows = $standings[$stage->id] ?? null;
                if (!$rows || $rows->isEmpty()) {
                    continue;
                }

                // Сортировка как в PlayoffService::fetchSeeds
                $sorted = $rows->sort(function (Standing $a, Standing $b) {
                    if ($a->points === $b->points) {
                        if ($a->gd === $b->gd) {
                            if ($a->gf === $b->gf) {
                                return $a->participant_id <=> $b->participant_id;
                            }
                            return $b->gf <=> $a->gf;
                        }
                        return $b->gd <=> $a->gd;
                    }
                    return $b->points <=> $a->points;
                });

                $position = 0;
                foreach ($sorted as $row) {
                    $position++;
                    $pid = $row->participant_id;
                    $uid = $participantToUser[$pid] ?? null;
                    if (!$uid || !isset($stats[$uid])) {
                        continue;
                    }

                    $stats[$uid]['_regular_positions'][] = $position;
                }
            }

            // Чемпиона по регулярке больше НЕ назначаем:
            // tournaments_won считаются только по победителю финала плей-офф.
        }
    }

    /**
     * Расчёт текущей серии и лучшей серии побед.
     *
     * @param array<string,mixed> $s
     */
    protected function computeStreaks(array &$s): void
    {
        if (empty($s['results'])) {
            $s['current_streak_type'] = null;
            $s['current_streak_length'] = 0;
            $s['best_win_streak'] = 0;
            return;
        }

        // Сортируем по дате
        usort($s['results'], function (array $a, array $b) {
            /** @var \Carbon\Carbon $da */
            $da = $a['date'];
            /** @var \Carbon\Carbon $db */
            $db = $b['date'];
            if ($da->eq($db)) {
                return 0;
            }
            return $da->lt($db) ? -1 : 1;
        });

        $currentType = null; // 'win'|'loss'
        $currentLen  = 0;
        $bestWinStreak = 0;

        foreach ($s['results'] as $row) {
            $type = $row['won'] ? 'win' : 'loss';

            if ($currentType === $type) {
                $currentLen++;
            } else {
                $currentType = $type;
                $currentLen = 1;
            }

            if ($type === 'win' && $currentLen > $bestWinStreak) {
                $bestWinStreak = $currentLen;
            }
        }

        $s['current_streak_type'] = $currentType;
        $s['current_streak_length'] = $currentLen;
        $s['best_win_streak'] = $bestWinStreak;
    }
}
