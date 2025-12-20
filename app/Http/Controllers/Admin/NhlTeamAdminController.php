<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NhlTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class NhlTeamAdminController extends Controller
{
    /** Простая проверка, что пользователь — админ */
    private function ensureAdmin(): void
    {
        $user = auth()->user();
        if (!$user || !($user->is_admin ?? false)) {
            abort(403, 'Only admins can access this area.');
        }
    }

    public function index()
    {
        $this->ensureAdmin();

        $teams = NhlTeam::orderBy('code')
            ->get()
            ->map(function (NhlTeam $team) {
                return [
                    'id'       => $team->id,
                    'code'     => $team->code,
                    'name'     => $team->name,
                    'logo_url' => $team->logo_url,
                ];
            });

        return Inertia::render('Admin/NhlTeams/Index', [
            'teams' => $teams,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:3'],
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'], // до ~2 МБ
        ]);

        $team = new NhlTeam();
        $team->code = strtoupper($data['code']);
        $team->name = $data['name'];

        if ($request->hasFile('logo')) {
            $team->logo_path = $request->file('logo')->store('nhl-logos', 'public');
        }

        $team->save();

        return redirect()
            ->route('admin.nhl-teams.index')
            ->with('success', 'Команда добавлена');
    }

public function update(Request $request, NhlTeam $team)
{
    $this->ensureAdmin();

    $request->validate([
        'name' => 'nullable|string|max:255',
        'logo' => 'nullable|image|max:2048',
    ]);

    // Обновляем название
    if ($request->filled('name')) {
        $team->name = $request->input('name');
    }

    // Обновляем логотип
    if ($request->hasFile('logo')) {
        // при желании можно удалить старый файл
        if ($team->logo_path) {
            Storage::disk('public')->delete($team->logo_path);
        }

        $team->logo_path = $request->file('logo')->store('nhl-logos', 'public');
    }

    $team->save();

    return back()->with('success', 'Команда обновлена');
}



    public function destroy(NhlTeam $team)
    {
        $this->ensureAdmin();

        if ($team->logo_path) {
            Storage::disk('public')->delete($team->logo_path);
        }

        $team->delete();

        return redirect()
            ->back()
            ->with('success', 'Команда удалена');
    }
}
