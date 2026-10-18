<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de départ : magasins, comptes par profil, catalogue.
 * Les données de démonstration (clients, commandes...) sont dans DemoSeeder.
 *
 *   php artisan migrate --seed
 *   php artisan db:seed --class=DemoSeeder   (facultatif)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $stores = collect([
            ['code' => 'YAM', 'name' => 'Mobilier Addict Yamoussoukro', 'city' => 'Yamoussoukro'],
            ['code' => 'BKE', 'name' => 'Mobilier Addict Bouaké', 'city' => 'Bouaké'],
            ['code' => 'BEO', 'name' => 'Mobilier Addict Béoumi', 'city' => 'Béoumi'],
            ['code' => 'BFL', 'name' => 'Mobilier Addict Bouaflé', 'city' => 'Bouaflé'],
        ])->map(fn ($s) => Store::firstOrCreate(['code' => $s['code']], $s + ['is_active' => true]));

        // Mot de passe initial à changer à la première connexion
        $password = Hash::make('GesMA2026!');
        User::firstOrCreate(['email' => 'direction@mobilieraddict.ci'], [
            'name' => 'Direction Mobilier Addict', 'role' => 'direction', 'password' => $password, 'is_active' => true,
        ]);
        foreach ($stores as $store) {
            $slug = strtolower($store->code);
            User::firstOrCreate(['email' => "responsable.{$slug}@mobilieraddict.ci"], [
                'name' => "Responsable {$store->city}", 'role' => 'responsable', 'store_id' => $store->id, 'password' => $password, 'is_active' => true,
            ]);
            User::firstOrCreate(['email' => "vente.{$slug}@mobilieraddict.ci"], [
                'name' => "Vendeur {$store->city}", 'role' => 'vendeur', 'store_id' => $store->id, 'password' => $password, 'is_active' => true,
            ]);
            User::firstOrCreate(['email' => "logistique.{$slug}@mobilieraddict.ci"], [
                'name' => "Livreur {$store->city}", 'role' => 'logistique', 'store_id' => $store->id, 'password' => $password, 'is_active' => true,
            ]);
        }

        $catalog = [
            'Salons' => [
                ['SAL-001', 'Canapé 3 places tissu gris', 285000],
                ['SAL-002', 'Canapé d’angle cuir marron', 650000],
                ['SAL-003', 'Fauteuil relax', 145000],
                ['SAL-004', 'Table basse bois massif', 85000],
            ],
            'Chambres' => [
                ['CHB-001', 'Lit 2 places 160×200', 320000],
                ['CHB-002', 'Armoire 3 portes', 275000],
                ['CHB-003', 'Matelas orthopédique 160×200', 180000],
                ['CHB-004', 'Table de chevet', 45000],
            ],
            'Salles à manger' => [
                ['SAM-001', 'Table à manger 6 places', 260000],
                ['SAM-002', 'Chaise rembourrée', 35000],
                ['SAM-003', 'Buffet bas 2 portes', 195000],
            ],
            'Bureaux' => [
                ['BUR-001', 'Bureau d’angle', 120000],
                ['BUR-002', 'Fauteuil de bureau ergonomique', 95000],
            ],
            'Décoration' => [
                ['DEC-001', 'Miroir mural doré', 40000],
                ['DEC-002', 'Lampadaire design', 55000],
                ['DEC-003', 'Tapis 200×300', 75000],
            ],
        ];

        foreach ($catalog as $categoryName => $items) {
            $category = Category::firstOrCreate(['name' => $categoryName]);
            foreach ($items as [$ref, $name, $price]) {
                Product::firstOrCreate(['reference' => $ref], [
                    'name' => $name, 'category_id' => $category->id, 'price' => $price,
                    'description' => $name.' — collection Mobilier Addict.', 'is_available' => true,
                ]);
            }
        }

        app(StockService::class)->ensureStockRows();
    }
}
