<?php

namespace App\Modules\Fiis\Http\Requests;

use App\Modules\Fiis\Application\CatalogRepository;
use App\Modules\Fiis\Application\InvalidInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class SaveHoldingRequest extends FormRequest
{
    private const SHARES = 'Informe um número inteiro de cotas, como 100.';

    /** O ticker vem da rota; entra nos dados para ser validado com o catálogo. */
    protected function prepareForValidation(): void
    {
        $this->merge(['ticker' => strtoupper(trim((string) $this->route('ticker')))]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'stay' => ['sometimes', 'boolean'],
            'shares' => ['required', 'integer', 'min:1'],
            'ticker' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! app(CatalogRepository::class)->exists($value)) {
                    $fail(InvalidInput::NOT_IN_CATALOG);
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'shares.required' => self::SHARES,
            'shares.integer' => self::SHARES,
            'shares.min' => self::SHARES,
        ];
    }
}
