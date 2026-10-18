<?php

namespace Tests;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Store $yam;
    protected Store $bke;
    protected Product $sofa;
    protected Product $chair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yam = Store::create(['code' => 'YAM', 'name' => 'Magasin Yamoussoukro', 'city' => 'Yamoussoukro']);
        $this->bke = Store::create(['code' => 'BKE', 'name' => 'Magasin Bouaké', 'city' => 'Bouaké']);
        $cat = Category::create(['name' => 'Salons']);
        $this->sofa = Product::create(['reference' => 'SAL-001', 'name' => 'Canapé', 'category_id' => $cat->id, 'price' => 200000]);
        $this->chair = Product::create(['reference' => 'SAM-002', 'name' => 'Chaise', 'category_id' => $cat->id, 'price' => 30000]);
        app(StockService::class)->ensureStockRows();
    }

    protected function user(string $role, ?Store $store = null): User
    {
        return User::create([
            'name' => ucfirst($role).' '.($store?->code ?? 'global'),
            'email' => $role.'.'.strtolower($store?->code ?? 'global').'.'.uniqid().'@test.ci',
            'role' => $role,
            'store_id' => $role === 'direction' ? null : ($store ?? $this->yam)->id,
            'password' => Hash::make('password1'),
            'is_active' => true,
        ]);
    }

    protected function stockOf(Product $product, Store $store): int
    {
        return app(StockService::class)->available($product->id, $store->id);
    }

    protected function addStock(Product $product, Store $store, int $qty): void
    {
        app(StockService::class)->move($product->id, $store->id, $qty, 'entree', 'Test');
    }
}
