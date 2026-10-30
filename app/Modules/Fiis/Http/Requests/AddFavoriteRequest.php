<?php

namespace App\Modules\Fiis\Http\Requests;

use App\Modules\Fiis\Application\CatalogRepository;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class AddFavoriteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['ticker' => strtoupper(trim($this->string('ticker')->toString()))]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'ticker' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! app(CatalogRepository::class)->exists($value)) {
                    $fail('Esse fundo não está no catálogo. Confira o código na B3.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ticker.required' => 'Informe o código do fundo.'];
    }
}
