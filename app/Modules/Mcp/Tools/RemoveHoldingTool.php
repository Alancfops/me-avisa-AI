<?php

namespace App\Modules\Mcp\Tools;

use App\Modules\Fiis\Application\HoldingRepository;
use App\Modules\Fiis\Application\InvalidInput;
use App\Modules\Fiis\Application\RemoveHolding;
use App\Modules\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('remove_holding')]
#[Description('Tira um FII da carteira, apagando a quantidade de cotas. Confirme o fundo com a pessoa antes.')]
#[IsDestructive]
final class RemoveHoldingTool extends Tool
{
    public function handle(Request $request, HoldingRepository $holdings, RemoveHolding $remove): Response
    {
        try {
            $ticker = ToolInput::ticker($request);
        } catch (InvalidInput $invalid) {
            return Response::error($invalid->getMessage().' Nada foi alterado.');
        }

        $shares = $holdings->shares($ticker);

        if ($shares === null) {
            return Response::error("{$ticker} não está na carteira. Nada foi alterado.");
        }

        $remove->handle($ticker);

        return Response::text("✅ {$ticker} saiu da carteira (tinha {$shares} cotas).");
    }

    public function schema(JsonSchema $schema): array
    {
        return ['codigo' => $schema->string()->description('Código do fundo, como MXRF11.')->required()];
    }
}
