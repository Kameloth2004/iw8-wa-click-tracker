# IW8 – Rastreador de Cliques WhatsApp

Plugin WordPress para rastrear cliques em links do WhatsApp e gerar relatórios detalhados.

## Descrição

O **IW8 – Rastreador de Cliques WhatsApp** é uma solução completa para monitorar e analisar o comportamento dos usuários em relação aos links do WhatsApp em seu site WordPress. Com recursos avançados de rastreamento, o plugin oferece insights valiosos sobre o engajamento dos visitantes.

## Características

- **Rastreamento Automático**: Detecta e rastreia automaticamente cliques em links do WhatsApp
- **Relatórios Detalhados**: Visualize estatísticas completas de cliques
- **Filtros Avançados**: Analise dados por período, página, dispositivo e mais
- **Exportação CSV**: Exporte relatórios para análise externa
- **Interface Administrativa**: Painel intuitivo para gerenciar configurações
- **Compatibilidade**: Funciona com todos os temas e page builders populares
- **Performance**: Código otimizado que não impacta a velocidade do site

## Requisitos

- **PHP**: 7.4 ou superior
- **WordPress**: 6.0 ou superior
- **MySQL**: 5.6 ou superior

## Instalação

1. Faça upload do plugin para a pasta `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure o plugin em 'IW8 Tracker' > 'Configurações'
4. Adicione links do WhatsApp ao seu conteúdo

## Uso

### Configuração Básica

1. Acesse **IW8 Tracker** > **Configurações**
2. Configure os padrões de URL do WhatsApp
3. Defina as páginas onde o rastreamento deve ser ativo
4. Salve as configurações

### Visualizando Relatórios

1. Acesse **IW8 Tracker** > **Cliques**
2. Use os filtros para analisar dados específicos
3. Exporte relatórios em CSV quando necessário

### Diagnósticos

1. Acesse **IW8 Tracker** > **Diagnósticos**
2. Verifique o status do plugin
3. Execute testes de funcionalidade

## Estrutura do Plugin

```
iw8-wa-click-tracker/
├── iw8-wa-click-tracker.php          # Arquivo principal
├── includes/                          # Classes PHP
│   ├── Core/                          # Funcionalidades principais
│   ├── Database/                      # Operações de banco
│   ├── Admin/                         # Interface administrativa
│   ├── Frontend/                      # Funcionalidades do frontend
│   ├── Ajax/                          # Handlers AJAX
│   └── Utils/                         # Funções utilitárias
├── assets/                            # CSS e JavaScript
└── languages/                         # Arquivos de tradução
```

## Desenvolvimento

### Namespaces

O plugin utiliza namespaces PSR-4:
- `IW8\WaClickTracker\Core` - Funcionalidades principais
- `IW8\WaClickTracker\Database` - Operações de banco
- `IW8\WaClickTracker\Admin` - Interface administrativa
- `IW8\WaClickTracker\Frontend` - Funcionalidades do frontend

### Hooks e Filtros

O plugin registra diversos hooks para extensibilidade:
- `iw8_wa_click_tracker_before_save_click`
- `iw8_wa_click_tracker_after_save_click`
- `iw8_wa_click_tracker_click_data`

## Changelog

### 1.3.0
- Estrutura inicial do plugin
- Classes base implementadas
- Sistema de autoload configurado

## Suporte

- **GitHub**: [Issues](https://github.com/iw8/iw8-wa-click-tracker/issues)
- **Documentação**: [Wiki](https://github.com/iw8/iw8-wa-click-tracker/wiki)

## Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

## Autores

- **IW8** - [Website](https://iw8.dev)

---

**Nota**: Este plugin está em desenvolvimento ativo. Funcionalidades serão implementadas nas próximas versões.
