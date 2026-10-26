<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGroupTournamentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'date' => ['nullable', 'date'],
            'season_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'counts_for_ranking' => ['nullable', 'boolean'],
            'best_of_legs' => ['nullable', 'integer', 'min:1', 'max:35'],
            'game_mode' => ['nullable', 'string'],
            'checkout_mode' => ['nullable', 'string'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'max:100'],
            'groups.*.player_ids' => ['required', 'array', 'min:2'],
            'groups.*.player_ids.*' => ['required', 'integer', 'exists:players,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Zadejte prosím název turnaje.',
            'name.max' => 'Název turnaje může mít maximálně 150 znaků.',
            'groups.required' => 'Musí být vytvořena alespoň jedna skupina.',
            'groups.min' => 'Musí být vytvořena alespoň jedna skupina.',
            'groups.*.name.required' => 'Každá skupina musí mít zadaný název.',
            'groups.*.player_ids.required' => 'V každé skupině musí být přiřazeni hráči.',
            'groups.*.player_ids.min' => 'Každá skupina musí obsahovat minimálně 2 hráče.',
            'groups.*.player_ids.*.exists' => 'Některý z hráčů ve skupině neexistuje v databázi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $groups = (array) $this->input('groups', []);
            $seenPlayerIds = [];

            foreach ($groups as $index => $group) {
                $groupName = $group['name'] ?? ('Skupina ' . ($index + 1));
                $playerIds = (array) ($group['player_ids'] ?? []);

                if (count($playerIds) < 2) {
                    $validator->errors()->add("groups.{$index}.player_ids", "Skupina \"{$groupName}\" musí obsahovat alespoň 2 hráče.");
                }

                foreach ($playerIds as $pId) {
                    $intId = (int) $pId;
                    if (in_array($intId, $seenPlayerIds, true)) {
                        $validator->errors()->add('groups', "Hráč s ID {$intId} je přiřazen do více skupin současně. Každý hráč smí být pouze v jedné skupině.");
                    }
                    $seenPlayerIds[] = $intId;
                }
            }
        });
    }
}
