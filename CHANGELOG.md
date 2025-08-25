# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [1.3.0] - 2024-01-01

### Adicionado
- Estrutura inicial do plugin
- Sistema de autoload PSR-4
- Classes base para todas as funcionalidades
- Arquivos de assets (CSS/JS) básicos
- Preparação para sistema de traduções
- Estrutura para atualizações via GitHub

### Estrutura Criada
- **Core**: Plugin principal, Assets, Versions, Updater, Security, Logger, Hooks
- **Database**: TableClicks, Migrations, ClickRepository
- **Admin**: Menu e páginas (Clicks, Diagnostics, Settings)
- **Frontend**: Tracker e UrlMatcher
- **Ajax**: ClickController
- **Export**: CsvExporter
- **Rest**: Api
- **Compat**: Builders
- **Utils**: Helpers

### Arquivos de Suporte
- README.md com documentação completa
- CHANGELOG.md para histórico de versões
- Arquivos de tradução (.pot)
- Assets JavaScript e CSS básicos
- Arquivos de configuração Git

### Notas Técnicas
- Compatível com PHP 7.4+ e WordPress 6.x
- Namespaces organizados seguindo PSR-4
- Preparado para internacionalização
- Estrutura modular para fácil manutenção
- Sem funcionalidades implementadas ainda (apenas stubs)

---

## [Próximas Versões]

### 1.4.0 (Planejado)
- Implementação do sistema de banco de dados
- Criação das tabelas necessárias
- Sistema de migrações

### 1.5.0 (Planejado)
- Funcionalidades de rastreamento no frontend
- Captura de cliques via JavaScript
- Sistema AJAX para envio de dados

### 1.6.0 (Planejado)
- Interface administrativa completa
- Relatórios e estatísticas
- Sistema de exportação

### 1.7.0 (Planejado)
- API REST
- Integrações com page builders
- Sistema de atualizações automáticas

---

**Nota**: Este changelog será atualizado conforme o desenvolvimento do plugin avança.
