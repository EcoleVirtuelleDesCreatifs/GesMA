<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données de référence nécessaires à la mise en service (sans données de démonstration).
 * php artisan db:seed --class=ReferenceDataSeeder
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'YAM', 'name' => 'Mobilier Addict Yamoussoukro', 'city' => 'Yamoussoukro'],
            ['code' => 'BKE', 'name' => 'Mobilier Addict Bouaké', 'city' => 'Bouaké'],
            ['code' => 'BEO', 'name' => 'Mobilier Addict Béoumi', 'city' => 'Béoumi'],
            ['code' => 'BFL', 'name' => 'Mobilier Addict Bouaflé', 'city' => 'Bouaflé'],
        ] as $store) {
            Store::firstOrCreate(['code' => $store['code']], $store + ['is_active' => true]);
        }

        foreach (['Salons', 'Chambres', 'Salles à manger', 'Bureaux', 'Rangements', 'Décoration', 'Literie', 'Extérieur'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        foreach (['Transport', 'Livraisons', 'Loyer', 'Électricité et eau', 'Salaires', 'Communication', 'Entretien et réparations', 'Fournitures', 'Marketing', 'Divers'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }

        $email = env('GESMA_ADMIN_EMAIL', 'direction@mobilieraddict.ci');
        if (! User::where('email', $email)->exists()) {
            $password = env('GESMA_ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
            User::create([
                'name' => env('GESMA_ADMIN_NAME', 'Direction Mobilier Addict'),
                'email' => $email,
                'password' => $password,
                'role' => Role::Direction,
                'is_active' => true,
            ]);
            if (! env('GESMA_ADMIN_PASSWORD')) {
                $this->command?->warn("Compte direction créé : {$email} / {$password} — changez ce mot de passe après la première connexion.");
            }
        }
    }
}
