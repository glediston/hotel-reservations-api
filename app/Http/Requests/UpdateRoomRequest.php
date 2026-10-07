<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms')
                    ->where('hotel_id', $room->hotel_id)
                    ->ignore($room->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do quarto.',
            'name.unique' => 'Já existe um quarto com esse nome neste hotel.',
        ];
    }
}
