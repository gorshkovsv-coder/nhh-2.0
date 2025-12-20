<?php

namespace App\Http\Controllers;

use App\Services\PlayerStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerRatingController extends Controller
{
    /**
     * Глобальный рейтинг игроков.
     */
    public function index(Request $request, PlayerStatsService $service): Response
    {
        $players = $service->buildGlobalStats();

        return Inertia::render('Player/RatingIndex', [
            'players' => $players,
        ]);
    }
}
