<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms')->where('hotel_id', $this->input('hotel_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hotel_id.required' => 'Informe o hotel do quarto.',
            'hotel_id.exists' => 'O hotel informado não existe.',
            'name.required' => 'Informe o nome do quarto.',
            'name.unique' => 'Já existe um quarto com esse nome neste hotel.',
        ];
    }
}
