<?php

namespace App\Modules\Fiis\Http\Requests;

use App\Modules\Fiis\Application\FavoriteSettingsInput;
use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Fiis\Domain\FavoriteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/** As regras de modo e preço ficam em `FavoriteSettingsInput`, as mesmas do MCP. */
final class UpdateFavoriteSettingsRequest extends FormRequest
{
    private ?FavoriteSettings $settings = null;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['sharpDrop' => ['required', 'boolean']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['sharpDrop.*' => 'Informe se a queda forte fica ligada.'];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            try {
                $this->settings = FavoriteSettingsInput::parse(
                    $this->text('mode') ?? '',
                    $this->text('target'),
                    $this->text('rangeMin'),
                    $this->text('rangeMax'),
                    $this->boolean('sharpDrop'),
                );
            } catch (InvalidInput $invalid) {
                foreach ($invalid->errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }];
    }

    public function settings(): FavoriteSettings
    {
        return $this->settings ?? throw new LogicException('settings() só vale depois da validação.');
    }

    private function text(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) ? $value : null;
    }
}
