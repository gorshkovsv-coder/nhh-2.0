<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\TournamentParticipant;
use Inertia\Inertia;

class MatchViewController extends Controller
{
    public function show(MatchModel $match)
    {
        $match->load([
            'stage.tournament',
            'home.user',
            'home.nhlTeam',   // добавили подгрузку команды хозяев
            'away.user',
            'away.nhlTeam',   // и гостей
            'reports.reporter.user',
            'reports.confirmer.user',
        ]);

        $userId = auth()->id();
        $myParticipant = null;

        if ($userId) {
            $myParticipant = TournamentParticipant::where('user_id', $userId)
                ->whereIn('id', [$match->home_participant_id, $match->away_participant_id])
                ->first();
        }

        return Inertia::render('Match/Show', [
            'match'         => $match,
            'myParticipant' => $myParticipant,
        ]);
    }
}
