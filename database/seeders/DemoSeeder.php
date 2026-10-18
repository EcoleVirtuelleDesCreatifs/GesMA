<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Données de démonstration pour la formation (à ne pas lancer en production) */
class DemoSeeder extends Seeder
{
    public function run(StockService $stock, OrderService $orders, DocumentService $documents): void
    {
        mt_srand(2026);
        $direction = User::where('role', 'direction')->firstOrFail();
        Auth::setUser($direction);
        $stores = Store::all();
        $products = Product::all();

        // Stock initial
        foreach ($stores as $store) {
            foreach ($products as $product) {
                $stock->move($product->id, $store->id, mt_rand(2, 15), 'entree', 'Stock initial', null, $direction);
            }
        }

        $names = ['Kouadio Yao', 'Aya Konan', 'Moussa Traoré', 'Adjoua N’Guessan', 'Ibrahim Coulibaly', 'Marie Kouassi',
            'Serge Bamba', 'Fatou Diabaté', 'Jean-Marc Koffi', 'Awa Ouattara', 'Patrick Gnamien', 'Mariam Touré'];
        $customers = collect($names)->map(function ($name, $i) use ($stores) {
            $store = $stores[$i % $stores->count()];

            return Customer::create([
                'name' => $name,
                'phone' => '07'.str_pad((string) (10000000 + $i * 7919), 8, '0', STR_PAD_LEFT),
                'address' => 'Quartier '.['Habitat', 'Commerce', 'Kennedy', 'Air France', 'Dioulabougou'][$i % 5],
                'city' => $store->city,
                'store_id' => $store->id,
            ]);
        });

        // Commandes des 45 derniers jours
        for ($i = 0; $i < 70; $i++) {
            $customer = $customers->random();
            $store = $stores->firstWhere('id', $customer->store_id);
            $seller = User::where('store_id', $store->id)->where('role', 'vendeur')->first();
            $items = $products->random(mt_rand(1, 3))->map(fn ($p) => ['product_id' => $p->id, 'quantity' => mt_rand(1, 2)])->values()->all();
            $direct = mt_rand(0, 2) === 0;
            $date = now()->subDays(mt_rand(0, 45))->setTime(mt_rand(8, 18), mt_rand(0, 59));

            try {
                [$order] = $orders->create([
                    'store_id' => $store->id,
                    'customer_id' => $customer->id,
                    'items' => $items,
                    'direct_sale' => $direct,
                    'payment_method' => 'especes',
                    'ordered_at' => $date,
                    'delivery' => $direct ? null : [
                        'address' => $customer->address, 'city' => $customer->city,
                        'scheduled_date' => $date->copy()->addDays(2)->toDateString(), 'fee' => 5000,
                    ],
                ], $seller, 'gesma');
            } catch (\Throwable) {
                continue; // stock insuffisant
            }

            if (! $direct && $date->lt(now()->subDays(3))) {
                $roll = mt_rand(1, 10);
                if ($roll <= 6) {
                    $orders->changeStatus($order, 'expediee', $seller);
                    $orders->changeStatus($order, 'livree', $seller);
                    $order->update(['amount_paid' => $order->total]);
                } elseif ($roll === 7) {
                    $orders->changeStatus($order, 'expediee', $seller);
                    $orders->changeStatus($order, 'non_livree', $seller, 'Client absent');
                } elseif ($roll === 8) {
                    $orders->changeStatus($order, 'annulee', $direction, 'Annulée à la demande du client');
                }
            }
        }

        // Dépenses
        $categories = array_keys(config('gesma.expense_categories'));
        foreach ($stores as $store) {
            for ($i = 0; $i < 8; $i++) {
                Expense::create([
                    'store_id' => $store->id,
                    'user_id' => $direction->id,
                    'spent_at' => now()->subDays(mt_rand(0, 40))->toDateString(),
                    'amount' => mt_rand(5, 120) * 1000,
                    'category' => $categories[array_rand($categories)],
                    'description' => 'Dépense de démonstration',
                ]);
            }
        }

        // Un devis
        $customer = $customers->first();
        $documents->create('devis', [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
            'items' => [['product_id' => $products->first()->id, 'quantity' => 1], ['product_id' => $products->last()->id, 'quantity' => 2]],
            'delivery_fee' => 10000,
        ], $direction);

    }
}
