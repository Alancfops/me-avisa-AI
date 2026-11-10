<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\ListAlerts;
use App\Modules\Mcp\Format;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('recent_alerts')]
#[Description('Lista os avisos mais recentes, do mais novo ao mais velho, de todos os favoritos ou de um só. Um aviso diz que uma regra foi atendida, não que vale comprar.')]
#[IsReadOnly]
final class RecentAlertsTool extends Tool
{
    private const DEFAULT_AMOUNT = 10;

    private const MAX_AMOUNT = 50;

    public function handle(Request $request, ListAlerts $alerts): Response
    {
        $ticker = $request->get('codigo');
        $amount = ToolInput::integer($request, 'quantidade') ?? self::DEFAULT_AMOUNT;

        $found = $alerts->handle(
            is_string($ticker) && trim($ticker) !== '' ? Format::ticker($ticker) : null,
            max(1, min($amount, self::MAX_AMOUNT)),
        );

        return Response::json(['avisos' => array_map(fn (Alert $alert): array => [
            'codigo' => $alert->ticker,
            'regra' => Format::rule($alert->rule),
            'preco' => Format::money($alert->price),
            'referencia' => $alert->reference,
            'motivo' => $alert->reason,
            'horario' => Format::moment($alert->occurredAt),
        ], $found)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'codigo' => $schema->string()->description('Código do fundo, como MXRF11. Sem ele, traz os de todos.'),
            'quantidade' => $schema->integer()->description('Quantos avisos trazer. Padrão 10, máximo 50.'),
        ];
    }
}
