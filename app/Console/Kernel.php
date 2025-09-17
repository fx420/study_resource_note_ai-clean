<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use \App\Console\Commands\ConvertTemplatesToTopics;

class Kernel extends ConsoleKernel
{
    protected $commands = [

        ConvertTemplatesToTopics::class,
        
    ];

    protected function schedule(Schedule $schedule)
    {
        
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
