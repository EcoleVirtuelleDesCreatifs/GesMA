<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ApiClient;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SyncOperation;
use Illuminate\Support\Str;

class SyncAndApiTest extends GesmaTestCase
{
    private function op(string $type, array $payload): array
    {
        return ['uuid' => (string) Str::uuid(), 'type' => $type, 'payload' => $payload, 'created_at' => now()->toIso8601String()];
    }

    public function test_offline_operations_are_applied_once(): void
    {
        $user = $this->user(Role::Sales);
        $order = $this->op('order.create', [
            'uuid' => (string) Str::uuid(), 'store_id' => $this->yam->id,
            'customer' => ['uuid' => (string) Str::uuid(), 'name' => 'Nouveau client', 'phone' => '0102030405'],
            'items' => [['product_id' => $this->chair->id, 'quantity' => 2]],
        ]);
        $expense = $this->op('expense.create', [
            'uuid' => (string) Str::uuid(), 'spent_at' => now()->toDateString(), 'amount' => 5000,
            'expense_category_id' => 1, 'store_id' => $this->yam->id, 'description' => 'Carburant',
        ]);

        $this->actingAs($user)->postJson('/sync/push', ['operations' => [$order, $expense]])
            ->assertOk()->assertJsonPath('results.0.status', 'applied')->assertJsonPath('results.1.status', 'applied');

        // Renvoi (réponse perdue) : aucun doublon
        $this->postJson('/sync/push', ['operations' => [$order, $expense]])
            ->assertJsonPath('results.0.status', 'duplicate')->assertJsonPath('results.1.status', 'duplicate');

        $this->assertSame(1, Order::count());
        $this->assertSame('offline', Order::first()->source->value);
        $this->assertSame(1, Expense::count());
        $this->assertSame(8, $this->qty($this->chair, $this->yam));
    }

    public function test_stock_conflict_is_flagged_then_resolved_by_manager(): void
    {
        $op = $this->op('order.create', [
            'uuid' => (string) Str::uuid(), 'store_id' => $this->yam->id,
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 7]],
        ]);
        $this->actingAs($this->user(Role::Sales))->postJson('/sync/push', ['operations' => [$op]])
            ->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame(0, Order::count());

        $record = SyncOperation::first();
        $this->actingAs($this->user(Role::Manager))->post("/sync/{$record->id}/resolve", ['action' => 'apply'])->assertRedirect();
        $this->assertSame(1, Order::count());
        $this->assertSame(-2, $this->qty($this->sofa, $this->yam));
    }

    public function test_offline_operation_for_another_store_fails(): void
    {
        $op = $this->op('expense.create', [
            'uuid' => (string) Str::uuid(), 'spent_at' => now()->toDateString(), 'amount' => 5000,
            'expense_category_id' => 1, 'store_id' => $this->bke->id, 'description' => 'x',
        ]);
        $this->actingAs($this->user(Role::Sales))->postJson('/sync/push', ['operations' => [$op]])
            ->assertJsonPath('results.0.status', 'failed');
        $this->assertSame(0, Expense::count());
    }

    public function test_bootstrap_requires_authentication(): void
    {
        $this->getJson('/sync/bootstrap')->assertUnauthorized();
        $this->actingAs($this->user(Role::Sales))->getJson('/sync/bootstrap')
            ->assertOk()->assertJsonStructure(['csrf_token', 'user', 'stores', 'products', 'stock', 'customers', 'deliveries']);
    }

    public function test_website_api_creates_orders_without_duplicates(): void
    {
        [, $token] = ApiClient::issue('Site', $this->yam->id);
        $payload = [
            'external_reference' => 'WEB-1001',
            'customer' => ['name' => 'Client Web', 'phone' => '0707070707'],
            'items' => [['reference' => 'MA-2', 'quantity' => 2]],
            'delivery' => ['address' => 'Cocody', 'fee' => 5000],
        ];

        $this->postJson('/api/v1/orders', $payload)->assertUnauthorized();
        $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertCreated()->assertJsonPath('duplicate', false);
        $this->withToken($token)->postJson('/api/v1/orders', $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertSame('website', $order->source->value);
        $this->assertSame('WEB-1001', $order->external_reference);
        $this->assertNotNull($order->delivery);

        $this->withToken($token)->getJson('/api/v1/orders/WEB-1001')->assertJsonPath('data.status', 'preparing');
        $this->withToken($token)->getJson('/api/v1/stock')->assertOk()->assertJsonPath('data.1.stores.YAM', 8);
        $this->withToken($token)->getJson('/api/v1/products')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_offline_app_and_service_worker_are_public(): void
    {
        $this->get('/hors-ligne')->assertOk();
        $this->get('/sw.js')->assertOk()->assertHeader('content-type', 'application/javascript; charset=utf-8');
        $this->get('/manifest.webmanifest')->assertOk();
    }
}
