<?php

namespace App\Jobs;

use App\Models\MatchModel;
use App\Models\Standing;
use App\Models\Stage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateStandings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $stageId;

    public function __construct(int $stageId)
    {
        $this->stageId = $stageId;
    }

    public function handle(): void
    {
        $stage = Stage::with('tournament')->findOrFail($this->stageId);
        $pointsCfg = config('tournament.points');

        // Берём все подтверждённые матчи стадии
        $matches = MatchModel::where('stage_id', $stage->id)
            ->where('status', 'confirmed')
            ->get();

        $stats = []; // participant_id => stat array

        $touch = function () {
            return [
                'gp'          => 0,
                'w'           => 0,  // победы в основное
                'otw'         => 0,  // победы в ОТ
                'sow'         => 0,  // победы по буллитам
                'otl'         => 0,  // поражения в ОТ
                'sol'         => 0,  // поражения по буллитам
                'l'           => 0,  // поражения в основное
                'gf'          => 0,
                'ga'          => 0,
                'gd'          => 0,
                'points'      => 0,
                'tech_losses' => 0,
            ];
        };

        foreach ($matches as $m) {
            $home = $m->home_participant_id;
            $away = $m->away_participant_id;

            if (!isset($stats[$home])) $stats[$home] = $touch();
            if (!isset($stats[$away])) $stats[$away] = $touch();

            // сыграно матчей
            $stats[$home]['gp']++;
            $stats[$away]['gp']++;

            // голы
            $stats[$home]['gf'] += (int)$m->score_home;
            $stats[$home]['ga'] += (int)$m->score_away;
            $stats[$away]['gf'] += (int)$m->score_away;
            $stats[$away]['ga'] += (int)$m->score_home;

            if ($m->score_home === $m->score_away) {
                // ничьих быть не должно, на всякий случай пропускаем
                continue;
            }

            $homeWin = $m->score_home > $m->score_away;
            $isOT    = (bool)$m->ot; // овертайм
            $isSO    = (bool)$m->so; // буллиты
            $extra   = $isOT || $isSO;

            if ($homeWin) {
                // победитель — home
                if ($isSO) {
                    $stats[$home]['sow']++;
                    $stats[$away]['sol']++;
                } elseif ($isOT) {
                    $stats[$home]['otw']++;
                    $stats[$away]['otl']++;
                } else {
                    $stats[$home]['w']++;
                    $stats[$away]['l']++;
                }

                // очки
                $stats[$home]['points'] += $pointsCfg['win'];
                $stats[$away]['points'] += $extra ? $pointsCfg['otl'] : $pointsCfg['loss'];
            } else {
                // победитель — away
                if ($isSO) {
                    $stats[$away]['sow']++;
                    $stats[$home]['sol']++;
                } elseif ($isOT) {
                    $stats[$away]['otw']++;
                    $stats[$home]['otl']++;
                } else {
                    $stats[$away]['w']++;
                    $stats[$home]['l']++;
                }

                // очки
                $stats[$away]['points'] += $pointsCfg['win'];
                $stats[$home]['points'] += $extra ? $pointsCfg['otl'] : $pointsCfg['loss'];
            }
        }

        // разница шайб
        foreach ($stats as $pid => &$s) {
            $s['gd'] = $s['gf'] - $s['ga'];
        }
        unset($s);

        // сохраняем в БД
        foreach ($stats as $pid => $s) {
            Standing::updateOrCreate(
                ['stage_id' => $stage->id, 'participant_id' => $pid],
                $s
            );
        }
    }
}
