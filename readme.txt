=== IW8 – Rastreador de Cliques WhatsApp ===
Contributors: iw8
Tags: whatsapp, tracking, analytics, clicks, reports, statistics, geo, automation, hub
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rastreie cliques em links do WhatsApp, colete geolocalização e envie dados automaticamente para o Hub central IW8.

== Description ==

O **IW8 – Rastreador de Cliques WhatsApp** é uma solução completa para monitorar e analisar o comportamento dos usuários em relação aos links do WhatsApp em seu site WordPress.

Agora, com **geolocalização automática** e integração nativa com o **Hub Central IW8**, seus dados de cliques são enviados periodicamente para um banco de dados central para análise unificada.

= Características Principais =

* **Rastreamento Automático**: Detecta e registra cliques em links do WhatsApp
* **Geolocalização**: Identifica automaticamente a **cidade** e **região** do visitante
* **Envio Automático (Hub)**: Sincroniza cliques com o banco de dados central IW8 a cada 6h
* **Relatórios Detalhados**: Estatísticas de cliques por página, data, dispositivo e localização
* **Filtros Avançados**: Analise períodos personalizados ou intervalos de datas
* **Exportação CSV**: Baixe relatórios para análise externa
* **Interface Administrativa Completa**:
  - **Relatórios de Cliques**
  - **Configurações Gerais**
  - **Diagnóstico**
  - **Hub (Envio Automático)** — novo painel dedicado ao envio de lotes
* **Compatibilidade Total**: Funciona com todos os temas e page builders
* **Performance**: Código leve e otimizado

= Como Funciona =

1. **Instalação Simples**: Ative o plugin e configure as opções básicas.
2. **Rastreamento Automático**: Detecta links de WhatsApp no site.
3. **Coleta de Dados**: Cada clique é salvo com informações detalhadas, incluindo geolocalização.
4. **Envio Automático**: A cada 6 horas, o plugin envia os dados ao **Hub Central IW8**.
5. **Análise Centralizada**: Consulte relatórios e dashboards no Hub.

= Casos de Uso =

* **E-commerce**: Rastreie cliques em botões “Fale Conosco”.
* **Landing Pages**: Otimize conversões com base na origem dos contatos.
* **Empresas Multidomínio**: Consolide relatórios de vários sites no Hub central IW8.

== Installation ==

1. Faça upload do plugin para a pasta `/wp-content/plugins/`.
2. Ative o plugin através do menu 'Plugins' no WordPress.
3. Configure o plugin em **IW8 Tracker > Configurações**.
4. Em seguida, acesse **IW8 Tracker > Hub (Envio Automático)** para verificar status e logs.

== Frequently Asked Questions ==

= O plugin coleta localização automaticamente? =  
Sim. Ao registrar o clique, o plugin obtém cidade e região aproximadas com base no IP do visitante.

= Como funciona o envio automático para o Hub? =  
A cada 6 horas, o plugin envia um lote de cliques para o servidor central IW8 (`/public/api/ingest.php`), usando autenticação via `X-IW8-Token`.

= E se o envio falhar? =  
Os cliques permanecem armazenados localmente até o próximo ciclo de envio.

= É possível desativar o envio automático? =  
Sim. Basta desativar o cron no painel “Hub (Envio Automático)” ou via WP-CLI.

= O plugin impacta a performance? =  
Não. O envio ocorre de forma assíncrona via WP-Cron, sem afetar o carregamento das páginas.

== Screenshots ==

1. Dashboard principal com estatísticas de cliques  
2. Página de relatórios e filtros por período  
3. Tela de **Hub (Envio Automático)** com logs e status  
4. Configurações gerais do plugin  

== Changelog ==

= 1.4.5 - 2025-10-30 =
### Added
* **Geolocalização**: novos campos `geo_city` e `geo_region` adicionados à tabela de metadados dos cliques (`*_iw8_wa_click_meta`).
* **HubSync**: implementação de envio automático em lotes via `HubSync::send_batch()` para o endpoint `/public/api/ingest.php`.
* **Painel Hub (Envio Automático)**: página administrativa dedicada para exibir status, logs e forçar envio manual.
* **Cron Automático**: agendamento WP-Cron a cada 6h para sincronização de dados.
* **Logs Detalhados**: inclusão de logs com status `finalize_ok` para auditoria dos envios.

### Changed
* Atualização da interface administrativa, adicionando a aba **Hub** no menu IW8 Tracker.
* Melhorias na coleta e persistência de dados no `ClickController`.
* Compatibilidade ampliada com WP 6.6.

### Fixed
* Correção na coleta de cidade e região (geo).
* Correção de duplicidade em envios manuais e automáticos.

### Security
* Autenticação via header `X-IW8-Token` em todas as requisições ao Hub central.
* Controle idempotente baseado em `event_uuid`/hash de evento.

### Migration Notes
1. Instale/atualize o plugin para **v1.4.5**.  
2. Verifique que o cron de 6h foi criado (Painel → Hub → Status).  
3. Confirme que os cliques aparecem no Hub (tabela `click_events`).  
4. Tokens devem ser configurados no painel de Configurações (um por domínio).

= 1.4.4 - 2025-10-02 =
* Autenticação aprimorada para múltiplos tokens (`iw8_wa_click_token`, `iw8_click_token`, `iw8_wa_domain_token`).
* Endpoints `/ping` e `/clicks` revisados.
* Extração de headers case-insensitive e migração segura via WP-CLI.

= 1.3.0 =
* Estrutura inicial do plugin.
* Classes base implementadas.
* Sistema de autoload configurado.

== Upgrade Notice ==

= 1.4.5 =
Atualização obrigatória. Inclui geolocalização dos cliques e integração com o Hub Central IW8.

== Development ==

Este plugin está em desenvolvimento ativo.  
Contribua ou reporte bugs no [GitHub](https://github.com/iw8/iw8-wa-click-tracker).

== Support ==

* **GitHub**: [Issues](https://github.com/iw8/iw8-wa-click-tracker/issues)  
* **Documentação**: [Wiki](https://github.com/iw8/iw8-wa-click-tracker/wiki)  
* **Website**: [https://iw8.dev](https://iw8.dev)
