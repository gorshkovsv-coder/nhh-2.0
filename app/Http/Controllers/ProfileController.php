<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\PlayerStatsService;
use App\Models\User;
use App\Models\MatchModel;

class ProfileController extends Controller
{
    /**
     * Alias: некоторые роуты могут ссылаться на show().
     */
    public function show(Request $request): Response
    {
        return $this->edit($request);
    }

    /**
     * Страница профиля пользователя.
     */
	public function edit(Request $request, PlayerStatsService $playerStats): Response
    {
        $user = $request->user();
        $stats = $playerStats->buildStatsForUser($user->id);
        $lastMatches = $this->buildLastMatches($user->id);

        return Inertia::render('Profile/Show', [
            'user'            => $user,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status'          => session('status'),
            'playerStats'     => $stats,
			'lastMatches'     => $lastMatches,
			'isOwner'         => true,
        ]);
    }
	
	    /**
     * Публичный профиль игрока (просмотр по клику из турниров).
     */
    public function showPublic(User $user, PlayerStatsService $playerStats): Response
    {
        $stats = $playerStats->buildStatsForUser($user->id);
        $lastMatches = $this->buildLastMatches($user->id);

        return Inertia::render('Profile/Show', [
            'user'            => $user,
            'mustVerifyEmail' => false,
            'status'          => null,
            'playerStats'     => $stats,
            'lastMatches'     => $lastMatches,
            'isOwner'         => auth()->check() && auth()->id() === $user->id,
        ]);
    }

    private function buildLastMatches(int $userId): array
    {
        $rawLastMatches = MatchModel::query()
            ->with([
                'stage.tournament',
                'home.user', 'home.nhlTeam', 'home.tournament',
                'away.user', 'away.nhlTeam', 'away.tournament',
                'reports' => function ($q) {
                    $q->latest('created_at');
                },
            ])
            ->where(function ($q) use ($userId) {
                $q->whereHas('home', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId);
                })->orWhereHas('away', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId);
                });
            })
            ->whereIn('status', ['confirmed', 'reported', 'disputed'])
            ->orderByRaw('COALESCE(confirmed_at, updated_at, created_at) DESC')
            ->take(10)
            ->get();

        if ($rawLastMatches->isEmpty()) {
            return [];
        }

        return $rawLastMatches->map(function (MatchModel $match) {
            $statusLabel = match ($match->status) {
                'confirmed' => 'Подтверждён',
                'reported'  => 'Ожидает подтверждения',
                'disputed'  => 'Спор',
                default     => null,
            };

            $stage    = $match->stage;
            $homePart = $match->home;
            $awayPart = $match->away;

            $tournament =
                $stage?->tournament
                ?? $homePart?->tournament
                ?? $awayPart?->tournament
                ?? null;

            $tournamentName = $tournament?->title
                ?? $tournament?->name
                ?? 'Турнир';

            $report = $match->reports?->first();

            return [
                'id'              => $match->id,
                'tournament_name' => $tournamentName,
                'stage_name'      => $stage?->name ?? 'Стадия',
                'status_label'    => $statusLabel,
                'score_home'      => $match->score_home ?? $report?->score_home,
                'score_away'      => $match->score_away ?? $report?->score_away,
                'home_team_logo_url' => $homePart?->nhlTeam?->logo_url,
                'home_team_name'     => $homePart?->nhlTeam?->name,
                'home_player_name'   => $homePart?->user?->name,
                'away_team_logo_url' => $awayPart?->nhlTeam?->logo_url,
                'away_team_name'     => $awayPart?->nhlTeam?->name,
                'away_player_name'   => $awayPart?->user?->name,
            ];
        })->values()->all();
    }



    /**
     * Обновление профиля: имя, email, PSN, аватар.
     */
public function update(Request $request): RedirectResponse
{
    $user = $request->user();

    $validated = $request->validate([
        // name / email — опциональны: если поле не пришло, оно просто не изменится
        'name'   => ['sometimes', 'string', 'max:255'],
        'email'  => [
            'sometimes',
            'string',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($user->id),
        ],
        'psn'    => ['nullable', 'string', 'max:255'],
        'avatar' => ['nullable', 'image', 'max:2048'],
    ]);

    // Имя
    if (array_key_exists('name', $validated)) {
        $user->name = $validated['name'];
    }

    // Email
    if (array_key_exists('email', $validated)) {
        $newEmail = $validated['email'];
        if ($newEmail !== $user->email) {
            $user->email = $newEmail;
            $user->email_verified_at = null;
        }
    }

    // PSN
    if (array_key_exists('psn', $validated)) {
        $user->psn = $validated['psn'] ?: null;
    }

    // Аватар (теперь это POST /profile, PHP нормально видит файл)
    if ($request->hasFile('avatar')) {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
    }

	$user->save();

	return Redirect::to('/profile')->with('success', 'Профиль обновлён.');
}



    /**
     * Смена пароля.
     */
public function updatePassword(Request $request): RedirectResponse
{
    $user = $request->user();

    $request->validate([
        'current_password'      => ['required', 'current_password'],
        'password'              => ['required', 'confirmed', 'min:8'],
        'password_confirmation' => ['required'],
    ]);

	$user->password = Hash::make($request->input('password'));
	$user->save();

	return Redirect::to('/profile')->with('success', 'Пароль успешно изменён.');
}


    /**
     * Удаление аккаунта.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Удаляем аватар, если есть
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('success', 'Аккаунт удалён.');
    }
}
