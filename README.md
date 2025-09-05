# IW8 – WA Click Tracker (v1.4.0)

Plugin WordPress para **rastrear cliques em links do WhatsApp** e expor **endpoints REST** para integrações externas com paginação por **cursor forward-only**, **autenticação por token**, **rate limit** e **fallback** de leitura para tabela legada.

## Sumário
- [Visão Geral](#visão-geral)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Atualizações Automáticas (PUC v5)](#atualizações-automáticas-puc-v5)
- [Segurança](#segurança)
- [Configuração Rápida (Token por Domínio)](#configuração-rápida-token-por-domínio)
- [Estrutura de Pastas](#estrutura-de-pastas)
- [Banco de Dados & Migrações](#banco-de-dados--migrações)
- [API REST](#api-rest)
  - [Autenticação](#autenticação)
  - [Rate Limit](#rate-limit)
  - [Paginação por Cursor (forward-only)](#paginação-por-cursor-forward-only)
  - [Cabeçalhos de Resposta](#cabeçalhos-de-resposta)
  - [/ping](#ping)
  - [/clicks](#clicks)
  - [Códigos de Erro](#códigos-de-erro)
- [Compatibilidade com Legado](#compatibilidade-com-legado)
- [Exemplos de Uso (cURL)](#exemplos-de-uso-curl)
- [Troubleshooting](#troubleshooting)
- [Roadmap (próximas versões)](#roadmap-próximas-versões)
- [Licença](#licença)

---

## Visão Geral
- **Rastreamento Automático:** coleta de cliques em links do WhatsApp no site WordPress.
- **Relatórios e Integrações:** leitura via REST **`/ping`** e **`/clicks`**.
- **Autenticação:** header **`X-IW8-Token`**.
- **Segurança:** **HTTPS obrigatório** em produção; bloqueio a requisições HTTP.
- **Paginação:** **cursor forward-only** com `next_cursor`.
- **Rate Limit:** **60 rpm** (por token), com cabeçalhos `X-RateLimit-*`.
- **Fallback Legado:** leitura da tabela **`{$prefix}wa_clicks`** quando **`{$prefix}iw8_wa_clicks`** não existir.
- **Alias de Data:** `created_at → clicked_at` (sempre retorna `clicked_at`).

## Requisitos
- WordPress **≥ 6.0**
- PHP **≥ 7.4**
- MySQL/MariaDB compatível com WP
- PHP cURL/JSON ativados

## Instalação
1. Envie a pasta do plugin para `wp-content/plugins/iw8-wa-click-tracker/`.
2. Garanta que **`vendor/`** está presente (este repositório versiona `vendor/`).
3. Ative o plugin no **WP-Admin → Plugins**.
4. No **primeiro acesso ao Admin**, as **migrações** são executadas automaticamente.

> **Empacotamento:** o diretório `dist/` é ignorado (não vai para o Git).  
> **Update URI:** cabeçalho do plugin aponta para o repositório GitHub.

## Atualizações Automáticas (PUC v5)
O plugin usa **Plugin Update Checker v5** (PUC) para buscar novas versões a partir do GitHub:
- Branch estável: **`main`**
- `enableReleaseAssets()` ativado (baixa o ZIP anexado na página da Release ou *Source code.zip*).

## Segurança
- **Produção:** **não** defina `IW8_WA_SEED_DEV` no `wp-config.php`.
- **HTTPS obrigatório:** chamadas HTTP são recusadas em produção.
- **WAF/CDN (Cloudflare, etc.):** liberar `/wp-json/iw8-wa/*` se houver bloqueios.
- **Token por domínio:** cada instalação tem seu próprio token.

## Configuração Rápida (Token por Domínio)
Defina a opção `iw8_wa_domain_token`:

```bash
wp option update iw8_wa_domain_token "SEU_TOKEN_FORTE"
# Para gerar um token forte:
wp eval 'echo bin2hex(random_bytes(32)).PHP_EOL;'
```

## Estrutura de Pastas
> **Consolidado (legado + 1.4.0):** visão geral das principais pastas/arquivos hoje no projeto.
> Os nomes podem variar levemente; a API/contratos permanecem.

```
iw8-wa-click-tracker/
├─ iw8-wa-click-tracker.php
├─ README.md
├─ CHANGELOG.md
├─ .gitignore                     # dist/ ignorado; vendor/ versionado
├─ vendor/                        # (versionado) PUC v5, autoload, etc.
├─ assets/
│  ├─ js/
│  │  ├─ tracker.js               # captura de cliques WhatsApp (fronte)
│  │  └─ admin.js                 # comportamentos no admin (se aplicável)
│  └─ css/
│     └─ admin.css                # estilos da interface admin (se aplicável)
├─ languages/
│  └─ iw8-wa-click-tracker-pt_BR.mo/.po (.pot)  # internacionalização
├─ includes/
│  ├─ Core/
│  │  ├─ Updater.php              # PUC v5 (branch main + release assets)
│  │  ├─ Security.php             # HTTPS gate, auth helpers, env flags
│  │  ├─ RateLimiter.php          # 60 rpm (por token)
│  │  ├─ Cursor.php               # geração/validação de cursor forward-only
│  │  └─ Hooks.php                # registro de actions/filters comuns
│  ├─ Rest/
│  │  ├─ Routes.php               # registro namespace /iw8-wa/v1
│  │  ├─ PingController.php       # GET /ping
│  │  └─ ClicksController.php     # GET /clicks (fields, limit, since/until, cursor)
│  ├─ Repository/
│  │  └─ ClickRepository.php      # leitura + fallback wa_clicks + alias created_at→clicked_at
│  ├─ Admin/
│  │  ├─ Pages/
│  │  │  └─ ClicksPage.php        # listagem/admin UI (legado 1.3.x)
│  │  ├─ AdminNotices.php         # avisos no painel (opcional)
│  │  └─ Assets.php               # enqueue de scripts/estilos admin
│  ├─ Export/
│  │  └─ CsvExporter.php          # exportação CSV (legado 1.3.x)
│  ├─ Migrations/
│  │  ├─ 2024_..._create_iw8_wa_clicks.php
│  │  └─ runner.php               # iw8_wa_run_migrations()
│  └─ Utils/
│     ├─ Validation.php
│     ├─ Response.php
│     └─ Arrays.php               # utilitários (opcional)
└─ dist/                          # (ignorado) builds/pacotes temporários
```

## Banco de Dados & Migrações
Tabela principal: **`{$prefix}iw8_wa_clicks`**  
Campos típicos: `id (PK)`, `clicked_at (DATETIME UTC)`, `url`, `page_url`, `element_tag`, `element_text`, `user_id`, `user_agent`, `ip` (se coletado), etc.

- **Migrações:**
  - Executadas via `register_activation_hook()` e `admin_init` → `iw8_wa_run_migrations()`.
  - **WP-CLI (opcional)**: `wp eval 'iw8_wa_run_migrations(); echo "ok\n";'`

## API REST
**Base:** `/wp-json/iw8-wa/v1`

### Autenticação
Envie o header **`X-IW8-Token: {token_do_domínio}`**.  
Sem esse header ou com token inválido → **401**.

### Rate Limit
- **60 rpm** por token.
- Cabeçalhos: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.
- Estouro → **429** (+ `Retry-After`).

### Paginação por Cursor (forward-only)
- Respostas incluem **`next_cursor`** quando `count == limit`.
- Envie `cursor={valor_opaco}` na próxima chamada para continuar do ponto mais recente já lido.
- Sem retrocesso (sem *seek backward*).

### Cabeçalhos de Resposta
- `X-Service-Version: 1.4.0`
- `X-Cursor-Semantics: forward-only`
- `X-RateLimit-*` (ver acima)

### /ping
**GET** `/wp-json/iw8-wa/v1/ping`  
**Headers obrigatórios:** `X-IW8-Token`  
**200 OK**:
```json
{
  "status": "ok",
  "token_last4": "ABCD",
  "service_version": "1.4.0",
  "now_utc": "2025-08-29T12:34:56Z"
}
```

### /clicks
**GET** `/wp-json/iw8-wa/v1/clicks`

**Headers obrigatórios:**
- `X-IW8-Token`

**Query params:**
- `fields` — lista separada por vírgulas. Ex.: `id,clicked_at,page_url,url,element_text,user_agent`
- `limit` — inteiros (ex.: `50`). *Default* recomendado: `50`.
- `since` | `until` — **ISO-8601 UTC**. Ex.: `2025-08-01T00:00:00Z`
- `cursor` — valor opaco retornado em `next_cursor` (para continuar a paginação).

**200 OK**:
```json
{
  "count": 2,
  "data": [
    {
      "id": 123,
      "clicked_at": "2025-08-28T09:15:20Z",
      "url": "https://wa.me/...",
      "page_url": "https://seusite/landing",
      "element_tag": "a",
      "element_text": "Fale no WhatsApp",
      "user_id": null,
      "user_agent": "Mozilla/5.0 ..."
    },
    {
      "id": 122,
      "clicked_at": "2025-08-28T09:14:10Z",
      "url": "https://wa.me/...",
      "page_url": "https://seusite/produto",
      "element_tag": "button",
      "element_text": "Contato",
      "user_id": null,
      "user_agent": "Mozilla/5.0 ..."
    }
  ],
  "next_cursor": "eyJvZmZzZXQiOiIyMDI1LTA4LTI4VDA5OjE0OjEwWl8xMjIifQ" 
}
```

**Regras importantes:**
- `fields` **é aplicado** (somente as colunas requisitadas vêm no `data`).
- `limit` **é respeitado**.
- `since`/`until` devem estar em **UTC** (ISO-8601 com `Z`).
- `next_cursor` **existe quando** `count == limit` (há mais páginas).

### Códigos de Erro
- **400** — parâmetros inválidos (ex.: formato de data incorreto, `limit` fora do intervalo).
- **401** — ausente/inválida a autenticação (ou **HTTP** em produção).
- **429** — limite de requisições excedido (rate limit).

## Compatibilidade com Legado
- Se **`{$prefix}iw8_wa_clicks`** **não** existir, o plugin **lê de** **`{$prefix}wa_clicks`**.
- Alias de data: **`clicked_at = COALESCE(clicked_at, created_at)`** — o campo **`clicked_at`** **sempre** é retornado no endpoint.

## Exemplos de Uso (cURL)

**/ping**
```bash
curl -sS https://seusite.com/wp-json/iw8-wa/v1/ping   -H "X-IW8-Token: SEU_TOKEN"
```

**/clicks (primeira página)**
```bash
curl -sS "https://seusite.com/wp-json/iw8-wa/v1/clicks?fields=id,clicked_at,page_url,url,element_text&limit=50&since=2025-08-01T00:00:00Z&until=2025-08-31T23:59:59Z"   -H "X-IW8-Token: SEU_TOKEN"
```

**/clicks (página seguinte com cursor)**
```bash
curl -sS "https://seusite.com/wp-json/iw8-wa/v1/clicks?limit=50&cursor=SEU_NEXT_CURSOR"   -H "X-IW8-Token: SEU_TOKEN"
```

## Troubleshooting
- **401 em produção usando HTTP:** forçar **HTTPS**; não defina `IW8_WA_SEED_DEV`.
- **429 constante:** aumente intervalo entre chamadas ou ajuste a política (60 rpm).
- **Sem dados, site migrado:** confira se a tabela nova não existe e o **fallback** para `wa_clicks` está ativo.
- **PUC não oferece update:** verifique `Update URI`, branch `main`, tag/release publicada e token do GitHub (se privado).
- **WAF/Cloudflare bloqueando:** liberar `/wp-json/iw8-wa/*`.

## Roadmap (próximas versões)
- Hardening de tipos `geo_city`/`geo_region`.
- Migração de unificação `wa_clicks → iw8_wa_clicks` **sem downtime**.
- (Opcional) Tela de admin para gerenciar o token do domínio.

## Licença
MIT (ou conforme arquivo de licença do repositório).
