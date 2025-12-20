<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id','reporter_participant_id','score_home','score_away','ot','so',
        'status','confirmer_participant_id','comment','attachments'
    ];
    protected $casts = ['attachments'=>'array','ot'=>'boolean','so'=>'boolean'];

    public function match() {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    public function reporter() {
        return $this->belongsTo(TournamentParticipant::class, 'reporter_participant_id');
    }

    public function confirmer() {
        return $this->belongsTo(TournamentParticipant::class, 'confirmer_participant_id');
    }
}
