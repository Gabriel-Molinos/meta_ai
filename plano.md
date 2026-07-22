# Plano v1 Do Meta Ads + Active View Integration API

## Persona
Você será um desenvolvedor full stack web, capaz de compreender facilmente as integrações webs via API.


## Visão Geral

Sistema interno para consolidação e analise de dados de marketing digital. Cruza métricas de **MetaAds** com dados de receita e sessões da **ActiveView**, calcula ROAS por campanha e persiste o histórico no MySQL. Suporta multiplas contas com credenciais criptografadas no banco. Possui interface Web no padrão MVC e módulo de analise feita por IA (Google Gemini)

## Stack
|Camada | Tecnologia | 
| --- | ---- |
| Linguagem | PHP 8.2+ (Sem framework) | 
| Autoload | Composer PSR-4 | 
| Banco de dados | SQLITE | 
| Cache | Redis (Predis) - graceful degradation | 
| HTTP Client | cURL wrapper proprio com retry exponencial |
| Criptografia | AES-256-CBC (OpenSSL) |
| Agendamento | Windows Task Scheduler (uma task por conta) |
| Servidor dev | PHP built-in server (`php -S localhost:8080 -t public`) |
| IA — analise | Google Gemini 2.5 Flash (`gemini-2.5-flash`) |
| IA — imagem | Google Gemini Image (`gemini-2.5-flash-image`) |
| IA — video | Google Gemini Omni Flash (`gemini-omni-flash-preview`) |
| IA — video | Google Gemini VEO (`veo-3.1-generate-preview`) |
| Frontend | DaisyUI v4 + Tailwind CDN + marked.js |

## Arquitetura de diretórios do Sistema inicial
-> Dentro dessa arquitetura de exemplo abaixo use o MVC
-> Pode alterar se achar necessário
Apps/
└──bin/
│
│
│
└──config/
|
└──database/
|   └──migrations/
|
└──public/
|
|
└──src/


## Documentações dos Endpoints do Meta Ads
-> https://developers.facebook.com/docs/?locale=pt_BR

## Documentações dos Endpoints da Active View
-> C:\projetos\AI\crons\src\Services\ActiveView
-> Apenas leia não altere nada nesses arquivos é proibido alterar.
