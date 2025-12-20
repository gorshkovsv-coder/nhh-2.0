<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

	protected $fillable = [
    'name',
    'email',
    'password',
    'psn',          // <--
    'avatar_path',  // <--
];
	
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
	
	protected $appends = [
		'avatar_url',
	];
	
	public function getAvatarUrlAttribute(): string
	{
		// Если у пользователя есть свой аватар — берём его
		if (!empty($this->avatar_path)) {
			return Storage::disk('public')->url($this->avatar_path);
		}

		// Иначе — стандартная картинка
		return Storage::disk('public')->url('avatars/default.png');
	}
	
	/**
     * Отправка письма для сброса пароля (кастомное уведомление).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
	
	    /**
     * Отправка письма для подтверждения email (кастомное уведомление).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
	
	
}
