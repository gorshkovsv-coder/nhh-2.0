<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TournamentController extends Controller
{
    /**
     * Список турниров (для пункта меню "Турниры").
     */
    public function index()
    {
        $tournaments = Tournament::query()
            ->withCount('participants') // => participants_count
            ->orderByRaw("FIELD(status, 'registration','active','draft','archived')")
            ->orderByDesc('created_at')
            ->get(['id','title','season','format','status','created_at']);

        return Inertia::render('Tournament/Index', [
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * Создание турнира.
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'title'    => 'required|string|max:255',
            'season'   => 'nullable|string|max:32',
            'format'   => 'required|in:round_robin,single_elim,groups_plus_playoff',
            'settings' => 'array',
        ]);

        $t = Tournament::create($data + ['status' => 'active']);

        return redirect()->to("/tournaments/{$t->id}");
    }
}