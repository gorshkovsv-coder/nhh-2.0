<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TournamentParticipant;
use App\Models\Stage;
use App\Models\NhlTeam;


class Tournament extends Model
{
    use HasFactory;

    protected $fillable = ['title','season','format','settings','status','logo_path'];
    protected $casts = ['settings' => 'array'];

    protected $appends = [
        'logo_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return asset('storage/' . $this->logo_path);
    }

    /**
     * Активные участники турнира (для форм, сеток, таблиц).
     */
    public function participants()
    {
        return $this->hasMany(TournamentParticipant::class)
            ->where('is_active', true);
    }

    /**
     * Все участники (в т.ч. деактивированные) — пригодится, если нужно
     * анализировать историю/отладку в админке.
     */
    public function allParticipants()
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function stages()
    {
        return $this->hasMany(Stage::class)->orderBy('order');
    }
	
	    /**
     * Команды, участвующие в жеребьёвке данного турнира.
     */
    public function draftTeams()
    {
        return $this->belongsToMany(NhlTeam::class, 'tournament_nhl_team')
            ->withTimestamps();
    }
}