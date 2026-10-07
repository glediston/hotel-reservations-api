<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'integer', Rule::in(array_keys(Payment::METHODS))],
            'value' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.in' => 'Forma de pagamento inválida. Use 1 (dinheiro), 2 (pix) ou 3 (cartão).',
            'value.gt' => 'O valor do pagamento deve ser maior que zero.',
        ];
    }
}
