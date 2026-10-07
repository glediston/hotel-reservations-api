<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Reserve $reserve;

    protected function setUp(): void
    {
        parent::setUp();

        $hotel = Hotel::create(['name' => 'Hotel Teste']);
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->reserve = Reserve::create([
            'hotel_id' => $hotel->id,
            'room_id' => $room->id,
            'check_in' => '2030-01-10',
            'check_out' => '2030-01-13',
            'total' => 300,
        ]);
    }

    private function pay(float $value, int $method = 2)
    {
        return $this->postJson("/api/reserves/{$this->reserve->id}/payments", [
            'method' => $method,
            'value' => $value,
        ]);
    }

    public function test_pagamento_parcial_gera_saldo_e_status_parcial(): void
    {
        $this->pay(100)
            ->assertCreated()
            ->assertJsonPath('data.paid', '100.00')
            ->assertJsonPath('data.balance', '200.00')
            ->assertJsonPath('data.payment_status', 'parcial')
            ->assertJsonPath('data.payments.0.method_name', 'pix');
    }

    public function test_pagando_tudo_fica_quitado(): void
    {
        $this->pay(100, 1)->assertCreated();

        $this->pay(200)
            ->assertCreated()
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.payment_status', 'quitado');

        $this->assertDatabaseCount('payments', 2);
    }

    public function test_rejeita_pagamento_maior_que_o_saldo(): void
    {
        $this->pay(250)->assertCreated();

        $this->pay(100)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_rejeita_forma_de_pagamento_invalida(): void
    {
        $this->pay(50, 9)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);
    }

    public function test_estorno_remove_o_pagamento_e_volta_o_saldo(): void
    {
        $paymentId = $this->pay(100)->json('data.payments.0.id');

        $this->deleteJson("/api/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.balance', '300.00')
            ->assertJsonPath('data.payment_status', 'pendente');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pagamento_em_reserva_inexistente_retorna_404(): void
    {
        $this->postJson('/api/reserves/999/payments', ['method' => 1, 'value' => 10])
            ->assertNotFound();
    }
}
