<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sector_id' => ['required', 'exists:sectors,id'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
