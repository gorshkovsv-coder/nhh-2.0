<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'name',
        'type',            // 'group' | 'playoff'
        'settings',        // JSON
        'order',
        'games_per_pair',  // для кругового и как длина серии в плей-офф
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    // Relations
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches()
    {
        return $this->hasMany(MatchModel::class, 'stage_id');
    }

    // -------- Helpers --------

    /** Нормируем games_per_pair. */
    public function getGamesPerPairAttribute($value): int
    {
        $v = (int)($value ?? 1);
        if ($v < 1) $v = 1;
        if ($v > 7) $v = 7; // до BO7 в плей-офф
        return $v;
    }

    public function setGamesPerPairAttribute($value): void
    {
        $v = (int)($value ?? 1);
        if ($v < 1) $v = 1;
        if ($v > 7) $v = 7;
        $this->attributes['games_per_pair'] = $v;
    }

    public function getSetting(string $key, $default = null)
    {
        $s = $this->settings ?? [];
        return data_get($s, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $s = $this->settings ?? [];
        data_set($s, $key, $value);
        $this->settings = $s;
    }

    /** Сколько команд выходит из группы (4/8/16/32). */
    public function getAdvancersAttribute(): int
    {
        $n = (int)($this->getSetting('advancers', 8));
        $allowed = [4, 8, 16, 32];
        if (!in_array($n, $allowed, true)) $n = 8;
        return $n;
    }

    public function setAdvancersAttribute($value): void
    {
        $n = (int)$value;
        $allowed = [4, 8, 16, 32];
        if (!in_array($n, $allowed, true)) $n = 8;
        $this->setSetting('advancers', $n);
    }
}
