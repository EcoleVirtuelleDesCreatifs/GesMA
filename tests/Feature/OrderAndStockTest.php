<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\StockTransfer;
use Tests\TestCase;

class OrderAndStockTest extends TestCase
{
    public function test_direct_sale_decrements_stock_and_is_delivered(): void
    {
        $this->addStock($this->sofa, $this->yam, 5);
        $seller = $this->user('vendeur', $this->yam);

        $this->actingAs($seller)->post('/commandes', [
            'mode' => 'direct',
            'customer' => ['name' => 'Kouadio Yao', 'phone' => '0700000001'],
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 2]],
            'payment_method' => 'especes',
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame('livree', $order->status);
        $this->assertEquals(400000, (float) $order->total);
        $this->assertEquals(400000, (float) $order->amount_paid);
        $this->assertSame($this->yam->id, $order->store_id);
        $this->assertSame(3, $this->stockOf($this->sofa, $this->yam));
    }

    public function test_order_is_refused_when_stock_is_insufficient(): void
    {
        $this->addStock($this->sofa, $this->yam, 1);
        $seller = $this->user('vendeur', $this->yam);

        $this->actingAs($seller)->from('/commandes/nouvelle')->post('/commandes', [
            'mode' => 'pickup',
            'customer' => ['name' => 'Client'],
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 3]],
        ])->assertRedirect('/commandes/nouvelle')->assertSessionHasErrors('business');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $this->stockOf($this->sofa, $this->yam));
    }

    public function test_delivery_order_and_cancellation_restores_stock_and_is_logged(): void
    {
        $this->addStock($this->chair, $this->yam, 10);
        $seller = $this->user('vendeur', $this->yam);
        $manager = $this->user('responsable', $this->yam);

        $this->actingAs($seller)->post('/commandes', [
            'mode' => 'delivery',
            'customer' => ['name' => 'Aya Konan', 'phone' => '0700000002'],
            'items' => [['product_id' => $this->chair->id, 'quantity' => 4]],
            'delivery' => ['address' => 'Quartier Habitat', 'fee' => 5000],
        ])->assertRedirect();

        $order = Order::first();
        $this->assertSame('en_preparation', $order->status);
        $this->assertNotNull($order->delivery);
        $this->assertEquals(125000, (float) $order->total);
        $this->assertSame(6, $this->stockOf($this->chair, $this->yam));

        // Un vendeur ne peut pas annuler
        $this->actingAs($seller)->put("/commandes/{$order->id}/statut", ['status' => 'annulee', 'reason' => 'x'])->assertForbidden();

        $this->actingAs($manager)->put("/commandes/{$order->id}/statut", ['status' => 'annulee', 'reason' => 'Client a renoncé'])->assertRedirect();
        $this->assertSame('annulee', $order->fresh()->status);
        $this->assertSame('annulee', $order->delivery->fresh()->status);
        $this->assertSame(10, $this->stockOf($this->chair, $this->yam));
        $this->assertTrue(ActivityLog::where('action', 'commande.annulation')->exists());
    }

    public function test_delivery_flow_updates_order_status_and_return_restocks(): void
    {
        $this->addStock($this->chair, $this->yam, 3);
        $seller = $this->user('vendeur', $this->yam);
        $driver = $this->user('logistique', $this->yam);

        $this->actingAs($seller)->post('/commandes', [
            'mode' => 'delivery',
            'customer' => ['name' => 'Moussa'],
            'items' => [['product_id' => $this->chair->id, 'quantity' => 2]],
            'delivery' => ['address' => 'Commerce'],
        ]);
        $delivery = Order::first()->delivery;

        $this->actingAs($driver)->put("/livraisons/{$delivery->id}/statut", ['status' => 'en_route'])->assertRedirect();
        $this->assertSame('expediee', $delivery->order->fresh()->status);

        $this->actingAs($driver)->put("/livraisons/{$delivery->id}/statut", ['status' => 'echec'])->assertSessionHasErrors('reason');
        $this->actingAs($driver)->put("/livraisons/{$delivery->id}/statut", ['status' => 'echec', 'reason' => 'Client absent'])->assertRedirect();
        $this->assertSame('non_livree', $delivery->order->fresh()->status);

        $this->actingAs($driver)->put("/livraisons/{$delivery->id}/statut", ['status' => 'retour', 'reason' => 'Refusé'])->assertRedirect();
        $this->assertSame('retournee', $delivery->order->fresh()->status);
        $this->assertSame(3, $this->stockOf($this->chair, $this->yam));
    }

    public function test_transfer_moves_stock_between_stores_with_trace(): void
    {
        $this->addStock($this->sofa, $this->yam, 4);
        $manager = $this->user('responsable', $this->yam);

        $this->actingAs($manager)->post('/stocks/transferts', [
            'product_id' => $this->sofa->id,
            'from_store_id' => $this->yam->id,
            'to_store_id' => $this->bke->id,
            'quantity' => 3,
        ])->assertRedirect('/stocks/transferts');

        $this->assertSame(1, $this->stockOf($this->sofa, $this->yam));
        $this->assertSame(3, $this->stockOf($this->sofa, $this->bke));
        $this->assertSame(1, StockTransfer::count());
        $this->assertTrue(ActivityLog::where('action', 'stock.transfert')->exists());
    }

    public function test_manager_cannot_transfer_from_another_store(): void
    {
        $this->addStock($this->sofa, $this->bke, 4);
        $manager = $this->user('responsable', $this->yam);

        $this->actingAs($manager)->post('/stocks/transferts', [
            'product_id' => $this->sofa->id,
            'from_store_id' => $this->bke->id,
            'to_store_id' => $this->yam->id,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_stock_correction_is_logged(): void
    {
        $this->addStock($this->sofa, $this->yam, 4);
        $manager = $this->user('responsable', $this->yam);

        $this->actingAs($manager)->post('/stocks/mouvements', [
            'mode' => 'correction',
            'product_id' => $this->sofa->id,
            'quantity' => 2,
            'reason' => 'Inventaire',
        ])->assertRedirect();

        $this->assertSame(2, $this->stockOf($this->sofa, $this->yam));
        $log = ActivityLog::where('action', 'stock.correction')->first();
        $this->assertNotNull($log);
        $this->assertSame(4, $log->properties['avant']);
    }

    public function test_only_direction_can_change_price_and_change_is_logged(): void
    {
        $manager = $this->user('responsable', $this->yam);
        $director = $this->user('direction');
        $payload = ['reference' => 'SAL-001', 'name' => 'Canapé', 'price' => 250000, 'is_available' => 1];

        $this->actingAs($manager)->put("/produits/{$this->sofa->id}", $payload)->assertForbidden();
        $this->actingAs($director)->put("/produits/{$this->sofa->id}", $payload)->assertRedirect();

        $this->assertEquals(250000, (float) $this->sofa->fresh()->price);
        $this->assertTrue(ActivityLog::where('action', 'produit.prix')->exists());
    }
}
