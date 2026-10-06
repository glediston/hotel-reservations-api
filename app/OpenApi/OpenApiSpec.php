<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Hotel Reservations API',
    description: 'API REST para gestão de hotéis, quartos e reservas (desafio técnico Foco Multimídia).'
)]
#[OA\Server(url: 'http://localhost:8000/api', description: 'Servidor local (Docker)')]
#[OA\Tag(name: 'Quartos', description: 'CRUD de quartos')]
#[OA\Tag(name: 'Reservas', description: 'Criação e consulta de reservas')]
class OpenApiSpec {}