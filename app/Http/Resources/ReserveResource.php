<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReserveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['hotel', 'room', 'guests', 'dailies', 'payments']);

        return [
            'id' => $this->id,
            'hotel' => ['id' => $this->hotel->id, 'name' => $this->hotel->name],
            'room' => ['id' => $this->room->id, 'name' => $this->room->name],
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->dailies->count(),
            'total' => $this->total,
            'paid' => number_format($this->paid(), 2, '.', ''),
            'balance' => number_format($this->balance(), 2, '.', ''),
            'payment_status' => $this->paymentStatus(),
            'guests' => $this->guests->map(fn ($guest) => [
                'name' => $guest->name,
                'last_name' => $guest->last_name,
                'phone' => $guest->phone,
            ]),
            'dailies' => $this->dailies->map(fn ($daily) => [
                'date' => $daily->date->toDateString(),
                'value' => $daily->value,
            ]),
            'payments' => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'method_name' => Payment::METHODS[$payment->method] ?? null,
                'value' => $payment->value,
                'paid_at' => $payment->created_at,
            ]),
        ];
    }
}
