<?php

namespace App\Console\Commands;

use App\Models\MatchModel;
use App\Models\MatchReport;
use App\Jobs\RecalculateStandings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoConfirmMatches extends Command
{
    /**
     * Команда: php artisan matches:auto-confirm
     */
    protected $signature = 'matches:auto-confirm';

    protected $description = 'Автоматически подтверждать матчи, ожидающие подтверждения более 24 часов';

    public function handle(): int
    {
        // Порог: 24 часа назад
        $threshold = now()->subHours(24);

        // Находим pending-репорты, старше 24 часов, по матчам в статусе reported
        $reports = MatchReport::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $threshold)
            ->whereHas('match', function ($q) {
                $q->where('status', 'reported');
            })
            ->with('match')
            ->get();

        if ($reports->isEmpty()) {
            $this->info('Нет репортов для автоподтверждения.');
            return self::SUCCESS;
        }

        $this->info('Найдено репортов для автоподтверждения: ' . $reports->count());

        $processed = 0;

        foreach ($reports as $report) {
            $match = $report->match;

            if (!$match || $match->status !== 'reported') {
                continue;
            }

            DB::transaction(function () use ($report, $match, &$processed) {
                // Ещё раз перечитываем на всякий случай
                $report->refresh();
                $match->refresh();

                if ($report->status !== 'pending' || $match->status !== 'reported') {
                    return;
                }

                // 1) Помечаем репорт как подтверждённый
                $report->update([
                    'status' => 'confirmed',
                ]);

                // 2) Обновляем матч: переносим счёт и ставим флаг auto_confirmed в meta
                $meta = $match->meta ?? [];
                $meta['auto_confirmed'] = true;
                $meta['auto_confirmed_at'] = now()->toIso8601String();

                $match->update([
                    'status'       => 'confirmed',
                    'score_home'   => $report->score_home,
                    'score_away'   => $report->score_away,
                    'ot'           => $report->ot,
                    'so'           => $report->so,
                    'confirmed_at' => now(),
                    'meta'         => $meta,
                ]);

                // 3) Остальные pending-репорты по этому матчу делаем устаревшими
                MatchReport::where('match_id', $match->id)
                    ->where('id', '!=', $report->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'obsolete']);

                // 4) Пересчитываем таблицу
                RecalculateStandings::dispatchSync($match->stage_id);

                $processed++;
            });
        }

        $this->info('Автоматически подтверждено матчей: ' . $processed);

        return self::SUCCESS;
    }
}
