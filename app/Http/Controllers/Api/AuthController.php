<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/login',
        summary: 'Autentica e devolve um token',
        description: 'Limitado a 5 tentativas por minuto. No ambiente de desenvolvimento use admin@hotel.test / password.',
        tags: ['Autenticação'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@hotel.test'),
                    new OA\Property(property: 'password', type: 'string', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Autenticado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                ])
            ),
            new OA\Response(
                response: 401,
                description: 'Credenciais inválidas',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Credenciais inválidas.'),
                ])
            ),
            new OA\Response(response: 422, description: 'E-mail ou senha ausentes'),
            new OA\Response(response: 429, description: 'Muitas tentativas de login'),
        ]
    )]
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Revoga o token atual',
        tags: ['Autenticação'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Token revogado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}