<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SyncConflict;
use Tests\TestCase;

class WebsiteApiTest extends TestCase
{
    private array $headers = ['X-API-KEY' => 'test-api-key'];

    private function payload(array $extra = []): array
    {
        return array_merge([
            'reference' => 'WEB-1001',
            'customer' => ['name' => 'Awa Ouattara', 'phone' => '0711111111', 'email' => 'awa@example.ci'],
            'items' => [['product_reference' => 'SAL-001', 'quantity' => 1, 'unit_price' => 195000]],
            'delivery' => ['address' => 'Cocody', 'city' => 'Yamoussoukro', 'fee' => 10000],
            'payment_method' => 'mobile_money',
            'amount_paid' => 205000,
        ], $extra);
    }

    public function test_api_key_is_required(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/products', ['X-API-KEY' => 'mauvaise-cle'])->assertUnauthorized();
        $this->getJson('/api/v1/products', $this->headers)->assertOk()->assertJsonPath('data.0.reference', 'SAL-001');
    }

    public function test_website_order_is_created_once(): void
    {
        $this->addStock($this->sofa, $this->yam, 2);

        $this->postJson('/api/v1/orders', $this->payload(), $this->headers)
            ->assertCreated()->assertJsonPath('duplicate', false)->assertJsonPath('data.reference', 'WEB-1001');
        $this->postJson('/api/v1/orders', $this->payload(), $this->headers)
            ->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertSame('site', $order->source);
        $this->assertSame('WEB-1001', $order->external_reference);
        $this->assertSame($this->yam->id, $order->store_id);
        $this->assertEquals(205000, (float) $order->total);
        $this->assertNotNull($order->delivery);
        $this->assertSame(1, $this->stockOf($this->sofa, $this->yam));

        $this->getJson('/api/v1/orders/WEB-1001', $this->headers)->assertOk()->assertJsonPath('data.status', 'en_preparation');
    }

    public function test_website_order_without_stock_is_flagged(): void
    {
        $this->postJson('/api/v1/orders', $this->payload(['reference' => 'WEB-2', 'store_code' => 'BKE']), $this->headers)
            ->assertCreated()->assertJsonPath('stock_warning', true);
        $this->assertSame('site_stock_insuffisant', SyncConflict::first()->type);
    }

    public function test_stocks_endpoint(): void
    {
        $this->addStock($this->sofa, $this->yam, 3);
        $this->addStock($this->sofa, $this->bke, 2);
        $this->getJson('/api/v1/stocks', $this->headers)->assertOk()->assertJsonPath('data.SAL-001.total', 5);
    }

    public function test_unknown_product_is_rejected(): void
    {
        $this->postJson('/api/v1/orders', $this->payload(['items' => [['product_reference' => 'NOPE', 'quantity' => 1]]]), $this->headers)
            ->assertStatus(422);
    }
}
