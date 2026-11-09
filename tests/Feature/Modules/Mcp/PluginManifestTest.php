<?php

use Laravel\Mcp\Server\Registrar;

function manifest(string $path): array
{
    $decoded = json_decode((string) file_get_contents(base_path($path)), true);

    expect($decoded)->toBeArray("{$path} não é um JSON válido");

    return $decoded;
}

it('has a marketplace that points to the plugin folder', function () {
    $marketplace = manifest('.claude-plugin/marketplace.json');

    expect($marketplace['name'])->toBe('me-avisa-ai')
        ->and($marketplace['plugins'][0]['name'])->toBe('me-avisa-ai')
        ->and($marketplace['plugins'][0]['source'])->toBe('./plugin');
});

it('describes the plugin', function () {
    $plugin = manifest('plugin/.claude-plugin/plugin.json');

    expect($plugin['name'])->toBe('me-avisa-ai')->and($plugin)->toHaveKeys(['version', 'description']);
});

it('starts the fiis server that routes/ai.php registers', function () {
    $server = manifest('plugin/.mcp.json')['mcpServers']['me-avisa-ai'];

    expect($server['command'])->toBe('docker')
        ->and($server['args'])->toContain('${ME_AVISA_AI_DIR}/compose.yaml')
        ->and(array_slice($server['args'], -2))->toBe(['mcp:start', 'fiis'])
        ->and(app(Registrar::class)->getLocalServer('fiis'))->not->toBeNull();
});

it('has a skill with name and description', function () {
    $skill = (string) file_get_contents(base_path('plugin/skills/fiis/SKILL.md'));

    expect($skill)->toStartWith("---\nname: fiis\ndescription: ")->and($skill)->toContain('me-avisa-ai');
});
