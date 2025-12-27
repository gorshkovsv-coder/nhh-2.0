<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateStandings;
use App\Models\MatchModel;
use App\Models\MatchReport;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Models\NhlTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;


class MatchAdminController extends Controller
{
    /** Простейшая проверка прав администратора (как в TournamentAdminController) */
    private function ensureAdmin(): void
    {
        $user = auth()->user();
        if (!$user || !($user->is_admin ?? false)) {
            abort(403, 'Only admins can access this area.');
        }
    }

    /**
     * Список матчей + фильтры + история репортов
     */
    public function index(Request $request): Response
    {
        $this->ensureAdmin();

        // Текущие значения фильтров из query
        $filters = [
            'tournament_id' => $request->input('tournament_id') ?: null,
            'stage_id'      => $request->input('stage_id') ?: null,
            'player_id'     => $request->input('player_id') ?: null, // user_id
            'team_id'       => $request->input('team_id') ?: null,   // nhl_team_id
            'status'        => $request->input('status') ?: null,
        ];

        $query = MatchModel::query()
            ->with([
                'stage.tournament',
                'home.user',
                'home.nhlTeam',
                'away.user',
                'away.nhlTeam',
                // история репортов (от новых к старым)
                'reports' => function ($q) {
                    $q->latest('created_at');
                },
                'reports.reporter.user',
                'reports.reporter.nhlTeam',
                'reports.confirmer.user',
                'reports.confirmer.nhlTeam',
            ])
            ->orderByRaw('COALESCE(scheduled_at, created_at) DESC');

        // Фильтр: турнир
        if ($filters['tournament_id']) {
            $tId = (int) $filters['tournament_id'];
            $query->whereHas('stage', function ($q) use ($tId) {
                $q->where('tournament_id', $tId);
            });
        }

        // Фильтр: стадия
        if ($filters['stage_id']) {
            $query->where('stage_id', (int) $filters['stage_id']);
        }

        // Фильтр: игрок (user_id)
        if ($filters['player_id']) {
            $userId = (int) $filters['player_id'];

            $query->where(function ($q) use ($userId) {
                $q->whereHas('home.user', function ($q2) use ($userId) {
                    $q2->where('id', $userId);
                })->orWhereHas('away.user', function ($q2) use ($userId) {
                    $q2->where('id', $userId);
                });
            });
        }

        // Фильтр: команда (NHL)
        if ($filters['team_id']) {
            $teamId = (int) $filters['team_id'];

            $query->where(function ($q) use ($teamId) {
                $q->whereHas('home.nhlTeam', function ($q2) use ($teamId) {
                    $q2->where('id', $teamId);
                })->orWhereHas('away.nhlTeam', function ($q2) use ($teamId) {
                    $q2->where('id', $teamId);
                });
            });
        }

		// Фильтр: статус матча
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

		// Пагинация матчей (по 50 шт. на страницу) с сохранением query-параметров
        $matches = $query
            ->paginate(20)
            ->withQueryString();

        // Опции для фильтров
        $tournaments = Tournament::orderByDesc('created_at')
            ->get(['id', 'title', 'season']);

        $stages = Stage::orderBy('order')
            ->get(['id', 'name', 'tournament_id']);

        // Только те пользователи, которые участвуют в турнирах
        $playerIds = TournamentParticipant::whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $players = User::whereIn('id', $playerIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'psn']);

        $teams = NhlTeam::orderBy('name')
            ->get(['id', 'name']);

        // Справочник статусов для фильтра и формы редактирования
        $statusOptions = [
            ['value' => 'scheduled',   'label' => 'Запланирован'],
            ['value' => 'reported',    'label' => 'Ожидает подтверждения'],
            ['value' => 'confirmed',   'label' => 'Подтверждён'],
            ['value' => 'canceled',    'label' => 'Отменён'],
            ['value' => 'pending',     'label' => 'Ожидает'],
            ['value' => 'in_progress', 'label' => 'Идёт'],
            ['value' => 'disputed',    'label' => 'Спор'],
            ['value' => 'finished',    'label' => 'Завершён'],
        ];

        return Inertia::render('Admin/Matches/Index', [
            'matches'       => $matches,
            'filters'       => $filters,
            'filterOptions' => [
                'tournaments' => $tournaments,
                'stages'      => $stages,
                'players'     => $players,
                'teams'       => $teams,
            ],
            'statuses'      => $statusOptions,
        ]);
    }

    /**
     * Обновление матча: счёт, OT/SO, статус, с пересчётом таблиц/плей-офф.
     */
public function update(Request $request, MatchModel $match)
{
    $this->ensureAdmin();

    $data = $request->validate([
        'status'     => ['required', 'string'],
        'score_home' => ['nullable', 'integer', 'min:0', 'max:99'],
        'score_away' => ['nullable', 'integer', 'min:0', 'max:99'],
        'ot'         => ['nullable', 'boolean'],
        'so'         => ['nullable', 'boolean'],
    ]);

    DB::transaction(function () use ($match, $data) {
        $stage = $match->stage; // заранее, пока матч не тронут

        // === Обновляем сам матч ===
        $match->status     = $data['status'];
        $match->score_home = $data['score_home'];
        $match->score_away = $data['score_away'];
        $match->ot         = (bool) ($data['ot'] ?? false);
        $match->so         = (bool) ($data['so'] ?? false);

        if (
            in_array($match->status, ['confirmed', 'finished'], true)
            && $match->score_home !== null
            && $match->score_away !== null
        ) {
            $match->confirmed_at = now();
        }

        // важно: save(), чтобы сработал booted() в MatchModel (плей-офф)
        $match->save();

        // === Синхронизируем последний актуальный репорт с результатом матча ===
        if ($match->score_home !== null && $match->score_away !== null) {
            // берём последний pending/confirmed репорт, он считается актуальным
            $primaryReport = $match->reports()
                ->whereIn('status', ['pending', 'confirmed'])
                ->latest('created_at')
                ->first();

            if ($primaryReport) {
                $primaryReport->score_home = $match->score_home;
                $primaryReport->score_away = $match->score_away;
                $primaryReport->ot         = $match->ot;
                $primaryReport->so         = $match->so;

                // если матч перевели в confirmed — метим и репорт как confirmed
                if ($match->status === 'confirmed') {
                    $primaryReport->status = 'confirmed';

                    // остальные ожидающие репорты помечаем obsolete (как в confirm())
                    MatchReport::where('match_id', $match->id)
                        ->where('id', '!=', $primaryReport->id)
                        ->where('status', 'pending')
                        ->update(['status' => 'obsolete']);
                }

                $primaryReport->save();
            }
        }

        // === Пересчёт таблицы для групповой стадии ===
        if ($stage && $stage->type === 'group') {
            RecalculateStandings::dispatchSync($stage->id);
        }
    });

    return back()->with('success', 'Матч обновлён.');
}


    /**
     * Удаление матча целиком (с репортами).
     */
    public function destroy(MatchModel $match)
    {
        $this->ensureAdmin();

        DB::transaction(function () use ($match) {
            $stage = $match->stage;

            MatchReport::where('match_id', $match->id)->delete();
            $match->delete();

            if ($stage && $stage->type === 'group') {
                RecalculateStandings::dispatchSync($stage->id);
            }
        });

        return back()->with('success', 'Матч удалён.');
    }

    /**
     * Удаление конкретного репорта матча.
     */
    public function destroyReport(MatchModel $match, MatchReport $report)
    {
        $this->ensureAdmin();

        if ($report->match_id !== $match->id) {
            abort(404);
        }

        DB::transaction(function () use ($match, $report) {
            $report->delete();

            // Если матч был "reported", но pending-репортов больше нет — вернём в scheduled
            if ($match->status === 'reported') {
                $stillPending = MatchReport::where('match_id', $match->id)
                    ->where('status', 'pending')
                    ->exists();

                if (!$stillPending) {
                    $match->status = 'scheduled';
                    $match->save();
                }
            }
        });

        return back()->with('success', 'Репорт удалён.');
    }

    /**
     * Удаление отдельного вложения (скриншота) из репорта.
     */
    public function destroyAttachment(MatchModel $match, MatchReport $report, int $index)
    {
        $this->ensureAdmin();

        if ($report->match_id !== $match->id) {
            abort(404);
        }

        $attachments = $report->attachments ?? [];
        if (!is_array($attachments) || !array_key_exists($index, $attachments)) {
            abort(404);
        }

        $path = $attachments[$index];

        // Удаляем ссылку из массива
        unset($attachments[$index]);
        $attachments = array_values($attachments);
        $report->attachments = $attachments;
        $report->save();

        // Опционально можно чистить и файл из storage
        if (is_string($path)) {
            $relative = preg_replace('#^/storage/#', '', $path);
            if ($relative) {
                Storage::disk('public')->delete($relative);
            }
        }

        return back()->with('success', 'Вложение удалено.');
    }
	
	public function confirmReport(MatchModel $match, MatchReport $report)
{
    $this->ensureAdmin();

    if ($report->match_id !== $match->id) {
        abort(404);
    }

    DB::transaction(function () use ($match, $report) {
        $stage = $match->stage;

        // === 1. Обновляем матч по данным репорта ===
        $match->status      = 'confirmed';
        $match->score_home  = $report->score_home;
        $match->score_away  = $report->score_away;
        $match->ot          = (bool) $report->ot;
        $match->so          = (bool) $report->so;

        if ($match->score_home !== null && $match->score_away !== null) {
            $match->confirmed_at = now();
        }

        // save() — чтобы сработал hook в MatchModel (продвижение плей-офф)
        $match->save();

        // === 2. Обновляем сам репорт ===
        // если confirmer ещё не задан — считаем его соперником отправителя
        if (!$report->confirmer_participant_id) {
            $reporterPid = $report->reporter_participant_id;
            $homePid     = $match->home_participant_id;
            $awayPid     = $match->away_participant_id;

            if ($reporterPid && $homePid && $awayPid) {
                if ((int)$reporterPid === (int)$homePid) {
                    $report->confirmer_participant_id = $awayPid;
                } elseif ((int)$reporterPid === (int)$awayPid) {
                    $report->confirmer_participant_id = $homePid;
                }
            }
        }

        $report->status = 'confirmed';
        $report->save();

        // все остальные pending-репорты этого матча считаем устаревшими
        MatchReport::where('match_id', $match->id)
            ->where('id', '!=', $report->id)
            ->where('status', 'pending')
            ->update(['status' => 'obsolete']);

        // === 3. Пересчёт таблицы для групповой стадии ===
        if ($stage && $stage->type === 'group') {
            RecalculateStandings::dispatchSync($stage->id);
        }
    });

    return back()->with('success', 'Репорт подтверждён.');
}

	
	
}
