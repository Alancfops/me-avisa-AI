<?php

namespace App\Modules\Fiis\Application;

use App\Modules\Fiis\Domain\AlertMode;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\Money;

/** Valida o que o usuário digitou para o alerta, igual no site e no MCP. */
final class FavoriteSettingsInput
{
    /** Até 12 inteiros e 6 casas, o que a coluna `numeric` guarda. */
    private const PRICE_FORMAT = '/^\d{1,12}(\.\d{1,6})?$/D';

    private const FORMAT_EXAMPLE = ['target' => '9,50', 'rangeMin' => '9,00', 'rangeMax' => '9,60'];

    /**
     * Vírgula decimal vira ponto; `1.234,50` não passa, para não adivinhar milhar.
     *
     * @throws InvalidInput
     */
    public static function parse(string $mode, ?string $target, ?string $rangeMin, ?string $rangeMax, bool $sharpDrop): FavoriteSettings
    {
        $alertMode = AlertMode::tryFrom($mode);
        $values = ['target' => self::normalize($target), 'rangeMin' => self::normalize($rangeMin), 'rangeMax' => self::normalize($rangeMax)];
        $errors = [];

        if ($alertMode === null) {
            $errors['mode'] = 'Escolha preço alvo, faixa de compra ou automático.';
        }

        foreach (self::requiredBy($alertMode) as $field => $message) {
            if ($values[$field] === null) {
                $errors[$field] = $message;
            }
        }

        foreach ($values as $field => $value) {
            if ($value !== null && ! isset($errors[$field])) {
                $error = self::priceError($field, $value);
                if ($error !== null) {
                    $errors[$field] = $error;
                }
            }
        }

        if ($alertMode === AlertMode::Range && $errors === []
            && ! self::money($values['rangeMin'])->isAtMost(self::money($values['rangeMax']))) {
            $errors['rangeMax'] = 'O máximo precisa ser maior ou igual ao mínimo.';
        }

        if ($errors !== []) {
            throw new InvalidInput($errors);
        }

        return match ($alertMode) {
            AlertMode::Target => FavoriteSettings::target(self::money($values['target']), $sharpDrop),
            AlertMode::Range => FavoriteSettings::range(self::money($values['rangeMin']), self::money($values['rangeMax']), $sharpDrop),
            default => FavoriteSettings::automatic($sharpDrop),
        };
    }

    /** @return array<string, string> campo => mensagem de campo vazio */
    private static function requiredBy(?AlertMode $mode): array
    {
        return match ($mode) {
            AlertMode::Target => ['target' => 'Informe o preço alvo.'],
            AlertMode::Range => ['rangeMin' => 'Informe o mínimo da faixa.', 'rangeMax' => 'Informe o máximo da faixa.'],
            default => [],
        };
    }

    private static function normalize(?string $value): ?string
    {
        $value = str_replace(',', '.', trim($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function priceError(string $field, string $value): ?string
    {
        if (preg_match(self::PRICE_FORMAT, $value) !== 1) {
            return 'Use só números, como '.self::FORMAT_EXAMPLE[$field].'.';
        }

        return Money::fromString($value)->isPositive() ? null : 'O valor precisa ser maior que zero.';
    }

    private static function money(?string $value): Money
    {
        return Money::fromString((string) $value);
    }
}
