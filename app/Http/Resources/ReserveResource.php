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

        $totalCents = (int) round($this->total * 100);
        $paidCents = (int) $this->payments->sum(fn ($p) => (int) round($p->value * 100));
        $balanceCents = $totalCents - $paidCents;

        return [
            'id' => $this->id,
            'hotel' => ['id' => $this->hotel->id, 'name' => $this->hotel->name],
            'room' => ['id' => $this->room->id, 'name' => $this->room->name],
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->dailies->count(),
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'paid' => number_format($paidCents / 100, 2, '.', ''),
            'balance' => number_format($balanceCents / 100, 2, '.', ''),
            'payment_status' => match (true) {
                $paidCents === 0 => 'pendente',
                $balanceCents > 0 => 'parcial',
                default => 'quitado',
            },
            'guests' => $this->guests->map(fn ($g) => [
                'name' => $g->name,
                'last_name' => $g->last_name,
                'phone' => $g->phone,
            ])->values(),
            'dailies' => $this->dailies->map(fn ($d) => [
                'date' => $d->date->toDateString(),
                'value' => $d->value,
            ])->values(),
            'payments' => $this->payments->map(fn ($p) => [
                'method' => $p->method,
                'method_name' => Payment::METHODS[$p->method] ?? null,
                'value' => $p->value,
            ])->values(),
        ];
    }
}