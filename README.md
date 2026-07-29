# Meta AI — Gerador de Campanhas Meta Ads + ROAS + WordPress

Sistema interno para: (1) criar campanhas no Meta Ads através de um wizard guiado com fluxo de aprovação por admin, (2) consolidar dados do **Meta Ads** com a **ActiveView** e calcular ROAS real por campanha, e (3) gerar e publicar conteúdo (posts/páginas) em sites WordPress usando IA.

## Stack

- **Backend:** PHP 8.2+ sem framework, Composer PSR-4, MVC feito à mão
- **Banco de dados:** MySQL 8 (PDO puro, sem ORM; suporte a SSL para bancos gerenciados)
- **Cache:** Redis / Predis (graceful degradation se indisponível — usado no sync de insights/receita)
- **IA:** Google Gemini 2.5 Flash (texto e geração de imagem)
- **Frontend:** DaisyUI v4 + Tailwind CDN + marked.js, sem build step
- **Autenticação:** admin via `APP_API_KEY` estático, usuários via Google OAuth 2.0 (sessão em banco)
- **Criptografia:** AES-256-CBC para segredos (tokens Meta, chaves ActiveView, senha de app WordPress) armazenados no banco

## Pré-requisitos

- PHP 8.2+ com extensões: `pdo_mysql`, `curl`, `openssl`
- Composer
- MySQL 8+ (local ou gerenciado)
- Redis (opcional)

## Instalação

```bash
git clone https://github.com/Gabriel-Molinos/meta_ai.git
cd meta_ai
composer install
```

Crie um arquivo `.env` na raiz com as variáveis da seção [Configuração](#configuração-env) abaixo e rode as migrations:

```bash
php bin/migrate.php
```

Suba o servidor de desenvolvimento:

```bash
php -S localhost:8080 -t public
```

## Configuração (.env)

```env
# Aplicação
APP_API_KEY=seu-token-aqui
APP_DEBUG=false
ENCRYPTION_KEY=chave-secreta-forte-256bits

# Meta Ads (Facebook Graph API) — usado por scripts de diagnóstico em bin/
META_APP_ID=
META_APP_SECRET=
META_ACCESS_TOKEN=
META_ACCOUNT_ID=act_

# ActiveView
AV_BASE_URL=https://external-api.activeview.app
AV_API_KEY=
AV_NETWORK_CODE=
AV_DOMAIN=

# Google Gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash

# MySQL
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=meta_ai
DB_USER=root
DB_PASS=
DB_SSL_CA=          # Caminho para CA cert (opcional — ex: DigitalOcean Managed DB)

# Redis (opcional)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Google OAuth (login de usuários não-admin)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8080/oauth/callback
```

Cada conta de anúncio Meta Ads + ActiveView é cadastrada via UI (`/accounts`), com credenciais próprias criptografadas no banco — as variáveis `META_*`/`AV_*` do `.env` acima são usadas apenas pelos scripts de diagnóstico em `bin/`.

## Autenticação

- **Admin:** login com o token `APP_API_KEY` (tela `/login`), guardado em cookie `_auth`. Acesso total.
- **Usuário:** login via Google OAuth (`/oauth/google` → `/oauth/callback`), sessão de 24h com `session_token` gravado na tabela `users`. Só enxerga/usa as contas Meta Ads vinculadas a ele (`user_accounts`, gerenciado por um admin em `/users`) e só pode **submeter** campanhas para aprovação, não publicá-las diretamente.

## Rotas

### Views (web)

| Rota | Descrição |
|------|-----------|
| `/login` | Tela de login (admin por token ou usuário por Google) |
| `/generator` | Wizard de criação de campanha Meta Ads |
| `/accounts` | Gerenciar contas Meta Ads + ActiveView (admin) |
| `/approvals` | Fila de aprovação de campanhas submetidas por usuários (admin) |
| `/my-campaigns` | Campanhas submetidas pelo usuário logado e seu status |
| `/users` | Gerenciamento de usuários e vínculo com contas (admin) |
| `/wordpress/pages` | Geração e publicação de posts/páginas via IA |

### API — Contas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/accounts` | Lista completa (admin) |
| GET | `/api/accounts/list` | Lista filtrada por permissão do usuário logado |
| POST | `/api/accounts` | Cria conta — exige `meta_access_token` e `meta_account_id` |
| PUT | `/api/accounts/{key}` | Atualiza conta (campos sensíveis em branco preservam o valor atual) |
| DELETE | `/api/accounts/{key}` | Remove conta |

### API — Gerador de campanhas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/generator/pixels` | Pixels da conta (Meta Graph API) |
| GET | `/api/generator/events` | Eventos disparados de um pixel |
| GET | `/api/generator/pages` | Páginas do Facebook vinculadas ao token |
| GET | `/api/generator/customconversions` | Conversões personalizadas da conta |
| POST | `/api/generator/create` | Cria campanha diretamente no Meta (uso interno/admin) |
| POST | `/api/generator/submit` | Usuário submete campanha como *draft* para aprovação |

### API — Aprovações e minhas campanhas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/approvals` | Lista drafts pendentes/revisados (admin) |
| GET | `/api/approvals/pending-count` | Contador para badge do menu |
| GET | `/api/approvals/{id}` | Detalhe de um draft |
| POST | `/api/approvals/{id}/approve` | Aprova e cria a campanha de fato no Meta |
| POST | `/api/approvals/{id}/reject` | Rejeita com motivo e campos apontados |
| GET | `/api/my-campaigns` | Drafts do usuário logado |
| GET | `/api/my-campaigns/{id}` | Detalhe de um draft do usuário |
| POST | `/api/my-campaigns/{id}/resubmit` | Reenvia draft rejeitado após correção |

### API — Usuários (admin only)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/users` | Lista usuários |
| PUT | `/api/users/{id}/accounts` | Define quais contas o usuário pode ver/usar |
| DELETE | `/api/users/{id}` | Remove usuário |

### API — WordPress

| Método | Rota | Descrição |
|--------|------|-----------|
| GET/POST/PUT/DELETE | `/api/wordpress/sites` | CRUD de sites WordPress (URL + Application Password) |
| GET/POST/PUT/DELETE | `/api/wordpress/templates` | CRUD de templates HTML reutilizáveis |
| POST | `/api/wordpress/templates/generate-from-url` | Extrai template HTML a partir de uma URL existente (Gemini) |
| POST | `/api/wordpress/generate` | Gera conteúdo do post/página com Gemini |
| POST | `/api/wordpress/generate-featured-image` | Gera imagem de destaque com Gemini |
| POST | `/api/wordpress/pages` | Publica o post/página no site via REST API do WordPress |

> Rotas de dashboard/listagem de campanhas/análise IA existiram em uma versão anterior e foram removidas do roteador; os arquivos de view (`dashboard.php`, `campaigns.php`, `views/ia/analysis.php`) e o método `GeminiService::analyzeCampaigns` ainda estão no repositório mas não são mais acessíveis.

## Gerador de Campanhas

Fluxo em 5 passos para criar campanha → adset → creative → ad:

1. **Passo 1** — Conta, nome da campanha, objetivo e status inicial (Ativa / Pausada)
2. **Passo 2** — Faixa etária, Advantage+ Audience, idiomas e posicionamentos por plataforma
3. **Passo 3** — Pixel, evento de conversão ou conversão personalizada, conta do Instagram
4. **Passo 4** — Criativo: imagem ou vídeo, headline, descrição, CTA, URL tags
5. **Passo 5** — Revisão e confirmação

Usuários não-admin não publicam direto: o passo final chama `/api/generator/submit`, que grava um *draft* em `campaign_drafts` (incluindo criativos em base64) para revisão em `/approvals`. Só a aprovação de um admin efetivamente cria a campanha no Meta via `CampaignCreatorService`.

### Posicionamentos suportados

| Plataforma | Valores aceitos pela API |
|------------|--------------------------|
| Facebook | `feed`, `facebook_reels`, `story`, `marketplace`, `video_feeds`, `right_hand_column`, `search`, `instream_video` |
| Instagram | `stream`, `reels`, `story`, `explore`, `explore_home`, `profile_feed` |
| Messenger | `sponsored_messages`, `story` |
| Audience Network | Sempre desativado |

## Sync de ROAS

Busca insights do Meta Ads e cruza com receita/sessões da ActiveView para os dias com dados faltantes, calculando `roas = receita_av / spend` por campanha (agendado via Windows Task Scheduler, uma task por conta):

```bash
php bin/sync.php
```

## Scripts de Diagnóstico

```bash
php bin/test_meta_api.php        # Testa token e permissões
php bin/test_create_campaign.php # Cria campanha via CampaignCreatorService
php bin/test_video_reels.php     # Cria campanha de vídeo Instagram Reels
php bin/check_account.php        # Detalha status de conta específica
php bin/inspect_campaign.php     # Lê estrutura de campanha e adsets
php bin/list_accounts.php        # Lista contas ativas no banco
```

## Estrutura do Projeto

```
├── bin/                        Scripts CLI (migrate, sync, diagnóstico)
├── config/
│   └── config.php              Configuração central (lê .env)
├── database/
│   └── migrations/             SQL de criação das tabelas (9 migrations numeradas)
├── public/
│   ├── index.php               Front controller — instancia tudo e registra as rotas
│   └── views/                  Templates PHP (generator, accounts, approvals, my-campaigns,
│                                users, wordpress/pages, layout compartilhado)
└── src/
    ├── Controllers/            AccountController, CampaignGeneratorController,
    │                           ApprovalController, AuthController, AiController,
    │                           CampaignController
    ├── Core/
    │   ├── Database/           Connection PDO (MySQL, singleton)
    │   ├── Encryption/         AES-256-CBC
    │   ├── Http/               Request, Response, Router (roteamento por regex, sem framework)
    │   └── HttpClient/         cURL com retry exponencial, upload multipart
    ├── Middleware/              AuthMiddleware (admin por API key / usuário por session_token)
    ├── Models/                 Account, CampaignDraft, CampaignReport, User,
    │                           WordPressSite, WordPressTemplate, ExecutionLog
    └── Services/
        ├── MetaAds/            CampaignService, CampaignCreatorService, InsightService, PixelService
        ├── ActiveView/         RevenueService, SessionService, GamCustomReportService
        ├── AI/                 GeminiService, CreativeGenerationService
        ├── Report/             ConsolidationService (cálculo de ROAS)
        └── WordPress/          WordPressService (publicação via REST API)
```

## Segurança

- Tokens e secrets (Meta Ads, ActiveView, WordPress Application Password) armazenados criptografados com AES-256-CBC
- Comparação de tokens/cookies com `hash_equals` (constant-time)
- Rotas web protegidas por cookie `_auth` (`requireWebAuth`); rotas de API por header `Authorization: Bearer`
- `.env` e `database/` ignorados no git
- Conexão MySQL com SSL suportado para ambientes gerenciados (ex: DigitalOcean Managed DB)
