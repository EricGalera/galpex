<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Los comandos de GALPEX están en app/Console/Commands (php artisan list galpex).
| Para que corran solos, el Programador de tareas de Windows ejecuta cada minuto:
|     C:\xampp\php\php.exe C:\xampp\htdocs\Galpex\artisan schedule:run
*/

Schedule::command('galpex:prices')->weekdays()->everyFifteenMinutes()->between('10:30', '17:30')->withoutOverlapping();
Schedule::command('galpex:alerts')->everyFiveMinutes()->withoutOverlapping();
