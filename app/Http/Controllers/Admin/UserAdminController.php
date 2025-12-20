<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserAdminController extends Controller
{
    private function ensureAdmin(): void
    {
        $user = auth()->user();

        if (!$user || !($user->is_admin ?? false)) {
            abort(403, 'Only admins can access this area.');
        }
    }

    /**
     * Список пользователей + поиск + пагинация
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $search = trim((string) $request->input('search', ''));

        $query = User::query()->orderBy('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('psn', 'like', "%{$search}%");
            });
        }

        // ВАЖНО: именно paginate(), а не get() и не simplePaginate()
        $users = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users'   => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'max:255', 'email', 'unique:users,email'],
            'psn'      => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->psn = $data['psn'] ?? null;
        $user->password = Hash::make($data['password']);
        $user->save();

        return redirect()->back()->with('success', 'Пользователь создан.');
    }

    public function verify(User $user)
    {
        $this->ensureAdmin();

        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        return redirect()->back()->with('success', 'Email пользователя подтверждён.');
    }

    public function destroy(User $user)
    {
        $this->ensureAdmin();

        $user->delete();

        return redirect()->back()->with('success', 'Пользователь удалён.');
    }

    /**
     * Массовое подтверждение
     */
    public function bulkVerify(Request $request)
    {
        $this->ensureAdmin();

        $ids = (array) $request->input('ids', []);

        if (!empty($ids)) {
            User::whereIn('id', $ids)
                ->whereNull('email_verified_at')
                ->update(['email_verified_at' => now()]);
        }

        return redirect()->back()->with('success', 'Email выбранных пользователей подтверждён.');
    }

    /**
     * Массовое удаление
     */
    public function bulkDelete(Request $request)
    {
        $this->ensureAdmin();

        $ids = (array) $request->input('ids', []);

        if (!empty($ids)) {
            User::whereIn('id', $ids)->delete();
        }

        return redirect()->back()->with('success', 'Выбранные пользователи удалены.');
    }
}
