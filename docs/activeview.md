# Integrando com a ActiveView (API de relatórios + SDK de anúncios)

Guia de referência reutilizável, escrito a partir de uma integração real em produção (2026). Cobre as **duas superfícies** de integração com a ActiveView — não são a mesma coisa e vivem em lugares diferentes do seu stack:

1. **API REST de relatórios** (`external-api.activeview.app`) — chamada pelo seu **backend**, para puxar receita/sessões/métricas de anúncio programático (GAM/Ad Exchange) por conta.
2. **SDK de anúncios client-side** (`scr.actview.net/{dominio}.js`) — injetado nas **páginas publicadas** (ex: posts de WordPress), responsável por renderizar os blocos de anúncio de verdade via Google Ad Manager e disparar eventos de tracking.

Nada aqui é específico deste projeto — sirva-se à vontade em qualquer stack que precise integrar com a ActiveView.

---

## Parte 1 — API REST de relatórios (backend)

### Autenticação e credenciais

Cada **conta/domínio** tem seu próprio conjunto de credenciais — não é uma chave global por empresa:

| Campo | Exemplo | Onde usar |
|---|---|---|
| `base_url` | `https://external-api.activeview.app` | host da API |
| `api_key` | token opaco | header `Authorization: Bearer {api_key}` |
| `network_code` | `22550324758` | segmento de path em quase todo endpoint |
| `domain` | `exemplo.com` | segmento de path em quase todo endpoint |

**Nunca** vai a chave via query string — é sempre header `Authorization: Bearer {api_key}`. Armazene a `api_key` **criptografada em repouso** se persistir credenciais por conta num banco (ela é tão sensível quanto um client secret).

Timeout recomendado: **60–100s**. Os endpoints de relatório (especialmente o de GAM custom report, abaixo) podem demorar bem mais que uma chamada REST comum — não usar o timeout padrão de 30s de HTTP client genérico.

### Endpoints

Todos retornam `{"response": [...]}` (array de linhas) em caso de sucesso. **Atenção**: alguns endpoints retornam erro como `HTTP 200` com corpo `{"error": {...}}` em vez de um status HTTP de erro de verdade — sempre checar `isset($response['error'])` explicitamente, não confiar só no status code.

#### `GET /report/kvp/{network_code}/{domain}` — receita por KVP (key-value pair)

Relatório genérico de receita segmentado por uma dimensão de targeting customizada (`key`). Normalmente usado com `key=utm_campaign` (receita por campanha) ou `key=utm_campaign_medium` (receita por campanha+mídia).

```
GET /report/kvp/{network_code}/{domain}
  ?start_date=YYYY-MM-DD
  &end_date=YYYY-MM-DD
  &key=utm_campaign            # nome da custom targeting key no GAM
  &additional_dimensions=date  # opcional — quebra o resultado também por dia
```

Campos de cada linha (minúsculo/snake case): `value` (o valor da KVP, ex: o campaign id), `date` (se `additional_dimensions=date`), `ad_exchange_line_item_level_revenue` (em **micros** — dividir por `1_000_000` pra chegar em USD), `ad_exchange_line_item_level_impressions`, `ad_exchange_line_item_level_clicks`, `ad_exchange_active_view_viewable_impressions`, `ad_exchange_responses_served`.

#### `GET /report/session/kvp/{network_code}/{domain}` — sessões por KVP

Mesmo conceito, mas para contagem de sessões (não receita). **Campos em UPPERCASE**, diferente do endpoint de revenue acima — é uma inconsistência real da API, não erro seu: `VALUE`, `RECORDED_DATE`, `TOTAL`.

```
GET /report/session/kvp/{network_code}/{domain}?start_date=...&end_date=...&key=utm_campaign&additional_dimensions=date
```

#### `GET /report/gam/custom/{network_code}/{domain}/from-gam` — relatório GAM cru, dimensões/métricas livres

O mais poderoso e flexível — dá acesso direto às dimensões/métricas do Google Ad Manager, não só o pré-agregado por KVP. Útil para quebrar receita por ad unit, por comprador (buyer/advertiser), etc.

```
GET /report/gam/custom/{network_code}/{domain}/from-gam
  ?start_date=YYYY-MM-DD
  &end_date=YYYY-MM-DD
  &key=utm_campaign
  &dimensions=DOMAIN,CUSTOM_CRITERIA,AD_UNIT_NAME       # CSV, nomes GAM em UPPERCASE
  &metrics=AD_EXCHANGE_LINE_ITEM_LEVEL_REVENUE,AD_EXCHANGE_LINE_ITEM_LEVEL_IMPRESSIONS,AD_EXCHANGE_LINE_ITEM_LEVEL_CLICKS,AD_EXCHANGE_RESPONSES_SERVED,AD_EXCHANGE_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS,PROGRAMMATIC_MATCH_RATE
```

Dimensões/métricas úteis:
- `AD_UNIT_NAME` — nome do bloco de anúncio (permite quebrar receita por posição na página).
- `CLASSIFIED_ADVERTISER_NAME` — comprador/rede de demanda que preencheu o anúncio.
- `CUSTOM_CRITERIA` — vem como uma string tipo `"utm_campaign=123456"` (formato `key=value`), **não** como um campo separado com o valor puro. Pra extrair o valor: checar `str_starts_with($row['CUSTOM_CRITERIA'], 'utm_campaign=')` e cortar o prefixo — linhas que não batem com esse padrão devem ser ignoradas (tráfego sem essa custom targeting key setada).
- `PROGRAMMATIC_MATCH_RATE` — vem como decimal **0–1**, não porcentagem. Multiplique por 100 antes de exibir/comparar com um threshold em %.
- `DATE` — adicionar como dimensão pra quebrar qualquer um dos relatórios acima por dia (sem isso vem agregado no período inteiro).

#### `GET /rules/{network_code}/{domain}` — regras de preço (floor rules)

Sem query params além do path. Retorna `response: [...]` com as regras de preço mínimo configuradas na ActiveView para aquele domínio. Cache generoso recomendado (essas regras mudam raramente — 5min já é seguro).

### Gotchas gerais (valem pra qualquer um dos endpoints acima)

1. **Valores de receita vêm em micros.** Sempre dividir por `1_000_000` pra chegar em unidade de moeda normal. Fácil de esquecer e reportar receita 1 milhão de vezes maior/menor do que o real.
2. **`""` e a string literal `"null"` são "vazio".** A API não omite o campo quando não há valor — ela devolve a string `"null"` mesmo. Todo parsing de KVP precisa checar `$value === '' || $value === 'null'` e pular a linha, não só `empty()`.
3. **Casing de campo inconsistente entre endpoints.** `/report/kvp` e `/report/kvp` com `additional_dimensions` usam `snake_case`/lowercase; `/report/session/kvp` usa `UPPERCASE`; `/report/gam/custom` usa os nomes de dimensão/métrica do próprio GAM (`UPPERCASE_COM_UNDERSCORE`, ecoando exatamente o que você mandou em `dimensions=`/`metrics=`). Não assuma que o padrão de um endpoint vale pros outros — confira a resposta real de cada um antes de escrever o parser.
4. **Erros podem vir com HTTP 200.** Alguns endpoints (confirmado no `/report/gam/custom`) retornam `{"error": {...}}` com status 200 em vez de 4xx/5xx. Sempre checar `isset($response['error'])` além do status HTTP.
5. **Rate/timeout**: relatórios com `dimensions` compostas (ex: `AD_UNIT_NAME,DATE` juntos) demoram sensivelmente mais — não usar o mesmo timeout curto de um endpoint simples de KVP.
6. **Cache agressivamente.** Esses dados não mudam retroativamente na prática (exceto o dia corrente, ainda em andamento) — cachear por 1h+ em dias fechados é seguro e evita re-bater na API sem necessidade a cada request do seu backend.

---

## Parte 2 — SDK de anúncios client-side (`scr.actview.net/{dominio}.js`)

Script **por domínio** (um arquivo JS específico pra cada site publisher, ex: `scr.actview.net/meusite.js`), incluído via `<script src="...">` na página publicada. Ele:

- Registra os **ad slots** no Google Ad Manager (`googletag`) — interstitial, rewarded, top, content, anchor, fixed, offerwall.
- Dispara eventos de tracking pro `dataLayer` (GTM) em cada impressão viewable: `ad_view_{content}`, `ad_interstitial_{content}`, `ad_rewarded_{content}`, `ad_view_double_{content}` (quando interstitial **e** rewarded foram vistos na mesma sessão).
- Implementa o fluxo interativo de **rewarded ads / offerwall**.

O ID do slot GAM (`slot.getSlotElementId()`) segue uma convenção de nome que o SDK usa pra rotear o evento certo — slots contendo `interstitial`, `rewarded`, `top`, `content`, `anchor`, `fixed` no ID disparam eventos diferentes. Nomeie seus slots GAM de acordo se quiser que o tracking automático funcione.

### Rewarded ads / offerwall — como funciona de verdade

Qualquer elemento na página com o atributo `data-av-rewarded="true"` **ou** a classe `.av-rewarded` é automaticamente detectado pelo SDK (ele varre a página com `querySelectorAll('[data-av-rewarded="true"],.av-rewarded')`) e ganha **o próprio listener de clique do SDK** — além de qualquer listener que seu código já tenha colocado no mesmo elemento.

Comportamento real do listener deles ao clicar (reconstituído a partir do bundle minificado, comportamento confirmado em produção):

```js
elem.addEventListener("click", function(event) {
  // sobe até 3 níveis de parentElement procurando o href, caso o clique
  // tenha vindo de um filho (ex: um <button> dentro do <a>)
  let href = event.target.getAttribute("href") || /* ...parentElement... */;

  if (lifecycle === "ready" && (
        modal !== undefined ||
        href.includes(av.host) ||        // href aponta pro MESMO domínio do site
        (href === "" && onclick !== "")
     )) {
    event.preventDefault();
    show(() => {
      // callback chamado quando o anúncio recompensado é CONCLUÍDO/FECHADO
      if (modal === undefined && href !== "") window.location = href;
      onReward();
    });
  }
});
```

`lifecycle` só vira `"ready"` quando o evento GAM `rewardedSlotReady` dispara (anúncio recompensado carregado e disponível pra exibir).

**A armadilha central**: se o `href` do seu elemento `data-av-rewarded` aponta pro mesmo domínio do site (`href.includes(av.host)`), isso **é o próprio gatilho** que ativa o fluxo "mostra o anúncio real via GAM → quando fecha, navega pra esse href com `window.location = href`". Essa navegação:

- **Não é a navegação nativa do `<a>`** — é uma atribuição direta a `window.location`, feita de dentro de um callback assíncrono do SDK deles, depois que o anúncio fecha.
- **`event.preventDefault()` no SEU próprio listener não tem efeito nenhum sobre ela.** `preventDefault()` só cancela a ação padrão do navegador pro clique original; não impede código JS de terceiros (rodando num listener separado, no mesmo elemento) de navegar manualmente depois, de forma assíncrona.
- Se você quer "mostrar o anúncio recompensado, mas nunca navegar pra lugar nenhum depois" — **isso não é possível só com `data-av-rewarded` + um href do mesmo domínio**, na versão do SDK observada. O redirect pós-anúncio faz parte do próprio mecanismo de recompensa deles, não é uma opção configurável exposta publicamente (não achamos nenhum hook tipo `onReward` sobrescrevível de fora, só `window.avCustomConfig.slots.rewarded.delay` e `window.avCustomConfig.slots.offerWall.condition`, que ajustam timing/condição, não o destino final).
- Se seu objetivo é só "revelar conteúdo já existente na página, sem navegar", a alternativa é **não usar `data-av-rewarded`/`.av-rewarded`** nesse elemento (perde o anúncio recompensado real) — ou aceitar que o clique leva a outra URL de verdade depois do anúncio (o que é, na prática, o modelo de monetização pretendido: assistir anúncio → ser levado a outra página monetizada).

### `window.avCustomConfig` — customização conhecida

```js
window.avCustomConfig = {
  slots: {
    rewarded:  { delay: 0 },          // ms de espera antes do slot rewarded ficar disponível
    offerWall: { condition: '...', extraOptions: { mabEndpoint: '...', title: '...' } },
  }
};
```

Definir `window.avCustomConfig` **antes** do `<script src="scr.actview.net/...">` carregar (early no `<head>` ou logo no topo do `<body>`), senão a config não é lida a tempo.

### Diagnosticando problemas de clique/navegação nesse SDK

`WebFetch`/conversores de HTML→Markdown **descartam `<script>` tags** — inúteis pra esse tipo de investigação. Baixe o HTML/JS bruto direto (`curl`) e procure por `data-av-rewarded`/`av-rewarded` no HTML da página e no bundle minificado do SDK antes de assumir que um comportamento estranho de clique é bug do seu próprio código — muito provavelmente é esse SDK interceptando o elemento.

---

## Checklist de integração

**API de relatórios:**
- [ ] Uma `api_key` + `network_code` + `domain` por conta, `api_key` criptografada em repouso
- [ ] Header `Authorization: Bearer {api_key}` (nunca em query string)
- [ ] Timeout de 60–100s, não o padrão curto de HTTP client genérico
- [ ] Parser de KVP trata `""` e `"null"` como vazio
- [ ] Conversão de receita: dividir por `1_000_000` (micros → unidade de moeda)
- [ ] `PROGRAMMATIC_MATCH_RATE` multiplicado por 100 antes de tratar como %
- [ ] `CUSTOM_CRITERIA` parseado como `key=value`, prefixo stripado, linhas sem o prefixo esperado ignoradas
- [ ] Checagem explícita de `isset($response['error'])` além do status HTTP
- [ ] Cache por endpoint (1h+ pra dados de dias fechados; 5min pra price rules)

**SDK client-side:**
- [ ] Slots GAM nomeados com `interstitial`/`rewarded`/`top`/`content`/`anchor`/`fixed` no ID, se quiser aproveitar o tracking automático de `dataLayer`
- [ ] `window.avCustomConfig` (se usado) definido ANTES do `<script src="scr.actview.net/...">`
- [ ] Nenhum elemento com `data-av-rewarded`/`.av-rewarded` e href do mesmo domínio, a menos que você QUEIRA que o usuário seja redirecionado pra esse href depois do anúncio — não existe modo documentado de "mostrar anúncio, nunca navegar"
- [ ] Ao debugar clique/navegação estranha numa página com esse SDK, inspecionar o HTML/JS bruto da página publicada (`curl`, não `WebFetch`) antes de assumir bug no seu próprio código
