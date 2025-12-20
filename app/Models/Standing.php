<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Standing extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id','participant_id','gp','w','otw','sow','otl','sol','l','gf','ga','gd','points','tech_losses'
    ];

    public function stage() {
        return $this->belongsTo(Stage::class);
    }

    public function participant() {
        return $this->belongsTo(TournamentParticipant::class, 'participant_id');
    }
}
