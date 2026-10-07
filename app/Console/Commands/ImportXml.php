<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;

class ImportXml extends Command
{
    protected $signature = 'import:xml {--path=database/xml : Pasta com hotels.xml, rooms.xml e reserves.xml}';

    protected $description = 'Importa hotéis, quartos e reservas a partir de arquivos XML';

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        try {
            $hotels = $this->load("$path/hotels.xml");
            $rooms = $this->load("$path/rooms.xml");
            $reserves = $this->load("$path/reserves.xml");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            Log::error('[import:xml] '.$e->getMessage());

            return self::FAILURE;
        }

        $this->importHotels($hotels);
        $this->importRooms($rooms);
        $this->importReserves($reserves);

        $this->info('Importação concluída.');

        return self::SUCCESS;
    }

    private function load(string $file): SimpleXMLElement
    {
        if (! is_file($file)) {
            throw new RuntimeException("Arquivo não encontrado: $file");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);

        if ($xml === false) {
            throw new RuntimeException("XML inválido: $file");
        }

        return $xml;
    }

    private function importHotels(SimpleXMLElement $xml): void
    {
        foreach ($xml->Hotel as $node) {
            Hotel::updateOrCreate(
                ['external_id' => (int) $node['id']],
                ['name' => (string) $node->Name],
            );
        }
    }

    private function importRooms(SimpleXMLElement $xml): void
    {
        foreach ($xml->Room as $node) {
            $id = (int) $node['id'];
            $hotel = Hotel::where('external_id', (int) $node['hotelCode'])->first();

            if (! $hotel) {
                $this->skip("Quarto $id: hotel não encontrado");

                continue;
            }

            Room::updateOrCreate(
                ['external_id' => $id],
                ['hotel_id' => $hotel->id, 'name' => (string) $node->Name],
            );
        }
    }

    private function importReserves(SimpleXMLElement $xml): void
    {
        foreach ($xml->Reserve as $node) {
            $id = (int) $node['id'];
            $hotel = Hotel::where('external_id', (int) $node['hotelCode'])->first();
            $room = Room::where('external_id', (int) $node['roomCode'])->first();

            if (! $hotel || ! $room || (int) $room->hotel_id !== (int) $hotel->id) {
                $this->skip("Reserva $id: hotel ou quarto inválido");

                continue;
            }

            $checkIn = (string) $node->CheckIn;
            $checkOut = (string) $node->CheckOut;

            DB::transaction(function () use ($node, $id, $hotel, $room, $checkIn, $checkOut) {
                $reserve = Reserve::updateOrCreate(
                    ['external_id' => $id],
                    [
                        'hotel_id' => $hotel->id,
                        'room_id' => $room->id,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'total' => (string) $node->Total,
                    ],
                );

                // Recria os filhos para a importação poder rodar várias vezes
                $reserve->guests()->delete();
                $reserve->dailies()->delete();
                $reserve->payments()->delete();

                foreach ($node->xpath('Guests/Guest') ?: [] as $guest) {
                    $reserve->guests()->create([
                        'name' => (string) $guest->Name,
                        'last_name' => (string) $guest->LastName,
                        'phone' => (string) $guest->Phone,
                    ]);
                }

                $sum = 0;

                foreach ($node->xpath('Dailies/Daily') ?: [] as $daily) {
                    $date = (string) $daily->Date;

                    if ($date < $checkIn || $date >= $checkOut) {
                        $this->skip("Reserva $id: diária de $date fora do período, ignorada");

                        continue;
                    }

                    $reserve->dailies()->create([
                        'date' => $date,
                        'value' => (string) $daily->Value,
                    ]);

                    $sum += (float) $daily->Value;
                }

                if (round($sum, 2) != round((float) $node->Total, 2)) {
                    $this->skip("Reserva $id: soma das diárias difere do total informado");
                }

                foreach ($node->xpath('Payments/Payment') ?: [] as $payment) {
                    $reserve->payments()->create([
                        'method' => (int) $payment->Method,
                        'value' => (string) $payment->Value,
                    ]);
                }
            });
        }
    }

    private function skip(string $message): void
    {
        $this->warn($message);
        Log::warning("[import:xml] $message");
    }
}
