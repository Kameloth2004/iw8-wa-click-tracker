# Checklist de Release — v1.4.3

## Pré-flight

- [ ] `Version: 1.4.3` no `iw8-wa-click-tracker.php`
- [ ] `Stable tag: 1.4.3` no `readme.txt`
- [ ] `X-Service-Version: 1.4.3` no `README.md`
- [ ] Sem `register_rest_route` fora de `src/Rest/ApiRegistrar.php`
- [ ] Sem namespaces legados (`IW8\WaClickTracker\Rest`, `IW8\Wa\Rest`)
- [ ] `includes/Rest/*` inexistente ou movido para `deprecated/`

## Smoke tests (local ou site de teste)

- [ ] Ativação do plugin sem notices/fatal
- [ ] `GET /wp-json/iw8-wa/v1/ping` com `X-IW8-Token` → `200` `{ ok: true }`
- [ ] `GET /wp-json/iw8-wa/v1/clicks` com `X-IW8-Token` → `200` com `items` (quando há dados)
- [ ] `GET /clicks` sem token → `401`

## Zip de distribuição

- [ ] ZIP contém **apenas** a pasta `iw8-wa-click-tracker/` na raiz
- [ ] Excluídos: `.git`, `deprecated` (opcional manter fora do zip), `logs/*` (opcional), arquivos `*.bak`, `test-*.php`
- [ ] `vendor/` presente quando necessário (produção)

## Rollout

- [ ] Upload e instalação no `<SITE_TESTE>`
- [ ] Validação de ping/clicks no teste
- [ ] Criar tag **`v1.4.3`** no git
- [ ] Changelog curto:
  - Fix: case-sensitive namespaces (`IW8\WA\Rest`)
  - Fix: registrador único de rotas (`ApiRegistrar`)
  - Cleanup: remoção de skeletons/duplicados; padronização de autoload/bootstrap
  - Docs: adicionados `docs/*`
