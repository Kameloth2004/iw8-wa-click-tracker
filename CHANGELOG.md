# Changelog — IW8 – WA Click Tracker

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.  
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/) e em [SemVer](https://semver.org/lang/pt-BR/).

## [1.4.0] - 2025-09-04

### Adicionado

- **Token novo** `iw8_click_token` com fallback no legado `iw8_wa_domain_token`.
- **Admin – Configurações**: gerar/rotacionar token, revelar/copiar (com fallback sem `navigator.clipboard`), e **exportar JSON** (contendo `base_url`, `token`, `plugin.version`).
- **API REST** com endpoints **`/wp-json/iw8-wa/v1/ping`** e **`/wp-json/iw8-wa/v1/clicks`**.
- **Paginação por cursor (forward-only)** no `/clicks` (**`next_cursor`**; cabeçalho **`X-Cursor-Semantics: forward_only`**).
- **Rate limit: 60 rpm** por token, com cabeçalhos **`X-RateLimit-Limit`**, **`X-RateLimit-Remaining`**, **`X-RateLimit-Reset`** (e **`Retry-After`** em estouro).
- **Cabeçalho de versão do serviço**: **`X-Service-Version: 1.4.0`**.
- **Compat REST**: _wrapper_ de `permission_callback` em **`/wp-json/iw8-wa/v1/*`** aceitando o token novo com fallback no legado.

### Corrigido

- **Telefone WhatsApp**: salvamento via `admin-post` (sem telas brancas), sanitização (apenas dígitos) e aviso quando ausente.

### Alterado

- **Update URI** do plugin apontando para o repositório GitHub correto.
- **Empacotamento**: `vendor/` versionado; `dist/` ignorado (compatível com PUC/ZIP).
- **Validação** de parâmetros nos endpoints (`fields`, `limit`, `since`, `until`) com erros 4xx claros.
- **Alias de data**: `created_at → clicked_at` (o endpoint sempre retorna `clicked_at`).

### Compatibilidade

- **Fallback de leitura** para tabela legada **`{$prefix}wa_clicks`** quando **`{$prefix}iw8_wa_clicks`** não existir.
- Prioridade de token em produção: **`iw8_click_token` → `iw8_wa_domain_token`**.
- Todos os horários na API são **UTC (ISO-8601 com `Z`)**.

### Segurança

- **HTTPS recomendado/obrigatório** em produção; ambientes sem TLS podem sofrer recusas (401) conforme política.
- Observação de **WAF/CDN** (ex.: Cloudflare): liberar **`/wp-json/iw8-wa/*`**.

---

## [1.3.0] - 2024-01-01

### Adicionado

- **Estrutura inicial do plugin** com autoload PSR-4, classes base e _stubs_ funcionais.
- **Admin**: Menu e páginas (**Clicks**, **Diagnostics**, **Settings**) iniciais.
- **Frontend**: _Tracker_ e `UrlMatcher` (base).
- **Ajax**: `ClickController` (base).
- **Export**: `CsvExporter` (base).
- **REST**: esqueleto de API (base).
- **Compat**: _Builders_ (base).
- **Utils**: `Helpers`.

### Estrutura criada

- **Core**: Plugin principal, Assets, Versions, Updater, Security, Logger, Hooks.
- **Database**: `TableClicks`, `Migrations`, `ClickRepository`.

### Arquivos de suporte

- `README.md`, `CHANGELOG.md`, arquivos de tradução `.pot`, JS/CSS básicos e configurações de Git.

### Notas técnicas

- Compatível com **PHP 7.4+** e **WordPress 6.x**; namespaces PSR-4; preparado para i18n; arquitetura modular.

---

## [Unreleased]

### Planejado

- **1.5.0** — Rastreamento no front-end (captura JS) e envio via AJAX.
- **1.6.0** — Interface administrativa ampliada; relatórios/estatísticas; exportações.
- **1.7.0** — Integrações com _page builders_; aprimoramentos da API REST; auto-update.

[1.4.0]: https://github.com/Kameloth2004/iw8-wa-click-tracker/releases/tag/v1.4.0
[1.3.0]: https://github.com/Kameloth2004/iw8-wa-click-tracker/releases/tag/v1.3.0
