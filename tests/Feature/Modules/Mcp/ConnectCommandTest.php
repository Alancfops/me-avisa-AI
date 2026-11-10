<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Carbon::setTestNow('2026-11-10 10:20:30');
    $this->dir = sys_get_temp_dir().'/connect-'.uniqid();
    mkdir($this->dir);
    $this->file = $this->dir.'/mcp.json';
    $this->server = [
        'command' => '/usr/bin/docker',
        'args' => ['compose', '--project-directory', '/home/a/me avisa', 'exec', '-T', 'web', 'php', 'artisan', 'mcp:start', 'fiis'],
    ];
    $this->block = (string) json_encode(['mcpServers' => ['me-avisa-ai' => $this->server]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $this->connect = fn (array $extra = []) => $this->artisan('mcp:connect', [
        'file' => $this->file,
        '--block' => $this->block,
        '--shown-as' => '/home/a/My Files/mcp.json',
    ] + $extra);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function backups(string $dir): array
{
    return glob($dir.'/mcp.json.bak-*') ?: [];
}

it('creates the file when it does not exist', function () {
    ($this->connect)()
        ->expectsOutputToContain('Gravado em /home/a/My Files/mcp.json')
        ->assertExitCode(0);

    expect(json_decode((string) file_get_contents($this->file), true))->toBe(['mcpServers' => ['me-avisa-ai' => $this->server]])
        ->and(backups($this->dir))->toBeEmpty();
});

it('keeps what the file already had and makes a backup', function () {
    $original = '{"preferences":{"x":1},"mcpServers":{"other":{"command":"o","args":[]}}}';
    file_put_contents($this->file, $original);

    ($this->connect)()
        ->expectsOutputToContain('cp "/home/a/My Files/mcp.json.bak-2026-11-10-102030" "/home/a/My Files/mcp.json"')
        ->assertExitCode(0);

    $written = json_decode((string) file_get_contents($this->file), true);
    expect(file_get_contents($this->file.'.bak-2026-11-10-102030'))->toBe($original)
        ->and($written['preferences'])->toBe(['x' => 1])
        ->and($written['mcpServers']['other'])->toBe(['command' => 'o', 'args' => []])
        ->and($written['mcpServers']['me-avisa-ai'])->toBe($this->server);
});

it('says it was already configured and writes nothing', function () {
    $original = json_encode(['mcpServers' => ['me-avisa-ai' => $this->server + ['env' => ['X' => '1']]]]);
    file_put_contents($this->file, $original);
    touch($this->file, 1_000_000);
    clearstatcache();

    ($this->connect)()
        ->expectsOutputToContain('Já estava configurado')
        ->assertExitCode(0);

    clearstatcache();
    expect(file_get_contents($this->file))->toBe($original)
        ->and(filemtime($this->file))->toBe(1_000_000)
        ->and(backups($this->dir))->toBeEmpty();
});

it('replaces a different block when the person says yes', function () {
    file_put_contents($this->file, json_encode(['mcpServers' => ['me-avisa-ai' => ['command' => 'docker', 'args' => ['old'], 'env' => ['X' => '1']]]]));

    ($this->connect)()
        ->expectsOutputToContain('docker old')
        ->expectsConfirmation('Trocar pelo novo?', 'yes')
        ->assertExitCode(0);

    $server = json_decode((string) file_get_contents($this->file), true)['mcpServers']['me-avisa-ai'];
    expect($server['args'])->toBe($this->server['args'])
        ->and($server['env'])->toBe(['X' => '1'])
        ->and(backups($this->dir))->toHaveCount(1);
});

it('changes nothing when the person says no', function () {
    $original = (string) json_encode(['mcpServers' => ['me-avisa-ai' => ['command' => 'docker', 'args' => ['old']]]]);
    file_put_contents($this->file, $original);

    ($this->connect)()
        ->expectsConfirmation('Trocar pelo novo?', 'no')
        ->expectsOutputToContain('Nada foi alterado')
        ->assertExitCode(2);

    expect(file_get_contents($this->file))->toBe($original)
        ->and(backups($this->dir))->toBeEmpty();
});

it('changes nothing without a terminal', function () {
    $original = (string) json_encode(['mcpServers' => ['me-avisa-ai' => ['command' => 'docker', 'args' => ['old']]]]);
    file_put_contents($this->file, $original);

    ($this->connect)(['--no-interaction' => true])
        ->expectsOutputToContain('make connect num terminal')
        ->assertExitCode(2);

    expect(file_get_contents($this->file))->toBe($original);
});

it('leaves an invalid file untouched', function () {
    file_put_contents($this->file, '{"a":');

    ($this->connect)()
        ->expectsOutputToContain('não é um JSON válido')
        ->assertExitCode(1);

    expect(file_get_contents($this->file))->toBe('{"a":')
        ->and(backups($this->dir))->toBeEmpty()
        ->and(glob($this->dir.'/*'))->toHaveCount(1);
});

it('keeps the permissions of the file it replaces', function () {
    file_put_contents($this->file, '{}');
    chmod($this->file, 0600);

    ($this->connect)()->assertExitCode(0);

    clearstatcache();
    expect(fileperms($this->file) & 0777)->toBe(0600);
});

it('refuses a symlinked file and changes nothing', function (bool $reachable) {
    $target = $reachable ? $this->dir.'/real.json' : '/nowhere/real.json';
    if ($reachable) {
        file_put_contents($target, '{"mcpServers":{"other":{"command":"o","args":[]}}}');
    }
    symlink($target, $this->file);

    ($this->connect)()
        ->expectsOutputToContain('é um link')
        ->assertExitCode(1);

    expect(is_link($this->file))->toBeTrue()
        ->and(readlink($this->file))->toBe($target)
        ->and(backups($this->dir))->toBeEmpty();

    if ($reachable) {
        expect(file_get_contents($target))->toBe('{"mcpServers":{"other":{"command":"o","args":[]}}}');
    }
})->with(['reachable' => true, 'dangling' => false]);
