<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReserveRequest;
use App\Http\Resources\ReserveResource;
use App\Models\Reserve;
use App\Services\ReservationService;
use OpenApi\Attributes as OA;

class ReserveController extends Controller
{
    public function __construct(private ReservationService $service) {}

    #[OA\Post(
        path: '/reserves',
        summary: 'Cria uma reserva',
        description: 'O total e as diárias são calculados pelo sistema a partir de daily_value. '
            .'O pagamento é opcional e pode ser parcial, mas não pode ultrapassar o total. '
            .'Formas de pagamento: 1 = dinheiro, 2 = pix, 3 = cartão.',
        tags: ['Reservas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hotel_id', 'room_id', 'check_in', 'check_out', 'daily_value', 'guests'],
                properties: [
                    new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                    new OA\Property(property: 'room_id', type: 'integer', example: 1),
                    new OA\Property(property: 'check_in', type: 'string', format: 'date', example: '2027-01-10'),
                    new OA\Property(property: 'check_out', type: 'string', format: 'date', example: '2027-01-13'),
                    new OA\Property(property: 'daily_value', type: 'number', format: 'float', example: 100),
                    new OA\Property(
                        property: 'guests',
                        type: 'array',
                        items: new OA\Items(
                            required: ['name', 'last_name', 'phone'],
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'Maria'),
                                new OA\Property(property: 'last_name', type: 'string', example: 'Souza'),
                                new OA\Property(property: 'phone', type: 'string', example: '5571999999999'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'payments',
                        type: 'array',
                        items: new OA\Items(
                            required: ['method', 'value'],
                            properties: [
                                new OA\Property(property: 'method', type: 'integer', enum: [1, 2, 3], example: 2),
                                new OA\Property(property: 'value', type: 'number', format: 'float', example: 100),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Reserva criada',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Reserve'),
                ])
            ),
            new OA\Response(
                response: 409,
                description: 'O quarto já está reservado em parte do período informado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        example: 'Este quarto já está reservado de 10/01/2027 a 13/01/2027.'
                    ),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Dados inválidos: quarto de outro hotel, datas incorretas, check-in no passado, sem hóspede, forma de pagamento inválida ou pagamento maior que o total',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function store(StoreReserveRequest $request)
    {
        $reserve = $this->service->create($request->validated());

        return (new ReserveResource($reserve))
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/reserves/{$reserve->id}"));
    }

    #[OA\Get(
        path: '/reserves/{id}',
        summary: 'Detalha uma reserva',
        description: 'Retorna hóspedes, diárias, pagamentos, saldo e status de pagamento.',
        tags: ['Reservas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Id da reserva',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reserva encontrada',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Reserve'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Reserva não encontrada',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                ])
            ),
        ]
    )]
    public function show(Reserve $reserve)
    {
        return new ReserveResource($reserve);
    }
}