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

        // Сортировка по очкам рейтинга, затем по Win%, затем по количеству матчей
        uasort($stats, function (array $a, array $b) {
            if ($a['rating_points'] === $b['rating_points']) {
                if ($a['win_rate'] === $b['win_rate']) {
                    return $b['matches_played'] <=> $a['matches_played'];
                }
                return $b['win_rate'] <=> $a['win_rate'];
            }
            return $b['rating_points'] <=> $a['rating_points'];
        });

        // Присвоение позиций (рангов)
        $position = 0;
        $lastPoints = null;
        $lastRank = 0;

        foreach ($stats as &$s) {
            $position++;
            if ($lastPoints === null || $s['rating_points'] < $lastPoints) {
                $lastRank = $position;
                $lastPoints = $s['rating_points'];
            }
            $s['rank'] = $lastRank;

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
     * Плей-офф: чемпионы, финалисты, выходы в плей-офф.
     *
     * @param array<int,array<string,mixed>> $stats
     * @param array<int,array<int,MatchModel>> $tournamentPlayoffMatches
     * @param array<int,int> $participantToUser
     */
    protected function enrichTournamentStatsFromPlayoffs(array &$stats, array $tournamentPlayoffMatches, array $participantToUser): void
    {
        foreach ($tournamentPlayoffMatches as $tournamentId => $matches) {
            if (empty($matches)) {
                continue;
            }

            // Определяем максимальный раунд (финал)
            $maxRound = 0;
            foreach ($matches as $m) {
                if (!is_array($m->meta)) {
                    continue;
                }
                $round = (int) ($m->meta['round'] ?? 0);
                if ($round > $maxRound) {
                    $maxRound = $round;
                }
            }

            if ($maxRound <= 0) {
                continue;
            }

            // Матчи финального раунда (без матча за 3-е место)
            $finalSeriesMatches = [];
            foreach ($matches as $m) {
                if (!is_array($m->meta)) {
                    continue;
                }
                $round = (int) ($m->meta['round'] ?? 0);
                $isThirdPlace = (bool) ($m->meta['third_place'] ?? false);
                if ($round === $maxRound && !$isThirdPlace) {
                    $finalSeriesMatches[] = $m;
                }
            }

            if (empty($finalSeriesMatches)) {
                continue;
            }

            // Считаем победителя серии финала
            $series = []; // key = "pidA-pidB" => ['a'=>, 'b'=>, 'wins'=>[pid=>count]]

            foreach ($finalSeriesMatches as $m) {
                $homePid = $m->home_participant_id;
                $awayPid = $m->away_participant_id;
                if (!$homePid || !$awayPid) {
                    continue;
                }

                $keyA = min($homePid, $awayPid);
                $keyB = max($homePid, $awayPid);
                $key = $keyA . '-' . $keyB;

                if (!isset($series[$key])) {
                    $series[$key] = [
                        'a'    => $keyA,
                        'b'    => $keyB,
                        'wins' => [$keyA => 0, $keyB => 0],
                    ];
                }

                if ($m->score_home === null || $m->score_away === null) {
                    continue;
                }

                if ($m->score_home === $m->score_away) {
                    continue;
                }

                $winnerPid = $m->score_home > $m->score_away ? $homePid : $awayPid;
                $series[$key]['wins'][$winnerPid] = ($series[$key]['wins'][$winnerPid] ?? 0) + 1;
            }

            if (empty($series)) {
                continue;
            }

            // Предполагаем один финал — берём первую серию
            $finalSeries = reset($series);
            $pidA = $finalSeries['a'];
            $pidB = $finalSeries['b'];
            $winsA = $finalSeries['wins'][$pidA] ?? 0;
            $winsB = $finalSeries['wins'][$pidB] ?? 0;

            // Чемпион турнира
            if ($winsA > 0 || $winsB > 0) {
                $championPid = $winsA >= $winsB ? $pidA : $pidB;
                $champUserId = $participantToUser[$championPid] ?? null;
                if ($champUserId && isset($stats[$champUserId])) {
                    $stats[$champUserId]['tournaments_won']++;
                    // +10 за 1 место
                    $stats[$champUserId]['rating_points'] += 10;
                }
            }

            // Финалисты — оба участника финальной серии
            foreach ([$pidA, $pidB] as $pid) {
                $uid = $participantToUser[$pid] ?? null;
                if ($uid && isset($stats[$uid])) {
                    $stats[$uid]['_final_tournaments'][$tournamentId] = true;
                }
            }
        }

        // Кол-во турниров с плей-офф и финалами
        foreach ($stats as &$s) {
            $s['playoff_appearances'] = !empty($s['_playoff_tournaments'])
                ? count($s['_playoff_tournaments'])
                : 0;

            $s['final_appearances'] = !empty($s['_final_tournaments'])
                ? count($s['_final_tournaments'])
                : 0;
        }
        unset($s);
    }

    /**
     * Регулярка (group): средние места и чемпионы без плей-офф.
     *
     * @param array<int,array<string,mixed>> $stats
     * @param array<int,int> $participantToUser
     * @param array<int,array<int,MatchModel>> $tournamentPlayoffMatches
     */
    protected function enrichRegularStageStats(array &$stats, array $participantToUser, array $tournamentPlayoffMatches): void
    {
        $tournamentsWithPlayoff = array_keys($tournamentPlayoffMatches);

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
            // Считаем места игроков в каждой групповой стадии
            foreach ($stagesOfTournament as $stage) {
                $rows = $standings[$stage->id] ?? null;
                if (!$rows || $rows->isEmpty()) {
                    continue;
                }

                // Сортировка как в PlayoffService::fetchSeeds:
                // points desc, gd desc, gf desc, participant_id asc
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

            // Если у турнира НЕТ плей-офф — чемпион определяется по последней по order групповой стадии
            if (!in_array($tournamentId, $tournamentsWithPlayoff, true)) {
                $stage = $stagesOfTournament->sortByDesc('order')->first();
                if (!$stage) {
                    continue;
                }

                $rows = $standings[$stage->id] ?? null;
                if (!$rows || $rows->isEmpty()) {
                    continue;
                }

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

                $winnerRow = $sorted->first();
                if ($winnerRow) {
                    $champPid = $winnerRow->participant_id;
                    $champUid = $participantToUser[$champPid] ?? null;
                    if ($champUid && isset($stats[$champUid])) {
                        $stats[$champUid]['tournaments_won']++;
                        $stats[$champUid]['rating_points'] += 10;
                    }
                }
            }
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
