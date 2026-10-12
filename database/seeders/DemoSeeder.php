<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\QuoteStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\ApiClient;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Store;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\NumberGenerator;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Jeu de démonstration : utilisateurs de chaque profil, catalogue, stocks, clients et activité des deux derniers mois. */
class DemoSeeder extends Seeder
{
    public function run(StockService $stock, OrderService $orders, DeliveryService $deliveries, NumberGenerator $numbers): void
    {
        mt_srand(2026);
        $stores = Store::orderBy('id')->get();

        // Comptes de démonstration (mot de passe : password)
        $direction = User::firstOrCreate(['email' => 'direction@gesma.test'], ['name' => 'Awa Koné (Direction)', 'password' => 'password', 'role' => Role::Direction]);
        $staff = [];
        foreach ($stores as $store) {
            $slug = strtolower($store->code);
            $staff[$store->id] = [
                'manager' => User::firstOrCreate(['email' => "responsable.$slug@gesma.test"], ['name' => 'Responsable '.$store->city, 'password' => 'password', 'role' => Role::Manager, 'store_id' => $store->id]),
                'sales' => User::firstOrCreate(['email' => "vente.$slug@gesma.test"], ['name' => 'Vendeur '.$store->city, 'password' => 'password', 'role' => Role::Sales, 'store_id' => $store->id]),
                'logistics' => User::firstOrCreate(['email' => "logistique.$slug@gesma.test"], ['name' => 'Livreur '.$store->city, 'password' => 'password', 'role' => Role::Logistics, 'store_id' => $store->id]),
            ];
        }

        // Catalogue
        $catalog = [
            'Salons' => [['Canapé 3 places Abidjan', 385000], ['Canapé d\'angle Lagune', 620000], ['Fauteuil club cuir', 210000], ['Table basse en teck', 95000], ['Meuble TV Iroko', 145000]],
            'Chambres' => [['Lit 2 places Bassam 160×200', 295000], ['Armoire 3 portes', 340000], ['Commode 5 tiroirs', 135000], ['Table de chevet', 45000]],
            'Salles à manger' => [['Table à manger 6 places', 260000], ['Chaise rembourrée', 38000], ['Buffet bas', 185000]],
            'Bureaux' => [['Bureau direction', 240000], ['Fauteuil de bureau ergonomique', 120000], ['Bibliothèque 5 étagères', 98000]],
            'Literie' => [['Matelas orthopédique 160×200', 175000], ['Matelas 140×190', 120000], ['Oreiller mémoire de forme', 18000]],
            'Décoration' => [['Miroir mural doré', 55000], ['Tapis berbère 200×300', 89000], ['Lampadaire design', 65000]],
        ];
        $products = collect();
        $i = 1;
        foreach ($catalog as $categoryName => $items) {
            $category = Category::firstOrCreate(['name' => $categoryName]);
            foreach ($items as [$name, $price]) {
                $products->push(Product::firstOrCreate(['reference' => sprintf('MA-%04d', $i++)], [
                    'name' => $name,
                    'category_id' => $category->id,
                    'description' => 'Produit '.$name.' de la collection Mobilier Addict.',
                    'price' => $price,
                    'cost_price' => (int) round($price * 0.62, -3),
                    'low_stock_threshold' => $price > 200000 ? 1 : 3,
                ]));
            }
        }

        // Stock initial (réception)
        foreach ($products as $product) {
            foreach ($stores as $k => $store) {
                $qty = max(0, mt_rand(2, 14) - $k * 2);
                if ($qty > 0) {
                    $stock->move($product->id, $store->id, $qty, StockMovementType::In, 'Stock initial', null, $staff[$store->id]['manager']);
                }
            }
        }

        // Clients
        $first = ['Kouassi', 'Aya', 'Yao', 'Adjoua', 'Konan', 'Amenan', 'Koffi', 'Affoué', 'Moussa', 'Fatou', 'Ibrahim', 'Mariam', 'Jean-Marc', 'Christelle', 'Serge', 'Nadège'];
        $last = ['Kouadio', 'N\'Guessan', 'Traoré', 'Koné', 'Bamba', 'Yao', 'Ouattara', 'Diabaté', 'Kouamé', 'Coulibaly', 'Touré', 'Aka'];
        $customers = collect();
        for ($n = 0; $n < 40; $n++) {
            $store = $stores[$n % $stores->count()];
            $customers->push(Customer::firstOrCreate(['phone' => '+22507'.str_pad((string) (10000000 + $n * 7919), 8, '0', STR_PAD_LEFT)], [
                'name' => $first[$n % count($first)].' '.$last[($n * 5) % count($last)],
                'city' => $store->city,
                'address' => 'Quartier '.['Habitat', 'Dioulakro', 'Commerce', 'Kokrenou', 'Air France', 'Belleville'][$n % 6],
                'store_id' => $store->id,
                'email' => $n % 3 === 0 ? 'client'.$n.'@exemple.ci' : null,
            ]));
        }

        // Activité des 60 derniers jours
        $expenseCategories = ExpenseCategory::pluck('id', 'name');
        for ($day = 60; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day)->setTime(9, 0);
            foreach ($stores as $k => $store) {
                $count = mt_rand(0, 3 - min(2, $k));
                for ($n = 0; $n < $count; $n++) {
                    $team = $staff[$store->id];
                    $isSale = mt_rand(1, 10) <= 4;
                    $withDelivery = ! $isSale && mt_rand(1, 10) <= 7;
                    $customer = $customers->where('store_id', $store->id)->random();
                    $items = $products->random(mt_rand(1, 3))->map(fn ($p) => ['product_id' => $p->id, 'quantity' => mt_rand(1, 2)])->values()->all();
                    try {
                        $order = $orders->create([
                            'type' => $isSale ? OrderType::Sale->value : OrderType::Order->value,
                            'source' => mt_rand(1, 10) <= 2 && ! $isSale ? OrderSource::Website->value : OrderSource::Gesma->value,
                            'external_reference' => null,
                            'store_id' => $store->id,
                            'customer_id' => $isSale && mt_rand(0, 1) ? null : $customer->id,
                            'items' => $items,
                            'discount' => mt_rand(1, 10) <= 2 ? 10000 : 0,
                            'payment_method' => ['cash', 'mobile_money', 'card'][mt_rand(0, 2)],
                            'requires_delivery' => $withDelivery,
                            'delivery' => $withDelivery ? [
                                'address' => $customer->address, 'city' => $customer->city, 'contact_name' => $customer->name,
                                'contact_phone' => $customer->phone, 'fee' => [5000, 10000, 15000][mt_rand(0, 2)],
                                'scheduled_date' => $date->copy()->addDays(mt_rand(1, 3))->toDateString(), 'driver_id' => $team['logistics']->id,
                            ] : null,
                            'ordered_at' => $date->copy()->addMinutes(mt_rand(0, 540)),
                        ], $team['sales']);
                    } catch (InsufficientStockException) {
                        continue;
                    }

                    if ($order->source === OrderSource::Website) {
                        $order->update(['external_reference' => 'WEB-'.$order->id]);
                    }
                    if ($isSale) {
                        continue;
                    }

                    // Évolution des commandes selon leur ancienneté
                    $roll = mt_rand(1, 100);
                    if ($day > 3) {
                        if ($order->delivery) {
                            $deliveries->updateStatus($order->delivery, DeliveryStatus::InTransit, $team['logistics']);
                            if ($roll <= 80) {
                                $deliveries->updateStatus($order->delivery->refresh(), DeliveryStatus::Delivered, $team['logistics']);
                            } elseif ($roll <= 88) {
                                $deliveries->updateStatus($order->delivery->refresh(), DeliveryStatus::Failed, $team['logistics'], 'Client absent');
                            } elseif ($roll <= 94) {
                                $deliveries->updateStatus($order->delivery->refresh(), DeliveryStatus::Returned, $team['logistics'], 'Produit refusé à la livraison');
                            } else {
                                $orders->changeStatus($order->refresh(), OrderStatus::Cancelled, $team['manager'], 'Annulation à la demande du client');
                            }
                        } elseif ($roll <= 90) {
                            $orders->changeStatus($order, OrderStatus::Delivered, $team['sales'], 'Retrait en magasin');
                        } else {
                            $orders->changeStatus($order, OrderStatus::Cancelled, $team['manager'], 'Client injoignable');
                        }
                        $order->refresh();
                        if ($order->status === OrderStatus::Delivered) {
                            $order->update(['amount_paid' => $order->total, 'delivered_at' => $order->ordered_at->copy()->addDays(2)]);
                        }
                    } elseif ($order->delivery && $roll <= 40) {
                        $deliveries->updateStatus($order->delivery, DeliveryStatus::Preparing, $team['logistics']);
                    }
                }

                if (mt_rand(1, 10) <= 3) {
                    $category = $expenseCategories->keys()->random();
                    Expense::create([
                        'spent_at' => $date->toDateString(),
                        'amount' => mt_rand(5, 120) * 1000,
                        'expense_category_id' => $expenseCategories[$category],
                        'store_id' => $store->id,
                        'user_id' => $staff[$store->id]['manager']->id,
                        'description' => $category.' — '.$store->city,
                        'payment_method' => 'cash',
                    ]);
                }
            }
        }

        // Quelques devis
        foreach ($customers->take(6) as $n => $customer) {
            $store = $stores->firstWhere('id', $customer->store_id);
            $quote = Quote::create([
                'number' => $numbers->next(NumberGenerator::QUOTE),
                'customer_id' => $customer->id,
                'store_id' => $store->id,
                'user_id' => $staff[$store->id]['sales']->id,
                'status' => [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Sent, QuoteStatus::Refused][$n % 4],
                'issued_at' => now()->subDays($n * 3),
                'valid_until' => now()->subDays($n * 3)->addDays(30),
                'delivery_fee' => 10000,
            ]);
            $quote->syncLines($products->random(2)->map(fn ($p) => [
                'product_id' => $p->id, 'designation' => $p->name, 'reference' => $p->reference, 'unit_price' => $p->price, 'quantity' => mt_rand(1, 3),
            ])->all());
        }

        if (! ApiClient::exists()) {
            [, $token] = ApiClient::issue('Site web Mobilier Addict (démo)', $stores->first()->id);
            $this->command?->info('Jeton API de démonstration : '.$token);
        }
    }
}
