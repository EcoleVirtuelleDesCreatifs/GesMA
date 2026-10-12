<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class GesmaTestCase extends TestCase
{
    use RefreshDatabase;

    protected Store $yam;

    protected Store $bke;

    protected Product $sofa;

    protected Product $chair;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yam = Store::create(['code' => 'YAM', 'name' => 'Yamoussoukro', 'city' => 'Yamoussoukro']);
        $this->bke = Store::create(['code' => 'BKE', 'name' => 'Bouaké', 'city' => 'Bouaké']);
        $this->sofa = Product::create(['reference' => 'MA-1', 'name' => 'Canapé', 'price' => 300000, 'status' => 'available', 'low_stock_threshold' => 1]);
        $this->chair = Product::create(['reference' => 'MA-2', 'name' => 'Chaise', 'price' => 40000, 'status' => 'available', 'low_stock_threshold' => 2]);
        $this->customer = Customer::create(['name' => 'Aya Koné', 'phone' => '+2250700000001', 'city' => 'Yamoussoukro']);
        ExpenseCategory::create(['name' => 'Transport']);

        $stock = app(StockService::class);
        $stock->move($this->sofa->id, $this->yam->id, 5, StockMovementType::In);
        $stock->move($this->chair->id, $this->yam->id, 10, StockMovementType::In);
    }

    protected function user(Role $role, ?Store $store = null): User
    {
        return User::factory()->create(['role' => $role, 'store_id' => $role === Role::Direction ? null : ($store ?? $this->yam)->id]);
    }

    protected function qty(Product $product, Store $store): int
    {
        return (int) $product->stocks()->where('store_id', $store->id)->value('quantity');
    }
}
