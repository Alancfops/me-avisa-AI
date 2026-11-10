# `make connect`: design

Data: 2026-11-10. Complemento do módulo 3 (servidor MCP), que está na `develop` em
`74aa451`. Spec do servidor: `2026-11-09-servidor-mcp-design.md`.

## Intenção

Para ligar uma IA ao app, hoje a pessoa roda `make mcp-config`, copia o JSON que ele
imprime e cola no arquivo de configuração do cliente (Claude Desktop, Cursor,
Antigravity). Na prática isso falhou com o próprio autor: ele rodou o comando, achou que
estava configurado, e o arquivo do Antigravity continuou vazio. Rodar na pasta errada
também liga a IA à instalação errada sem ninguém perceber (aconteceu com a instalação de
teste e a principal).

`make connect` faz isso pela pessoa: pergunta qual cliente ela usa, grava no arquivo
certo sem apagar o que já existe, testa a conexão e diz o próximo passo.

**Para quem é:** alguém que acabou de clonar o projeto e talvez nunca tenha mexido com
MCP. O autor usa só o Claude; o comando existe para outras pessoas. Por isso a saída
explica, sem pressupor que a pessoa sabe o que é `mcpServers` ou onde fica cada arquivo.

## Metas

1. Comando **separado e opcional**, `make connect` (decisão do usuário). Ligar uma IA é
   escolha da pessoa; o `make install` não muda.
2. Pergunta o cliente numa lista: **Claude Desktop, Claude Code, Cursor, Antigravity** e
   **"só mostrar o JSON"**. Também aceita `make connect CLIENT=<cliente>`, sem a pergunta
   do cliente.
3. Para Claude Desktop, Cursor e Antigravity: grava o bloco `mcpServers.me-avisa-ai` no
   arquivo do cliente, **mantendo tudo o que já existe** no arquivo, com **backup antes**
   de qualquer mudança. Cria a pasta e o arquivo se não existirem.
   A mensagem final mostra onde ficou o backup e o comando pronto para voltar a ele
   (`cp "<backup>" "<arquivo>"`): voltar é copiar o arquivo de volta, sem programar nada.
4. Para o Claude Code: não grava arquivo. Mostra o `export ME_AVISA_AI_DIR=<pasta>` já
   preenchido e os dois comandos `/plugin` do README, prontos para copiar.
5. Se já houver um `me-avisa-ai` diferente do novo (outra pasta, outro comando): mostra o
   atual e o novo, **pergunta se quer trocar** e faz o backup antes de trocar (decisão do
   usuário). Se a resposta for não, nada muda. Se o bloco já for igual, não grava nada e
   diz que já estava configurado.
6. Mostra **qual pasta** (qual instalação) a IA vai usar, para ninguém ligar a IA na
   instalação errada sem ver.
7. Ao final, **testa a conexão** e diz o próximo passo ("feche e abra o Cursor").
8. **Linux e Mac** primeiro (decisão do usuário). No Windows (WSL), diz que ali é manual e
   mostra o JSON.
9. `make mcp-config` continua como está (opção manual, decisão do usuário).
10. README (pt e en): `make connect` vira o caminho principal da seção do MCP, com o manual
    logo abaixo; entra na tabela de comandos.

## Fora do escopo

- Windows automático (WSL gravando em `%APPDATA%` e chamando o Docker por `wsl`).
- Outros clientes além dos quatro. A lista deve ser fácil de estender.
- Desfazer (`make disconnect`). O backup cobre a volta.
- Instalar o plugin do Claude Code sozinho ou mexer no `settings.json` dele.
- Mudar o servidor MCP ou as ferramentas.

## Anti-metas

- **Apagar ou reformatar o que a pessoa já tem** de um jeito que perca dados. Outros
  servidores, outras chaves do arquivo (o `claude_desktop_config.json` tem `preferences`,
  por exemplo) e valores desconhecidos têm de sobreviver iguais.
- **Exigir instalar algo** além do que a instalação já pede (contêineres, `git`, `make`).
  Nada de `jq`, Python ou Node no computador da pessoa.
- **Dizer "pronto" sem ter gravado**, que é exatamente o erro que motivou o comando.
- Gravar em arquivo com JSON inválido por cima: se o arquivo atual não for JSON válido,
  não mexe e explica.

## Restrições

- Mac e Linux, com Docker ou Podman (`COMPOSE`, como o resto do `Makefile`).
- Caminho do arquivo de cada cliente:

  | Cliente | Mac | Linux |
  |---|---|---|
  | Claude Desktop | `~/Library/Application Support/Claude/claude_desktop_config.json` | `~/.config/Claude/claude_desktop_config.json` |
  | Cursor | `~/.cursor/mcp.json` | `~/.cursor/mcp.json` |
  | Antigravity | `~/.gemini/config/mcp_config.json` | `~/.gemini/config/mcp_config.json` |

  O caminho do Antigravity veio da documentação que acompanha o próprio Antigravity
  (`~/.gemini/antigravity/builtin/skills/agy-customizations/docs/mcp_servers.md`) e foi
  conferido no Linux. No Mac, os caminhos do Cursor e do Antigravity não foram conferidos.
- O bloco gravado é o que o `make mcp-config` imprime
  (`<command> compose --project-directory <pasta> exec -T web php artisan mcp:start fiis`),
  respeitando o `COMPOSE` em uso (veja o item 4 de "Como" sobre o caminho completo).
- O teste de conexão precisa do app no ar (`make start`). Com o app parado, grava assim
  mesmo e avisa: "Configurado, mas o app está parado: rode `make start`".

## Como (decidido nesta conversa)

Tudo nesta seção é decisão minha (do assistente), tomada depois da revisão do documento,
salvo onde diz "do usuário".

O trabalho se divide em duas partes: o **computador da pessoa** (um script de shell
chamado pelo `Makefile`) e o **contêiner `tools`** (um comando artisan).

1. **Pergunta do cliente no shell.** O script pergunta o cliente (ou lê `CLIENT=`),
   descobre o sistema (`uname`) e o caminho do arquivo, cria a pasta se faltar
   (`mkdir -p`) e só então chama o contêiner, montando **só a pasta desse cliente**. O
   cliente precisa ser conhecido antes do `compose run`, por isso a pergunta não fica no
   PHP. Sem terminal interativo e sem `CLIENT=`, o script para e explica como usar
   `CLIENT=`. Exige o app instalado (`make install`); sem isso, diz para rodar antes.
2. **Junção do JSON em PHP, no `tools`**, num comando artisan que recebe o arquivo
   montado e o bloco novo. Atende à anti-meta de não instalar nada e é testável com Pest.
   É também ele que mostra atual × novo e pergunta se troca (Laravel Prompts), faz o
   backup e grava. Regras da junção:
   - arquivo inexistente, vazio ou só com espaços conta como `{}`;
   - arquivo com JSON inválido, ou cuja raiz não é um objeto: não mexe e explica;
   - objetos vazios continuam objetos (`{}` não pode virar `[]`), e as demais chaves e
     servidores saem iguais;
   - "bloco igual" compara só `command` e `args`. Na troca, o comando substitui
     `command` e `args` e **mantém as outras chaves** do bloco (`env`, `disabled`…);
   - no modo `CLIENT=` sem terminal, um bloco diferente não é trocado: o comando explica
     e sugere rodar de forma interativa.
3. **Bloco gerado num lugar só: o `make mcp-config`.** O script pega a saída dele e passa
   ao PHP; o PHP só junta. Assim o `mcp-config` continua rodando sem imagem e sem banco.
4. **Caminho completo do programa de contêineres.** No Mac, programas abertos pelo Dock
   não herdam o `PATH` do terminal, então `docker` ou `podman` às vezes não são achados
   pelo cliente: o teste passaria no terminal e a IA falharia. O bloco passa a usar o
   caminho completo (`command -v docker` → `/usr/local/bin/docker`), e isso vale também
   para o `make mcp-config` (a saída dele muda só nisso). Se o programa não for achado,
   mantém o nome curto.
5. **Teste de conexão no script de shell**, rodando o comando exatamente como ficou
   gravado, com o mesmo handshake (`initialize`, `notifications/initialized`,
   `tools/list`) usado à mão em 2026-11-10. Sem `jq`: basta conferir que a resposta lista
   `list_favorites`. Esta parte fica fora do Pest; o teste é manual. Depois do teste, o
   script mostra o próximo passo ("feche e abra o Cursor").
6. **Claude Code com Podman.** O plugin traz `docker` fixo. Com Podman, em vez dos
   comandos `/plugin`, o `make connect` mostra o `claude mcp add` equivalente, que
   registra o servidor com o comando certo. O plugin não muda.

## Deixado para quem constrói

- Onde o código mora (provavelmente `app/Modules/Mcp/` e um script em `bin/`) e o nome
  do comando artisan.
- Nome e formato do backup (sugestão: `<arquivo>.bak-AAAA-MM-DD-HHMMSS`, ao lado do
  original).
- Como fazer o teste de conexão (sugestão: o mesmo handshake `initialize` + `tools/list`
  pelo comando gravado, contando as ferramentas).
- Indentação do JSON gravado.
- Textos exatos das mensagens, em português, no tom do resto do app ("✅ …").
