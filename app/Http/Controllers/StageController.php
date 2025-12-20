<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\MatchModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\Standing;

class StageController extends Controller
{	
	private function ensureAdmin(): void
	{
		$user = auth()->user();
		if (!$user || !($user->is_admin ?? false)) {
			abort(403, 'Only admins can access this area.');
		}
	}
	
	public function generateRoundRobin(Stage $stage)
	{
    $this->ensureAdmin();

	$participantIds = TournamentParticipant::where('tournament_id', $stage->tournament_id)
		->where('is_active', true)
		->pluck('id')
		->values()
		->all();

	if (count($participantIds) < 2) {
		return back()->with('error', 'Недостаточно участников для генерации.');
	}

    $games = max(1, min(4, (int) ($stage->games_per_pair ?? 1))); // 1..4

    DB::transaction(function () use ($stage, $participantIds, $games) {
        $n = count($participantIds);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $homeId = $participantIds[$i];
                $awayId = $participantIds[$j];

                // максимальный game_no для пары в ОБЕИХ ориентациях
                $maxNo = (int) MatchModel::where('stage_id', $stage->id)
                    ->where(function ($q) use ($homeId, $awayId) {
                        $q->where(function ($qq) use ($homeId, $awayId) {
                            $qq->where('home_participant_id', $homeId)
                               ->where('away_participant_id', $awayId);
                        })->orWhere(function ($qq) use ($homeId, $awayId) {
                            $qq->where('home_participant_id', $awayId)
                               ->where('away_participant_id', $homeId);
                        });
                    })
                    ->max('game_no');

                // дозаполняем до нужного количества игр
                for ($no = $maxNo + 1; $no <= $games; $no++) {
                    // чётные игры — меняем хозяина и гостя
                    $h = ($no % 2 === 0) ? $awayId : $homeId;
                    $a = ($no % 2 === 0) ? $homeId : $awayId;

                    MatchModel::firstOrCreate(
                        [
                            'stage_id'            => $stage->id,
                            'home_participant_id' => $h,
                            'away_participant_id' => $a,
                            'game_no'             => $no,
                        ],
                        [
                            'status'              => 'scheduled',
                        ]
                    );
                }
            }
        }
    });

    return back()->with('ok', 'Расписание Round Robin сгенерировано.');
	}

	public function destroy(Stage $stage) {
    // сохраним турнир для редиректа
    $tournamentId = $stage->tournament_id;

    DB::transaction(function () use ($stage) {
        // 1) Соберём id матчей этой стадии
        $matchIds = DB::table('matches')
            ->where('stage_id', $stage->id)
            ->pluck('id');

        // 2) Если есть таблица репортов — удалим их
        if (Schema::hasTable('match_reports') && $matchIds->isNotEmpty()) {
            DB::table('match_reports')
                ->whereIn('match_id', $matchIds)
                ->delete();
        }

        // 3) Удалим матчи стадии
        if ($matchIds->isNotEmpty()) {
            DB::table('matches')
                ->whereIn('id', $matchIds)
                ->delete();
        }

		// --- CLEAN standings of this stage ---
		Standing::where('stage_id', $stage->id)->delete();
		
        // 5) Удалим саму стадию
        $stage->delete();
    });

    // Возвращаемся на страницу редактирования турнира
    return redirect()
        ->route('admin.tournaments.edit', $tournamentId)
        ->with('success', 'Стадия удалена.');
	}

	/**
 * Создать стадию турнира.
 * POST /admin/tournaments/{tournament}/stages
 */
	public function store(Request $request, Tournament $tournament) {
    $data = $request->validate([
        'type'           => ['required', 'in:group,playoff'],
        'name'           => ['required', 'string', 'max:255'],
        'games_per_pair' => ['required', 'integer', 'min:1', 'max:7'],
        // settings — опционально
    ]);

    DB::transaction(function () use ($request, $tournament, $data) {
        // Порядок — следующий после максимального для турнира
        $nextOrder = (int) Stage::where('tournament_id', $tournament->id)->max('order') + 1;

        // 1) создаём и СРАЗУ сохраняем стадию
        $stage                 = new Stage();
        $stage->tournament_id  = $tournament->id;
        $stage->name           = $data['name'];
        $stage->type           = $data['type'];           // 'group' | 'playoff'
        $stage->games_per_pair = (int) $data['games_per_pair'];
        $stage->order          = $nextOrder;

        // settings (оставляю твою текущую схему)
        $settings = $request->input('settings');
        if (is_array($settings)) {
            $stage->settings = json_encode($settings, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($settings) && $settings !== '') {
            $stage->settings = $settings;
        } else {
            $stage->settings = null;
        }

        // ВАЖНО: получить id
        $stage->save();

        // 2) только для групповой стадии — автосоздание standings
        if ($stage->type === 'group') {
            $participantIds = $tournament->participants()->pluck('id')->all(); // id из tournament_participants

            foreach ($participantIds as $pid) {
                Standing::updateOrCreate(
                    ['stage_id' => $stage->id, 'participant_id' => $pid],
                    [
                        'gp' => 0, 'w' => 0, 'otw' => 0, 'sow' => 0,
                        'otl' => 0, 'sol' => 0, 'l' => 0,
                        'gf' => 0, 'ga' => 0, 'gd' => 0,
                        'points' => 0, 'tech_losses' => 0,
                    ]
                );
            }
        }
    });

    return redirect()
        ->route('admin.tournaments.edit', $tournament)
        ->with('success', 'Стадия создана.');
}

    private function roundRobinPairs(array $ids): array {
        $ids = array_values($ids);
        $bye = null;
        if (count($ids) % 2 === 1) { $ids[] = $bye; }
        $n = count($ids);
        $half = $n / 2;
        $rounds = [];
        for ($r=0; $r<$n-1; $r++) {
            $round = [];
            for ($i=0; $i<$half; $i++) {
                $a = $ids[$i];
                $b = $ids[$n-1-$i];
                if ($a !== null && $b !== null) {
                    // чередуем домашние/гостевые
                    $round[] = ($r % 2 === 0) ? [$a,$b] : [$b,$a];
                }
            }
            $rounds[] = $round;
            // ротация (кроме первого элемента)
            $fixed = array_shift($ids);
            $last = array_pop($ids);
            array_unshift($ids, $fixed);
            array_splice($ids, 1, 0, [$last]);
        }
        return $rounds;
    }
}
