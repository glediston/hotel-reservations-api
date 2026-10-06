<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'admin@hotel.test',
            'password' => Hash::make('secret123'),
        ]);
    }

    public function test_login_com_credenciais_corretas_devolve_token(): void
    {
        $this->user();

        $this->postJson('/api/login', ['email' => 'admin@hotel.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_login_com_senha_errada_retorna_401(): void
    {
        $this->user();

        $this->postJson('/api/login', ['email' => 'admin@hotel.test', 'password' => 'errada'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_login_exige_email_e_senha(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cadastro_de_quarto_sem_token_retorna_401(): void
    {
        $hotel = Hotel::create(['name' => 'Hotel Teste']);

        $this->postJson('/api/rooms', ['hotel_id' => $hotel->id, 'name' => 'Quarto 1'])
            ->assertUnauthorized();
    }

    public function test_alterar_e_excluir_quarto_sem_token_retorna_401(): void
    {
        $hotel = Hotel::create(['name' => 'Hotel Teste']);
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->putJson("/api/rooms/{$room->id}", ['name' => 'Novo nome'])->assertUnauthorized();
        $this->deleteJson("/api/rooms/{$room->id}")->assertUnauthorized();
    }

    public function test_reservas_sem_token_retornam_401(): void
    {
        $this->postJson('/api/reserves', [])->assertUnauthorized();
        $this->getJson('/api/reserves/1')->assertUnauthorized();
    }

    public function test_consulta_de_quartos_e_publica(): void
    {
        $hotel = Hotel::create(['name' => 'Hotel Teste']);
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto 1']);

        $this->getJson('/api/rooms')->assertOk();
        $this->getJson("/api/rooms/{$room->id}")->assertOk();
    }

    public function test_token_do_login_permite_cadastrar_quarto(): void
    {
        $this->user();
        $hotel = Hotel::create(['name' => 'Hotel Teste']);

        $token = $this->postJson('/api/login', ['email' => 'admin@hotel.test', 'password' => 'secret123'])
            ->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rooms', ['hotel_id' => $hotel->id, 'name' => 'Quarto 1'])
            ->assertCreated();
    }

    public function test_logout_apaga_o_token(): void
    {
        $this->user();

        $token = $this->postJson('/api/login', ['email' => 'admin@hotel.test', 'password' => 'secret123'])
            ->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}