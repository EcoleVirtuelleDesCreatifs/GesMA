<?php

use Illuminate\Support\Facades\Schedule;

// Sauvegarde quotidienne de la base de données (section 14)
Schedule::command('gesma:backup')->dailyAt('02:00')->withoutOverlapping();
