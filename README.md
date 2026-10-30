# Me Avisa Aí

**Português** · [English](README.en.md)

Acompanha seus fundos imobiliários (FIIs) favoritos e avisa quando algum chega ao
preço que você definiu ou cai além do normal. Em vez de abrir o home broker para
conferir preço, você olha uma tela e sabe se tem algo para analisar hoje.

> **Em construção.** O módulo de FIIs já funciona com Docker: favoritos, cotações, regras,
> avisos no site e a tela de Saúde. A interface ainda é mínima, sem o acabamento visual, e
> o modo de demonstração não existe ainda. Fundos em que mais de um registro da CVM declara
> o mesmo ISIN ficam sem VP, e a regra automática não dispara para eles.
>
> Situação da instalação: **Docker** testado no Linux; **OrbStack** e **Podman** ainda não
> testados; **sem contêineres** ainda não disponível.

O "Aí" vem de "me avisa aí". Não é IA.

## O que ele faz

O módulo de FIIs oferece:

- **Favoritos:** busca por ticker ou nome.
- **Preço alvo:** avisa quando o preço chega ao valor definido ou fica abaixo dele.
- **Faixa de compra:** avisa quando o preço entra em um intervalo.
- **Regra automática:** avisa quando o preço está 5% abaixo da média de 30 pregões e
  o P/VP está abaixo da mediana de 12 meses do próprio fundo.
- **Queda forte:** avisa quando a cota cai mais de 3% no dia.
- **Antispam:** evita repetir o mesmo aviso, sem esconder um aviso diferente.

As cotações são atualizadas a cada 30 minutos, das 10h às 18h, em dias úteis. Os
avisos aparecem no site. As regras completas estão em
[`docs/fiis/alert.md`](docs/fiis/alert.md).

**Este app informa e não recomenda investimentos.** Um aviso diz que uma regra foi
atendida, não que o ativo está barato ou deve ser comprado.

## Instalação

Cada instalação atende uma pessoa, com as próprias chaves, e fica acessível só no
seu computador. Não há login, então não exponha o app na internet.

**Antes de começar:** crie uma conta gratuita na [brapi](https://brapi.dev/dashboard)
e copie seu token. O plano gratuito permite 15 mil requisições por mês, e cada fundo
acompanhado consome cerca de 350.

Escolha uma das formas abaixo:

| Forma | Indicada para |
|---|---|
| [Docker](#docker) | Linux e Windows, ou quem já usa Docker |
| [OrbStack](#orbstack-mac) | Mac, com um consumo menor que o do Docker Desktop |
| [Podman](#podman) | Quem prefere não usar o Docker |
| [Sem contêineres](#sem-contêineres) | Quem quer instalar tudo direto na máquina (em breve) |

Todas usam PostgreSQL. Os comandos de `make` do dia a dia valem para as formas com contêiner.

### Docker

Requer [Docker](https://docs.docker.com/get-docker/) com Compose e `make`.

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install   # cria o .env, constrói as imagens e prepara o banco
```

Coloque o token no `.env`:

```dotenv
BRAPI_TOKEN=seu_token
```

Depois, suba o app e abra <http://127.0.0.1:8000>:

```bash
make start
```

Na primeira vez, carregue o catálogo da busca e os informes da CVM. Depois disso, eles
se atualizam sozinhos, uma vez por dia:

```bash
docker compose exec web php artisan fiis:sync-catalog
docker compose exec web php artisan fiis:import-reports
```

Sem `make`, como costuma ser no Windows, use o Compose direto:

```bash
cp .env.example .env
docker compose up -d --build
```

No Linux, se o seu usuário não tiver o UID 1000, defina `APP_UID=$(id -u)` antes desses
comandos, ou o app não consegue gravar no `.env`.

### OrbStack (Mac)

O [OrbStack](https://orbstack.dev) oferece os comandos `docker` e `docker compose`,
então os passos são os mesmos da seção [Docker](#docker). Confira a licença na
página do OrbStack se o uso for profissional.

```bash
brew install orbstack
open -a OrbStack
```

### Podman

Requer [Podman](https://podman.io/docs/installation) com suporte a Compose. No Mac,
inicie antes a máquina virtual:

```bash
brew install podman podman-compose
podman machine init
podman machine start
```

Instale e suba o app indicando o comando do Podman:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install COMPOSE="podman compose"
make start COMPOSE="podman compose"
```

No Podman, o app pode não voltar sozinho quando o computador liga. Depois de ligar a
máquina, rode `podman machine start`, se estiver no Mac, e `make start COMPOSE="podman compose"`.

### Sem contêineres

> **Ainda não disponível.** Os comandos `make native-*` não existem ainda; esta seção
> mostra como essa forma vai funcionar.

Requer PHP 8.4 (com `pdo_pgsql`, `bcmath` e `zip`), Composer, Node 22, PostgreSQL 17 e `make`.
No Mac, com o [Homebrew](https://brew.sh):

```bash
brew install php composer node@22 postgresql@17
brew services start postgresql@17
createdb me_avisa_ai
```

Instale e suba o app:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make native-install   # cria o .env, instala as dependências e prepara o banco
make native-start     # sobe o site, o agendador e a fila juntos
```

Ajuste no `.env` o `BRAPI_TOKEN` e os dados de acesso ao banco. Nesta forma, o app
não inicia sozinho com o computador por padrão.

## Comandos

| Comando | O que faz |
|---|---|
| `make` | Lista os comandos |
| `make install` | Cria o `.env` sem sobrescrever um existente, constrói as imagens e prepara o banco |
| `make start` / `make stop` | Sobe ou para o app |
| `make restart` / `make logs` | Reinicia ou acompanha os logs |
| `make test` | Roda os testes em um banco separado, sem tocar nos seus dados |
| `make lint` / `make format` | Confere ou corrige a formatação |
| `make migrate` | Atualiza o banco |
| `make shell` | Abre um terminal em um contêiner de ferramentas |
| `make check` | Roda as checagens de backend do CI |
| `make backtest TICKER=KNRI11` | Conta os avisos que teriam saído nos preços passados, sem gravar nada; não diz se a compra daria lucro |

## Arquitetura

Monólito modular em Laravel, com Clean Architecture pragmática. As regras de alerta
não dependem do framework, do banco nem das APIs, então podem ser testadas sozinhas.

```text
app/Modules/Fiis/
├── Domain/          regras de alerta e antispam, só PHP
├── Application/     casos de uso e interfaces
├── Infrastructure/  PostgreSQL, brapi, CVM
└── Http/            controllers e respostas do Inertia
```

Testes de arquitetura garantem que essas camadas não se misturem.

**Stack:** Laravel, React (Inertia), TypeScript, Tailwind v4, shadcn/ui, PostgreSQL e
Docker Compose.

**Dados:** cotações da [brapi](https://brapi.dev); valor patrimonial e dividend yield
do [Informe Mensal de FII da CVM](https://dados.cvm.gov.br/dataset/fii-doc-inf_mensal).

## Próximos módulos

1. **FIIs:** favoritos, dados de mercado, regras e avisos no site *(funcional, com interface mínima)*
2. Bot do Telegram
3. Servidor MCP, para gerenciar favoritos conversando com o Claude
4. Ações e ETFs

## Documentação

- [Design do módulo de FIIs](docs/superpowers/specs/2026-10-29-modulo-fiis-design.md)
- Regras de produto: [FIIs](docs/fiis/), [ações](docs/acoes/), [ETFs](docs/ets/) e [bot](docs/bot/)
- [Protótipo visual](docs/prototipo-lovable/)
- [O que veio da versão anterior](docs/legado.md)

## Licença

[MIT](LICENSE)
