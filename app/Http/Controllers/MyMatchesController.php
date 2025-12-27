<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\NhlTeam;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyMatchesController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $participantIds = TournamentParticipant::where('user_id', $userId)
            ->pluck('id');

        // Базовый запрос: все матчи игрока (без фильтров)
        $baseQuery = MatchModel::query()
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('home_participant_id', $participantIds)
                    ->orWhereIn('away_participant_id', $participantIds);
            });

        $overallTotal = (clone $baseQuery)->count();

        // ===== Фильтры из query string =====
        $filters = [
            'tournament_id'     => $request->input('tournament_id') ?: '',
            'opponent_team_id'  => $request->input('opponent_team_id') ?: '',
            'status'            => $request->input('status') ?: '',
            'awaiting'          => $request->boolean('awaiting'),
            'not_played'        => $request->boolean('not_played'),
        ];

        // Если включён быстрый фильтр — он приоритетнее статуса
        if ($filters['awaiting'] || $filters['not_played']) {
            $filters['status'] = '';
        }

        // ===== Основной запрос матчей (фильтрация ДО пагинации) =====
        $query = MatchModel::with([
            'stage.tournament',
            'home.user',
            'home.nhlTeam',
            'away.user',
            'away.nhlTeam',
            'reports' => function ($q) {
                $q->latest();
            },
        ])
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('home_participant_id', $participantIds)
                    ->orWhereIn('away_participant_id', $participantIds);
            });

        // Турнир
        if ($filters['tournament_id'] !== '') {
            $tid = (int) $filters['tournament_id'];
            $query->whereHas('stage', function ($q) use ($tid) {
                $q->where('tournament_id', $tid);
            });
        }

        // Команда соперника
        if ($filters['opponent_team_id'] !== '') {
            $teamId = (int) $filters['opponent_team_id'];
            $query->where(function ($q) use ($participantIds, $teamId) {
                // Я дома — команда соперника в away
                $q->where(function ($q2) use ($participantIds, $teamId) {
                    $q2->whereIn('home_participant_id', $participantIds)
                        ->whereHas('away', function ($qq) use ($teamId) {
                            $qq->where('nhl_team_id', $teamId);
                        });
                })
                // Я в гостях — команда соперника в home
                ->orWhere(function ($q2) use ($participantIds, $teamId) {
                    $q2->whereIn('away_participant_id', $participantIds)
                        ->whereHas('home', function ($qq) use ($teamId) {
                            $qq->where('nhl_team_id', $teamId);
                        });
                });
            });
        }

        // Быстрые фильтры
        if ($filters['awaiting']) {
            // "Ждёт моего подтверждения": есть pending-репорт, отправленный НЕ мной
            $query->where('status', 'reported')
                ->whereHas('reports', function ($q) use ($participantIds) {
                    $q->where('status', 'pending')
                        ->whereNotIn('reporter_participant_id', $participantIds);
                });
        } elseif ($filters['not_played']) {
            // "С кем ещё не сыграл": матч запланирован
            $query->where('status', 'scheduled');
        } elseif ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $matches = $query
            ->orderByRaw('COALESCE(scheduled_at, created_at) DESC')
            ->paginate(20)
            ->withQueryString();

        // ===== Справочники для фильтров (НЕ зависят от пагинации) =====
        // Список турниров — по всем матчам игрока
        $tournaments = Tournament::query()
            ->select('tournaments.id', 'tournaments.title')
            ->join('stages', 'stages.tournament_id', '=', 'tournaments.id')
            ->join('matches', 'matches.stage_id', '=', 'stages.id')
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('matches.home_participant_id', $participantIds)
                    ->orWhereIn('matches.away_participant_id', $participantIds);
            })
            ->distinct()
            ->orderBy('tournaments.title')
            ->get();

        // Список команд соперников.
        // Чтобы было удобнее, если выбран турнир — ограничиваем список соперников этим турниром.
        $baseForOpponents = MatchModel::query()
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('home_participant_id', $participantIds)
                    ->orWhereIn('away_participant_id', $participantIds);
            });

        if ($filters['tournament_id'] !== '') {
            $tid = (int) $filters['tournament_id'];
            $baseForOpponents->whereHas('stage', function ($q) use ($tid) {
                $q->where('tournament_id', $tid);
            });
        }

        $oppTeamIdsHome = (clone $baseForOpponents)
            ->whereIn('matches.home_participant_id', $participantIds)
            ->join('tournament_participants as tp_away', 'tp_away.id', '=', 'matches.away_participant_id')
            ->whereNotNull('tp_away.nhl_team_id')
            ->distinct()
            ->pluck('tp_away.nhl_team_id');

        $oppTeamIdsAway = (clone $baseForOpponents)
            ->whereIn('matches.away_participant_id', $participantIds)
            ->join('tournament_participants as tp_home', 'tp_home.id', '=', 'matches.home_participant_id')
            ->whereNotNull('tp_home.nhl_team_id')
            ->distinct()
            ->pluck('tp_home.nhl_team_id');

        $opponentTeamIds = $oppTeamIdsHome
            ->merge($oppTeamIdsAway)
            ->filter()
            ->unique()
            ->values();

        $opponents = NhlTeam::query()
            ->whereIn('id', $opponentTeamIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Справочник статусов (для выпадающего списка)
        $statusOptions = [
            ['value' => 'scheduled', 'label' => 'Запланирован'],
            ['value' => 'reported',  'label' => 'Ожидает подтверждения'],
            ['value' => 'confirmed', 'label' => 'Подтверждён'],
            ['value' => 'canceled',  'label' => 'Отменён'],
            ['value' => 'disputed',  'label' => 'Спор'],
        ];

        return Inertia::render('Match/MyMatches', [
            'matches'         => $matches,
            'participant_ids' => $participantIds->values(),
            'overall_total'   => $overallTotal,
            'filters'         => $filters,
            'filterOptions'   => [
                'tournaments' => $tournaments,
                'opponents'   => $opponents,
            ],
            'statuses'        => $statusOptions,
        ]);
    }
}
