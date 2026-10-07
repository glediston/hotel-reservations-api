<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\ReserveResource;
use App\Models\Payment;
use App\Models\Reserve;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    // Registra um pagamento na reserva (pode ser parcial)
    public function store(StorePaymentRequest $request, Reserve $reserve)
    {
        // Não deixa pagar mais do que falta
        if ($request->value > $reserve->balance()) {
            $balance = number_format($reserve->balance(), 2, ',', '.');

            throw ValidationException::withMessages([
                'value' => "O valor é maior que o saldo da reserva (R$ {$balance}).",
            ]);
        }

        $payment = $reserve->payments()->create($request->validated());

        Log::info("Pagamento {$payment->id} de R$ {$payment->value} registrado na reserva {$reserve->id}.");

        // Devolve a reserva atualizada, com o novo saldo
        return (new ReserveResource($reserve->load('payments')))
            ->response()
            ->setStatusCode(201);
    }

    // Estorna (remove) um pagamento lançado
    public function destroy(Payment $payment)
    {
        $payment->delete();

        Log::info("Pagamento {$payment->id} estornado da reserva {$payment->reserve_id}.");

        return new ReserveResource($payment->reserve);
    }
}
