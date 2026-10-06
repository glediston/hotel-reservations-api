<?php

namespace Tests\Feature;

use App\Models\Daily;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportXmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_hoteis_quartos_e_reservas(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $this->assertSame(3, Hotel::count());
        $this->assertSame(6, Room::count());
        $this->assertSame(6, Reserve::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_importacao_pode_rodar_varias_vezes_sem_duplicar(): void
    {
        $this->artisan('import:xml')->assertSuccessful();
        $this->artisan('import:xml')->assertSuccessful();

        $this->assertSame(3, Hotel::count());
        $this->assertSame(6, Room::count());
        $this->assertSame(6, Reserve::count());
        $this->assertSame(17, Daily::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_ignora_diaria_fora_do_periodo_da_reserva(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $reserve = Reserve::where('external_id', 6)->firstOrFail();

        $this->assertSame(2, $reserve->dailies()->count());
        $this->assertFalse($reserve->dailies()->whereDate('date', '2022-12-03')->exists());
    }

    public function test_reserva_sem_pagamento_e_importada(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $reserve = Reserve::where('external_id', 2)->firstOrFail();

        $this->assertSame(0, $reserve->payments()->count());
        $this->assertSame(2, $reserve->dailies()->count());
    }

    public function test_falha_quando_a_pasta_dos_xmls_nao_existe(): void
    {
        $this->artisan('import:xml --path=pasta/inexistente')->assertFailed();

        $this->assertSame(0, Hotel::count());
    }
}