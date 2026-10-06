<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Hotel Reservations API',
    description: 'API REST para gestão de hotéis, quartos e reservas (desafio técnico Foco Multimídia). '
        .'A consulta de quartos é pública. Cadastro, edição e exclusão de quartos e todas as rotas de reservas exigem token: '
        .'faça login em POST /login e use o botão Authorize.'
)]
#[OA\Server(url: 'http://localhost:8000/api', description: 'Servidor local (Docker)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Cole apenas o token devolvido pelo login (sem a palavra Bearer).'
)]
#[OA\Tag(name: 'Autenticação', description: 'Login e logout')]
#[OA\Tag(name: 'Quartos', description: 'CRUD de quartos')]
#[OA\Tag(name: 'Reservas', description: 'Criação e consulta de reservas')]
class OpenApiSpec {}