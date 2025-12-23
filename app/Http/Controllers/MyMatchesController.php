<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\TournamentParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MyMatchesController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $participantIds = TournamentParticipant::where('user_id', $userId)->pluck('id')->toArray();

        $filters = [
            'tournament_id' => $request->integer('tournament_id') ?: null,
            'opponent_team_id' => $request->integer('opponent_team_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'awaiting' => $request->boolean('awaiting'),
            'not_played' => $request->boolean('not_played'),
        ];

        if ($filters['status'] === 'all') {
            $filters['status'] = null;
        }

        $baseQuery = MatchModel::query()
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('home_participant_id', $participantIds)
                    ->orWhereIn('away_participant_id', $participantIds);
            });

        $tournaments = DB::table('matches')
            ->join('stages', 'matches.stage_id', '=', 'stages.id')
            ->join('tournaments', 'stages.tournament_id', '=', 'tournaments.id')
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('matches.home_participant_id', $participantIds)
                    ->orWhereIn('matches.away_participant_id', $participantIds);
            })
            ->select('tournaments.id', 'tournaments.title')
            ->distinct()
            ->orderBy('tournaments.title')
            ->get();

        $opponentsHome = DB::table('matches')
            ->join('tournament_participants as home', 'matches.home_participant_id', '=', 'home.id')
            ->join('tournament_participants as away', 'matches.away_participant_id', '=', 'away.id')
            ->join('nhl_teams as away_team', 'away.nhl_team_id', '=', 'away_team.id')
            ->whereIn('matches.home_participant_id', $participantIds)
            ->select('away_team.id', 'away_team.name');

        $opponentsAway = DB::table('matches')
            ->join('tournament_participants as home', 'matches.home_participant_id', '=', 'home.id')
            ->join('tournament_participants as away', 'matches.away_participant_id', '=', 'away.id')
            ->join('nhl_teams as home_team', 'home.nhl_team_id', '=', 'home_team.id')
            ->whereIn('matches.away_participant_id', $participantIds)
            ->select('home_team.id', 'home_team.name');

        $opponents = $opponentsHome
            ->union($opponentsAway)
            ->distinct()
            ->orderBy('name')
            ->get();

        $statuses = (clone $baseQuery)
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->values();

        $matchesQuery = MatchModel::with([
            'stage.tournament',
            'home.user',
            'home.nhlTeam',
            'away.user',
            'away.nhlTeam',
            'reports' => function ($q) {
                $q->latest();
            },
        ])->where(function ($q) use ($participantIds) {
            $q->whereIn('home_participant_id', $participantIds)
                ->orWhereIn('away_participant_id', $participantIds);
        });

        if ($filters['tournament_id']) {
            $matchesQuery->whereHas('stage.tournament', function ($q) use ($filters) {
                $q->where('id', $filters['tournament_id']);
            });
        }

        if ($filters['opponent_team_id']) {
            $matchesQuery->where(function ($q) use ($participantIds, $filters) {
                $q->where(function ($q2) use ($participantIds, $filters) {
                    $q2->whereIn('home_participant_id', $participantIds)
                        ->whereHas('away.nhlTeam', function ($q3) use ($filters) {
                            $q3->where('id', $filters['opponent_team_id']);
                        });
                })->orWhere(function ($q2) use ($participantIds, $filters) {
                    $q2->whereIn('away_participant_id', $participantIds)
                        ->whereHas('home.nhlTeam', function ($q3) use ($filters) {
                            $q3->where('id', $filters['opponent_team_id']);
                        });
                });
            });
        }

        if ($filters['not_played']) {
            $matchesQuery->where('status', 'scheduled');
        } elseif ($filters['awaiting']) {
            $matchesQuery->whereExists(function ($q) use ($participantIds) {
                $q->select(DB::raw(1))
                    ->from('match_reports as mr')
                    ->whereColumn('mr.match_id', 'matches.id')
                    ->where('mr.status', 'pending')
                    ->whereNotIn('mr.reporter_participant_id', $participantIds)
                    ->whereRaw('mr.id = (select id from match_reports mr2 where mr2.match_id = matches.id order by mr2.created_at desc limit 1)');
            });
        } elseif ($filters['status']) {
            $matchesQuery->where('status', $filters['status']);
        }

        $matches = $matchesQuery
            ->orderByRaw('COALESCE(scheduled_at, created_at) DESC')
            ->paginate(20)
            ->withQueryString();
		
		return Inertia::render('Match/MyMatches', [
            'matches' => $matches,
            'participant_ids' => $participantIds,
            'tournaments' => $tournaments,
            'opponents' => $opponents,
            'statuses' => $statuses,
            'filters' => $filters,
        ]);
    }
}
