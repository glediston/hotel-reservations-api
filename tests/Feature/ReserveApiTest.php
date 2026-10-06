<?php

namespace Tests\Feature;


use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Models\Hotel;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReserveApiTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());

        $this->hotel = Hotel::create(['name' => 'Hotel Teste']);
        $this->room = Room::create(['hotel_id' => $this->hotel->id, 'name' => 'Quarto 1']);
    }

    private function date(int $daysFromNow): Carbon
    {
        return now()->addDays($daysFromNow)->startOfDay();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'check_in' => $this->date(10)->toDateString(),
            'check_out' => $this->date(13)->toDateString(),
            'daily_value' => 100,
            'guests' => [
                ['name' => 'Maria', 'last_name' => 'Souza', 'phone' => '5571999999999'],
            ],
        ], $overrides);
    }

    public function test_cria_reserva_e_calcula_total_e_diarias(): void
    {
        $this->postJson('/api/reserves', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.total', '300.00')
            ->assertJsonPath('data.nights', 3)
            ->assertJsonPath('data.payment_status', 'pendente');

        $this->assertDatabaseCount('reserves', 1);
        $this->assertDatabaseCount('dailies', 3);
        $this->assertDatabaseCount('guests', 1);
    }

    public function test_pagamento_parcial_gera_saldo_e_status_parcial(): void
    {
        $this->postJson('/api/reserves', $this->payload([
            'payments' => [['method' => 2, 'value' => 100]],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.paid', '100.00')
            ->assertJsonPath('data.balance', '200.00')
            ->assertJsonPath('data.payment_status', 'parcial')
            ->assertJsonPath('data.payments.0.method_name', 'pix');
    }

    public function test_pagamento_total_fica_quitado(): void
    {
        $this->postJson('/api/reserves', $this->payload([
            'payments' => [
                ['method' => 1, 'value' => 100],
                ['method' => 2, 'value' => 200],
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.payment_status', 'quitado');
    }

    public function test_rejeita_pagamento_maior_que_o_total(): void
    {
        $this->postJson('/api/reserves', $this->payload([
            'payments' => [['method' => 1, 'value' => 400]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payments']);

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_rejeita_reserva_em_quarto_ocupado(): void
    {
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        $this->postJson('/api/reserves', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', sprintf(
                'Este quarto já está reservado de %s a %s.',
                $this->date(10)->format('d/m/Y'),
                $this->date(13)->format('d/m/Y'),
            ));

        $this->assertDatabaseCount('reserves', 1);
    }

    public function test_rejeita_periodo_que_se_sobrepoe_parcialmente(): void
    {
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        $this->postJson('/api/reserves', $this->payload([
            'check_in' => $this->date(12)->toDateString(),
            'check_out' => $this->date(15)->toDateString(),
        ]))->assertStatus(409);
    }

    public function test_dia_do_checkout_fica_livre_para_novo_checkin(): void
    {
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        $this->postJson('/api/reserves', $this->payload([
            'check_in' => $this->date(13)->toDateString(),
            'check_out' => $this->date(15)->toDateString(),
        ]))->assertCreated();
    }

    public function test_mesmo_periodo_em_outro_quarto_e_permitido(): void
    {
        $this->postJson('/api/reserves', $this->payload())->assertCreated();

        $outro = Room::create(['hotel_id' => $this->hotel->id, 'name' => 'Quarto 2']);

        $this->postJson('/api/reserves', $this->payload(['room_id' => $outro->id]))
            ->assertCreated();
    }

    public function test_rejeita_quarto_de_outro_hotel(): void
    {
        $outroHotel = Hotel::create(['name' => 'Outro Hotel']);

        $this->postJson('/api/reserves', $this->payload(['hotel_id' => $outroHotel->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['room_id']);
    }

    public function test_rejeita_checkout_antes_do_checkin(): void
    {
        $this->postJson('/api/reserves', $this->payload([
            'check_in' => $this->date(13)->toDateString(),
            'check_out' => $this->date(10)->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_out']);
    }

    public function test_rejeita_checkin_no_passado(): void
    {
        $this->postJson('/api/reserves', $this->payload([
            'check_in' => $this->date(-2)->toDateString(),
            'check_out' => $this->date(1)->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in']);
    }

    public function test_exige_ao_menos_um_hospede(): void
    {
        $this->postJson('/api/reserves', $this->payload(['guests' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guests']);
    }

    public function test_rejeita_forma_de_pagamento_invalida(): void
    {
        $this->postJson('/api/reserves', $this->payload([
            'payments' => [['method' => 9, 'value' => 50]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payments.0.method']);
    }

    public function test_mostra_uma_reserva(): void
    {
        $id = $this->postJson('/api/reserves', $this->payload())->json('data.id');

        $this->getJson("/api/reserves/{$id}")
            ->assertOk()
            ->assertJsonPath('data.nights', 3)
            ->assertJsonPath('data.hotel.name', 'Hotel Teste');
    }

    public function test_reserva_inexistente_retorna_404(): void
    {
        $this->getJson('/api/reserves/999')->assertNotFound();
    }
}