<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'display_name',
        'seed',
        'nhl_team_id',
        'meta',
        'is_active',       // ← добавить
    ];

    protected $casts = [
        'meta'      => 'array',
        'is_active' => 'boolean',   // ← добавить
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function nhlTeam()
    {
        return $this->belongsTo(NhlTeam::class);
    }
}
