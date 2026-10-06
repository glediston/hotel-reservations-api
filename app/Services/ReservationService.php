<?php

namespace App\Services;

use App\Exceptions\RoomUnavailableException;
use App\Models\Reserve;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function create(array $data): Reserve
    {
        return DB::transaction(function () use ($data) {
            // Trava o quarto: duas requisições simultâneas ficam em fila
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();

            if ((int) $room->hotel_id !== (int) $data['hotel_id']) {
                throw ValidationException::withMessages([
                    'room_id' => 'O quarto informado não pertence ao hotel escolhido.',
                ]);
            }

            $checkIn = Carbon::parse($data['check_in'])->startOfDay();
            $checkOut = Carbon::parse($data['check_out'])->startOfDay();

            // Conflito: começa antes do meu check-out E termina depois do meu check-in
            $conflict = Reserve::where('room_id', $room->id)
            ->whereDate('check_in', '<', $checkOut->toDateString())
            ->whereDate('check_out', '>', $checkIn->toDateString())
            ->first();

            if ($conflict) {
                throw new RoomUnavailableException(sprintf(
                    'Este quarto já está reservado de %s a %s.',
                    $conflict->check_in->format('d/m/Y'),
                    $conflict->check_out->format('d/m/Y'),
                ));
            }

            // Dinheiro em centavos (inteiros), para evitar erro de arredondamento
            $dailyCents = (int) round($data['daily_value'] * 100);
            $dailies = [];

            for ($day = $checkIn->copy(); $day < $checkOut; $day->addDay()) {
                $dailies[] = [
                    'date' => $day->toDateString(),
                    'value' => $this->money($dailyCents),
                ];
            }

            $totalCents = $dailyCents * count($dailies);

            $payments = $data['payments'] ?? [];
            $paidCents = array_sum(array_map(
                fn (array $p) => (int) round($p['value'] * 100),
                $payments,
            ));

            if ($paidCents > $totalCents) {
                throw ValidationException::withMessages([
                    'payments' => sprintf(
                        'O valor pago (R$ %s) é maior que o total da reserva (R$ %s).',
                        number_format($paidCents / 100, 2, ',', '.'),
                        number_format($totalCents / 100, 2, ',', '.'),
                    ),
                ]);
            }

            $reserve = Reserve::create([
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'total' => $this->money($totalCents),
            ]);

            $reserve->guests()->createMany($data['guests']);
            $reserve->dailies()->createMany($dailies);

            if ($payments !== []) {
                $reserve->payments()->createMany($payments);
            }

            return $reserve->load(['hotel', 'room', 'guests', 'dailies', 'payments']);
        });
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}