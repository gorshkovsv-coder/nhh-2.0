namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


protected function schedule(Schedule $schedule): void
{
    // уже существующие задачи, если есть...

    $schedule->command('matches:auto-confirm')->everyFifteenMinutes();
}

protected function commands(): void
{
    $this->load(__DIR__.'/Commands');

    require base_path('routes/console.php');
}
