<?php

namespace App\Modules\Mcp\Connect;

use Illuminate\Console\Command;
use stdClass;

/**
 * Grava o servidor me-avisa-ai no arquivo de um cliente de IA. Quem chama é o `bin/connect`
 * (`make connect`), que monta a pasta do cliente no contêiner e passa a saída do
 * `make mcp-config`. O código de saída diz ao script se pode testar a conexão.
 */
final class ConnectCommand extends Command
{
    /** Nada mudou por escolha: a pessoa disse não, ou não havia terminal para perguntar. */
    public const UNCHANGED = 2;

    protected $signature = 'mcp:connect
        {file : Arquivo do cliente, como o contêiner o vê}
        {--block= : Saída do make mcp-config}
        {--shown-as= : Caminho do arquivo no computador, para as mensagens}';

    protected $description = 'Grava o servidor MCP me-avisa-ai no arquivo de configuração de um cliente de IA';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $shownAs = (string) ($this->option('shown-as') ?: $file);
        // Gravar trocaria o link por um arquivo comum; e, no contêiner, o destino do link
        // costuma estar fora da pasta montada, então o conteúdo atual nem seria lido.
        if (is_link($file)) {
            $this->error("{$shownAs} é um link para outro arquivo. Nada foi alterado.");
            $this->line('Cole o bloco do make mcp-config no arquivo de destino (make connect CLIENT=json mostra o JSON).');

            return self::FAILURE;
        }

        $exists = is_file($file);

        if ($exists && ! is_readable($file)) {
            $this->error("Não dá para ler {$shownAs}: confira as permissões do arquivo. Nada foi alterado.");

            return self::FAILURE;
        }

        try {
            $config = ClientConfig::fromJson($exists ? (string) file_get_contents($file) : '');
            $new = ClientConfig::serverFrom((string) $this->option('block'));
        } catch (InvalidClientConfig $invalid) {
            $this->error("{$shownAs}: {$invalid->getMessage()}");

            return self::FAILURE;
        }

        $current = $config->server();

        if ($current !== null && ClientConfig::sameServer($current, $new)) {
            $this->info("✅ Já estava configurado em {$shownAs}. Nada foi alterado.");

            return self::SUCCESS;
        }

        if ($current !== null) {
            $this->line("Já existe um me-avisa-ai em {$shownAs}, diferente do novo:");
            $this->line('  Atual: '.$this->describe($current));
            $this->line('  Novo:  '.$this->describe($new));

            if (! $this->input->isInteractive()) {
                $this->warn('Rode make connect num terminal para escolher se troca. Nada foi alterado.');

                return self::UNCHANGED;
            }

            if (! $this->confirm('Trocar pelo novo?', false)) {
                $this->line('Nada foi alterado.');

                return self::UNCHANGED;
            }
        }

        // Backup, arquivo temporário e troca acontecem na pasta do arquivo.
        if (! is_writable(dirname($file))) {
            $this->error('Não dá para gravar na pasta de '.$shownAs.': confira as permissões. Nada foi alterado.');

            return self::FAILURE;
        }

        $backup = $exists ? $this->backup($file) : null;

        // Grava ao lado e troca de uma vez: o arquivo nunca fica pela metade.
        file_put_contents($file.'.tmp', $config->withServer($new)->toJson());

        if ($exists) {
            chmod($file.'.tmp', fileperms($file) & 0777);
        }

        rename($file.'.tmp', $file);

        $this->info("✅ Gravado em {$shownAs}.");

        if ($backup !== null) {
            $this->line("Backup do arquivo anterior: {$shownAs}{$backup}");
            $this->line('Para voltar a ele: cp '.escapeshellarg($shownAs.$backup).' '.escapeshellarg($shownAs));
        }

        return self::SUCCESS;
    }

    /** Copia o arquivo para um .bak-<data> novo, sem cobrir outro feito no mesmo segundo. */
    private function backup(string $file): string
    {
        $base = '.bak-'.now()->format('Y-m-d-His');
        $backup = $base;

        for ($n = 2; file_exists($file.$backup); $n++) {
            $backup = "{$base}-{$n}";
        }

        copy($file, $file.$backup);

        return $backup;
    }

    private function describe(stdClass $server): string
    {
        $args = is_array($server->args ?? null) ? $server->args : [];

        return trim(((string) ($server->command ?? '')).' '.implode(' ', array_map(strval(...), $args)));
    }
}
