<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReserveRequest;
use App\Http\Resources\ReserveResource;
use App\Models\Reserve;
use App\Services\ReservationService;

class ReserveController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function store(StoreReserveRequest $request)
    {
        $reserve = $this->service->create($request->validated());

        return (new ReserveResource($reserve))
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/reserves/{$reserve->id}"));
    }

    public function show(Reserve $reserve)
    {
        return new ReserveResource($reserve);
    }
}