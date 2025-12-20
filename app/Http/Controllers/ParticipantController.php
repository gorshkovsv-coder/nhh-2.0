<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    /**
     * Добавить/обновить участника через админку.
     */
    public function store(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'display_name' => 'required|string|max:255',
            'seed'         => 'nullable|integer',
        ]);

        // один user в одном турнире — максимум один участник
        TournamentParticipant::updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'user_id'       => $data['user_id'],
            ],
            [
                'display_name' => $data['display_name'],
                'seed'         => $data['seed'] ?? null,
                'is_active'    => true,
            ]
        );

        return back()->with('ok', 'Участник добавлен');
    }

    /**
     * Саморегистрация игрока в турнир.
     */
    public function register(Request $request, Tournament $tournament)
    {
        $user = $request->user();

        if ($tournament->status !== 'registration') {
            return back(303)->with('error', 'Регистрация закрыта.');
        }

        $participant = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant && $participant->is_active) {
            return back(303)->with('info', 'Вы уже зарегистрированы в этом турнире.');
        }

        if ($participant) {
            // был участником раньше — просто переактивируем
            $participant->is_active    = true;
            $participant->display_name = $user->name;
            $participant->save();
        } else {
            TournamentParticipant::create([
                'tournament_id' => $tournament->id,
                'user_id'       => $user->id,
                'display_name'  => $user->name,
                'seed'          => null,
                'meta'          => null,
                'is_active'     => true,
            ]);
        }

        return back(303)->with('success', 'Вы зарегистрированы на турнир.');
    }

    /**
     * Игрок сам отменяет регистрацию.
     * ВАЖНО: только is_active=false, standings не трогаем.
     */
    public function unregister(Request $request, Tournament $tournament)
    {
        $user = $request->user();

        if ($tournament->status !== 'registration') {
            return back(303)->with('error', 'Нельзя отменить регистрацию — турнир уже начался.');
        }

        $participant = TournamentParticipant::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            return back(303)->with('info', 'Вы не были зарегистрированы в этом турнире.');
        }

        $participant->is_active = false;
        $participant->save();

        return back(303)->with('success', 'Регистрация отменена (история матчей и турнирная статистика сохранены).');
    }

    /**
     * Удаление участника из турнира через админку.
     * Тоже только is_active=false.
     */
    public function destroy(Tournament $tournament, TournamentParticipant $participant)
    {
        if ($participant->tournament_id !== $tournament->id) {
            abort(404);
        }

        $participant->is_active = false;
        $participant->save();

        return back(303)->with([
            'ok'      => 'Команда удалена из активного состава турнира.',
            'success' => 'Команда удалена из активного состава турнира.',
        ]);
    }
}
