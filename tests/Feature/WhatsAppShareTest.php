<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WhatsAppShareTest extends TestCase
{
    private function makeDocument(string $type = 'devis', ?string $phone = '07 99 14 03 56')
    {
        $seller = $this->user('vendeur', $this->yam);
        $customer = \App\Models\Customer::create([
            'store_id' => $this->yam->id,
            'name' => 'Awa Koné',
            'phone' => $phone,
        ]);

        return app(\App\Services\DocumentService::class)->create($type, [
            'customer_id' => $customer->id,
            'store_id' => $this->yam->id,
            'issued_at' => today()->toDateString(),
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1]],
        ], $seller);
    }

    public function test_whatsapp_number_keeps_ivorian_leading_zero(): void
    {
        $this->assertSame('2250799140356', whatsapp_number('07 99 14 03 56'));
        $this->assertSame('2250799140356', whatsapp_number('+225 07 99 14 03 56'));
        $this->assertSame('2250799140356', whatsapp_number('00225 0799140356'));
        $this->assertSame('2250799140356', whatsapp_number('2250799140356'));
        $this->assertNull(whatsapp_number(null));
        $this->assertNull(whatsapp_number(''));
    }

    public function test_whatsapp_link_contains_phone_message_and_signed_pdf(): void
    {
        $document = $this->makeDocument('devis');

        $link = whatsapp_document_link($document->fresh('customer'));

        $this->assertStringStartsWith('https://wa.me/2250799140356?text=', $link);
        $decoded = rawurldecode(substr($link, strlen('https://wa.me/2250799140356?text=')));
        $this->assertStringContainsString('Awa Koné', $decoded);
        $this->assertStringContainsString('devis '.$document->number, $decoded);
        $this->assertStringContainsString('/documents/'.$document->id.'/partage-pdf', $decoded);
        $this->assertStringContainsString('signature=', $decoded);
    }

    public function test_whatsapp_link_without_customer_phone(): void
    {
        $document = $this->makeDocument('devis', null);

        $link = whatsapp_document_link($document->fresh('customer'));

        // Pas de numéro : l'utilisateur choisit le contact dans WhatsApp
        $this->assertStringStartsWith('https://wa.me/?text=', $link);
    }

    public function test_signed_pdf_is_publicly_accessible(): void
    {
        $document = $this->makeDocument('facture');

        $url = URL::temporarySignedRoute('documents.share.pdf', now()->addHour(), ['document' => $document->id]);

        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_signed_pdf_rejects_invalid_and_expired_signatures(): void
    {
        $document = $this->makeDocument('devis');

        // Sans signature
        $this->get("/documents/{$document->id}/partage-pdf")->assertForbidden();

        // Signature falsifiée
        $this->get("/documents/{$document->id}/partage-pdf?expires=9999999999&signature=bad")->assertForbidden();

        // Signature valide mais expirée
        $expired = URL::temporarySignedRoute('documents.share.pdf', now()->subMinute(), ['document' => $document->id]);
        $this->get($expired)->assertForbidden();
    }
}
