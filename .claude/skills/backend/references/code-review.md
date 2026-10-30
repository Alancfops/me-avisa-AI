# Code review do backend

Revise o que mudou, não o projeto inteiro. Cada achado precisa de arquivo, linha,
o que dá errado e como corrigir. Se não consegue descrever a falha, não é achado.

## Bloqueia

**Regra de negócio**
- A regra dispara diferente de `docs/fiis/alert.md`? Confira limites exatos (`<` ou `≤`).
- O código criou regra, limite ou fórmula que não está nos docs?
- O antispam vale por regra de cada favorito? Trata exceção de 2%, teto de 24 h e aviso
  bloqueado que sai depois?
- Dado ausente virou `0`, ou uma cotação velha aparece como atual?
- Algum texto soa como recomendação ("compre agora")?

**Fronteiras**
- `Domain` usa Laravel, Eloquent, HTTP ou relógio real?
- `Application` conhece `Infrastructure`, ou um model Eloquent saiu de lá?
- Um módulo usa algo interno de outro?

**Dados e segurança**
- Resposta externa é salva sem validação, ou sobrescreve dado bom?
- Token ou segredo aparece em código, log ou fixture de teste?
- Query monta SQL com entrada do usuário? Falta validação no Form Request?
- Migration pode perder dado?

**Testes**
- Regra nova ou alterada sem teste do limite exato?
- Teste chama a brapi ou a CVM de verdade, ou depende do relógio real?
- Os testes passam?

## Deveria mudar

- Controller com regra de negócio, ou caso de uso fazendo várias coisas.
- `float` em preço, número mágico, array solto onde caberia um tipo.
- Nome que não diz o que é, função misturando níveis de abstração, `if` aninhado evitável.
- N+1, ou chamadas à brapi que gastam a cota sem necessidade.
- Erro engolido sem log.

## Sugestão

- Simplificações e cerimônia que não se paga: interface sem motivo, DTO que só repassa
  campos, abstração para ações e ETFs antes da hora.

## Formato da resposta

Em português, nesta ordem, omitindo as seções vazias:

1. **Bloqueia**: `arquivo:linha`, o problema, um exemplo concreto da falha, a correção.
2. **Deveria mudar**: mesmo formato.
3. **Sugestão**: uma linha cada.
4. **Verificado**: comandos rodados e resultado; o que não deu para rodar.
5. **Veredito**: pode seguir ou corrigir antes.
