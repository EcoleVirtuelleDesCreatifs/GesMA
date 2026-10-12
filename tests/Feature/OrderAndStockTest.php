<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;

class OrderAndStockTest extends GesmaTestCase
{
    public function test_creating_an_order_reserves_stock_and_creates_delivery(): void
    {
        $this->actingAs($this->user(Role::Sales))->post('/commandes', [
            'type' => 'order',
            'customer_id' => $this->customer->id,
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 2]],
            'requires_delivery' => 1,
            'delivery' => ['address' => 'Quartier Habitat', 'fee' => 10000],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame(OrderStatus::Preparing, $order->status);
        $this->assertSame(610000, $order->total);
        $this->assertSame($this->yam->id, $order->store_id);
        $this->assertNotNull($order->delivery);
        $this->assertSame(3, $this->qty($this->sofa, $this->yam));
    }

    public function test_order_is_refused_when_stock_is_insufficient(): void
    {
        $this->actingAs($this->user(Role::Sales))->from('/commandes/nouvelle')->post('/commandes', [
            'type' => 'order',
            'customer_id' => $this->customer->id,
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 9]],
        ])->assertRedirect('/commandes/nouvelle')->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $this->qty($this->sofa, $this->yam));
    }

    public function test_sales_team_cannot_change_prices_but_manager_can_and_it_is_logged(): void
    {
        $item = ['product_id' => $this->chair->id, 'quantity' => 1, 'unit_price' => 1000];
        $this->actingAs($this->user(Role::Sales))->post('/commandes', ['type' => 'sale', 'items' => [$item]]);
        $this->assertSame(40000, Order::latest('id')->first()->total);

        $this->actingAs($this->user(Role::Manager))->post('/commandes', ['type' => 'sale', 'items' => [$item]]);
        $this->assertSame(1000, Order::latest('id')->first()->total);
        $this->assertTrue(ActivityLog::where('action', 'order.price_override')->exists());
    }

    public function test_cancellation_restocks_and_requires_permission_and_reason(): void
    {
        $this->actingAs($this->user(Role::Sales))->post('/commandes', [
            'type' => 'order', 'customer_id' => $this->customer->id,
            'items' => [['product_id' => $this->chair->id, 'quantity' => 4]],
        ]);
        $order = Order::first();
        $this->assertSame(6, $this->qty($this->chair, $this->yam));

        // L'équipe de vente ne peut pas annuler
        $this->post("/commandes/{$order->number}/statut", ['status' => 'cancelled', 'reason' => 'x'])->assertForbidden();

        $manager = $this->user(Role::Manager);
        $this->actingAs($manager)->post("/commandes/{$order->number}/statut", ['status' => 'cancelled'])->assertSessionHas('error');
        $this->actingAs($manager)->post("/commandes/{$order->number}/statut", ['status' => 'cancelled', 'reason' => 'Client a changé d\'avis']);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(10, $this->qty($this->chair, $this->yam));
        $this->assertTrue(ActivityLog::where('action', 'order.cancelled')->exists());
    }

    public function test_delivery_flow_updates_order_and_return_restocks(): void
    {
        $this->actingAs($this->user(Role::Sales))->post('/commandes', [
            'type' => 'order', 'customer_id' => $this->customer->id, 'requires_delivery' => 1,
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 1]],
            'delivery' => ['address' => 'Habitat'],
        ]);
        $delivery = Order::first()->delivery;
        $logistics = $this->user(Role::Logistics);

        $this->actingAs($logistics)->post("/livraisons/{$delivery->id}/statut", ['status' => 'in_transit']);
        $this->assertSame(OrderStatus::Shipped, $delivery->order->refresh()->status);

        $this->actingAs($logistics)->post("/livraisons/{$delivery->id}/statut", ['status' => 'returned', 'reason' => 'Refusé']);
        $this->assertSame(OrderStatus::Returned, $delivery->order->refresh()->status);
        $this->assertSame(5, $this->qty($this->sofa, $this->yam));
    }

    public function test_stock_transfer_updates_both_stores(): void
    {
        $this->actingAs($this->user(Role::Manager))->post('/transferts', [
            'from_store_id' => $this->yam->id, 'to_store_id' => $this->bke->id,
            'items' => [['product_id' => $this->chair->id, 'quantity' => 3]],
        ])->assertRedirect();

        $this->assertSame(7, $this->qty($this->chair, $this->yam));
        $this->assertSame(3, $this->qty($this->chair, $this->bke));
    }

    public function test_inventory_correction_is_logged(): void
    {
        $this->actingAs($this->user(Role::Manager))->post('/stocks/inventaire', [
            'store_id' => $this->yam->id, 'reason' => 'Inventaire', 'counts' => [$this->chair->id => 8],
        ]);
        $this->assertSame(8, $this->qty($this->chair, $this->yam));
        $this->assertTrue(ActivityLog::where('action', 'stock.correction')->exists());
    }

    public function test_store_data_is_isolated_between_stores(): void
    {
        $this->actingAs($this->user(Role::Sales))->post('/commandes', [
            'type' => 'sale', 'items' => [['product_id' => $this->chair->id, 'quantity' => 1]],
        ]);
        $order = Order::first();

        $this->actingAs($this->user(Role::Manager, $this->bke))->get("/commandes/{$order->number}")->assertForbidden();
        $this->actingAs($this->user(Role::Direction))->get("/commandes/{$order->number}")->assertOk();
    }

    public function test_dashboard_excludes_cancelled_orders_from_revenue(): void
    {
        $sales = $this->user(Role::Sales);
        $this->actingAs($sales)->post('/commandes', ['type' => 'sale', 'items' => [['product_id' => $this->chair->id, 'quantity' => 1]]]);
        $this->actingAs($sales)->post('/commandes', ['type' => 'order', 'customer_id' => $this->customer->id, 'items' => [['product_id' => $this->sofa->id, 'quantity' => 1]]]);
        $order = Order::where('type', 'order')->first();
        $this->actingAs($this->user(Role::Manager))->post("/commandes/{$order->number}/statut", ['status' => 'cancelled', 'reason' => 'test']);

        $this->actingAs($this->user(Role::Direction))->get('/')
            ->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['revenue'] === 40000 && $s['cancelled'] === 1 && $s['cancelled_amount'] === 300000);
    }

    public function test_quote_pdf_and_invoice_from_order(): void
    {
        $sales = $this->user(Role::Sales);
        $this->actingAs($sales)->post('/devis', [
            'customer_id' => $this->customer->id, 'issued_at' => now()->toDateString(),
            'lines' => [['product_id' => $this->sofa->id, 'quantity' => 1], ['designation' => 'Montage', 'quantity' => 1, 'unit_price' => 5000]],
        ])->assertRedirect();
        $quote = Quote::first();
        $this->assertSame(305000, $quote->total);
        $this->get("/devis/{$quote->number}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->post("/devis/{$quote->number}/commande")->assertRedirect();
        $order = Order::first();
        $this->post("/commandes/{$order->number}/facture")->assertRedirect();
        $this->assertStringStartsWith('FAC-', Invoice::first()->number);
    }
}
