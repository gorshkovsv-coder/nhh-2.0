<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Stage;
use App\Services\PlayoffService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class PlayoffController extends Controller
{
    public function generate(Request $request, Tournament $tournament, PlayoffService $service): RedirectResponse
    {
        $data = $request->validate([
            'source_stage_id'      => ['required', 'integer', 'exists:stages,id'],
            'size'                 => ['required', 'integer', 'in:4,8,16'],
            'losses_to_eliminate'  => ['required', 'integer', 'min:1', 'max:4'],
            'third_place'          => ['required', 'boolean'],
            'games_per_pair'       => ['nullable', 'integer', 'min:1', 'max:7'],
        ]);

        $src = Stage::findOrFail($data['source_stage_id']);
        if ($src->type !== 'group') {
            return back()->with('error', 'Источник должен быть стадией типа "Группа".');
        }

        $service->generateFirstRound(
            $tournament,
            (int) $data['source_stage_id'],
            (int) $data['size'],
            (int) $data['losses_to_eliminate'],
            (bool) $data['third_place'],
            $data['games_per_pair'] ?? null
        );

        return back()->with('success', 'Плей-офф сформирован');
    }
}
