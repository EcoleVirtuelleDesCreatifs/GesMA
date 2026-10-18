<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderService;
use Tests\TestCase;

class AccessTest extends TestCase
{
    public function test_login_and_inactive_account(): void
    {
        $user = $this->user('vendeur');
        $this->post('/login', ['email' => $user->email, 'password' => 'password1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/commandes')->assertRedirect('/login');
    }

    public function test_each_profile_reaches_its_pages_only(): void
    {
        $seller = $this->user('vendeur');
        $logistic = $this->user('logistique');
        $manager = $this->user('responsable');
        $director = $this->user('direction');

        $this->actingAs($seller)->get('/commandes')->assertOk();
        $this->actingAs($seller)->get('/depenses')->assertForbidden();
        $this->actingAs($seller)->get('/utilisateurs')->assertForbidden();
        $this->actingAs($seller)->get('/')->assertRedirect(route('orders.index'));

        $this->actingAs($logistic)->get('/livraisons')->assertOk();
        $this->actingAs($logistic)->get('/clients')->assertForbidden();
        $this->actingAs($logistic)->get('/commandes/nouvelle')->assertForbidden();

        $this->actingAs($manager)->get('/')->assertOk();
        $this->actingAs($manager)->get('/depenses')->assertOk();
        $this->actingAs($manager)->get('/magasins')->assertForbidden();

        $this->actingAs($director)->get('/magasins')->assertOk();
        $this->actingAs($director)->get('/utilisateurs')->assertOk();
        $this->actingAs($director)->get('/historique')->assertOk();
    }

    public function test_store_data_is_separated(): void
    {
        $this->addStock($this->sofa, $this->bke, 2);
        $bkeSeller = $this->user('vendeur', $this->bke);
        [$order] = app(OrderService::class)->create([
            'store_id' => $this->bke->id,
            'customer' => ['name' => 'Client Bouaké'],
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 1]],
            'direct_sale' => true,
        ], $bkeSeller);

        $yamManager = $this->user('responsable', $this->yam);
        $this->actingAs($yamManager)->get("/commandes/{$order->id}")->assertForbidden();
        $this->actingAs($yamManager)->get('/commandes')->assertOk()->assertDontSee($order->number);

        $this->actingAs($this->user('direction'))->get('/commandes')->assertOk()->assertSee($order->number);
    }

    public function test_customer_deletion(): void
    {
        $customer = \App\Models\Customer::create(['store_id' => $this->yam->id, 'name' => 'Client À Supprimer']);

        // Vendeur et responsable : interdit. Direction : autorisée.
        $this->actingAs($this->user('vendeur', $this->yam))->delete("/clients/{$customer->id}")->assertForbidden();
        $this->actingAs($this->user('responsable', $this->yam))->delete("/clients/{$customer->id}")->assertForbidden();
        $this->actingAs($this->user('direction'))->delete("/clients/{$customer->id}")->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'client.suppression']);
    }

    public function test_customer_with_documents_cannot_be_deleted(): void
    {
        $customer = \App\Models\Customer::create(['store_id' => $this->yam->id, 'name' => 'Client Facturé']);
        app(\App\Services\DocumentService::class)->create('devis', [
            'customer_id' => $customer->id,
            'store_id' => $this->yam->id,
            'issued_at' => today()->toDateString(),
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1]],
        ], $this->user('direction'));

        $this->actingAs($this->user('direction'))->delete("/clients/{$customer->id}")->assertSessionHasErrors();
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_main_pages_render(): void
    {
        $this->addStock($this->sofa, $this->yam, 3);
        $director = $this->user('direction');
        app(OrderService::class)->create([
            'store_id' => $this->yam->id,
            'customer' => ['name' => 'Client Test', 'phone' => '0102030405'],
            'items' => [['product_id' => $this->sofa->id, 'quantity' => 1]],
            'delivery' => ['address' => 'Rue 1'],
        ], $director);
        $order = Order::first();

        foreach (['/', '/?period=custom&from=2026-01-01&to=2026-12-31', '/produits', "/produits/{$this->sofa->id}", '/produits/nouveau',
            '/stocks', '/stocks/mouvements', '/stocks/mouvements/nouveau', '/stocks/transferts', '/stocks/transferts/nouveau',
            '/clients', "/clients/{$order->customer_id}", '/clients/nouveau', '/commandes', '/commandes?period=today', '/commandes/nouvelle', "/commandes/{$order->id}",
            '/livraisons', "/livraisons/{$order->delivery->id}", '/documents', '/documents/nouveau?type=devis', '/depenses', '/depenses/nouvelle',
            '/rapports', '/conflits', '/magasins', '/magasins/nouveau', '/utilisateurs', '/utilisateurs/nouveau', '/historique',
            '/hors-ligne', '/hors-ligne/historique', '/mot-de-passe'] as $url) {
            $this->actingAs($director)->get($url)->assertOk();
        }
        $this->actingAs($director)->getJson('/hors-ligne/donnees')->assertOk()->assertJsonStructure(['csrf_token', 'products', 'customers', 'deliveries', 'stores', 'user']);
    }
}
