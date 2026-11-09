<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\ChangeHolding;
use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('change_holding')]
#[Description('Muda as cotas de um FII na carteira: informe o total novo (total) ou quanto somar ou tirar (variacao, negativa para tirar), nunca os dois. Chegar a zero tira o fundo da carteira.')]
final class ChangeHoldingTool extends Tool
{
    public function handle(Request $request, ChangeHolding $change): Response
    {
        try {
            $result = $change->handle(
                ToolInput::ticker($request),
                ToolInput::integer($request, 'total'),
                ToolInput::integer($request, 'variacao'),
            );
        } catch (InvalidInput $invalid) {
            return Response::error($invalid->getMessage().' Nada foi alterado.');
        }

        $line = match (true) {
            $result->removed => "{$result->ticker}: {$result->before} → 0 cotas, saiu da carteira.",
            $result->before === null => "{$result->ticker}: entrou com {$result->after} cotas",
            default => "{$result->ticker}: {$result->before} → {$result->after} cotas",
        };

        return Response::text("✅ Carteira atualizada.\n{$line}");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'codigo' => $schema->string()->description('Código do fundo, como MXRF11.')->required(),
            'total' => $schema->integer()->description('Total de cotas depois da mudança.'),
            'variacao' => $schema->integer()->description('Quantas cotas somar (positivo) ou tirar (negativo).'),
        ];
    }
}
