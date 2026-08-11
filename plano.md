# Plano Do Meta Ads + Active View Integration API

> Atualizado em 2026-07-28 para refletir o estado real do sistema (v1 era um plano inicial focado só em dashboard de ROAS; o escopo cresceu para gerador de campanhas com aprovação, gestão de usuários e módulo WordPress — ver histórico de commits).

## Persona
Você será um desenvolvedor full stack web, capaz de compreender facilmente as integrações webs via API.

## Visão Geral

Sistema interno com três frentes:

1. **Gerador de campanhas Meta Ads** — wizard guiado (5 passos) que monta campanha → adset → creative → ad. Usuários não-admin não publicam direto: submetem um *draft* que passa por aprovação de um admin antes de ir ao ar.
2. **Consolidação de ROAS** — cruza métricas do **Meta Ads** com dados de receita e sessões da **ActiveView**, calcula ROAS por campanha e persiste o histórico no MySQL (`bin/sync.php`, agendado via Windows Task Scheduler, uma task por conta).
3. **Geração de conteúdo WordPress** — cria posts/páginas com Gemini (texto + imagem de destaque) e publica em sites WordPress externos via REST API (Application Password).

Suporta múltiplas contas Meta Ads + ActiveView com credenciais criptografadas no banco. Interface Web em MVC feito à mão (sem framework), autenticação dupla (admin por token estático, usuários por Google OAuth).

## Stack

| Camada | Tecnologia |
| --- | ---- |
| Linguagem | PHP 8.2+ (sem framework) |
| Autoload | Composer PSR-4 |
| Banco de dados | MySQL 8 (PDO puro, sem ORM) — migrado de SQLite (plano original) para suportar banco gerenciado (DigitalOcean, SSL) |
| Cache | Redis (Predis) — graceful degradation, usado no sync de insights/receita |
| HTTP Client | cURL wrapper próprio com retry exponencial e upload multipart |
| Criptografia | AES-256-CBC (OpenSSL) para segredos em repouso (tokens Meta, chaves ActiveView, senha de app WordPress) |
| Autenticação | Admin: `APP_API_KEY` estático. Usuário: Google OAuth 2.0, sessão de 24h (`session_token` em `users`) |
| Agendamento | Windows Task Scheduler (uma task por conta) rodando `bin/sync.php` |
| Servidor dev | PHP built-in server (`php -S localhost:8080 -t public`) |
| IA — texto/análise | Google Gemini 2.5 Flash (`gemini-2.5-flash`) |
| IA — imagem | Google Gemini Image, usado para imagem de destaque de posts WordPress |
| Frontend | DaisyUI v4 + Tailwind CDN + marked.js, sem build step |

## Arquitetura de diretórios (como implementado)

```
meta_ai/
├── bin/                        Scripts CLI: migrate.php, sync.php, e diagnósticos (test_*.php)
│                                para experimentar direto contra a Graph API do Meta
├── config/
│   └── config.php              Lê .env e monta o array de configuração central
├── database/
│   └── migrations/             9 arquivos SQL numerados (accounts, executions,
│                                campaign_reports, users, wordpress_sites,
│                                wordpress_templates, user_accounts, campaign_drafts)
├── public/
│   ├── index.php               Front controller único — instancia tudo (DI manual)
│   │                            e registra todas as rotas (API + views)
│   └── views/                  Templates PHP renderizados no servidor (DaisyUI/Tailwind)
└── src/
    ├── Controllers/            AccountController, CampaignGeneratorController,
    │                           ApprovalController, AuthController, AiController, CampaignController
    ├── Core/                   Database (PDO singleton), Encryption, Http (Request/Response/Router),
    │                           HttpClient (cURL + retry), Cache (Redis), Exceptions
    ├── Middleware/              AuthMiddleware — distingue contexto admin (API key) de usuário (OAuth)
    ├── Models/                 Account, CampaignDraft, CampaignReport, User,
    │                           WordPressSite, WordPressTemplate, ExecutionLog
    └── Services/
        ├── MetaAds/            CampaignService, CampaignCreatorService, InsightService, PixelService
        ├── ActiveView/         RevenueService, SessionService, GamCustomReportService
        ├── AI/                 GeminiService, CreativeGenerationService
        ├── Report/             ConsolidationService (cálculo de ROAS)
        └── WordPress/          WordPressService (publicação via REST API)
```

## Módulos implementados

- **Contas** (`/accounts`): CRUD de contas Meta Ads + ActiveView, credenciais sensíveis criptografadas. Criação exige `meta_access_token` e `meta_account_id` válidos (validação adicionada após bug em que contas eram salvas sem credenciais e o wizard falhava silenciosamente ao buscar pixels/páginas).
- **Gerador de campanhas** (`/generator`): wizard de 5 passos; busca pixels/eventos/páginas/conversões personalizadas ao vivo na Graph API por conta selecionada.
- **Aprovação de campanhas** (`/approvals`, `/my-campaigns`): usuários OAuth submetem drafts (`campaign_drafts`, criativos em base64); admin aprova (cria de fato no Meta via `CampaignCreatorService`) ou rejeita com motivo, permitindo reenvio.
- **Usuários** (`/users`): gestão de usuários Google OAuth e vínculo de quais contas cada um pode ver/usar (`user_accounts`).
- **WordPress** (`/wordpress/pages`): geração de conteúdo (texto + imagem) com Gemini e publicação via REST API do WordPress, com templates reutilizáveis.
- **Sync de ROAS** (`bin/sync.php`): cron por conta que busca insights do Meta + receita/sessões da ActiveView e persiste o ROAS calculado.

### Removido do roteador (arquivos ainda presentes, mas inacessíveis)

Uma versão anterior tinha dashboard de ROAS, listagem/filtro de campanhas e análise de campanhas por IA. As rotas foram removidas (`chore: remove dashboard, campaigns e IA routes e menu items`), mas os arquivos de view (`dashboard.php`, `campaigns.php`, `views/ia/analysis.php`) e `GeminiService::analyzeCampaigns` continuam no repositório como código morto — não vale confiar neles sem revisar antes de reativar.

## Documentações dos Endpoints do Meta Ads
-> https://developers.facebook.com/docs/?locale=pt_BR

## Documentações dos Endpoints da Active View
-> C:\projetos\AI\crons\src\Services\ActiveView
-> Apenas leia não altere nada nesses arquivos é proibido alterar.
