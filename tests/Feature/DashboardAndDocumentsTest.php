<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Expense;
use App\Services\OrderService;
use App\Services\StatsService;
use App\Support\Period;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardAndDocumentsTest extends TestCase
{
    public function test_cancelled_and_returned_orders_are_excluded_from_revenue(): void
    {
        $this->addStock($this->chair, $this->yam, 20);
        $this->addStock($this->chair, $this->bke, 20);
        $director = $this->user('direction');
        $service = app(OrderService::class);

        $make = fn ($store, $qty, $direct = true) => $service->create([
            'store_id' => $store->id,
            'customer' => ['name' => 'C'.$qty.$store->code],
            'items' => [['product_id' => $this->chair->id, 'quantity' => $qty]],
            'direct_sale' => $direct,
            'delivery' => $direct ? null : ['address' => 'X'],
        ], $director)[0];

        $make($this->yam, 2);                                    // 60 000
        $make($this->bke, 1);                                    // 30 000
        $cancelled = $make($this->yam, 5, false);
        $service->changeStatus($cancelled, 'annulee', $director, 'test');
        $returned = $make($this->bke, 3);
        $service->changeStatus($returned, 'retournee', $director, 'défaut');

        Expense::create(['store_id' => $this->yam->id, 'spent_at' => today(), 'amount' => 10000, 'category' => 'transport']);

        $stats = app(StatsService::class)->dashboard($director, null, Period::fromRequest(new Request(['period' => 'month'])));
        $this->assertEquals(90000, $stats['revenue']);
        $this->assertEquals(10000, $stats['expenses']);
        $this->assertEquals(80000, $stats['result']);
        $this->assertSame(1, $stats['by_status']['annulee']['count']);
        $this->assertSame(1, $stats['by_status']['retournee']['count']);

        $yam = $stats['by_store']->firstWhere('store.id', $this->yam->id);
        $this->assertEquals(60000, $yam->revenue);
        $this->assertEquals(50000, $yam->result);

        // Un responsable ne voit que son magasin
        $manager = $this->user('responsable', $this->bke);
        $bkeStats = app(StatsService::class)->dashboard($manager, null, Period::fromRequest(new Request(['period' => 'month'])));
        $this->assertEquals(30000, $bkeStats['revenue']);
    }

    public function test_quote_invoice_and_pdf(): void
    {
        $this->addStock($this->sofa, $this->yam, 3);
        $seller = $this->user('vendeur', $this->yam);
        [$order] = app(OrderService::class)->create([
            'store_id' => $this->yam->id,
            'customer' => ['name' => 'Jean Koffi', 'phone' => '0712121212'],
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 1]],
            'direct_sale' => true,
        ], $seller);

        // Devis : le prix catalogue s'impose à un vendeur
        $this->actingAs($seller)->post('/documents', [
            'type' => 'devis',
            'customer_id' => $order->customer_id,
            'issued_at' => today()->toDateString(),
            'items' => [
                ['product_id' => $this->sofa->id, 'quantity' => 2, 'unit_price' => 1],
                ['description' => 'Montage', 'quantity' => 1, 'unit_price' => 15000],
            ],
        ])->assertRedirect();
        $quote = Document::where('type', 'devis')->first();
        $this->assertEquals(415000, (float) $quote->total);
        $this->assertStringStartsWith('DEV-', $quote->number);

        $this->actingAs($seller)->post("/documents/{$quote->id}/facturer")->assertRedirect();
        $this->assertSame(1, Document::where('type', 'facture')->where('source_document_id', $quote->id)->count());

        $this->actingAs($seller)->post("/commandes/{$order->id}/facture")->assertRedirect();
        $invoice = Document::where('order_id', $order->id)->first();
        $this->assertEquals(200000, (float) $invoice->total);

        $this->actingAs($seller)->get("/documents/{$invoice->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_document_can_be_deleted(): void
    {
        $seller = $this->user('vendeur', $this->yam);
        $customer = \App\Models\Customer::create(['store_id' => $this->yam->id, 'name' => 'Client', 'phone' => '0101010101']);
        $quote = app(\App\Services\DocumentService::class)->create('devis', [
            'customer_id' => $customer->id,
            'store_id' => $this->yam->id,
            'issued_at' => today()->toDateString(),
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1]],
        ], $seller);

        // Un vendeur ne peut pas supprimer
        $this->actingAs($seller)->delete("/documents/{$quote->id}")->assertForbidden();
        $this->assertDatabaseHas('documents', ['id' => $quote->id]);

        // Le responsable peut supprimer : lignes en cascade, journal d'activité
        $manager = $this->user('responsable', $this->yam);
        $this->actingAs($manager)->delete("/documents/{$quote->id}")->assertRedirect(route('documents.index'));
        $this->assertDatabaseMissing('documents', ['id' => $quote->id]);
        $this->assertDatabaseMissing('document_items', ['document_id' => $quote->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'document.suppression']);
    }

    public function test_reports_pdf(): void
    {
        $director = $this->user('direction');
        foreach (['ventes', 'commandes', 'stocks', 'depenses'] as $type) {
            $this->actingAs($director)->get('/rapports/pdf?type='.$type.'&period=month')->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_new_store_gets_stock_rows(): void
    {
        $director = $this->user('direction');
        $this->actingAs($director)->post('/magasins', ['code' => 'beo', 'name' => 'Magasin Béoumi', 'city' => 'Béoumi', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('stores', ['code' => 'BEO']);
        $this->assertDatabaseCount('stocks', 6);
    }
}
