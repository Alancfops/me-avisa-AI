<?php

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

it('speaks only json over stdio', function () {
    $messages = [
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new stdClass,
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ]],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'list_favorites', 'arguments' => new stdClass]],
    ];
    $input = implode("\n", array_map('json_encode', $messages))."\n";

    $result = Process::path(base_path())->input($input)->timeout(30)->run(['php', 'artisan', 'mcp:start', 'fiis']);

    $lines = array_values(array_filter(explode("\n", $result->output()), fn (string $line): bool => trim($line) !== ''));

    expect($lines)->not->toBeEmpty();

    foreach ($lines as $line) {
        expect(json_decode($line, true))->toBeArray("saída que não é JSON: {$line}");
    }

    $initialize = json_decode($lines[0], true);
    expect($initialize['id'])->toBe(1)
        ->and($initialize['result']['serverInfo']['name'])->toBe('Me Avisa Aí');

    $responses = collect($lines)->map(fn (string $line): array => json_decode($line, true))->keyBy('id');
    expect($responses[2]['result']['tools'])->toHaveCount(10)
        ->and($responses[3]['result']['isError'] ?? false)->toBeFalse()
        ->and($responses[3]['result']['content'])->not->toBeEmpty();
});

it('has no web transport', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route): string => $route->uri());

    expect($uris->contains(fn (string $uri): bool => $uri === 'mcp' || str_starts_with($uri, 'mcp/')))->toBeFalse();
});
