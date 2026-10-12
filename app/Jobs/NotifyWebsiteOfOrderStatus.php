<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Informe le site web du changement de statut d'une commande qu'il a envoyée (signature HMAC-SHA256). */
class NotifyWebsiteOfOrderStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $url = config('gesma.website.webhook_url');
        $order = Order::with('delivery')->find($this->orderId);
        if (! $url || ! $order) {
            return;
        }

        $body = json_encode([
            'event' => 'order.status_changed',
            'external_reference' => $order->external_reference,
            'number' => $order->number,
            'status' => $order->status->value,
            'delivery_status' => $order->delivery?->status->value,
            'reason' => $order->status_reason,
            'updated_at' => $order->updated_at->toIso8601String(),
        ]);

        Http::timeout(10)
            ->withHeaders(['X-Gesma-Signature' => hash_hmac('sha256', $body, (string) config('gesma.website.webhook_secret'))])
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }
}
