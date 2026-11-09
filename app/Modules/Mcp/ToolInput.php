<?php

namespace App\Modules\Mcp;

use App\Modules\Fiis\Application\InvalidInput;
use Laravel\Mcp\Request;

/** Lê e confere os argumentos que a ferramenta recebe, que chegam sem tipo garantido. */
final class ToolInput
{
    /** @throws InvalidInput */
    public static function ticker(Request $request): string
    {
        $value = $request->get('codigo');

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidInput(['ticker' => 'Informe o código do fundo, como MXRF11.']);
        }

        return Format::ticker($value);
    }

    /** @throws InvalidInput */
    public static function integer(Request $request, string $key): ?int
    {
        $value = $request->get($key);

        return match (true) {
            $value === null || $value === '' => null,
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/D', trim($value)) === 1 => (int) trim($value),
            // 10.0 chega como float em alguns clientes; 10.5 não é número de cotas.
            is_float($value) && floor($value) === $value => (int) $value,
            default => throw new InvalidInput(['shares' => 'Informe um número inteiro de cotas, como 100.']),
        };
    }

    /**
     * Preço como texto que o `FavoriteSettingsInput` entende: número vira decimal com
     * ponto e sem notação científica; texto segue como veio (vírgula é tratada lá).
     *
     * @throws InvalidInput
     */
    public static function price(Request $request, string $key): ?string
    {
        $value = $request->get($key);

        return match (true) {
            $value === null => null,
            is_int($value) => (string) $value,
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_string($value) => $value,
            default => throw new InvalidInput([$key => 'Use só números, como 9,50.']),
        };
    }

    public static function flag(Request $request, string $key, bool $default = false): bool
    {
        return filter_var($request->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
