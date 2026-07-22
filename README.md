# Meta AI — Dashboard Meta Ads + ActiveView

Sistema interno para consolidar dados do **Meta Ads** com a **ActiveView**, calcular ROAS real por campanha e exibir em dashboard web. Inclui gerador de campanhas diretamente na UI.

## Stack

- **Backend:** PHP 8.2 sem framework, Composer PSR-4
- **Banco de dados:** MySQL 8 (PDO, suporte a SSL)
- **Cache:** Redis / Predis (graceful degradation se indisponível)
- **IA:** Google Gemini 2.5 Flash
- **Frontend:** DaisyUI v4 + Tailwind CDN + marked.js

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
cp .env.example .env
```

Edite o `.env` com suas credenciais e rode as migrations:

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

# Meta Ads (Facebook Graph API)
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
```

## Rotas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/dashboard` | Dashboard ROAS por conta |
| GET | `/campaigns` | Listagem e filtro de campanhas |
| GET | `/accounts` | Gerenciar contas Meta + ActiveView |
| GET | `/ia` | Análise de campanhas com Gemini |
| GET | `/generator` | Gerador de campanhas Meta Ads |
| GET | `/api/generator/pixels` | Pixels disponíveis na conta |
| GET | `/api/generator/events` | Eventos de um pixel |
| GET | `/api/generator/pages` | Páginas do Facebook |
| GET | `/api/generator/customconversions` | Conversões personalizadas |
| POST | `/api/generator/create` | Cria campanha completa no Meta |

## Gerador de Campanhas

Fluxo em 5 passos para criar campanha → adset → creative → ad diretamente na UI:

1. **Passo 1** — Conta, nome da campanha, objetivo e status inicial (Ativa / Pausada)
2. **Passo 2** — Faixa etária, Advantage+ Audience, idiomas e posicionamentos por plataforma
3. **Passo 3** — Pixel, evento de conversão ou conversão personalizada, conta do Instagram
4. **Passo 4** — Criativo: imagem ou vídeo, headline, descrição, CTA, URL tags
5. **Passo 5** — Revisão e confirmação antes de enviar à API

### Posicionamentos suportados

| Plataforma | Valores aceitos pela API |
|------------|--------------------------|
| Facebook | `feed`, `facebook_reels`, `story`, `marketplace`, `video_feeds`, `right_hand_column`, `search`, `instream_video` |
| Instagram | `stream`, `reels`, `story`, `explore`, `explore_home`, `profile_feed` |
| Messenger | `sponsored_messages`, `story` |
| Audience Network | Sempre desativado |

## Sync de Dados

Busca insights do Meta Ads e cruza com receita da ActiveView para os dias com dados faltantes:

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
├── bin/                    Scripts CLI (migrate, sync, diagnóstico)
├── config/
│   └── config.php          Configuração central (lê .env)
├── database/
│   └── migrations/         SQL de criação das tabelas
├── public/
│   ├── index.php           Front controller
│   └── views/              Templates PHP (dashboard, campaigns, generator...)
└── src/
    ├── Controllers/        Lógica de cada rota
    ├── Core/
    │   ├── Database/       Connection PDO (MySQL)
    │   ├── Encryption/     AES-256-CBC
    │   └── HttpClient/     cURL com retry exponencial
    ├── Models/             Account, CampaignReport, ExecutionLog
    └── Services/
        └── MetaAds/        MetaAdsService, CampaignCreatorService, PixelService
```

## Segurança

- Tokens e secrets da Meta Ads são armazenados criptografados com AES-256-CBC
- Rotas da API protegidas por `APP_API_KEY` no header `X-API-Key`
- `.env` e `database/` ignorados no git
- Conexão MySQL com SSL obrigatório em ambientes gerenciados
