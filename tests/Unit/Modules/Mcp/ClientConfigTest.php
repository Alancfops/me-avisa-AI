<?php

use App\Modules\Mcp\Connect\ClientConfig;
use App\Modules\Mcp\Connect\InvalidClientConfig;

function newServer(): stdClass
{
    return (object) [
        'command' => '/usr/bin/docker',
        'args' => ['compose', '--project-directory', '/home/a/me avisa', 'exec', '-T', 'web', 'php', 'artisan', 'mcp:start', 'fiis'],
    ];
}

it('treats a missing or blank file as an empty object', function (string $contents) {
    $config = ClientConfig::fromJson($contents);

    expect($config->server())->toBeNull()
        ->and(json_decode($config->withServer(newServer())->toJson(), true))
        ->toBe(['mcpServers' => ['me-avisa-ai' => (array) newServer()]]);
})->with(['empty' => '', 'blank' => "  \n"]);

it('keeps other servers, keys and empty objects untouched', function () {
    $json = ClientConfig::fromJson('{"preferences":{},"mcpServers":{"other":{"command":"x","args":[],"env":{}}},"n":1.5}')
        ->withServer(newServer())
        ->toJson();

    expect($json)->toContain('"preferences": {}')
        ->and($json)->toContain('"env": {}')
        ->and($json)->toContain('"n": 1.5')
        ->and(array_keys(json_decode($json, true)))->toBe(['preferences', 'mcpServers', 'n'])
        ->and(json_decode($json, true)['mcpServers']['other'])->toBe(['command' => 'x', 'args' => [], 'env' => []]);
});

it('rejects invalid json', function () {
    ClientConfig::fromJson('{"a":');
})->throws(InvalidClientConfig::class);

it('rejects a root that is not an object', function (string $contents) {
    ClientConfig::fromJson($contents);
})->with(['[]', '"x"', '1'])->throws(InvalidClientConfig::class);

it('rejects mcpServers or the block that are not objects', function (string $contents) {
    ClientConfig::fromJson($contents);
})->with([
    '{"mcpServers":[]}',
    '{"mcpServers":"x"}',
    '{"mcpServers":null}',
    '{"mcpServers":{"me-avisa-ai":[]}}',
])->throws(InvalidClientConfig::class);

it('replaces command and args and keeps the other block keys', function () {
    $server = ClientConfig::fromJson('{"mcpServers":{"me-avisa-ai":{"command":"docker","args":["a"],"env":{"X":"1"},"disabled":false}}}')
        ->withServer(newServer())
        ->server();

    expect($server->command)->toBe('/usr/bin/docker')
        ->and($server->args)->toBe(newServer()->args)
        ->and($server->env)->toEqual((object) ['X' => '1'])
        ->and($server->disabled)->toBeFalse();
});

it('does not change the original config', function () {
    $config = ClientConfig::fromJson('{"mcpServers":{"me-avisa-ai":{"command":"docker","args":["a"]}}}');

    $config->withServer(newServer());

    expect($config->server()->command)->toBe('docker');
});

it('compares only command and args', function () {
    $withEnv = newServer();
    $withEnv->env = (object) ['X' => '1'];
    $otherArgs = newServer();
    $otherArgs->args = ['compose'];

    expect(ClientConfig::sameServer(newServer(), $withEnv))->toBeTrue()
        ->and(ClientConfig::sameServer(newServer(), $otherArgs))->toBeFalse();
});

it('reads the block from the mcp-config output', function () {
    $output = json_encode(['mcpServers' => ['me-avisa-ai' => newServer()]], JSON_PRETTY_PRINT);

    expect(ClientConfig::serverFrom((string) $output))->toEqual(newServer());
});

it('rejects an mcp-config output without the block', function () {
    ClientConfig::serverFrom('{}');
})->throws(InvalidClientConfig::class);

it('writes two-space json with unescaped slashes and a final newline', function () {
    $json = ClientConfig::fromJson('')->withServer(newServer())->toJson();

    expect($json)->toStartWith("{\n  \"mcpServers\": {\n    \"me-avisa-ai\"")
        ->and($json)->toContain('"/usr/bin/docker"')
        ->and($json)->toEndWith("}\n");
});
