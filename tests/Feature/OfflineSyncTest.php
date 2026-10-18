<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SyncConflict;
use App\Services\OrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    private function orderOp(string $uuid, int $qty, array $extra = []): array
    {
        return [
            'uuid' => $uuid,
            'type' => 'order',
            'recorded_at' => now()->subHour()->toIso8601String(),
            'payload' => array_merge([
                'store_id' => $this->yam->id,
                'customer' => ['name' => 'Client hors ligne', 'phone' => '0799999999', 'client_uuid' => (string) Str::uuid()],
                'items' => [['product_id' => $this->sofa->id, 'quantity' => $qty, 'unit_price' => 190000]],
                'direct_sale' => true,
                'payment_method' => 'especes',
                'ordered_at' => now()->subHour()->toIso8601String(),
            ], $extra),
        ];
    }

    public function test_offline_order_is_applied_once_even_if_sent_twice(): void
    {
        $this->addStock($this->sofa, $this->yam, 5);
        $seller = $this->user('vendeur', $this->yam);
        $op = $this->orderOp((string) Str::uuid(), 2);

        $first = $this->actingAs($seller)->postJson('/sync', ['device_id' => 'tel-1', 'operations' => [$op]])->assertOk()->json('results.0');
        $second = $this->actingAs($seller)->postJson('/sync', ['device_id' => 'tel-1', 'operations' => [$op]])->assertOk()->json('results.0');

        $this->assertSame('appliquee', $first['status']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Customer::count());
        $order = Order::first();
        $this->assertSame('hors_ligne', $order->source);
        $this->assertEquals(380000, (float) $order->total); // prix pratiqué hors connexion
        $this->assertSame(3, $this->stockOf($this->sofa, $this->yam));
    }

    public function test_insufficient_stock_creates_conflict_to_review(): void
    {
        $this->addStock($this->sofa, $this->yam, 1);
        $seller = $this->user('vendeur', $this->yam);

        $result = $this->actingAs($seller)->postJson('/sync', ['operations' => [$this->orderOp((string) Str::uuid(), 3)]])->json('results.0');

        $this->assertSame('conflit', $result['status']);
        $this->assertSame(1, Order::count());
        $this->assertSame(-2, $this->stockOf($this->sofa, $this->yam));
        $conflict = SyncConflict::first();
        $this->assertSame('stock_insuffisant', $conflict->type);

        $manager = $this->user('responsable', $this->yam);
        $this->actingAs($manager)->get('/conflits')->assertOk()->assertSee(Order::first()->number);
        $this->actingAs($manager)->put("/conflits/{$conflict->id}", ['resolution_note' => 'Stock corrigé'])->assertRedirect();
        $this->assertNotNull($conflict->fresh()->resolved_at);
    }

    public function test_offline_expense_and_permissions(): void
    {
        $manager = $this->user('responsable', $this->yam);
        $seller = $this->user('vendeur', $this->yam);
        $op = ['uuid' => (string) Str::uuid(), 'type' => 'expense', 'payload' => [
            'store_id' => $this->yam->id, 'spent_at' => today()->toDateString(), 'amount' => 15000, 'category' => 'transport', 'description' => 'Carburant',
        ]];

        $r = $this->actingAs($seller)->postJson('/sync', ['operations' => [$op]])->json('results.0');
        $this->assertSame('rejetee', $r['status']);

        $op['uuid'] = (string) Str::uuid();
        $r = $this->actingAs($manager)->postJson('/sync', ['operations' => [$op]])->json('results.0');
        $this->assertSame('appliquee', $r['status']);
        $this->assertSame(1, Expense::count());
    }

    public function test_user_cannot_sync_into_another_store(): void
    {
        $this->addStock($this->sofa, $this->bke, 5);
        $seller = $this->user('vendeur', $this->yam);
        $op = $this->orderOp((string) Str::uuid(), 1, ['store_id' => $this->bke->id]);

        $r = $this->actingAs($seller)->postJson('/sync', ['operations' => [$op]])->json('results.0');
        $this->assertSame('rejetee', $r['status']);
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_status_changed_meanwhile_is_flagged(): void
    {
        $this->addStock($this->chair, $this->yam, 5);
        $manager = $this->user('responsable', $this->yam);
        $driver = $this->user('logistique', $this->yam);
        [$order] = app(OrderService::class)->create([
            'store_id' => $this->yam->id,
            'customer' => ['name' => 'Client'],
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1]],
            'delivery' => ['address' => 'Rue 2'],
        ], $manager);

        // Pendant la coupure, la commande est annulée au magasin
        app(OrderService::class)->changeStatus($order, 'annulee', $manager, 'Annulée par téléphone');

        $r = $this->actingAs($driver)->postJson('/sync', ['operations' => [[
            'uuid' => (string) Str::uuid(), 'type' => 'delivery_status',
            'payload' => ['delivery_id' => $order->delivery->id, 'status' => 'livree', 'expected_status' => 'en_route'],
        ]]])->json('results.0');

        $this->assertSame('conflit', $r['status']);
        $this->assertSame('annulee', $order->fresh()->status);
        $this->assertSame('statut_modifie', SyncConflict::first()->type);
    }

    public function test_offline_delivery_update_is_applied(): void
    {
        $this->addStock($this->chair, $this->yam, 5);
        $driver = $this->user('logistique', $this->yam);
        [$order] = app(OrderService::class)->create([
            'store_id' => $this->yam->id,
            'customer' => ['name' => 'Client'],
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1]],
            'delivery' => ['address' => 'Rue 3'],
        ], $driver);

        $ops = [
            ['uuid' => (string) Str::uuid(), 'type' => 'delivery_status', 'payload' => ['delivery_id' => $order->delivery->id, 'status' => 'en_route', 'expected_status' => 'preparation']],
            ['uuid' => (string) Str::uuid(), 'type' => 'delivery_status', 'payload' => ['delivery_id' => $order->delivery->id, 'status' => 'livree', 'expected_status' => 'en_route']],
        ];
        $results = $this->actingAs($driver)->postJson('/sync', ['operations' => $ops])->json('results');

        $this->assertSame(['appliquee', 'appliquee'], array_column($results, 'status'));
        $this->assertSame('livree', $order->fresh()->status);
    }
}
