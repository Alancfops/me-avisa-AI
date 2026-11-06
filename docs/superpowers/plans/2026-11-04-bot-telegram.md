# Bot do Telegram Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bot do Telegram que recebe todo aviso do site e responde a `/start <código>`, `/alertas`, `/ver`, `/favoritos`, `/carteira` e `/ajuda`, com aba de configuração no site e README atualizado.

**Architecture:** Módulo novo `app/Modules/Telegram/` (Domain/Application/Infrastructure/Http) que consome casos de uso do módulo de FIIs. O módulo de FIIs ganha a porta `AlertNotifier`; o Telegram a implementa com um job na fila. Recebimento por long polling num serviço `bot` do Compose.

**Tech Stack:** Laravel 12, Pest, `Http::fake`, PostgreSQL, fila `database`, React + Inertia + TypeScript, shadcn/ui.

**Spec:** `docs/superpowers/specs/2026-11-04-bot-telegram-design.md`

## Global Constraints

- Token só em `TELEGRAM_BOT_TOKEN` (`.env` → `config('services.telegram.token')`, URL base `config('services.telegram.url')`, padrão `https://api.telegram.org`). Nunca no banco, no log ou numa mensagem de erro.
- Sem webhook: só `getMe`, `getUpdates` (`timeout` 30) e `sendMessage` (`parse_mode=HTML`, `disable_web_page_preview=true`).
- Um chat conectado. Conectado: outros chats são ignorados sem resposta. Não conectado: `/start` sem código ou com código inválido recebe só a explicação de como conectar.
- Código de conexão: 6 caracteres de `ABCDEFGHJKMNPQRSTUVWXYZ23456789`, válido por 15 min; gerar outro invalida o anterior.
- Emojis com significado fixo (tabela da spec): 🔔 aviso, 🎯 alvo, 📥 faixa, 🤖 automático, 🔻 queda forte, 💰 preço, 📊 P/VP, 💸 rendimento/DY, 💼 carteira, ⭐ favoritos, ✅ ok, ⚠️ falta/problema; 🏢 título do `/ver`.
- Texto vindo de fora (nome do fundo, motivo) passa por `htmlspecialchars`. Datas `dd/mm, HH:MM` em `America/Sao_Paulo`. Dinheiro pelo `(string) Money` (`R$ 1.234,50`). Dado ausente: "sem preço", "sem P/VP", "sem DY", nunca R$ 0 ou "null".
- Mensagens informam, não recomendam: nada de "oportunidade", "compre", "barato".
- Links: `rtrim(config('app.url'), '/').'/fundos/{TICKER}'`.
- Módulo de FIIs não depende de `App\Modules\Telegram` (teste de arquitetura).
- Comandos pelo `make` (`make test`, `make check`); front `npm run check`, `npm run types:check`.

## Review Focus

- Mensagem de um chat estranho depois de conectado → nenhuma chamada a `sendMessage` para ele.
- `update_id` repetido depois de reiniciar o poll → não responde de novo.
- Nome de fundo com `&` ou `<` → escapado, e o Telegram aceita a mensagem (HTML válido).
- Telegram fora do ar ou token revogado → aviso continua gravado no site; job falha e a Saúde mostra; o poll não morre em laço apertado (espera antes de tentar de novo).
- `/ver knri11`, `/ver` sem ticker, `/VER KNRI11`, `/ver@NomeDoBot KNRI11` → todos tratados.

---

### Task 1: Porta `AlertNotifier` no módulo de FIIs

**Files:**
- Create: `app/Modules/Fiis/Application/AlertNotifier.php`, `app/Modules/Fiis/Application/NullAlertNotifier.php`
- Modify: `app/Modules/Fiis/Application/EvaluateFavorites.php` (construtor + chamada depois de `apply`), `app/Modules/Fiis/FiisServiceProvider.php` (binding padrão), `app/Modules/Fiis/Application/Source.php` (`case Telegram = 'telegram';`), testes que constroem `EvaluateFavorites`
- Create: `tests/Support/Fiis/RecordingNotifier.php`
- Test: `tests/Unit/Modules/Fiis/Application/EvaluateFavoritesTest.php`

**Interfaces:**
- Produces: `interface AlertNotifier { public function notify(Alert $alert): void; }`; `NullAlertNotifier` (não faz nada); `Source::Telegram`.

- [ ] **Step 1: Testes que falham:** `it('notifies every alert it records')` (o notifier recebe o mesmo `Alert` gravado, uma vez); `it('does not notify when no alert is recorded')` (regra continua valendo dentro das 24 h → zero chamadas); `it('keeps the alert when the notifier throws')` (notifier que lança exceção → `apply` já foi chamado com o alerta e o erro vai para o logger).
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. Chamar `notify` depois de `apply`, dentro de `try/catch (Throwable)` próprio, com log `"Envio do aviso de {$ticker} falhou: ".$e::class`.
- [ ] **Step 4:** `make check` → PASS.
- [ ] **Step 5:** Commit `feat(fiis): alert notifier port`.

### Task 2: Base do módulo Telegram (config, API, estado)

**Files:**
- Create: `app/Modules/Telegram/TelegramServiceProvider.php` (registrar em `bootstrap/providers.php`)
- Modify: `config/services.php` (`'telegram' => ['url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'), 'token' => env('TELEGRAM_BOT_TOKEN')]`), `.env.example` (`# Token do bot: crie no @BotFather` + `TELEGRAM_BOT_TOKEN=`)
- Create: `app/Modules/Telegram/Application/TelegramApi.php`, `BotProfile.php`, `IncomingMessage.php`, `TelegramUnavailable.php`, `TelegramState.php`
- Create: `app/Modules/Telegram/Infrastructure/HttpTelegramApi.php`, `EloquentTelegramState.php`, `TelegramStateModel.php`
- Create: `database/migrations/2026_11_05_000000_create_telegram_state_table.php` (uma linha: `chat_id` bigint null, `connected_at` timestampTz null, `link_code` string(6) null, `link_code_expires_at` timestampTz null, `last_update_id` bigint null, timestamps)
- Modify: `tests/Arch/LayersTest.php` (mesmas regras para `App\Modules\Telegram\*`; `App\Modules\Fiis` não usa `App\Modules\Telegram`)
- Create: `tests/Support/Telegram/FakeTelegramApi.php`, `InMemoryTelegramState.php`
- Test: `tests/Feature/Modules/Telegram/HttpTelegramApiTest.php`, `tests/Feature/Modules/Telegram/TelegramStateTest.php`

**Interfaces:**
- Produces:
  - `interface TelegramApi { isConfigured(): bool; me(): BotProfile; updates(?int $offset): list<IncomingMessage>; send(int $chatId, string $html): void; }` — erros de rede/HTTP/`ok:false` viram `TelegramUnavailable` (mensagem em português, sem token).
  - `final readonly class BotProfile(string $username, string $name)`; `final readonly class IncomingMessage(int $updateId, int $chatId, string $text)` (updates sem `message.text` são devolvidos com `text` `''`, para o offset avançar).
  - `interface TelegramState { chatId(): ?int; connectedAt(): ?DateTimeImmutable; connect(int $chatId, DateTimeImmutable $at): void; disconnect(): void; linkCode(): ?string; linkCodeExpiresAt(): ?DateTimeImmutable; saveLinkCode(string $code, DateTimeImmutable $expiresAt): void; clearLinkCode(): void; lastUpdateId(): ?int; saveLastUpdateId(int $id): void; }`

- [ ] **Step 1: Testes que falham:** `HttpTelegramApi` com `Http::fake`: `getMe` vira `BotProfile`; `getUpdates` manda `offset` e `timeout=30` e devolve `IncomingMessage`s; `sendMessage` manda `chat_id`, `text`, `parse_mode=HTML`, `disable_web_page_preview=true`; 401 e `ok:false` → `TelegramUnavailable` cuja mensagem não contém o token; sem token → `isConfigured()` false e nenhuma requisição. `EloquentTelegramState`: conectar, desconectar, código e `last_update_id` persistem numa linha só. Arquitetura: as novas regras passam.
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. URL `{url}/bot{token}/{método}`; timeout HTTP 35 s para `getUpdates`, 10 s para os outros.
- [ ] **Step 4:** `make check` → PASS.
- [ ] **Step 5:** Commit `feat(telegram): module base, API client and state`.

### Task 3: Mensagens

**Files:**
- Create: `app/Modules/Telegram/Application/Messages.php`
- Test: `tests/Unit/Modules/Telegram/MessagesTest.php`

**Interfaces:**
- Consumes: `Alert`, `FavoriteView` (de `DescribeFund`/`ListFavorites`), `PortfolioView`, `Clock`, `MarketTime`.
- Produces: `final readonly class Messages(string $appUrl)` com `alert(Alert, ?FavoriteView): string`, `todayAlerts(list<Alert>, string $day): string`, `fund(FavoriteView): string`, `notFound(string $ticker): string`, `verUsage(): string`, `favorites(list<FavoriteView>, list<string> $alertedToday): string`, `portfolio(PortfolioView): string`, `help(): string`, `welcome(string $botName): string`, `howToConnect(): string`, `test(): string`.

- [ ] **Step 1: Testes que falham** com as strings exatas dos modelos da spec ("Mensagens"): aviso de alvo com P/VP e DY; aviso sem P/VP ("sem P/VP"); `/alertas` com dois avisos e o vazio `✅ Nenhum favorito bateu regra hoje (30/10).`; `/ver` favorito (linha ⭐ com as regras), não favorito ("fechamento de 29/10 (B3)", sem linha ⭐), fora do catálogo `⚠️ Não achei XPTO11. Confira o código na B3.`; `/favoritos` com 🔔 no que teve aviso e o vazio; `/carteira` com totais, "N fundos ficaram fora do total", no máximo 5 posições e a linha fixa da estimativa; vazio; nome `Fundo A&B <x>` sai `Fundo A&amp;B &lt;x&gt;`; nenhum texto contém "oportunidade", "compre" ou "barato".
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. Rótulos das regras: alvo "🎯 Preço alvo atingido", faixa "📥 Dentro da faixa de compra", automático "🤖 Regra automática atendida", queda "🔻 Queda forte no dia".
- [ ] **Step 4:** `make check` → PASS.
- [ ] **Step 5:** Commit `feat(telegram): message formatting`.

### Task 4: Comandos, conexão e envio dos avisos

**Files:**
- Create: `app/Modules/Telegram/Application/HandleMessage.php`, `CreateLinkCode.php`, `Disconnect.php`, `SendToConnectedChat.php`
- Create: `app/Modules/Telegram/Infrastructure/TelegramAlertNotifier.php`, `SendTelegramMessageJob.php`
- Modify: `TelegramServiceProvider.php` (bind `AlertNotifier` → `TelegramAlertNotifier`, sobrescrevendo o `NullAlertNotifier`)
- Test: `tests/Unit/Modules/Telegram/HandleMessageTest.php`, `tests/Unit/Modules/Telegram/CreateLinkCodeTest.php`, `tests/Feature/Modules/Telegram/AlertDeliveryTest.php`

**Interfaces:**
- Consumes: Task 2 (`TelegramApi`, `TelegramState`), Task 3 (`Messages`), `ListAlerts`, `DescribeFund`, `ListFavorites`, `ShowPortfolio`, `CatalogRepository`, `Clock`, `SourceLog`.
- Produces:
  - `HandleMessage::handle(IncomingMessage $message): void` — interpreta o comando (`/cmd`, `/cmd@bot`, maiúsculas e minúsculas, ticker normalizado) e responde pelo `TelegramApi::send`.
  - `CreateLinkCode::handle(): string` (grava código e validade de 15 min).
  - `Disconnect::handle(): void`.
  - `SendToConnectedChat::handle(string $html): bool` (false se não há chat; `SourceLog` success/failure em `Source::Telegram`, relança `TelegramUnavailable` para o job tentar de novo).
  - `SendTelegramMessageJob(string $html)` com `$tries = 3` e `$backoff = [10, 60]`.

- [ ] **Step 1: Testes que falham:** conexão com código certo (grava chat, apaga código, responde `welcome`), errado, vencido e já substituído (responde `howToConnect`, não conecta); conectado: chat estranho → nenhuma resposta; cada comando chama o `Messages` certo (`/alertas` só com avisos do dia corrente em São Paulo pela `occurredAt`); `/ver` sem ticker → `verUsage`; texto livre → `help`. `AlertDelivery`: com chat conectado, um `Alert` notificado vira job e `sendMessage` com o texto de `Messages::alert`; sem chat → nada enviado; API fora → job falha, aviso continua no banco e a Saúde registra falha em `telegram`.
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. `TelegramAlertNotifier::notify` monta o texto (com `DescribeFund` para P/VP e DY) e despacha o job; sem chat conectado não despacha.
- [ ] **Step 4:** `make check` → PASS.
- [ ] **Step 5:** Commit `feat(telegram): commands, linking and alert delivery`.

### Task 5: Poll e serviço `bot`

**Files:**
- Create: `app/Modules/Telegram/Application/PollOnce.php`, `app/Modules/Telegram/Infrastructure/Console/PollCommand.php` (`telegram:poll`, opção `--once` para testes)
- Modify: `compose.yaml` (serviço `bot`: `<<: *app`, `command: ["php", "artisan", "telegram:poll"]`, `depends_on: web: condition: service_healthy`), `docker/smoke-test.sh` (lista `bot postgres scheduler web worker` e mensagem "os cinco serviços")
- Test: `tests/Unit/Modules/Telegram/PollOnceTest.php`, `tests/Feature/Modules/Telegram/PollCommandTest.php`

**Interfaces:**
- Consumes: Task 2, Task 4.
- Produces: `PollOnce::handle(): int` (quantas mensagens tratou). Offset = `lastUpdateId + 1`; grava `last_update_id` depois de cada mensagem tratada; erro de uma mensagem é logado e não impede as seguintes.

- [ ] **Step 1: Testes que falham:** não reprocessa `update_id` já gravado; grava o maior id; mensagem que lança exceção não trava as outras; sem token → `PollOnce` não chama a API e devolve 0; `telegram:poll --once` roda uma rodada e sai 0; falha da API → `SourceLog` falha em `telegram`.
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. Sem `--once`, laço infinito: sem token dorme 60 s; depois de `TelegramUnavailable` dorme 10 s; trata `SIGTERM` (pcntl já está na imagem) para sair limpo no `make stop`.
- [ ] **Step 4:** `make check` → PASS; `sh docker/smoke-test.sh` → "smoke test passou".
- [ ] **Step 5:** Commit `feat(telegram): long polling service`.

### Task 6: Aba Telegram no site e Saúde

**Files:**
- Create: `app/Modules/Telegram/Http/TelegramController.php`, `resources/js/pages/telegram.tsx`
- Modify: `routes/web.php` (`GET telegram` `telegram`, `POST telegram/codigo` `telegram.code`, `POST telegram/teste` `telegram.test`, `DELETE telegram` `telegram.disconnect`), `resources/js/components/app-sidebar.tsx` (item "Telegram", ícone `Send`, antes de Saúde), página/controller da Saúde para listar a fonte `telegram`
- Test: `tests/Feature/Modules/Telegram/TelegramPageTest.php`

**Interfaces:**
- Consumes: Tasks 2 e 4.
- Produces (Inertia `telegram`): `state: 'no_token' | 'invalid_token' | 'ready' | 'connected'`, `bot: {username, name} | null`, `code: string | null`, `codeExpiresAt: string | null` (ATOM), `connectedAt: string | null`, `link: string | null` (`https://t.me/{username}?start={code}`), `commands: list<{command, example, description}>`.

- [ ] **Step 1: Testes que falham:** os quatro estados (sem token; `getMe` 401; pronto com código gerado na hora se não houver válido; conectado); `POST telegram/codigo` troca o código; `POST telegram/teste` envia `Messages::test()` ao chat e volta com flash de sucesso, ou erro se não conectado; `DELETE telegram` desconecta; Saúde mostra a linha do Telegram.
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar a página: passos do @BotFather e do `.env` (estado `no_token`), aviso de token inválido, código grande e copiável + botão "Abrir no Telegram" + validade + "Gerar outro código", conectado com "Enviar mensagem de teste" e "Desconectar" (confirmação como `remove-favorite.tsx`); sempre a lista de comandos e o aviso de que os links só abrem neste computador. WCAG 2.2 AA.
- [ ] **Step 4:** `make check`, `npm run check`, `npm run types:check`, `npm run build` → PASS.
- [ ] **Step 5:** Commit `feat(telegram): settings page`.

### Task 7: README e docs

**Files:**
- Modify: `README.md`, `README.en.md` (seções "Bot do Telegram" e "Iniciar com o computador" com o conteúdo da spec; tabela de comandos do `make` sem mudança; "Próximos módulos" marca o bot como funcional), `docs/bot/instruction.md` (nota no topo: o design `2026-11-04-bot-telegram-design.md` prevalece), `CLAUDE.md` ("Estado" cita o módulo Telegram).

- [ ] **Step 1:** Escrever as seções. Comandos de boot exatamente como na spec (`make stop`/`make start`; `sudo systemctl disable docker`/`enable docker`; Docker Desktop/OrbStack "iniciar ao entrar"; `podman machine`).
- [ ] **Step 2:** Conferir links relativos dos `.md` (nenhum quebrado).
- [ ] **Step 3:** Commit `docs: Telegram bot and start-on-boot instructions`.
