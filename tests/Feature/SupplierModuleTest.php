<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierReturn;
use App\Services\PurchaseService;
use Tests\TestCase;

class SupplierModuleTest extends TestCase
{
    private function buyer()
    {
        return $this->user('achats', $this->yam);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['code' => 'FOUR-0001', 'name' => 'Sofa Import SARL', 'phone' => '0701010101', 'is_active' => true]);
    }

    private function makeOrder(Supplier $supplier): PurchaseOrder
    {
        return app(PurchaseService::class)->createOrder([
            'supplier_id' => $supplier->id,
            'store_id' => $this->yam->id,
            'ordered_at' => today(),
            'expected_at' => today()->addDays(7),
            'items' => [['product_id' => $this->chair->id, 'quantity' => 100, 'unit_price' => 10000]],
        ], $this->buyer());
    }

    public function test_supplier_crud_and_archive(): void
    {
        $buyer = $this->buyer();

        // Création avec code auto
        $this->actingAs($buyer)->post('/fournisseurs', [
            'name' => 'Matelas Pro', 'phone' => '0799140356', 'type' => 'fabricant', 'is_active' => 1,
        ])->assertRedirect();
        $supplier = Supplier::first();
        $this->assertStringStartsWith('FOUR-', $supplier->code);

        // Un vendeur ne gère pas les fournisseurs
        $this->actingAs($this->user('vendeur', $this->yam))->post('/fournisseurs', ['name' => 'X'])->assertForbidden();

        // Archivage au lieu de suppression
        $this->actingAs($buyer)->delete("/fournisseurs/{$supplier->id}")->assertRedirect();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_product_association_keeps_sale_price(): void
    {
        $buyer = $this->buyer();
        $supplier = $this->makeSupplier();

        $this->actingAs($buyer)->post("/fournisseurs/{$supplier->id}/produits", [
            'product_id' => $this->chair->id, 'purchase_price' => 12000, 'min_quantity' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('supplier_products', ['supplier_id' => $supplier->id, 'product_id' => $this->chair->id, 'purchase_price' => 12000]);
        // Le prix de vente n'est pas modifié
        $this->assertEquals(30000, (float) $this->chair->fresh()->price);
    }

    public function test_purchase_order_lifecycle_and_stock_receipt(): void
    {
        $buyer = $this->buyer();
        $manager = $this->user('responsable', $this->yam);
        $director = $this->user('direction');
        $supplier = $this->makeSupplier();
        $order = $this->makeOrder($supplier);
        $this->assertEquals(1000000, (float) $order->total);
        $this->assertSame('brouillon', $order->status);
        $this->assertSame(0, $this->stockOf($this->chair, $this->yam));

        // Soumission puis validation (Direction uniquement)
        $this->actingAs($buyer)->put("/achats/{$order->id}/statut", ['status' => 'en_attente'])->assertRedirect();
        $this->actingAs($buyer)->put("/achats/{$order->id}/statut", ['status' => 'validee'])->assertForbidden();
        $this->actingAs($director)->put("/achats/{$order->id}/statut", ['status' => 'validee'])->assertRedirect();
        $this->actingAs($buyer)->put("/achats/{$order->id}/statut", ['status' => 'envoyee'])->assertRedirect();

        // Première réception partielle : 60 acceptés -> stock +60, statut partielle
        $item = $order->items()->first();
        $this->actingAs($manager)->post("/achats/{$order->id}/recevoir", [
            'items' => [$item->id => ['received' => 60]],
        ])->assertRedirect();
        $this->assertSame(60, $this->stockOf($this->chair, $this->yam));
        $this->assertSame('partielle', $order->fresh()->status);
        $this->assertDatabaseHas('stock_movements', ['type' => 'approvisionnement', 'quantity' => 60, 'product_id' => $this->chair->id]);

        // Deuxième réception : 40 acceptés -> commande clôturée
        $this->actingAs($manager)->post("/achats/{$order->id}/recevoir", [
            'items' => [$item->id => ['received' => 40]],
        ])->assertRedirect();
        $this->assertSame(100, $this->stockOf($this->chair, $this->yam));
        $this->assertSame('receptionnee', $order->fresh()->status);
        $this->assertSame(2, $order->receipts()->count());

        // Réception excédentaire impossible (déjà tout reçu)
        $this->actingAs($manager)->post("/achats/{$order->id}/recevoir", [
            'items' => [$item->id => ['received' => 5]],
        ]);
        $this->assertSame(100, $this->stockOf($this->chair, $this->yam));
    }

    public function test_invoice_payments_and_debt(): void
    {
        $accountant = $this->user('comptable', $this->yam);
        $supplier = $this->makeSupplier();
        $order = $this->makeOrder($supplier);

        $this->actingAs($accountant)->post('/factures-fournisseurs', [
            'supplier_id' => $supplier->id,
            'number' => 'F-2026-77',
            'purchase_order_id' => $order->id,
            'invoiced_at' => today()->toDateString(),
            'due_at' => today()->addDays(30)->toDateString(),
            'total' => 1000000,
        ])->assertRedirect();
        $invoice = SupplierInvoice::first();
        $this->assertSame(1000000.0, $invoice->balance());
        $this->assertSame(1000000.0, $supplier->debt());

        // Deux règlements : 300 000 + 200 000 -> solde 500 000
        $this->actingAs($accountant)->post("/factures-fournisseurs/{$invoice->id}/reglement", [
            'paid_at' => today()->toDateString(), 'amount' => 300000, 'method' => 'virement',
        ])->assertRedirect();
        $this->actingAs($accountant)->post("/factures-fournisseurs/{$invoice->id}/reglement", [
            'paid_at' => today()->toDateString(), 'amount' => 200000, 'method' => 'especes',
        ])->assertRedirect();
        $this->assertSame(500000.0, $invoice->fresh()->balance());
        $this->assertSame(500000.0, $supplier->debt());

        // Chaque paiement alimente la trésorerie sans doublon
        $this->assertSame(2, Expense::where('category', 'achat_marchandises')->count());
        $this->assertSame(2, $invoice->payments()->whereNotNull('expense_id')->count());

        // Paiement excédentaire refusé
        $this->actingAs($accountant)->post("/factures-fournisseurs/{$invoice->id}/reglement", [
            'paid_at' => today()->toDateString(), 'amount' => 600000, 'method' => 'especes',
        ]);
        $this->assertSame(500000.0, $invoice->fresh()->balance());
    }

    public function test_supplier_return_exits_stock_once(): void
    {
        $buyer = $this->buyer();
        $supplier = $this->makeSupplier();
        $this->addStock($this->chair, $this->yam, 20);

        $this->actingAs($buyer)->post('/retours-fournisseurs', [
            'supplier_id' => $supplier->id,
            'returned_at' => today()->toDateString(),
            'decision' => 'avoir',
            'items' => [
                ['product_id' => $this->chair->id, 'quantity' => 5, 'from_stock' => 1, 'reason' => 'Défectueux'],
                ['product_id' => $this->sofa->id, 'quantity' => 2, 'reason' => 'Refusé avant réception'],
            ],
        ])->assertRedirect();

        // -5 en stock, le canapé refusé avant entrée ne touche pas le stock
        $this->assertSame(15, $this->stockOf($this->chair, $this->yam));
        $this->assertSame(0, $this->stockOf($this->sofa, $this->yam));
        $this->assertDatabaseHas('stock_movements', ['type' => 'retour_fournisseur', 'quantity' => -5]);
        $this->assertSame('declaree', SupplierReturn::first()->status);
    }

    public function test_purchase_order_pdf_and_dashboard(): void
    {
        $buyer = $this->buyer();
        $order = $this->makeOrder($this->makeSupplier());

        $this->actingAs($buyer)->get("/achats/{$order->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($buyer)->get('/fournisseurs/tableau-de-bord')->assertOk();
        $this->actingAs($this->user('vendeur', $this->yam))->get('/fournisseurs')->assertForbidden();
    }
}
