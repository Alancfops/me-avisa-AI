<?php

use App\Modules\Fiis\Application\Alert;
use App\Modules\Fiis\Application\FavoriteView;
use App\Modules\Fiis\Application\PortfolioRow;
use App\Modules\Fiis\Application\PortfolioView;
use App\Modules\Fiis\Application\Quote;
use App\Modules\Fiis\Domain\DailyClose;
use App\Modules\Fiis\Domain\FavoriteSettings;
use App\Modules\Fiis\Domain\FundSnapshot;
use App\Modules\Fiis\Domain\Money;
use App\Modules\Fiis\Domain\MonthlyDividend;
use App\Modules\Fiis\Domain\Percentage;
use App\Modules\Fiis\Domain\PortfolioLine;
use App\Modules\Fiis\Domain\PortfolioTotals;
use App\Modules\Fiis\Domain\Ratio;
use App\Modules\Fiis\Domain\RuleKind;
use App\Modules\Fiis\Domain\WindowAverage;
use App\Modules\Telegram\Application\Messages;

function tgMessages(): Messages
{
    return new Messages('http://127.0.0.1:8000/');
}

function tgAt(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time);
}

function fundView(array $o = []): FavoriteView
{
    $favorite = $o['favorite'] ?? true;
    $price = Money::fromString($o['price'] ?? '146.46');
    $quote = $favorite ? new Quote('KNRI11', $price, Percentage::fromString($o['change'] ?? '-0.5'), null, tgAt('2026-10-30 14:30:00-03:00')) : null;
    $snapshot = new FundSnapshot('KNRI11', $price, null, null,
        array_key_exists('pvp', $o) ? ($o['pvp'] === null ? null : Ratio::fromString($o['pvp'])) : Ratio::fromString('0.95'),
        Ratio::fromString('0.98'), 200);

    return new FavoriteView(
        'KNRI11',
        array_key_exists('name', $o) ? $o['name'] : 'Kinea Renda Imobiliária',
        $quote,
        $favorite ? ($o['settings'] ?? FavoriteSettings::target(Money::fromString('150'), true)) : null,
        $snapshot,
        [],
        array_key_exists('dy', $o) ? $o['dy'] : '0.098',
        null,
        new WindowAverage(6, 0, null),
        new WindowAverage(12, 0, null),
        [],
        [new MonthlyDividend('2026-09', Money::fromString('1.10'))],
        $favorite ? null : new DailyClose('2026-10-29', $price),
    );
}

function alertOf(RuleKind $rule, string $ticker = 'KNRI11', string $occurred = '2026-10-30 14:30:00-03:00'): Alert
{
    return new Alert($ticker, $rule, Money::fromString('146.46'), 'alvo R$ 150,00', 'Preço R$ 146,46 no alvo R$ 150,00', tgAt($occurred), tgAt('2026-10-30 14:30:00-03:00'));
}

it('formats an alert with price, reference, P/VP, DY and link', function () {
    expect(tgMessages()->alert(alertOf(RuleKind::PriceTarget), fundView()))->toBe(implode("\n", [
        '🔔 <b>KNRI11</b> · 🎯 Preço alvo atingido',
        '💰 R$ 146,46 · cotação de 30/10, 14:30',
        '🎯 Sua referência: alvo R$ 150,00',
        '📊 P/VP 0,95 · 💸 DY 12m 9,8%',
        '<i>Preço R$ 146,46 no alvo R$ 150,00</i>',
        '',
        'Ver no app: http://127.0.0.1:8000/fundos/KNRI11',
    ]));
});

it('says what is missing instead of zero', function () {
    $text = tgMessages()->alert(alertOf(RuleKind::SharpDrop), fundView(['pvp' => null, 'dy' => null]));

    expect($text)->toContain('🔔 <b>KNRI11</b> · 🔻 Queda forte no dia')
        ->toContain('📊 sem P/VP · 💸 sem DY')
        ->not->toContain('R$ 0');
    expect(tgMessages()->alert(alertOf(RuleKind::Automatic), null))->toContain('🤖 Regra automática atendida')->toContain('📊 sem P/VP · 💸 sem DY');
});

it('lists today alerts and the empty day', function () {
    expect(tgMessages()->todayAlerts([alertOf(RuleKind::PriceTarget), alertOf(RuleKind::SharpDrop, 'MXRF11', '2026-10-30 11:00:00-03:00')], '2026-10-30'))
        ->toBe(implode("\n", [
            '🔔 <b>Avisos de hoje (30/10)</b>',
            '',
            '🎯 <b>KNRI11</b> · alvo · R$ 146,46 · 14:30',
            '🔻 <b>MXRF11</b> · queda forte · R$ 146,46 · 11:00',
        ]))
        ->and(tgMessages()->todayAlerts([], '2026-10-30'))->toBe('✅ Nenhum favorito bateu regra hoje (30/10).');
});

it('describes a favorite fund', function () {
    expect(tgMessages()->fund(fundView()))->toBe(implode("\n", [
        '🏢 <b>KNRI11</b> · Kinea Renda Imobiliária',
        '💰 R$ 146,46 (-0,50%) · cotação de 30/10, 14:30',
        '📊 P/VP 0,95 · mediana 12m 0,98',
        '💸 DY 12m 9,8% · último rendimento ≈ R$ 1,10 por cota',
        '⭐ Favorito · regras: alvo R$ 150,00, queda forte',
        '',
        'Ver no app: http://127.0.0.1:8000/fundos/KNRI11',
    ]));
});

it('describes a fund that is not a favorite by the B3 close', function () {
    $text = tgMessages()->fund(fundView(['favorite' => false]));

    expect($text)->toContain('💰 R$ 146,46 · fechamento de 29/10 (B3)')->not->toContain('⭐');
});

it('escapes names that come from outside', function () {
    expect(tgMessages()->fund(fundView(['name' => 'Fundo A&B <x>'])))->toContain('Fundo A&amp;B &lt;x&gt;');
});

it('answers unknown tickers and a missing ticker', function () {
    expect(tgMessages()->notFound('XPTO11'))->toBe('⚠️ Não achei <b>XPTO11</b>. Confira o código na B3.')
        ->and(tgMessages()->verUsage())->toBe('💡 Use assim: <code>/ver CÓDIGO</code>, com o código do fundo na B3 (termina em 11).');
});

it('lists favorites marking the ones alerted today', function () {
    expect(tgMessages()->favorites([fundView()], ['KNRI11']))->toBe(implode("\n", [
        '⭐ <b>Seus favoritos (1)</b>',
        '',
        '🔔 <b>KNRI11</b> · R$ 146,46 · 30/10, 14:30',
    ]))
        ->and(tgMessages()->favorites([fundView()], []))->toContain('• <b>KNRI11</b>')
        ->and(tgMessages()->favorites([], []))->toBe('⭐ Você ainda não tem favoritos. Favorite um FII no app para receber avisos aqui.');
});

it('summarizes the portfolio with at most five funds and the estimate note', function () {
    $lines = [];
    foreach (['AAAA11', 'BBBB11', 'CCCC11', 'DDDD11', 'EEEE11', 'FFFF11'] as $i => $ticker) {
        $lines[] = new PortfolioLine($ticker, 10, Money::fromString((string) (60 - $i * 10 + 10)), [new MonthlyDividend('2026-09', Money::fromString('0.10'))]);
    }
    $lines[] = new PortfolioLine('GGGG11', 1, null, []);
    $view = new PortfolioView(array_map(fn ($l) => new PortfolioRow($l, null, null, null), $lines), PortfolioTotals::of($lines));

    $text = tgMessages()->portfolio($view);

    expect($text)->toContain('💼 <b>Sua carteira</b>')
        ->toContain('💰 Valor: R$ 2.700,00')
        ->toContain('💸 Por mês: R$ 6,00 pelo último rendimento · R$ 6,00 pela média')
        ->toContain('7 fundos · 1 sem preço · 1 sem rendimento informado')
        ->toContain('<b>AAAA11</b> · 10 cotas · R$ 700,00 · R$ 1,00/mês')
        ->toContain('<b>EEEE11</b>')
        ->not->toContain('FFFF11')
        ->toContain('<i>Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.</i>');
    expect(tgMessages()->portfolio(new PortfolioView([], PortfolioTotals::of([]))))
        ->toBe('💼 Sua carteira está vazia. Adicione FIIs em Minha carteira, no app.');
});

it('shows the commands menu with an emoji per command', function () {
    expect(tgMessages()->help())->toBe(implode("\n", [
        '📋 <b>Comandos</b>',
        '',
        '🔔 /alertas · avisos de hoje',
        '🏢 /ver CÓDIGO · dados de um fundo',
        '⭐ /favoritos · seus favoritos',
        '💼 /carteira · sua carteira',
        '❓ /ajuda · esta lista',
    ]))->and(tgMessages()->welcome('Me Avisa'))->toStartWith("🎉 ✅ Conectado ao <b>Me Avisa</b>!\n🔔 Os avisos do Me Avisa Aí vão chegar aqui.\n\n📋");
});

it('never sounds like advice', function () {
    $all = implode("\n", [
        tgMessages()->alert(alertOf(RuleKind::BuyRange), fundView()),
        tgMessages()->fund(fundView()), tgMessages()->help(), tgMessages()->welcome('Me Avisa'),
        tgMessages()->howToConnect(), tgMessages()->test(),
    ]);

    expect(mb_strtolower($all))->not->toContain('oportunidade')->not->toContain('compre')->not->toContain('barato')
        ->and(tgMessages()->help())->toContain('/alertas')->toContain('/ver CÓDIGO')->not->toContain('KNRI11')->toContain('/favoritos')->toContain('/carteira')->toContain('/ajuda');
});
