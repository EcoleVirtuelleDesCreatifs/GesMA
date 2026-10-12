<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function __construct(private OrderService $orders) {}

    public function updateStatus(Delivery $delivery, DeliveryStatus $to, ?User $user, ?string $reason = null, ?string $notes = null): Delivery
    {
        $from = $delivery->status;
        if ($from === $to) {
            return $delivery;
        }
        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw new BusinessRuleException(sprintf('Impossible de passer la livraison de « %s » à « %s ».', $from->label(), $to->label()));
        }
        if ($to->requiresReason() && ! $reason) {
            throw new BusinessRuleException('Veuillez indiquer le motif de l\'échec ou du retour.');
        }

        return DB::transaction(function () use ($delivery, $to, $user, $reason, $notes) {
            $delivery->status = $to;
            if ($to === DeliveryStatus::InTransit) {
                $delivery->departed_at = now();
            }
            if ($to === DeliveryStatus::Delivered) {
                $delivery->delivered_at = now();
            }
            if ($to->requiresReason()) {
                $delivery->failure_reason = $reason;
            }
            if ($notes) {
                $delivery->notes = trim(($delivery->notes ? $delivery->notes."\n" : '').$notes);
            }
            $delivery->save();

            $order = $delivery->order;
            $orderStatus = $to->orderStatus();
            if ($orderStatus && $order->status !== $orderStatus && $order->status->canTransitionTo($orderStatus)) {
                $this->orders->changeStatus($order, $orderStatus, $user, $reason ?? 'Livraison : '.$to->label(), true);
            }

            return $delivery->refresh();
        });
    }
}
