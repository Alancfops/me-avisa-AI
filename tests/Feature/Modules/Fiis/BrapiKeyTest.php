<?php

use App\Modules\Fiis\Application\BrapiCredentials;
use App\Modules\Fiis\Infrastructure\Brapi\BrapiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const GOOD_KEY = 'aB3dE5fG7hJ9kL1mN2pQ4r';

function usageOk(): array
{
    return ['usage' => ['planName' => 'Free', 'planLimit' => 15000, 'currentUsage' => 10, 'remainingUsage' => 14990]];
}

beforeEach(fn () => config(['services.brapi.url' => 'https://brapi.test', 'services.brapi.token' => '']));

it('shows where the brapi key comes from on Settings', function (?string $env, bool $saved, ?string $source) {
    config(['services.brapi.token' => $env]);
    if ($saved) {
        app(BrapiCredentials::class)->save(GOOD_KEY);
    }

    $this->get('/settings/appearance')->assertInertia(fn ($page) => $page
        ->component('settings/appearance')
        ->where('brapi.source', $source));
})->with([
    'sem chave' => [null, false, null],
    'do .env' => ['env-key-1234567890', false, 'env'],
    'salva no app' => [null, true, 'app'],
]);

it('saves a pasted key after brapi accepts it, encrypted', function () {
    Http::fake(['brapi.test/api/v2/user/usage' => Http::response(usageOk())]);
    Cache::put('brapi.usage', ['velho'], 300);

    $this->post('/settings/brapi', ['token' => '  '.GOOD_KEY.'  '])->assertRedirect()->assertSessionHasNoErrors();

    expect(app(BrapiCredentials::class)->token())->toBe(GOOD_KEY)
        ->and(app(BrapiCredentials::class)->source())->toBe('app')
        ->and(DB::table('app_secrets')->value('value'))->not->toContain(GOOD_KEY)
        ->and(Cache::has('brapi.usage'))->toBeFalse();
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.GOOD_KEY));
});

it('refuses a key that cannot be used', function (array $body, Closure $fake, ?string $env) {
    config(['services.brapi.token' => $env]);
    $fake();

    $this->post('/settings/brapi', $body)->assertSessionHasErrors('token');

    expect(app(BrapiCredentials::class)->source())->toBe($env === null ? null : 'env');
})->with([
    'vazio' => [[], fn () => Http::fake(), null],
    'com espaço' => [['token' => 'minha chave'], fn () => Http::fake(), null],
    'recusada pela brapi' => [['token' => GOOD_KEY], fn () => Http::fake(['brapi.test/*' => Http::response([], 401)]), null],
    'brapi fora do ar' => [['token' => GOOD_KEY], fn () => Http::fake(['brapi.test/*' => Http::failedConnection()]), null],
    'chave já no .env' => [['token' => GOOD_KEY], fn () => Http::fake(), 'env-key-1234567890'],
]);

it('forgets the key saved in the app', function () {
    app(BrapiCredentials::class)->save(GOOD_KEY);

    $this->delete('/settings/brapi')->assertRedirect();

    expect(app(BrapiCredentials::class)->token())->toBeNull();
});

it('calls brapi with the saved key, and with the .env one first', function () {
    Http::fake(['brapi.test/*' => Http::response(['ok' => true])]);
    app(BrapiCredentials::class)->save(GOOD_KEY);

    app(BrapiClient::class)->get('/api/x');
    config(['services.brapi.token' => 'env-key-1234567890']);
    app(BrapiClient::class)->get('/api/x');

    $auth = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('Authorization')[0])->all();
    expect($auth)->toBe(['Bearer '.GOOD_KEY, 'Bearer env-key-1234567890']);
});

it('tells every page whether the brapi key is missing', function () {
    Http::fake(); // a Saúde consulta a cota da brapi

    $this->get('/saude')->assertInertia(fn ($page) => $page->where('brapiConfigured', false));

    app(BrapiCredentials::class)->save(GOOD_KEY);

    $this->get('/saude')->assertInertia(fn ($page) => $page->where('brapiConfigured', true));
});
