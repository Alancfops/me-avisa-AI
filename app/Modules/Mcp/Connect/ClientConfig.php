<?php

namespace App\Modules\Mcp\Connect;

use JsonException;
use stdClass;

/**
 * O arquivo de configuração de um cliente de IA (Claude Desktop, Cursor, Antigravity).
 *
 * O JSON é lido com objetos (stdClass), e não arrays, para que `{}` continue `{}` ao gravar:
 * tudo o que a pessoa já tinha no arquivo tem de sair igual.
 */
final class ClientConfig
{
    public const SERVER = 'me-avisa-ai';

    private function __construct(private readonly stdClass $root) {}

    public static function fromJson(string $contents): self
    {
        if (trim($contents) === '') {
            return new self(new stdClass);
        }

        $root = self::decode($contents, 'O arquivo não é um JSON válido. Corrija ou apague o arquivo e rode de novo; nada foi alterado.');

        if (! $root instanceof stdClass) {
            throw self::unknownShape('na raiz do arquivo');
        }

        if (property_exists($root, 'mcpServers')) {
            if (! $root->mcpServers instanceof stdClass) {
                throw self::unknownShape('em mcpServers');
            }

            if (property_exists($root->mcpServers, self::SERVER) && ! $root->mcpServers->{self::SERVER} instanceof stdClass) {
                throw self::unknownShape('em mcpServers.'.self::SERVER);
            }
        }

        return new self($root);
    }

    /** O bloco me-avisa-ai que o `make mcp-config` imprime. */
    public static function serverFrom(string $mcpConfigOutput): stdClass
    {
        $missing = 'A saída do make mcp-config não tem o bloco '.self::SERVER.'.';
        $root = self::decode($mcpConfigOutput, $missing);
        $server = $root->mcpServers->{self::SERVER} ?? null;

        if (! $server instanceof stdClass) {
            throw new InvalidClientConfig($missing);
        }

        return $server;
    }

    /** Igual é ter o mesmo command e os mesmos args; env e outras chaves não contam. */
    public static function sameServer(stdClass $a, stdClass $b): bool
    {
        return ($a->command ?? null) === ($b->command ?? null) && ($a->args ?? null) === ($b->args ?? null);
    }

    public function server(): ?stdClass
    {
        return $this->root->mcpServers->{self::SERVER} ?? null;
    }

    /** Troca command e args e mantém as outras chaves do bloco e todo o resto do arquivo. */
    public function withServer(stdClass $new): self
    {
        $root = self::copy($this->root);
        $root->mcpServers ??= new stdClass;
        $server = $root->mcpServers->{self::SERVER} ?? new stdClass;
        $server->command = $new->command ?? null;
        $server->args = $new->args ?? [];
        $root->mcpServers->{self::SERVER} = $server;

        return new self($root);
    }

    public function toJson(): string
    {
        $json = json_encode($this->root, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        // O PHP indenta com 4 espaços; os clientes costumam usar 2.
        $json = (string) preg_replace_callback('/^ +/m', fn (array $spaces): string => str_repeat(' ', intdiv(strlen($spaces[0]), 2)), $json);

        return $json."\n";
    }

    private static function decode(string $json, string $error): mixed
    {
        try {
            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidClientConfig($error);
        }
    }

    private static function copy(stdClass $root): stdClass
    {
        return json_decode(json_encode($root, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private static function unknownShape(string $where): InvalidClientConfig
    {
        return new InvalidClientConfig("O arquivo tem um formato que o comando não conhece (esperava um objeto {$where}). Nada foi alterado.");
    }
}
