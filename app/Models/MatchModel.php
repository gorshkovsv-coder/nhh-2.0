<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\PlayoffService;

class MatchModel extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'stage_id',
        'home_participant_id',
        'away_participant_id',
        'game_no',
        'scheduled_at',
        'status',        // scheduled|reported|confirmed|disputed|canceled
        'score_home',
        'score_away',
        'ot',
        'so',
        'reporter_id',
        'confirmed_at',
        'meta',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'ot'           => 'bool',
        'so'           => 'bool',
        'meta'         => 'array',
    ];

    // Relations
    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function home()
    {
        return $this->belongsTo(TournamentParticipant::class, 'home_participant_id');
    }

    public function away()
    {
        return $this->belongsTo(TournamentParticipant::class, 'away_participant_id');
    }

    public function reports()
    {
        return $this->hasMany(MatchReport::class, 'match_id');
    }

    // ----------------- series auto-finalize -----------------
    protected static function booted(): void
    {
        // как только матч подтверждён — проверим серию
        static::saved(function (MatchModel $match) {
            if ($match->status !== 'confirmed') {
                return;
            }
            $stage = $match->stage;
            if (!$stage || $stage->type !== 'playoff') {
                return;
            }

            // L = до скольких поражений идёт серия
            $L = (int)($stage->settings['losses_to_eliminate'] ?? 0);
            if ($L < 1) {
                $gpp = (int)($stage->games_per_pair ?? 1);
                // если L не задан — восстановим по формуле (макс. игр = 2*L-1)
                $L = (int)max(1, min(4, (int)ceil(($gpp + 1) / 2)));
            } else {
                $L = (int)min(4, max(1, $L));
            }

            // Нормализуем пару участников (без учёта порядка дом/гость)
            $A = (int) $match->home_participant_id;
            $B = (int) $match->away_participant_id;

            $seriesQuery = MatchModel::where('stage_id', $match->stage_id)
                ->where(function ($q) use ($A, $B) {
                    $q->where(function ($q2) use ($A, $B) {
                        $q2->where('home_participant_id', $A)->where('away_participant_id', $B);
                    })->orWhere(function ($q2) use ($A, $B) {
                        $q2->where('home_participant_id', $B)->where('away_participant_id', $A);
                    });
                });

            // Подтверждённые матчи серии
            $confirmed = (clone $seriesQuery)->where('status', 'confirmed')->get([
                'home_participant_id','away_participant_id','score_home','score_away'
            ]);

            // Считаем поражения по участникам
            $losses = [$A => 0, $B => 0];
            foreach ($confirmed as $m) {
                if ($m->score_home === null || $m->score_away === null) continue;

                if ((int)$m->score_home > (int)$m->score_away) {
                    // проиграл гость
                    $losses[(int)$m->away_participant_id]++;
                } elseif ((int)$m->score_home < (int)$m->score_away) {
                    // проиграл хозяин
                    $losses[(int)$m->home_participant_id]++;
                }
            }

            // Если серия завершилась — отменяем все оставшиеся игры этой пары
            if ($losses[$A] >= $L || $losses[$B] >= $L) {
                (clone $seriesQuery)
                    ->whereIn('status', ['scheduled', 'reported', 'pending', 'created', 'proposed', 'disputed'])
                    ->update(['status' => 'canceled']);

                // Попробуем продвинуть плей-офф дальше (если все серии раунда закрыты)
                app(PlayoffService::class)->tryAdvance($stage);
            }
        });
    }
}
