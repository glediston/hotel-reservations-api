<?php

namespace Tests\Feature;


use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

        protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function hotel(string $name = 'Hotel Teste'): Hotel
    {
        return Hotel::create(['name' => $name]);
    }

    public function test_lista_quartos_em_json(): void
    {
        $hotel = $this->hotel();
        Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Quarto 1');
    }

    public function test_cria_quarto(): void
    {
        $hotel = $this->hotel();

        $this->postJson('/api/rooms', ['hotel_id' => $hotel->id, 'name' => 'Suite Master'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Suite Master');

        $this->assertDatabaseHas('rooms', ['hotel_id' => $hotel->id, 'name' => 'Suite Master']);
    }

    public function test_nao_cria_quarto_com_hotel_inexistente(): void
    {
        $this->postJson('/api/rooms', ['hotel_id' => 999, 'name' => 'Quarto X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hotel_id']);
    }

    public function test_nao_cria_quarto_sem_nome(): void
    {
        $hotel = $this->hotel();

        $this->postJson('/api/rooms', ['hotel_id' => $hotel->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_nao_cria_nome_duplicado_no_mesmo_hotel(): void
    {
        $hotel = $this->hotel();
        Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->postJson('/api/rooms', ['hotel_id' => $hotel->id, 'name' => 'Quarto 1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_permite_mesmo_nome_em_hoteis_diferentes(): void
    {
        $hotelA = $this->hotel('Hotel A');
        $hotelB = $this->hotel('Hotel B');
        Room::create(['hotel_id' => $hotelA->id, 'name' => 'Quarto 1']);

        $this->postJson('/api/rooms', ['hotel_id' => $hotelB->id, 'name' => 'Quarto 1'])
            ->assertCreated();
    }

    public function test_mostra_um_quarto(): void
    {
        $hotel = $this->hotel();
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->getJson("/api/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $room->id)
            ->assertJsonPath('data.hotel.name', 'Hotel Teste');
    }

    public function test_quarto_inexistente_retorna_404_em_json(): void
    {
        $this->getJson('/api/rooms/999')->assertNotFound();
    }

    public function test_atualiza_nome_do_quarto(): void
    {
        $hotel = $this->hotel();
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->putJson("/api/rooms/{$room->id}", ['name' => 'Suite Luxo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Suite Luxo');

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'name' => 'Suite Luxo']);
    }

    public function test_exclui_quarto_sem_reservas(): void
    {
        $hotel = $this->hotel();
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->deleteJson("/api/rooms/{$room->id}")->assertNoContent();

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_nao_exclui_quarto_com_reservas(): void
    {
        $hotel = $this->hotel();
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);
        Reserve::create([
            'hotel_id' => $hotel->id,
            'room_id' => $room->id,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-03',
            'total' => 200,
        ]);

        $this->deleteJson("/api/rooms/{$room->id}")->assertStatus(409);

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }
}