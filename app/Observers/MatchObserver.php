<?php

namespace App\Observers;

use App\Models\MatchModel;
use App\Services\PlayoffService;

class MatchObserver
{
    public function saved(MatchModel $match): void
    {
        $stage = $match->stage()->first();
        if (!$stage || $stage->type !== 'playoff') return;

        // Автопродвижение: при любом изменении матча в плей-офф
        app(PlayoffService::class)->tryAdvance($stage);
    }
}
