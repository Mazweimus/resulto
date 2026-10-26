<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DrawGroupsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'player_ids' => ['required', 'array', 'min:2'],
            'player_ids.*' => ['required', 'integer', 'exists:players,id'],
            'groups_count' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'player_ids.required' => 'Vyberte alespoň 2 hráče pro rozlosování do skupin.',
            'player_ids.array' => 'Seznam hráčů musí být pole.',
            'player_ids.min' => 'Pro rozlosování do skupin vyberte alespoň 2 hráče.',
            'player_ids.*.exists' => 'Někteří z vybraných hráčů neexistují v databázi.',
            'groups_count.required' => 'Zadejte požadovaný počet skupin.',
            'groups_count.integer' => 'Počet skupin musí být celé číslo.',
            'groups_count.min' => 'Počet skupin musí být alespoň 1.',
            'groups_count.max' => 'Maximální povolený počet skupin je 50.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $playerIds = (array) $this->input('player_ids', []);
            $groupsCount = (int) $this->input('groups_count', 0);
            $totalPlayers = count($playerIds);

            // Kontrola duplicit v předaných ID
            if ($totalPlayers !== count(array_unique($playerIds))) {
                $validator->errors()->add('player_ids', 'V seznamu vybraných hráčů se vyskytují duplicity.');
            }

            // Kontrola: počet skupin > počet hráčů
            if ($groupsCount > $totalPlayers && $totalPlayers > 0) {
                $validator->errors()->add('groups_count', 'Počet skupin nemůže být vyšší než počet vybraných hráčů.');
            }

            // Kontrola: minimálně 2 hráči na skupinu
            if ($groupsCount > 0 && $totalPlayers < ($groupsCount * 2) && $totalPlayers > 0) {
                $minNeeded = $groupsCount * 2;
                $validator->errors()->add('groups_count', "Každá skupina musí obsahovat minimálně 2 hráče. Pro {$groupsCount} skupin je potřeba vybrat alespoň {$minNeeded} hráčů.");
            }
        });
    }
}
