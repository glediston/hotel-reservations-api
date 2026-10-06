<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Room',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Room 1 Hotel 1'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'Reserve',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 9),
        new OA\Property(property: 'hotel', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Hotel Foco Prime'),
        ]),
        new OA\Property(property: 'room', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'name', type: 'string', example: 'Room 1 Hotel 1'),
        ]),
        new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2026-12-20'),
        new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2026-12-23'),
        new OA\Property(property: 'nights', type: 'integer', example: 3),
        new OA\Property(property: 'total', type: 'string', example: '300.00'),
        new OA\Property(property: 'paid', type: 'string', example: '100.00'),
        new OA\Property(property: 'balance', type: 'string', example: '200.00'),
        new OA\Property(
            property: 'payment_status',
            type: 'string',
            enum: ['pendente', 'parcial', 'quitado'],
            example: 'parcial'
        ),
        new OA\Property(property: 'guests', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Maria'),
            new OA\Property(property: 'last_name', type: 'string', example: 'Souza'),
            new OA\Property(property: 'phone', type: 'string', example: '5571999999999'),
        ])),
        new OA\Property(property: 'dailies', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-12-20'),
            new OA\Property(property: 'value', type: 'string', example: '100.00'),
        ])),
        new OA\Property(property: 'payments', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'method', type: 'integer', example: 2),
            new OA\Property(property: 'method_name', type: 'string', example: 'pix'),
            new OA\Property(property: 'value', type: 'string', example: '100.00'),
        ])),
    ]
)]
class Schemas {}