<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\TournamentParticipant;
use Inertia\Inertia;

class MyMatchesController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $participantIds = TournamentParticipant::where('user_id', $userId)->pluck('id')->toArray();

		$matches = MatchModel::with([
        'stage.tournament',
        'home.user',
        'home.nhlTeam',   // <-- добавили
        'away.user',
        'away.nhlTeam',   // <-- добавили
        'reports' => function ($q) {
            $q->latest();
        },
			])
			->whereIn('home_participant_id', $participantIds)
			->orWhereIn('away_participant_id', $participantIds)
			->orderByRaw('COALESCE(scheduled_at, created_at) DESC')
			->get();
		
		return Inertia::render('Match/MyMatches', [
            'matches' => $matches,
            'participant_ids' => $participantIds,
        ]);
    }
}
