<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Разрешаем доступ к админке турниров только админам
        Gate::define('manage-tournaments', function (User $user) {
            return (bool) $user->is_admin;
        });
		
		
		    // ----- Кастомное письмо для СБРОСА ПАРОЛЯ -----
		ResetPassword::toMailUsing(function (object $notifiable, string $url) {
			$expire = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

			return (new MailMessage)
				->subject('Сброс пароля — NHL26 Tournaments')
				->greeting('Здравствуйте!')
				->line('Вы получили это письмо, потому что для вашего аккаунта был запрошен сброс пароля.')
				->action('Сбросить пароль', $url)
				->line('Ссылка для сброса пароля будет действительна в течение ' . $expire . ' минут.')
				->line('Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.');
		});
		
			// ----- Кастомное письмо для ПОДТВЕРЖДЕНИЯ EMAIL -----
		VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
			return (new MailMessage)
				->subject('Подтверждение email — NHL26 Tournaments')
				->greeting('Здравствуйте!')
				->line('Спасибо за регистрацию в NHL26 Tournaments.')
				->line('Пожалуйста, подтвердите адрес электронной почты, нажав на кнопку ниже.')
				->action('Подтвердить email', $url)
				->line('Если вы не создавали аккаунт, просто проигнорируйте это письмо.');
		});
		
		
    }
}
