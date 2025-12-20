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

        return Inertia::render('Profile/Show', [
            'user'            => $user,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status'          => session('status'),
            'playerStats'     => $stats,
			'isOwner'         => true,
        ]);
    }
	
	    /**
     * Публичный профиль игрока (просмотр по клику из турниров).
     */
    public function showPublic(User $user, PlayerStatsService $playerStats): Response
    {
        $stats = $playerStats->buildStatsForUser($user->id);

        return Inertia::render('Profile/Show', [
            'user'            => $user,
            'mustVerifyEmail' => false,
            'status'          => null,
            'playerStats'     => $stats,
            'isOwner'         => auth()->check() && auth()->id() === $user->id,
        ]);
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
