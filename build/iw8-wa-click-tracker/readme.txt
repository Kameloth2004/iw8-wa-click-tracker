=== IW8 – Rastreador de Cliques WhatsApp ===
Contributors: iw8
Tags: whatsapp, tracking, analytics, clicks, reports, statistics
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rastreie cliques em links do WhatsApp e gere relatórios detalhados para analisar o engajamento dos visitantes.

== Description ==

O **IW8 – Rastreador de Cliques WhatsApp** é uma solução completa para monitorar e analisar o comportamento dos usuários em relação aos links do WhatsApp em seu site WordPress.

= Características Principais =

* **Rastreamento Automático**: Detecta e rastreia automaticamente cliques em links do WhatsApp
* **Relatórios Detalhados**: Visualize estatísticas completas de cliques
* **Filtros Avançados**: Analise dados por período, página, dispositivo e mais
* **Exportação CSV**: Exporte relatórios para análise externa
* **Interface Administrativa**: Painel intuitivo para gerenciar configurações
* **Compatibilidade**: Funciona com todos os temas e page builders populares
* **Performance**: Código otimizado que não impacta a velocidade do site

= Como Funciona =

1. **Instalação Simples**: Ative o plugin e configure as opções básicas
2. **Rastreamento Automático**: O plugin detecta automaticamente links do WhatsApp
3. **Coleta de Dados**: Cada clique é registrado com informações detalhadas
4. **Análise**: Visualize relatórios e estatísticas no painel administrativo
5. **Exportação**: Exporte dados para análise externa ou relatórios

= Casos de Uso =

* **E-commerce**: Rastreie cliques em botões "Fale Conosco" do WhatsApp
* **Blogs**: Monitore engajamento em links de compartilhamento
* **Sites Corporativos**: Analise interação com canais de comunicação
* **Landing Pages**: Otimize conversões baseado em dados de cliques

== Installation ==

1. Faça upload do plugin para a pasta `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure o plugin em 'IW8 Tracker' > 'Configurações'
4. Adicione links do WhatsApp ao seu conteúdo

== Frequently Asked Questions ==

= O plugin funciona com todos os temas? =
Sim, o plugin é compatível com todos os temas WordPress padrão e a maioria dos temas premium.

= Posso exportar os dados? =
Sim, o plugin oferece funcionalidade de exportação em formato CSV para análise externa.

= O plugin afeta a performance do site? =
Não, o código foi otimizado para ter impacto mínimo na velocidade do site.

= Posso rastrear cliques em dispositivos móveis? =
Sim, o plugin rastreia cliques em todos os dispositivos e navegadores.

= O plugin é compatível com page builders? =
Sim, o plugin foi desenvolvido para funcionar com Elementor, WPBakery, Beaver Builder e outros.

== Screenshots ==

1. Dashboard principal com estatísticas
2. Página de listagem de cliques
3. Configurações do plugin
4. Relatórios e gráficos

== Changelog ==

= 1.4.5 - 2025-10-30 =
* Envio automático em lotes para o Hub (HubSync::send_batch) com autenticação via X-IW8-Token.
* Cron interno a cada 6 horas via wp_schedule_event.
* Coleta de geolocalização (geo_city e geo_region) salva no *_iw8_wa_click_meta.
* Ajustes de compat REST/headers e logs com finalize_ok.
* Notas de migração e verificação documentadas (feature.php, Hub click_events).

= 1.4.4 - 2025-10-02 =
* Autenticação: aceita iw8_wa_click_token, iw8_click_token e iw8_wa_domain_token (prioridade novo→legados).
* Endpoints: /wp-json/iw8-wa/v1/ping e /wp-json/iw8-wa/v1/clicks validados com header X-IW8-Token.
* Compatibilidade: extração de header case-insensitive.
* Migração segura: comandos WP-CLI para popular opções ausentes.
* Backward-compat garantida (rotas e cabeçalhos preservados).

= 1.4.3 - 2025-09-01 =
* Fix: relatórios exibiam linhas vazias — ajuste no ClickRepository::list().
* Fix: captura de texto visível no tracker.js (aria-label/title/alt/innerText).
* Fix: inserção de cliques restaurada (insertClick) + logs de depuração mais claros.
* Dev: limpeza de código em páginas de admin e SQL preparado de forma segura.

== Upgrade Notice ==

= 1.4.5 =
Ativa envio em lotes para o Hub, cron de 6h e geolocalização. Configure o X-IW8-Token por domínio e valide com feature.php.

= 1.4.4 =
Compatibilidade REST revisada e suporte a múltiplos tokens (iw8_wa_click_token, iw8_click_token, iw8_wa_domain_token).

= 1.3.0 =
Versão inicial do plugin com estrutura base implementada.

== Development ==

Este plugin está em desenvolvimento ativo. Para contribuir ou reportar bugs, visite nosso [repositório no GitHub](https://github.com/Kameloth2004/iw8-wa-click-tracker).

== Support ==

* **GitHub**: [Issues](https://github.com/Kameloth2004/iw8-wa-click-tracker/issues)
* **Documentação**: [Wiki](https://github.com/Kameloth2004/iw8-wa-click-tracker/wiki)
* **Website**: [https://iw8.dev](https://iw8.dev)

== Links ==

* **Hub ingest:** `/public/api/ingest.php` (servidor ClickTracker Hub)
* **Banco Hub:** `iw8apicom_iw8_clicktracker_hub`, tabela `click_events`

Para o changelog detalhado, consulte o arquivo `CHANGELOG.md` incluído no repositório.
