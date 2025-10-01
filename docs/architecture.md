# IW8 WA Click Tracker — Arquitetura (v1.4.3)

## Objetivo

Rastrear cliques em links do WhatsApp no WordPress, expondo um contrato REST estável para integrações e relatórios.

## Mapa de módulos

iw8-wa-click-tracker/
├─ iw8-wa-click-tracker.php # bootstrap do plugin (ponto único de entrada)
├─ src/
│ ├─ Rest/ # controladores e registrador de rotas (único ponto)
│ ├─ Repositories/ # acesso a dados (DB)
│ ├─ Security/ # autenticação/token, rate limit, HTTPS
│ ├─ Services/ # serviços auxiliares (tempo/limites)
│ ├─ Http/ # respostas/erros HTTP
│ ├─ Validation/ # validação de query params/cursor
│ └─ Support/ # helpers não-WP (env/infra)
├─ includes/ # compat/legado (autoload opcional)
│ ├─ Core/ install/ Frontend/ … # (mantidos por compatibilidade)
│ └─ autoload.php
├─ vendor/ # Composer autoload e dependências
├─ assets/, languages/, plugin-update-checker/ …
└─ deprecated/ # arquivos aposentados (LEGACY)

## Fluxo de bootstrap (ordem de carga)

1. **Composer autoload** (`vendor/autoload.php`)
2. **includes/autoload.php** (legado necessário p/ migrações e alguns admins)
3. **includes/Core/Security.php** (se existir) → `Security::init()`
4. **includes/install/db-migrations.php** (define `iw8_wa_run_migrations()`)
5. **Admin opcional** (arquivos não-PSR-4, guardados por `is_readable`)
6. **Updater** (PUC) apenas em admin
7. **REST**: `add_action('rest_api_init', ...)` → `src/Rest/ApiRegistrar::register()`
8. **Schema check em admin** (`admin_init`) → roda `iw8_wa_run_migrations()` se necessário
9. **init (20)** → inicializa `\IW8\WaClickTracker\Core\Plugin` se presente

## Dependências e diretrizes de acoplamento

- **Rest** pode depender de: `Validation`, `Repositories`, `Security`, `Services`, `Http`.
- **Repositories** só dependem de WP/DB; não devem conhecer `Rest`/`Admin`.
- **Security** fornece `TokenAuthenticator`, `RateLimiter`, `HttpsEnforcer` — sem dependência circular.
- **includes/** é **compat/legado**. Nada em `src/` deve depender de `includes/`.

## Namespaces canônicos

- Raiz: **`IW8\WA\…`** (WA **sempre** em maiúsculas).
- Exemplos: `IW8\WA\Rest\ApiRegistrar`, `IW8\WA\Repositories\ClickRepository`.

## Banco de dados (resumo)

- Tabela principal: **`{prefix}iw8_wa_clicks`**
  - Campos típicos: `id (PK)`, `url`, `page_url`, `element_tag`, `element_text`, `user_agent`, `clicked_at (datetime)`, chaves auxiliares.
- Versão do schema: **`IW8_WA_DB_VERSION = '1.1'`** (persistida em `iw8_wa_db_version`).

## Registro das rotas (ponto único)

- `src/Rest/ApiRegistrar::register()` chama `register_rest_route()` para:
  - `GET /iw8-wa/v1/ping`
  - `GET /iw8-wa/v1/clicks`
- **Proibido** registrar rotas fora do `ApiRegistrar`.

## Autenticação REST

- Header obrigatório: **`X-IW8-Token: <token>`**.
- Validação: `src/Security/TokenAuthenticator.php`.

## Erros e logging

- Respostas padronizadas (ver `docs/api/rest.md`).
- Logs via `error_log` (se `iw8_wa_debug = true`) e fallback em `logs/plugin.log`.
