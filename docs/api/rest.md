# API REST — Contrato (v1.4.3)

Base: `/wp-json/iw8-wa/v1`  
Auth requerida em todas as rotas via header: **`X-IW8-Token: <token>`**

## Headers comuns

- `X-IW8-Token: <token>` (obrigatório)
- `X-Service-Version: 1.4.3` (recomendado para troubleshooting)
- `Content-Type: application/json; charset=utf-8` (respostas)

---

## 1) GET `/ping`

**Objetivo:** saúde da API e verificação de autenticação.

### Requisição

- Método: `GET`
- Headers: `X-IW8-Token`

### Respostas

- `200 OK`

```json
{ "ok": true, "service": "iw8-wa-click-tracker", "version": "1.4.3" }
```

- `401 Unauthorized` — token ausente/inválido

```json
{ "ok": false, "error": "unauthorized" }
```

---

## 2) GET `/clicks`

**Objetivo:** retornar cliques registrados.

### Query params

- `since` (string, opcional) — ISO8601 ou `YYYY-MM-DD HH:MM:SS` (timezone do WP)
- `until` (string, opcional) — idem
- `limit` (int, opcional) — default p.ex. 100; teto p.ex. 1000
- `fields` (string, opcional) — lista separada por vírgulas (ex.: `id,url,page_url,clicked_at`)

### Exemplo

`GET /wp-json/iw8-wa/v1/clicks?since=2024-01-01&limit=100&fields=id,url,clicked_at`

### Respostas

- `200 OK`

```json
{
  "ok": true,
  "items": [
    {
      "id": 123,
      "url": "https://api.whatsapp.com/send?phone=...",
      "page_url": "https://exemplo.com/pagina",
      "element_tag": "A",
      "element_text": "WhatsApp",
      "user_agent": "Mozilla/5.0 ...",
      "clicked_at": "2025-09-25 12:34:56"
    }
  ],
  "count": 1
}
```

- `400 Bad Request` — parâmetro malformado

```json
{ "ok": false, "error": "bad_request", "message": "param 'since' inválido" }
```

- `401 Unauthorized` — token ausente/inválido

```json
{ "ok": false, "error": "unauthorized" }
```

- `429 Too Many Requests` — limite excedido (se habilitado)

```json
{ "ok": false, "error": "rate_limited" }
```

- `500 Internal Server Error`

```json
{ "ok": false, "error": "server_error" }
```

### Regras

- Auth **obrigatória** em `/clicks`.
- `fields` retorna apenas colunas válidas; inválidas são ignoradas ou geram `400` (conforme implementação).
- `since/until` são interpretados no fuso horário configurado no WordPress.
- `limit` respeita teto de segurança (ex.: 1000).
