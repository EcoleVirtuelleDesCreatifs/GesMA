<?php

use App\Models\Quote;
use Illuminate\Support\Facades\Schedule;

// Sauvegarde quotidienne de la base et des fichiers (conservation 30 jours).
Schedule::command('gesma:backup')->dailyAt('02:30')->withoutOverlapping();

// Les devis dépassés passent au statut « expiré ».
Schedule::call(function () {
    Quote::whereIn('status', ['draft', 'sent'])->whereDate('valid_until', '<', today())->update(['status' => 'expired']);
})->dailyAt('01:00')->name('quotes:expire');
