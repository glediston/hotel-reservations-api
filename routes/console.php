<?php

use Illuminate\Support\Facades\Schedule;

// Importa os XMLs todo dia às 02:00 (no Docker, o serviço "scheduler" executa isso)
Schedule::command('import:xml')->dailyAt('02:00')->withoutOverlapping();
