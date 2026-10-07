<?php

namespace App\Services;

use App\Exceptions\RoomUnavailableException;
use App\Models\Reserve;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    public function create(array $data): Reserve
    {
        // Transação: se algo der errado no meio, nada é gravado
        return DB::transaction(function () use ($data) {
            // Trava o quarto até o fim da transação: duas reservas ao mesmo tempo ficam em fila
            $room = Room::lockForUpdate()->findOrFail($data['room_id']);

            $checkIn = Carbon::parse($data['check_in']);
            $checkOut = Carbon::parse($data['check_out']);

            // Conflito: outra reserva que começa antes do meu check-out e termina depois do meu check-in
            $conflict = $room->reserves()
                ->whereDate('check_in', '<', $checkOut)
                ->whereDate('check_out', '>', $checkIn)
                ->first();

            if ($conflict) {
                // Essa exceção vira uma resposta 409 com a mensagem abaixo
                throw new RoomUnavailableException(sprintf(
                    'Este quarto já está reservado de %s a %s.',
                    $conflict->check_in->format('d/m/Y'),
                    $conflict->check_out->format('d/m/Y'),
                ));
            }

            // Uma diária por noite (o dia do check-out não conta)
            $dailies = [];

            for ($day = $checkIn->copy(); $day < $checkOut; $day->addDay()) {
                $dailies[] = ['date' => $day->toDateString(), 'value' => $data['daily_value']];
            }

            $reserve = Reserve::create([
                'hotel_id' => $room->hotel_id,
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'total' => round($data['daily_value'] * count($dailies), 2),
            ]);

            $reserve->guests()->createMany($data['guests']);
            $reserve->dailies()->createMany($dailies);

            Log::info("Reserva {$reserve->id} criada para o quarto {$room->id}.");

            return $reserve;
        });
    }
}
