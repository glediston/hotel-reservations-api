<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomController extends Controller
{
    #[OA\Get(
        path: '/rooms',
        summary: 'Lista os quartos',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'hotel_id',
                in: 'query',
                required: false,
                description: 'Filtra os quartos de um hotel',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Número da página (15 itens por página)',
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada de quartos',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/Room')
                    ),
                ])
            ),
        ]
    )]
    public function index(Request $request)
    {
        $rooms = Room::query()
            ->when($request->query('hotel_id'), fn ($q, $hotelId) => $q->where('hotel_id', $hotelId))
            ->orderBy('id')
            ->paginate(15);

        return RoomResource::collection($rooms);
    }

    #[OA\Post(
        path: '/rooms',
        summary: 'Cadastra um quarto',
        tags: ['Quartos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hotel_id', 'name'],
                properties: [
                    new OA\Property(property: 'hotel_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Suite Master'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Quarto criado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Room'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Dados inválidos (hotel inexistente, nome ausente ou repetido no mesmo hotel)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function store(StoreRoomRequest $request)
    {
        $room = Room::create($request->validated());

        return (new RoomResource($room->load('hotel')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/rooms/{id}',
        summary: 'Detalha um quarto',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Id do quarto',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto encontrado, com o hotel',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Room'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                ])
            ),
        ]
    )]
    public function show(Room $room)
    {
        return new RoomResource($room->load('hotel'));
    }

    #[OA\Put(
        path: '/rooms/{id}',
        summary: 'Atualiza o nome de um quarto',
        description: 'Apenas o nome pode ser alterado. O nome deve ser único dentro do hotel.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Id do quarto',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Suite Luxo'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Quarto atualizado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/Room'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Nome ausente ou já usado por outro quarto do mesmo hotel',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function update(UpdateRoomRequest $request, Room $room)
    {
        $room->update($request->validated());

        return new RoomResource($room->load('hotel'));
    }

    #[OA\Delete(
        path: '/rooms/{id}',
        summary: 'Exclui um quarto',
        description: 'Quartos que possuem reservas não podem ser excluídos.',
        tags: ['Quartos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Id do quarto',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Quarto excluído (sem conteúdo)'),
            new OA\Response(
                response: 404,
                description: 'Quarto não encontrado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                ])
            ),
            new OA\Response(
                response: 409,
                description: 'O quarto possui reservas e não pode ser excluído',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                ])
            ),
        ]
    )]
    public function destroy(Room $room)
    {
        if ($room->reserves()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir um quarto que possui reservas.',
            ], 409);
        }

        $room->delete();

        return response()->noContent();
    }
}