<?php

namespace App\Modules\Fiis\Http\Requests;

use App\Modules\Fiis\Domain\AlertMode;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFavoriteSettingsRequest extends FormRequest
{
    /** Vírgula decimal vira ponto; `1.234,50` não passa, para não adivinhar milhar. */
    protected function prepareForValidation(): void
    {
        foreach (['target', 'rangeMin', 'rangeMax'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => str_replace(',', '.', trim($this->string($field)->toString()))]);
            }
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $price = ['regex:/^\d{1,12}(\.\d{1,6})?$/D', $this->positive(...)];

        return [
            'mode' => ['required', Rule::enum(AlertMode::class)],
            'sharpDrop' => ['required', 'boolean'],
            'target' => ['required_if:mode,target', 'nullable', 'string', ...$price],
            'rangeMin' => ['required_if:mode,range', 'nullable', 'string', ...$price],
            'rangeMax' => ['required_if:mode,range', 'nullable', 'string', ...$price, $this->notBelowMinimum(...)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mode.*' => 'Escolha preço alvo, faixa de compra ou automático.',
            'sharpDrop.*' => 'Informe se a queda forte fica ligada.',
            'target.required_if' => 'Informe o preço alvo.',
            'target.regex' => 'Use só números, como 9,50.',
            'rangeMin.required_if' => 'Informe o mínimo da faixa.',
            'rangeMin.regex' => 'Use só números, como 9,00.',
            'rangeMax.required_if' => 'Informe o máximo da faixa.',
            'rangeMax.regex' => 'Use só números, como 9,60.',
        ];
    }

    public function settings(): FavoriteSettings
    {
        $sharpDrop = $this->boolean('sharpDrop');

        return match ($this->enum('mode', AlertMode::class)) {
            AlertMode::Target => FavoriteSettings::target($this->money('target'), $sharpDrop),
            AlertMode::Range => FavoriteSettings::range($this->money('rangeMin'), $this->money('rangeMax'), $sharpDrop),
            default => FavoriteSettings::automatic($sharpDrop),
        };
    }

    private function money(string $field): Money
    {
        return Money::fromString($this->string($field)->toString());
    }

    private function positive(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->isPrice($value) && ! Money::fromString($value)->isPositive()) {
            $fail('O valor precisa ser maior que zero.');
        }
    }

    private function notBelowMinimum(string $attribute, mixed $value, Closure $fail): void
    {
        $min = $this->string('rangeMin')->toString();

        if ($this->input('mode') === AlertMode::Range->value && is_string($value) && $this->isPrice($value) && $this->isPrice($min)
            && ! Money::fromString($min)->isAtMost(Money::fromString($value))) {
            $fail('O máximo precisa ser maior ou igual ao mínimo.');
        }
    }

    private function isPrice(string $value): bool
    {
        return preg_match('/^\d{1,12}(\.\d{1,6})?$/D', $value) === 1;
    }
}
