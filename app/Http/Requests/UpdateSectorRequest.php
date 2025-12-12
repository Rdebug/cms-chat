<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $sectorId = $this->route('sector')->id ?? $this->route('sector');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('sectors')->ignore($sectorId)],
            'menu_code' => ['required', 'string', 'max:10', Rule::unique('sectors')->ignore($sectorId)],
            'active' => ['boolean'],
        ];
    }
}
