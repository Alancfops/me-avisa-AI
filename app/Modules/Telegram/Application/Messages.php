<?php

namespace App\Modules\Telegram\Application;

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\FavoriteView;
use App\Modules\Fiis\Application\MarketTime;
use App\Modules\Fiis\Application\PortfolioRow;
use App\Modules\Fiis\Application\PortfolioView;
use App\Modules\Fiis\Domain\AlertMode;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\RuleKind;
use DateTimeImmutable;

/**
 * Texto das mensagens do bot, em HTML do Telegram. Emojis com significado fixo
 * (docs/superpowers/specs/2026-11-04-bot-telegram-design.md, "Mensagens").
 * Informa, não recomenda.
 */
final readonly class Messages
{
    private const ESTIMATE = 'Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.';

    private const PORTFOLIO_SHOWN = 5;

    public function __construct(private string $appUrl) {}

    public function alert(Alert $alert, ?FavoriteView $view): string
    {
        return implode("\n", [
            '🔔 <b>'.self::e($alert->ticker).'</b> · '.self::ruleTitle($alert->rule),
            '💰 '.$alert->price.' · cotação de '.self::moment($alert->quotedAt),
            self::ruleEmoji($alert->rule).' Sua referência: '.self::e($alert->reference),
            '📊 '.self::priceToBook($view).' · 💸 '.self::dividendYield($view),
            '<i>'.self::e($alert->reason).'</i>',
            '',
            $this->link($alert->ticker),
        ]);
    }

    /** @param list<Alert> $alerts só os do dia */
    public function todayAlerts(array $alerts, string $day): string
    {
        $label = self::dayMonth($day);

        if ($alerts === []) {
            return "✅ Nenhum favorito bateu regra hoje ({$label}).";
        }

        $lines = ["🔔 <b>Avisos de hoje ({$label})</b>", ''];

        foreach ($alerts as $alert) {
            $lines[] = self::ruleEmoji($alert->rule).' <b>'.self::e($alert->ticker).'</b> · '.self::ruleShort($alert->rule)
                .' · '.$alert->price.' · '.MarketTime::local($alert->occurredAt)->format('H:i');
        }

        return implode("\n", $lines);
    }

    public function fund(FavoriteView $view): string
    {
        $title = '📈 <b>'.self::e($view->ticker).'</b>'.($view->name === null ? '' : ' · '.self::e($view->name));
        $dividend = $view->dividends === [] ? null : $view->dividends[array_key_last($view->dividends)];
        $median = $view->snapshot?->medianPriceToBook;
        $lines = [
            $title,
            '💰 '.self::price($view, withChange: true),
            '📊 '.self::priceToBook($view).($view->snapshot?->priceToBook !== null && $median !== null ? ' · mediana 12m '.$median : ''),
            '💸 '.self::dividendYield($view).($dividend === null ? '' : ' · último rendimento ≈ '.$dividend->perShare.' por cota'),
        ];

        if ($view->settings !== null) {
            $lines[] = '⭐ Favorito · regras: '.self::rules($view->settings);
        }

        return implode("\n", [...$lines, '', $this->link($view->ticker)]);
    }

    public function notFound(string $ticker): string
    {
        return '⚠️ Não achei <b>'.self::e($ticker).'</b>. Confira o código na B3.';
    }

    public function verUsage(): string
    {
        return '💡 Use assim: <code>/ver CÓDIGO</code>, com o código do fundo na B3 (termina em 11).';
    }

    /**
     * @param  list<FavoriteView>  $favorites
     * @param  list<string>  $alertedToday
     */
    public function favorites(array $favorites, array $alertedToday): string
    {
        if ($favorites === []) {
            return '⭐ Você ainda não tem favoritos. Favorite um FII no app para receber avisos aqui.';
        }

        $lines = ['⭐ <b>Seus favoritos ('.count($favorites).')</b>', ''];

        foreach ($favorites as $view) {
            $mark = in_array($view->ticker, $alertedToday, true) ? '🔔' : '•';
            $lines[] = "{$mark} <b>".self::e($view->ticker).'</b> · '.self::shortPrice($view);
        }

        return implode("\n", $lines);
    }

    public function portfolio(PortfolioView $view): string
    {
        if ($view->rows === []) {
            return '💼 Sua carteira está vazia. Adicione FIIs em Minha carteira, no app.';
        }

        $totals = $view->totals;
        $count = count($view->rows);
        $summary = [$count === 1 ? '1 fundo' : "{$count} fundos"];

        if ($totals->withoutPrice > 0) {
            $summary[] = "{$totals->withoutPrice} sem preço";
        }

        if ($totals->withoutDividends > 0) {
            $summary[] = "{$totals->withoutDividends} sem rendimento informado";
        }

        $lines = [
            '💼 <b>Sua carteira</b>',
            '💰 Valor: '.($totals->value ?? 'sem preço'),
            '💸 Por mês: '.($totals->lastIncome === null || $totals->averageIncome === null
                ? 'sem rendimento informado'
                : "{$totals->lastIncome} pelo último rendimento · {$totals->averageIncome} pela média"),
            implode(' · ', $summary),
            '',
        ];

        foreach (array_slice($view->rows, 0, self::PORTFOLIO_SHOWN) as $row) {
            $lines[] = self::holding($row);
        }

        return implode("\n", [...$lines, '', '<i>'.self::ESTIMATE.'</i>']);
    }

    public function help(): string
    {
        return implode("\n", [
            '📋 <b>Comandos</b>',
            '',
            '🔔 /alertas · avisos de hoje',
            '📈 /ver CÓDIGO · dados de um fundo',
            '⭐ /favoritos · seus favoritos',
            '💼 /carteira · sua carteira',
            '❓ /ajuda · esta lista',
        ]);
    }

    public function welcome(string $botName): string
    {
        return '🎉 ✅ Conectado ao <b>'.self::e($botName)."</b>!\n🔔 Os avisos do Me Avisa Aí vão chegar aqui.\n\n".$this->help();
    }

    public function howToConnect(): string
    {
        return '⚠️ Este bot ainda não está conectado ao app. Abra a aba <b>Telegram</b> no Me Avisa Aí, '
            .'gere um código e mande <code>/start CÓDIGO</code>.';
    }

    public function test(): string
    {
        return '✅ Mensagem de teste do Me Avisa Aí. Os avisos vão chegar aqui.';
    }

    private function link(string $ticker): string
    {
        return 'Ver no app: '.rtrim($this->appUrl, '/').'/fundos/'.rawurlencode($ticker);
    }

    private static function holding(PortfolioRow $row): string
    {
        $line = $row->line;
        $parts = ['<b>'.self::e($line->ticker).'</b>', $line->shares === 1 ? '1 cota' : "{$line->shares} cotas"];
        $parts[] = (string) ($line->positionValue() ?? 'sem preço');
        $income = $line->lastIncome();

        if ($income !== null) {
            $parts[] = "{$income}/mês";
        }

        return implode(' · ', $parts);
    }

    private static function price(FavoriteView $view, bool $withChange = false): string
    {
        if ($view->quote !== null) {
            $change = $withChange && $view->quote->changePercent !== null ? ' ('.self::signedPercent($view->quote->changePercent->toDecimal()).')' : '';

            return $view->quote->price.$change.' · cotação de '.self::moment($view->quote->quotedAt);
        }

        if ($view->close !== null) {
            return $view->close->close.' · fechamento de '.self::dayMonth($view->close->date).' (B3)';
        }

        return 'sem preço';
    }

    private static function shortPrice(FavoriteView $view): string
    {
        if ($view->quote !== null) {
            return $view->quote->price.' · '.self::moment($view->quote->quotedAt);
        }

        return $view->close === null ? 'sem preço' : $view->close->close.' · fechamento de '.self::dayMonth($view->close->date);
    }

    private static function priceToBook(?FavoriteView $view): string
    {
        $ratio = $view?->snapshot?->priceToBook;

        return $ratio === null ? 'sem P/VP' : 'P/VP '.$ratio;
    }

    private static function dividendYield(?FavoriteView $view): string
    {
        $fraction = $view?->dividendYield12m;

        return $fraction === null ? 'sem DY' : 'DY 12m '.number_format((float) $fraction * 100, 1, ',', '.').'%';
    }

    private static function rules(FavoriteSettings $settings): string
    {
        $rules = [match ($settings->mode) {
            AlertMode::Target => 'alvo '.$settings->target,
            AlertMode::Range => 'faixa '.$settings->rangeMin.'–'.$settings->rangeMax,
            AlertMode::Automatic => 'automático',
        }];

        if ($settings->sharpDrop) {
            $rules[] = 'queda forte';
        }

        return implode(', ', $rules);
    }

    private static function ruleTitle(RuleKind $rule): string
    {
        return match ($rule) {
            RuleKind::PriceTarget => '🎯 Preço alvo atingido',
            RuleKind::BuyRange => '📥 Dentro da faixa de compra',
            RuleKind::Automatic => '🤖 Regra automática atendida',
            RuleKind::SharpDrop => '🔻 Queda forte no dia',
        };
    }

    private static function ruleEmoji(RuleKind $rule): string
    {
        return match ($rule) {
            RuleKind::PriceTarget => '🎯',
            RuleKind::BuyRange => '📥',
            RuleKind::Automatic => '🤖',
            RuleKind::SharpDrop => '🔻',
        };
    }

    private static function ruleShort(RuleKind $rule): string
    {
        return match ($rule) {
            RuleKind::PriceTarget => 'alvo',
            RuleKind::BuyRange => 'faixa',
            RuleKind::Automatic => 'automático',
            RuleKind::SharpDrop => 'queda forte',
        };
    }

    /** Percentual da brapi ("-0.5") como "-0,50%". */
    private static function signedPercent(string $value): string
    {
        $number = (float) $value;

        return ($number > 0 ? '+' : '').number_format($number, 2, ',', '.').'%';
    }

    private static function moment(DateTimeImmutable $at): string
    {
        return MarketTime::local($at)->format('d/m, H:i');
    }

    private static function dayMonth(string $day): string
    {
        [, $month, $date] = explode('-', $day);

        return "{$date}/{$month}";
    }

    /** HTML401 escreve o apóstrofo como &#039;: o Telegram não aceita &apos;. */
    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML401, 'UTF-8');
    }
}
