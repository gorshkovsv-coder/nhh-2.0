<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Stage;
use App\Models\MatchModel;
use Illuminate\Support\Facades\DB;

final class PlayoffService
{
    /**
     * Создать (пересоздать) первый раунд плей-офф.
     *
     * @param Tournament $tournament
     * @param int $sourceStageId  ID групповой стадии-источника
     * @param int $size           4|8|16
     * @param int $lossesToElim   1..4 (используем как "best-of": 1→Bo1, 2→Bo3, 3→Bo5, 4→Bo7)
     * @param bool $thirdPlace    создавать матч за 3-е
     * @param int|null $gamesPerPair  если null — берём из стадии, а если там пусто, то считаем как 2*L-1
     */
    public function generateFirstRound(
        Tournament $tournament,
        int $sourceStageId,
        int $size,
        int $lossesToElim,
        bool $thirdPlace,
        ?int $gamesPerPair = null
    ): void {
        $size = in_array($size, [4, 8, 16], true) ? $size : 8;
        $lossesToElim = max(1, min(4, $lossesToElim));

        /** @var Stage $source */
        $source = Stage::query()
            ->where('id', $sourceStageId)
            ->where('type', 'group')
            ->firstOrFail();

        // найдём/создадим плей-офф стадию
        $playoff = Stage::query()
            ->where('tournament_id', $tournament->id)
            ->where('type', 'playoff')
            ->orderBy('order')
            ->first();

        if (!$playoff) {
            $playoff = new Stage();
            $playoff->tournament_id = $tournament->id;
            $playoff->name = 'Плей-офф';
            $playoff->type = 'playoff';
            $playoff->order = ($source->order ?? 1) + 1;
        }

        // рассчитать games_per_pair
		$computed = 2 * $lossesToElim - 1;           // 1→1, 2→3, 3→5, 4→7
		$stageVal = (int) ($playoff->games_per_pair ?: 0);
		$gp = $gamesPerPair ?? $computed;            // <— приоритет: явно переданное → из "До поражений"
		if (!$gp) {                                  // совсем запасной сценарий
			$gp = $stageVal ?: 1;
		}
		$gp = max(1, min(7, (int) $gp));
		$playoff->games_per_pair = $gp;
		

        // сохранить настройки
        $settings = (array) ($playoff->settings ?? []);
        $settings['source_stage_id']     = $source->id;
        $settings['size']                = $size;
        $settings['losses_to_eliminate'] = $lossesToElim;
        $settings['third_place']         = (bool) $thirdPlace;
        $playoff->settings = $settings;
        $playoff->save();

        // посев — первые $size по таблице группы
        $seeds = $this->fetchSeeds($source, $size);
        $pairs = $this->pairBySeed($seeds); // [[hi, lo], ...]

        // очистить матчи стадии и создать первый раунд (round=1)
        DB::transaction(function () use ($playoff, $pairs, $gp) {
            MatchModel::where('stage_id', $playoff->id)->delete();

            $slot = 1;
            foreach ($pairs as [$hi, $lo]) {
                for ($g = 1; $g <= $gp; $g++) {
                    MatchModel::create([
                        'stage_id'            => $playoff->id,
                        'home_participant_id' => $hi,
                        'away_participant_id' => $lo,
                        'game_no'             => $g,
                        'status'              => 'scheduled',
                        'meta'                => ['round' => 1, 'slot' => $slot],
                    ]);
                }
                $slot++;
            }
        });
    }

    /**
     * Автогенерация следующего раунда, когда текущий завершён.
     * Вызывается из MatchObserver::saved($match).
     */
    public function tryAdvance(Stage $stage): void
    {
        if ($stage->type !== 'playoff') return;

        $settings      = (array) ($stage->settings ?? []);
        $gp            = (int) ($stage->games_per_pair ?? 1);
        $gp            = max(1, min(7, $gp));
        $thirdPlace    = (bool) ($settings['third_place'] ?? false);
        $sourceStageId = (int) ($settings['source_stage_id'] ?? 0);

        // все матчи стадии
        $all = MatchModel::where('stage_id', $stage->id)->get();
        if ($all->isEmpty()) return;

        // текущий раунд
        $currentRound = 1;
        foreach ($all as $m) {
            $r = (int) ($m->meta['round'] ?? 1);
            if ($r > $currentRound) $currentRound = $r;
        }

        // собрать серии текущего раунда
        $pairs = []; // key => ['a','b','wins'=>[id=>cnt],'open'=>bool]
        foreach ($all as $m) {
            if ((int) ($m->meta['round'] ?? 1) !== $currentRound) continue;
            if ($m->status === 'canceled') continue; // игнорируем отменённые

            $a = min($m->home_participant_id, $m->away_participant_id);
            $b = max($m->home_participant_id, $m->away_participant_id);
            $key = $a . '-' . $b;

            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'a' => $a,
                    'b' => $b,
                    'wins' => [$a => 0, $b => 0],
                    'open' => false,
                ];
            }

            if (in_array($m->status, ['scheduled','reported','pending','created','proposed'], true)) {
                $pairs[$key]['open'] = true;
            }

            if ($m->status === 'confirmed' && $m->score_home !== null && $m->score_away !== null) {
                $winner = $m->score_home > $m->score_away
                    ? $m->home_participant_id
                    : ($m->score_home < $m->score_away ? $m->away_participant_id : null);
                if ($winner) {
                    $pairs[$key]['wins'][$winner] = ($pairs[$key]['wins'][$winner] ?? 0) + 1;
                }
            }
        }

        $wait    = false;
        $winners = [];
        $losers  = [];

        foreach ($pairs as $p) {
            $wa = $p['wins'][$p['a']] ?? 0;
            $wb = $p['wins'][$p['b']] ?? 0;

            // завершение серии по большинству побед
            $need = intdiv($gp, 2) + 1; // Bo1→1, Bo3→2, Bo5→3, Bo7→4

            if (max($wa, $wb) >= $need) {
                $winner = $wa > $wb ? $p['a'] : $p['b'];
                $loser  = $wa > $wb ? $p['b'] : $p['a'];
                $winners[] = $winner;
                $losers[]  = $loser;
            } else {
                $wait = true; // ещё не все серии решили победителя
            }
        }

        if ($wait || count($winners) < 2) return; // рано создавать следующий раунд

        // Ресеединг победителей по исходной группе
        $seedMap = $this->seedMapFromGroup($sourceStageId);
        usort($winners, fn ($x, $y) =>
            ($seedMap[$x] ?? PHP_INT_MAX) <=> ($seedMap[$y] ?? PHP_INT_MAX)
        );

        $nextRound = $currentRound + 1;

        // уже есть следующий раунд? — не плодим дубли
        $exists = MatchModel::where('stage_id', $stage->id)
            ->whereNotNull('meta')
            ->get()
            ->contains(fn ($m) => (int) ($m->meta['round'] ?? 0) === $nextRound);
        if ($exists) return;

        // пары следующего раунда: hi vs lo
        $expectedPairs = [];
        $N = count($winners);
        for ($i = 0; $i < intdiv($N, 2); $i++) {
            $expectedPairs[] = [$winners[$i], $winners[$N - 1 - $i]];
        }

        DB::transaction(function () use ($stage, $gp, $nextRound, $expectedPairs, $thirdPlace, $losers) {
            $slot = 1;
            foreach ($expectedPairs as [$hi, $lo]) {
                for ($g = 1; $g <= $gp; $g++) {
                    MatchModel::create([
                        'stage_id'            => $stage->id,
                        'home_participant_id' => $hi,
                        'away_participant_id' => $lo,
                        'game_no'             => $g,
                        'status'              => 'scheduled',
                        'meta'                => ['round' => $nextRound, 'slot' => $slot],
                    ]);
                }
                $slot++;
            }

            // Матч за 3-е место — появляется после полуфиналов (когда создали ФИНАЛ = одна пара)
            if ($thirdPlace && count($expectedPairs) === 1 && count($losers) >= 2) {
                $a = $losers[0];
                $b = $losers[1];

                $has = MatchModel::where('stage_id', $stage->id)
                    ->whereNotNull('meta')
                    ->get()
                    ->contains(fn ($m) => (bool) ($m->meta['third_place'] ?? false));
                if (!$has) {
                    for ($g = 1; $g <= $gp; $g++) {
                        MatchModel::create([
                            'stage_id'            => $stage->id,
                            'home_participant_id' => $a,
                            'away_participant_id' => $b,
                            'game_no'             => $g,
                            'status'              => 'scheduled',
                            'meta'                => ['third_place' => true, 'round_label' => 'Матч за 3-е место'],
                        ]);
                    }
                }
            }
        });
    }

    /** @return array<int,array{participant_id:int,seed:int}> */
    private function fetchSeeds(Stage $groupStage, int $take): array
    {
        $rows = DB::table('standings')
            ->select('participant_id', 'points', 'gd', 'gf')
            ->where('stage_id', $groupStage->id)
            ->orderByDesc('points')->orderByDesc('gd')->orderByDesc('gf')
            ->orderBy('participant_id')
            ->limit($take)
            ->get();

        $i = 1;
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['participant_id' => (int) $r->participant_id, 'seed' => $i++];
        }
        return $out;
    }

    /** @return array<int,int> participant_id => seed */
    private function seedMapFromGroup(int $groupStageId): array
    {
        if (!$groupStageId) return [];
        $rows = DB::table('standings')
            ->select('participant_id')
            ->where('stage_id', $groupStageId)
            ->orderByDesc('points')->orderByDesc('gd')->orderByDesc('gf')
            ->orderBy('participant_id')
            ->get();

        $i = 1; $map = [];
        foreach ($rows as $r) $map[(int)$r->participant_id] = $i++;
        return $map;
    }

    /** Пары: 1–N, 2–N-1, ... (по seeding) */
    private function pairBySeed(array $seeds): array
    {
        $N = count($seeds);
        $pairs = [];
        for ($i = 0; $i < intdiv($N, 2); $i++) {
            $pairs[] = [$seeds[$i]['participant_id'], $seeds[$N - 1 - $i]['participant_id']];
        }
        return $pairs;
    }
}
