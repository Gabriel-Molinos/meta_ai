# Plano v2 — Meta Ads + ActiveView Integration

## Visão Geral

Sistema interno para consolidação e análise de dados de marketing digital. Cruza métricas do **Meta Ads** com dados de receita e sessões da **ActiveView**, calcula ROAS por campanha e persiste o histórico no MySQL. Suporta múltiplas contas com credenciais criptografadas. Possui interface Web com sistema de usuários (Google OAuth), gerador de campanhas Meta Ads com fluxo de aprovação, e gerador de páginas WordPress com IA.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.2+ (sem framework) |
| Autoload | Composer PSR-4 |
| Banco de dados | MySQL 8 (DigitalOcean Managed) |
| Cache | Redis (Predis) — graceful degradation |
| HTTP Client | cURL wrapper próprio com retry exponencial |
| Criptografia | AES-256-CBC (OpenSSL) |
| Servidor dev | `php -S localhost:8080 -t public` |
| IA — texto | Google Gemini 2.5 Flash (`gemini-2.5-flash`) |
| IA — imagem | Google Gemini Image (`gemini-2.5-flash-image`) |
| Frontend | DaisyUI v4 + Tailwind CDN + marked.js |

---

## Arquitetura de Diretórios

```
meta/
├── bin/                        # Scripts CLI (sync, migrate, diagnóstico)
├── config/
│   └── config.php              # Configurações centrais (lê .env)
├── database/
│   └── migrations/             # SQLs versionados (001–009)
├── public/
│   ├── index.php               # Front controller + roteador
│   └── views/                  # Views PHP (layout + páginas)
│       ├── layout.php
│       ├── accounts.php
│       ├── approvals.php
│       ├── generator.php
│       ├── login.php
│       ├── my-campaigns.php
│       ├── users.php
│       └── wordpress/
│           └── pages.php
└── src/
    ├── Controllers/
    ├── Middleware/
    ├── Models/
    ├── Services/
    └── Core/
```

---

## Módulos Implementados

### 1. Autenticação e Usuários
- **Dois roles:** `admin` (API key) e `user` (Google OAuth)
- Login admin: campo de senha na tela de login → cookie `_auth`
- Login user: "Entrar com Google" → OAuth2 → cookie `_auth` com session_token (24h)
- `AuthMiddleware::resolveContext()` — distingue admin vs user nos endpoints de API
- `requireWebAuth()` em index.php — protege rotas web, seta `$GLOBALS['_authType']`, `_authUserId`, `_authEmail`
- Tela `/users` (admin): lista usuários cadastrados, vincula/desvincula contas Meta por usuário
- Variáveis `.env` necessárias: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`

### 2. Gerador de Campanhas Meta Ads
- Formulário em 4 passos: dados da campanha → segmentação/orçamento → pixel/conversão → anúncios (imagem/vídeo)
- **Admin:** cria campanha diretamente no Meta via `CampaignCreatorService`
- **User:** envia para aprovação (salva como draft com criativos em base64)
- Suporte a múltiplos anúncios por campanha, múltiplas plataformas e posicionamentos
- Pré-preenchimento de draft rejeitado via `?draft_id=X` — campos rejeitados destacados em vermelho

### 3. Sistema de Aprovação de Campanhas
- **Fluxo:** User submete → Admin revisa → Aprova (publica no Meta) ou Rejeita (com campos + motivo)
- Painel `/approvals` (admin): tabs Pendentes / Aprovadas / Rejeitadas, painel lateral de revisão
- Revisão exibe: objetivo, orçamento, datas, países, idiomas, faixa etária, Advantage+ Audience, posicionamentos por plataforma, pixel, URL de destino, url_tags, preview de criativos (imagem/vídeo)
- Rejeição: admin seleciona campos problemáticos (10 categorias) + escreve motivo
- User vê resultado em `/my-campaigns` — pode editar e reenviar drafts rejeitados

### 4. Gerador de Páginas WordPress
- Geração de conteúdo HTML via Gemini (AIDA framework ou template customizado)
- Geração de imagem destacada via `gemini-2.5-flash-image`
- Publicação via REST API do WordPress (Basic Auth com Application Password)
- Extração de template a partir de URL via Gemini
- CRUD de sites WordPress e templates HTML

---

## Banco de Dados (MySQL — DigitalOcean Managed)

| Migration | Tabela | Descrição |
|---|---|---|
| 001 | `accounts` | Contas Meta+ActiveView com credenciais criptografadas |
| 002 | `executions` | Log de execuções do sync |
| 003 | `campaign_reports` | Dados diários de campanhas (spend, ROAS) |
| 004 | `users` | Autenticação (email, name, google_id, role, session_token) |
| 005 | `wordpress_sites` | Sites WP com senha criptografada |
| 006 | `wordpress_templates` | Templates HTML para geração de posts |
| 007 | `users` (ALTER) | Adiciona name, google_id, role, updated_at |
| 008 | `user_accounts` | Pivot: vínculo usuário ↔ conta Meta |
| 009 | `campaign_drafts` | Campanhas aguardando aprovação (payload + criativos JSON) |

**Atenção:** `ANSI_QUOTES` ativo no servidor — usar aspas simples para valores string em SQL (aspas duplas são tratadas como identificadores de coluna).

---

## Rotas

### Web (HTML)
| Rota | Acesso | Descrição |
|---|---|---|
| `/login` | público | Tela de login |
| `/accounts` | admin | Gerenciar contas Meta |
| `/users` | admin | Gerenciar usuários e vínculos de conta |
| `/generator` | ambos | Gerador de campanhas |
| `/approvals` | admin | Painel de aprovação |
| `/my-campaigns` | user | Campanhas enviadas pelo usuário |
| `/wordpress/pages` | ambos | Gerador de páginas WordPress |

### API
| Rota | Método | Acesso | Descrição |
|---|---|---|---|
| `/oauth/google` | GET | público | Redireciona para Google OAuth |
| `/oauth/callback` | GET | público | Callback OAuth |
| `/api/auth/admin-login` | POST | público | Login admin por API key |
| `/api/accounts` | GET/POST/PUT/DELETE | admin | CRUD contas |
| `/api/users` | GET | admin | Lista usuários |
| `/api/users/{id}/accounts` | PUT | admin | Vincula contas ao usuário |
| `/api/users/{id}` | DELETE | admin | Remove usuário |
| `/api/generator/create` | POST | admin | Cria campanha direto no Meta |
| `/api/generator/submit` | POST | user | Envia campanha para aprovação |
| `/api/approvals` | GET | admin | Lista todos os drafts |
| `/api/approvals/pending-count` | GET | admin | Contagem de pendentes (badge) |
| `/api/approvals/{id}` | GET | admin | Detalhes de um draft |
| `/api/approvals/{id}/approve` | POST | admin | Aprova e cria no Meta |
| `/api/approvals/{id}/reject` | POST | admin | Rejeita com campos + motivo |
| `/api/my-campaigns` | GET | user | Campanhas do usuário logado |
| `/api/my-campaigns/{id}` | GET | ambos | Detalhes de um draft |
| `/api/my-campaigns/{id}/resubmit` | POST | user | Resubmete draft rejeitado |
| `/api/wordpress/sites` | GET/POST/PUT/DELETE | admin | CRUD sites WordPress |
| `/api/wordpress/templates` | GET/POST/PUT/DELETE | admin | CRUD templates |
| `/api/wordpress/generate` | POST | admin | Gera HTML com IA |
| `/api/wordpress/generate-featured-image` | POST | admin | Gera imagem com IA |
| `/api/wordpress/pages` | POST | admin | Publica no WordPress |

---

## Observações Técnicas

**CampaignCreatorService:**
- `postMultipart()` em todos os métodos (API Meta exige form-data)
- `bid_strategy = LOWEST_COST_WITHOUT_CAP` explícito
- Posições por plataforma só enviadas se a plataforma estiver em `publisher_platforms`
- `custom_conversion_id` tem prioridade sobre `pixel_event` no `promoted_object`

**Placements válidos:**
- `facebook_positions`: `feed`, `facebook_reels`, `story`, `marketplace`, `video_feeds`, `right_hand_column`, `search`, `instream_video`
- `instagram_positions`: `stream`, `reels`, `story`, `explore`, `explore_home`, `profile_feed`
- `messenger_positions`: `sponsored_messages`, `story`

**Bloqueios conhecidos na conta `act_834518578730293`:**
- `facebook_reels` bloqueado (sem permissão `business_management`) — Instagram Reels funciona
- `instagram_user_id: 17841469630403537` pertence à conta pxmind — não usar em outras ad accounts

---

## Documentações de Referência

- Meta Ads API: https://developers.facebook.com/docs/?locale=pt_BR
- ActiveView Services: `C:\projetos\AI\crons\src\Services\ActiveView` (somente leitura)
