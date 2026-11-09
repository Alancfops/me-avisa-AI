<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\AlertChange;
use App\Modules\Fiis\Application\AlertChangeStatus;
use App\Modules\Fiis\Application\ChangeAlert;
use App\Modules\Fiis\Application\FavoriteSettingsInput;
use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Mcp\Format;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('change_alert')]
#[Description('Troca o alerta de um FII favorito: automático, preço alvo ou faixa de compra. Um fundo tem um só alerta principal, e trocar substitui o atual. Se a pessoa não falar da queda forte, mantenha o valor atual (aparece em list_favorites). Alvo ou faixa a mais de 30% do preço atual voltam sem salvar; só chame de novo com confirmado: true depois de a pessoa dizer que sim.')]
final class ChangeAlertTool extends Tool
{
    private const FIELD_LABELS = ['target' => 'O alvo', 'rangeMin' => 'O mínimo da faixa', 'rangeMax' => 'O máximo da faixa'];

    public function handle(Request $request, ChangeAlert $change): Response
    {
        try {
            $ticker = ToolInput::ticker($request);
            $mode = $request->get('tipo');

            // Sem isso, esquecer o campo desligaria a queda forte sem ninguém pedir.
            if ($request->get('queda_forte') === null) {
                throw new InvalidInput(['sharpDrop' => 'Diga se a queda forte fica ligada ou desligada.']);
            }

            $settings = FavoriteSettingsInput::parse(
                is_string($mode) ? $mode : '',
                ToolInput::price($request, 'preco_alvo'),
                ToolInput::price($request, 'faixa_min'),
                ToolInput::price($request, 'faixa_max'),
                ToolInput::flag($request, 'queda_forte'),
            );
        } catch (InvalidInput $invalid) {
            return Response::error($invalid->getMessage().' Nada foi alterado.');
        }

        $result = $change->handle($ticker, $settings, ToolInput::flag($request, 'confirmado'));

        return match ($result->status) {
            AlertChangeStatus::NotFavorite => Response::error("{$ticker} não é favorito. Nada foi alterado."),
            AlertChangeStatus::Unchanged => Response::text("O alerta de {$ticker} já era esse: ".Format::settings($result->requested).'. Nada foi alterado.'),
            AlertChangeStatus::NeedsConfirmation => Response::text(self::confirmation($result)),
            AlertChangeStatus::Saved => Response::text(self::saved($result)),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'codigo' => $schema->string()->description('Código do fundo favorito, como MXRF11.')->required(),
            'tipo' => $schema->string()->enum(['automatic', 'target', 'range'])->description('automatic: regra automática; target: preço alvo; range: faixa de compra.')->required(),
            'preco_alvo' => $schema->string()->description('Preço alvo em reais, como 9,50. Só para tipo target.'),
            'faixa_min' => $schema->string()->description('Mínimo da faixa em reais. Só para tipo range.'),
            'faixa_max' => $schema->string()->description('Máximo da faixa em reais. Só para tipo range.'),
            'queda_forte' => $schema->boolean()->description('Liga ou desliga o aviso de queda forte.')->required(),
            'confirmado' => $schema->boolean()->description('true só depois de a pessoa confirmar um valor a mais de 30% do preço atual.'),
        ];
    }

    private static function saved(AlertChange $result): string
    {
        $lines = [
            "✅ Alerta salvo para {$result->ticker}.",
            'Antes: '.($result->before === null ? 'sem dado' : Format::settings($result->before)),
            'Agora: '.Format::settings($result->requested),
        ];

        if ($result->triggersNow) {
            $lines[] = 'O preço atual ('.Format::money($result->currentPrice).', '.Format::priceSource($result->quote, $result->close).') já atende esse alerta; o aviso deve sair agora ou na próxima atualização.';
        }

        if ($result->priceUnchecked) {
            $lines[] = 'Sem preço atual para conferir a distância do alvo.';
        }

        return implode("\n", $lines);
    }

    private static function confirmation(AlertChange $result): string
    {
        $lines = ['⚠️ Nada foi alterado.'];
        $price = Format::money($result->currentPrice).', '.Format::priceSource($result->quote, $result->close);

        foreach ($result->exceeded as $field => $distance) {
            $value = match ($field) {
                'target' => $result->requested->target,
                'rangeMin' => $result->requested->rangeMin,
                default => $result->requested->rangeMax,
            };
            $side = str_starts_with($distance->toDecimal(), '-') ? 'abaixo' : 'acima';

            $lines[] = self::FIELD_LABELS[$field].' '.Format::money($value)." está {$distance->magnitude()} {$side} do preço atual de {$result->ticker} ({$price}).";
        }

        $lines[] = 'Confirme com a pessoa. Se ela disser que sim, chame de novo com confirmado: true.';

        return implode("\n", $lines);
    }
}
