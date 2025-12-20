<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMatchReportRequest;
use App\Jobs\RecalculateStandings;
use App\Models\MatchModel;
use App\Models\MatchReport;
use App\Models\TournamentParticipant;
use Illuminate\Support\Facades\DB;

class MatchReportController extends Controller
{
    /**
     * Блокируем действия с репортами для закрытых матчей:
     * - canceled, confirmed, finished
     */
    private function assertMatchOpen(MatchModel $match): void
    {
        if (in_array($match->status, ['canceled', 'confirmed', 'finished'], true)) {
            abort(422, 'Этот матч закрыт (отменён или уже подтверждён) — действие недоступно.');
        }
    }

public function store(StoreMatchReportRequest $req, MatchModel $match)
{
    $this->assertMatchOpen($match); // защита от действий по отменённым/закрытым матчам

    $userId = auth()->id();

    $participant = TournamentParticipant::where('user_id', $userId)
        ->whereIn('id', [$match->home_participant_id, $match->away_participant_id])
        ->firstOrFail();

    if ($match->status === 'confirmed') {
        abort(422, 'Матч уже подтверждён.');
    }

    DB::transaction(function () use ($req, $match, $participant) {
        // помечаем предыдущие репорты как устаревшие, если были
        MatchReport::where('match_id', $match->id)
            ->where('status', 'pending')
            ->update(['status' => 'obsolete']);

        MatchReport::create([
            'match_id'                => $match->id,
            'reporter_participant_id' => $participant->id,
            'score_home'              => $req->score_home,
            'score_away'              => $req->score_away,
            'ot'                      => (bool) $req->ot,
            'so'                      => (bool) $req->so,
            'comment'                 => $req->comment,
            'status'                  => 'pending',
        ]);

        $match->update(['status' => 'reported']);
    });

    // После отправки репорта возвращаем игрока в список его матчей
    return redirect()
        ->route('my.matches')
        ->with('ok', 'Результат отправлен и ожидает подтверждения соперником.');
}


    public function confirm(MatchReport $report)
    {
        $match    = $report->match;
        $this->assertMatchOpen($match); // NEW

        if ($match->status !== 'reported') { // NEW: подтверждать можно только "reported"
            abort(422, 'Подтверждать можно только матч в статусе "reported".');
        }

        $userId    = auth()->id();
        $confirmer = TournamentParticipant::where('user_id', $userId)
            ->whereIn('id', [$match->home_participant_id, $match->away_participant_id])
            ->firstOrFail();

        if ($confirmer->id === $report->reporter_participant_id) {
            abort(403, 'Нельзя подтверждать собственный отчёт.');
        }

        DB::transaction(function () use ($match, $report) {
            if ($report->status !== 'pending') {
                abort(422, 'Репорт неактуален.');
            }

            $report->update([
                'status' => 'confirmed',
            ]);

            // переносим результат в сам матч
            $match->update([
                'status'       => 'confirmed',
                'score_home'   => $report->score_home,
                'score_away'   => $report->score_away,
                'ot'           => $report->ot,
                'so'           => $report->so,
                'confirmed_at' => now(),
            ]);

            // остальные ожидающие репорты помечаем как устаревшие
            MatchReport::where('match_id', $match->id)
                ->where('id', '!=', $report->id)
                ->where('status', 'pending')
                ->update(['status' => 'obsolete']);

            // ВАЖНО: пересчитываем таблицу синхронно (без очереди)
            RecalculateStandings::dispatchSync($match->stage_id);
        });

        return back()->with('ok', 'Результат подтверждён.');
    }

    public function dispute(MatchReport $report)
    {
        $match = $report->match;
        $this->assertMatchOpen($match); // NEW

        if ($match->status !== 'reported') { // NEW: спорить есть смысл только в "reported"
            abort(422, 'Отклонять можно только матч в статусе "reported".');
        }

        $userId    = auth()->id();
        $confirmer = TournamentParticipant::where('user_id', $userId)
            ->whereIn('id', [$match->home_participant_id, $match->away_participant_id])
            ->firstOrFail();

        if ($confirmer->id === $report->reporter_participant_id) {
            abort(403, 'Нельзя оспаривать собственный отчёт.');
        }

        DB::transaction(function () use ($match, $report) {
            if ($report->status !== 'pending') {
                abort(422, 'Репорт неактуален.');
            }

            $report->update(['status' => 'rejected']);
            $match->update(['status' => 'disputed']);
            // TODO: уведомить администратора, создать запись спора при необходимости
        });

        return back()->with('ok', 'Репорт отклонён, спор отправлен администратору.');
	
    }
	
	    /**
     * Алиас для маршрута /reports/{report}/reject
     * (исторически метод назывался dispute, а в роуте – reject).
     */
    public function reject(MatchReport $report)
    {
        return $this->dispute($report);
    }
	
}
