<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReserveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'daily_value' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.last_name' => ['required', 'string', 'max:255'],
            'guests.*.phone' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.exists' => 'O quarto informado não existe.',
            'check_in.after_or_equal' => 'O check-in não pode ser uma data passada.',
            'check_out.after' => 'O check-out deve ser depois do check-in.',
            'daily_value.gt' => 'O valor da diária deve ser maior que zero.',
            'guests.required' => 'Informe ao menos um hóspede.',
            'guests.min' => 'Informe ao menos um hóspede.',
        ];
    }
}
