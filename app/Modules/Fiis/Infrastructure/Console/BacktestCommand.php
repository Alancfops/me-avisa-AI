<?php

namespace App\Modules\Fiis\Infrastructure\Console;

use App\Modules\Fiis\Application\Backtest;
use App\Modules\Fiis\Application\SimulatedAlert;
use App\Modules\Fiis\Application\SourceUnavailable;
use App\Modules\Fiis\Domain\RuleKind;
use Illuminate\Console\Command;

final class BacktestCommand extends Command
{
    protected $signature = 'fiis:backtest {ticker}';

    protected $description = 'Simula os avisos sobre preços passados, sem gravar nem enviar nada';

    public function handle(Backtest $backtest): int
    {
        $ticker = strtoupper(trim((string) $this->argument('ticker')));

        try {
            $result = $backtest->handle($ticker);
        } catch (SourceUnavailable $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->info("{$ticker}: {$result->tradingDays} pregões. Avisos que teriam saído: ".count($result->alerts).'.');

        foreach (RuleKind::cases() as $rule) {
            $count = count(array_filter($result->alerts, fn (SimulatedAlert $alert): bool => $alert->rule === $rule));

            if ($count > 0) {
                $this->line("  {$this->label($rule)}: {$count}");
            }
        }

        foreach ($result->alerts as $alert) {
            $this->line("  {$alert->day}  {$this->label($alert->rule)}  {$alert->price}  {$alert->reason}");
        }

        foreach ($result->unavailableReasons as $reason) {
            $this->warn("Regra sem avaliar em alguns dias: {$reason}");
        }

        $this->comment('Só contagem: não diz se a compra daria lucro. Nada foi gravado nem enviado.');

        return self::SUCCESS;
    }

    private function label(RuleKind $rule): string
    {
        return match ($rule) {
            RuleKind::PriceTarget => 'Preço alvo',
            RuleKind::BuyRange => 'Faixa de compra',
            RuleKind::Automatic => 'Regra automática',
            RuleKind::SharpDrop => 'Queda forte',
        };
    }
}
