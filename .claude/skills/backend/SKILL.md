---
name: backend
description: Backend do Me Avisa Aí em Laravel com Clean Architecture pragmática, Clean Code e checklist de code review. Use sempre que for criar, alterar, refatorar ou revisar código PHP do projeto, mesmo que o pedido não fale em arquitetura (ex.: "implementa a regra de queda forte", "busca a cotação na brapi", "importa o informe da CVM", "cria o antispam", "adiciona o controller do favorito", "cria a migration", "agenda a atualização", "revisa esse PR"). Também use ao mexer em app/, routes/, database/, config/ ou tests/, e ao decidir onde um código novo deve ficar.
---

# Backend do Me Avisa Aí

O app acompanha FIIs favoritos e avisa quando uma regra de preço bate. Um aviso
errado leva a uma decisão errada com dinheiro de verdade, então as regras precisam
ser fáceis de ler e de testar. O projeto também serve para estudar arquitetura:
cada camada tem que justificar o que custa, sem cerimônia para um usuário só.

## Antes de começar

1. Leia a regra de negócio em `docs/fiis/alert.md` e as decisões em
   `docs/superpowers/specs/2026-10-29-modulo-fiis-design.md`. Se faltar regra,
   limite ou fórmula, atualize o doc primeiro: não invente no código.
2. Confira o que está instalado (`composer.json`, `app/`, `tests/`) antes de
   assumir versão ou caminho. Se divergir desta skill, siga o projeto e atualize
   a skill.
3. Decida a camada de cada coisa nova com a tabela abaixo.

## Arquitetura

Cada módulo fica em `app/Modules/<Modulo>/`. As dependências apontam só para dentro:
`Http` e `Infrastructure` → `Application` → `Domain`.

| Camada | Contém | Pode depender de |
|---|---|---|
| `Domain/` | Regras de alerta, antispam, objetos de valor, retrato do fundo | Só PHP e o próprio `Domain` |
| `Application/` | Casos de uso e interfaces que eles precisam (`Quotes`, `FundReports`, repositórios, relógio) | `Domain` |
| `Infrastructure/` | Eloquent, clientes da brapi e da CVM, implementação das interfaces | `Application`, `Domain`, Laravel |
| `Http/` | Controllers finos, Form Requests, respostas Inertia | `Application`, `Domain`, Laravel |

Isso existe para que uma regra de alerta rode num teste sem banco nem rede, e para
trocar a brapi sem tocar nas regras. O módulo de Telegram e o de MCP vão consumir os
avisos depois, então um módulo só usa de outro o que está em `Application`.

### Onde a pragmática entra

- **Não duplique por duplicar.** Sem entidade espelhando um model quando o `Domain`
  só precisa de um retrato do fundo. Sem interface para algo que tem uma implementação
  e não cruza fronteira.
- **Crie interface** para o que sai do processo ou varia nos testes: API externa,
  persistência usada por caso de uso, relógio.
- **Leitura de tela** pode ser uma query em `Infrastructure` exposta por interface de
  `Application`, sem passar pelo `Domain`.
- **Model Eloquent não sai de `Infrastructure`.** O caso de uso devolve objeto de
  valor ou DTO, e o controller monta as props do Inertia a partir dele.

### Exemplo de regra

```php
final readonly class PriceTargetRule
{
    public function __construct(private Money $target) {}

    public function evaluate(FundSnapshot $fund): RuleResult
    {
        return $fund->price->isAtMost($this->target)
            ? RuleResult::matched("Preço {$fund->price} no alvo {$this->target}")
            : RuleResult::notMatched();
    }
}
```

## Regras que valem em todo o código

**Dinheiro e números**
- Preço, VP e percentual não usam `float` em comparação de regra: use objeto de valor
  sobre decimal e `numeric` no Postgres. Um erro de arredondamento muda se `preço ≤
  alvo` dispara.
- Dado ausente é `null`, nunca `0`. A regra que depende dele não dispara e diz por quê.
- Limites têm nome e origem: `SHARP_DROP_PERCENT`, com referência a `docs/fiis/alert.md`,
  e não `-3` solto.

**Fontes externas**
- Valide a resposta dentro do adaptador, antes de virar objeto de domínio. Resposta
  quebrada não sobrescreve dado bom: registre em log e siga.
- Trate 401, 403, 404 e 429 (respeitando `Retry-After`). O token vem de `BRAPI_TOKEN`
  via `config/`, no header `Authorization: Bearer`, e nunca aparece em log.
- Guarde o horário informado pela fonte (`regularMarketTime`), não o da consulta.

**Tempo**
- O `Domain` recebe o momento como valor; o relógio é injetado. Antispam de 24 h e
  pregão não podem depender do relógio real nos testes. Fuso: `America/Sao_Paulo`.

**Clean Code**
- Nomes do domínio em inglês no código, textos de tela em português: `Favorite`,
  `Alert` (aviso), `AlertRule` (regra), `FundSnapshot` (retrato), `PriceTarget` (alvo),
  `BuyRange` (faixa), `SharpDrop` (queda forte).
- Uma função, um nível de abstração. Prefira retorno antecipado a `if` aninhado, e tipos
  declarados a arrays soltos. `final` e `readonly` por padrão.
- Comentário explica o porquê, não repete o código.
- Nada de abstração para um futuro hipotético: ações e ETFs entram quando forem o módulo
  da vez. Duplicação pequena é melhor que a abstração errada.

## Testes

| O quê | Como |
|---|---|
| Regras e antispam | Pest puro, sem Laravel, com os exemplos de `docs/fiis/alert.md` e os limites exatos (−3% não dispara) |
| Casos de uso | Implementações falsas das interfaces |
| Adaptadores brapi e CVM | Respostas gravadas (`Http::fake`), incluindo as quebradas |
| Eloquent e controllers | Teste de feature com Postgres |
| Fronteiras | Teste de arquitetura, abaixo |

Fontes externas nunca são chamadas nos testes. Escreva o teste da regra antes da regra.

```php
arch('domain has no framework')
    ->expect('App\Modules\Fiis\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Infrastructure']);

arch('application does not know infrastructure')
    ->expect('App\Modules\Fiis\Application')
    ->not->toUse(['Illuminate\Database', 'App\Modules\Fiis\Infrastructure']);
```

## Ao terminar

Rode os comandos que o projeto tiver e relate o resultado: testes (`php artisan test`),
formatação (`vendor/bin/pint --test`) e análise estática, se instalada. Depois, passe
pelo `references/code-review.md`. Em mudança relevante, peça a revisão ao subagente
`backend-reviewer`, que lê o código sem o contexto de quem o escreveu.
