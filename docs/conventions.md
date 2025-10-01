### docs/conventions.md

```markdown
# Convenções de Código e Projeto

## Namespaces e case

- Raiz **`IW8\WA\…`** (com `WA` em MAIÚSCULAS).
- Exemplos:
  - `IW8\WA\Rest\ApiRegistrar`
  - `IW8\WA\Rest\ClicksController`
  - `IW8\WA\Repositories\ClickRepository`
- **Proibido**: `IW8\WaClickTracker\Rest` e `IW8\Wa\Rest` (legado).

## PSR-4 e nomes de arquivos

- Um arquivo por classe, nome do arquivo = nome da classe.
- Pastas de `src/` seguem a segmentação do namespace.
- Nada em `src/` deve ter `require` de `includes/` (apenas o bootstrap carrega includes).

## Layout de pastas

- `src/Rest`: controladores e **ApiRegistrar** (único lugar com `register_rest_route`).
- `src/Repositories`: acesso a dados (usam `$wpdb`, não conhecem `Rest`).
- `src/Security`: auth e proteção (token/HTTPS/rate limit).
- `src/Validation`: validação de inputs; sem WP output.
- `includes/`: compat/legado. Evoluções novas vão para `src/`.

## Hooks

- `rest_api_init` só no **bootstrap** → instancia `ApiRegistrar`.
- `admin_init` checa e roda migrations se necessário.
- `init` (20) inicializa núcleo do plugin (se existir).

## Erros e Respostas

- Estrutura JSON:
  - Sucesso: `{ "ok": true, ... }`
  - Erro: `{ "ok": false, "error": "<slug>", "message"?: "<detalhe>" }`
- Não expor stack trace em produção; logar apenas se `iw8_wa_debug = true`.

## Logging

- `error_log` quando `iw8_wa_debug = true`.
- Fallback: `logs/plugin.log` (se gravável).

## Padrões de nomenclatura

- Sufixos: `*Controller`, `*Repository`, `*Provider`, `*Authenticator`, `*Validator`.
- Endpoints REST: substantivos no plural quando coleção (`/clicks`), singular quando item (futuro).

## Deprecações

- Arquivos ou classes aposentadas vão para `deprecated/` com sufixo `.LEGACY.php`.
- Remoção definitiva apenas em major/minor subsequente, após changelog e nota de migração.
```
