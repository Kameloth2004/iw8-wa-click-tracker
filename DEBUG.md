# 🐛 Debug e Troubleshooting - IW8 WaClickTracker

Este documento explica como diagnosticar e resolver problemas comuns durante a instalação e uso do plugin.

## 📍 **Onde Encontrar Logs de Erro**

### **1. Logs do Plugin (Recomendado)**
- **Localização:** `wp-content/plugins/iw8-wa-click-tracker/logs/plugin.log`
- **Conteúdo:** Logs específicos do plugin com timestamp e contexto
- **Acesso:** Admin → WA Cliques → Diagnóstico → Seção "Logs do Sistema"

### **2. WordPress Debug Log**
- **Localização:** `wp-content/debug.log`
- **Habilitar:** Adicione no `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### **3. Logs do Sistema**
- **XAMPP:** `xampp/apache/logs/error.log`
- **Apache:** `/var/log/apache2/error.log`
- **Nginx:** `/var/log/nginx/error.log`
- **Windows:** Event Viewer → Windows Logs → Application

---

## 🔍 **Problemas Comuns e Soluções**

### **❌ Erro na Ativação do Plugin**

#### **Sintomas:**
- Plugin não ativa
- Mensagem "Plugin could not be activated"
- Erro 500 na ativação

#### **Diagnóstico:**
1. **Verificar logs do plugin:**
   ```
   [2024-01-15 10:30:00] [IW8_WA_CLICK_TRACKER] ERROR: ACTIVATION ERROR: Falha ao criar tabela na ativação
   ```

2. **Verificar WordPress debug log:**
   ```
   [15-Jan-2024 10:30:00] PHP Fatal error: Uncaught Exception: Database connection failed
   ```

#### **Soluções:**
- **Permissões de banco:** Verificar se usuário MySQL tem `CREATE` e `INSERT`
- **Prefixo de tabela:** Verificar se `$wpdb->prefix` está definido
- **Versão PHP:** Confirmar PHP 7.4+ instalado
- **Versão WordPress:** Confirmar WordPress 6.0+ instalado

---

### **❌ Tabela Não Cria**

#### **Sintomas:**
- Plugin ativa mas não rastreia cliques
- Erro "Table doesn't exist" no banco

#### **Diagnóstico:**
1. **Verificar se tabela existe:**
   ```sql
   SHOW TABLES LIKE 'wp_wa_clicks';
   ```

2. **Verificar logs:**
   ```
   [IW8_WA_CLICK_TRACKER] ERROR: DATABASE ERROR: Table creation failed
   ```

#### **Soluções:**
- **Desativar e reativar** o plugin
- **Verificar permissões** do diretório `wp-content/plugins/`
- **Executar manualmente** via Diagnóstico → "Inserir Registro de Teste"

---

### **❌ JavaScript Não Carrega**

#### **Sintomas:**
- Cliques não são rastreados
- Console mostra erro JavaScript
- Arquivo `tracker.js` não carrega

#### **Diagnóstico:**
1. **Verificar se telefone está configurado:**
   - Admin → WA Cliques → Configurações
   - Opção `iw8_wa_phone` deve ter valor válido

2. **Verificar console do navegador:**
   ```
   Uncaught ReferenceError: iw8WaData is not defined
   ```

3. **Verificar se script está enfileirado:**
   - DevTools → Network → Procurar por `tracker.js`

#### **Soluções:**
- **Configurar telefone** válido (10-15 dígitos)
- **Verificar permissões** do diretório `assets/js/`
- **Limpar cache** do navegador e WordPress

---

### **❌ AJAX Não Funciona**

#### **Sintomas:**
- Cliques não são enviados ao servidor
- Erro 403 ou 500 no Network tab
- Mensagem "Ação não autorizada"

#### **Diagnóstico:**
1. **Verificar nonce:**
   ```
   [IW8_WA_CLICK_TRACKER] ERROR: AJAX ERROR: Nonce verification failed
   ```

2. **Verificar permissões AJAX:**
   - Usuário deve ter acesso ao frontend
   - Nonce deve ser válido

#### **Soluções:**
- **Recarregar página** para gerar novo nonce
- **Verificar se usuário está logado** (se aplicável)
- **Verificar configuração** de AJAX no WordPress

---

### **❌ Export CSV Falha**

#### **Sintomas:**
- Botão "Exportar CSV" não funciona
- Erro ao baixar arquivo
- Arquivo vazio ou corrompido

#### **Diagnóstico:**
1. **Verificar logs:**
   ```
   [IW8_WA_CLICK_TRACKER] ERROR: CSV Export Error: Headers already sent
   ```

2. **Verificar permissões:**
   - Usuário deve ter `manage_options`
   - Telefone deve estar configurado

#### **Soluções:**
- **Verificar permissões** do usuário
- **Configurar telefone** válido
- **Verificar se há saída** antes dos headers HTTP

---

## 🛠️ **Ferramentas de Debug**

### **1. Página de Diagnóstico**
- **Localização:** Admin → WA Cliques → Diagnóstico
- **Funcionalidades:**
  - Visualizar logs em tempo real
  - Inserir registro de teste
  - Verificar métricas do sistema
  - Download e limpeza de logs

### **2. Sistema de Logging Automático**
```php
// Logs automáticos em pontos críticos
\IW8\WaClickTracker\Core\Logger::activation('Plugin ativado');
\IW8\WaClickTracker\Core\Logger::database('Tabela criada');
\IW8\WaClickTracker\Core\Logger::error('Erro ocorreu', ['context' => 'info']);
```

### **3. Verificação de Status**
```php
// Verificar se telefone está configurado
if (\IW8\WaClickTracker\Utils\Helpers::isPhoneConfigured()) {
    // Sistema funcionando
}

// Verificar se tabela existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wa_clicks'");
```

---

## 🔧 **Comandos de Debug (Manual)**

### **Verificar Status do Plugin:**
```sql
-- Verificar se tabela existe
SHOW TABLES LIKE 'wp_wa_clicks';

-- Verificar estrutura da tabela
DESCRIBE wp_wa_clicks;

-- Verificar opções do plugin
SELECT * FROM wp_options WHERE option_name LIKE 'iw8_wa_%';

-- Verificar cliques registrados
SELECT COUNT(*) FROM wp_wa_clicks;
```

### **Verificar Logs do Sistema:**
```bash
# WordPress debug log
tail -f wp-content/debug.log

# Log do plugin
tail -f wp-content/plugins/iw8-wa-click-tracker/logs/plugin.log

# Log do servidor (XAMPP)
tail -f xampp/apache/logs/error.log
```

---

## 📋 **Checklist de Troubleshooting**

### **Instalação:**
- [ ] PHP 7.4+ instalado
- [ ] WordPress 6.0+ instalado
- [ ] Permissões de diretório corretas
- [ ] Usuário MySQL com permissões adequadas

### **Configuração:**
- [ ] Telefone configurado (10-15 dígitos)
- [ ] Opções salvas corretamente
- [ ] Tabela criada no banco
- [ ] Hooks WordPress registrados

### **Funcionamento:**
- [ ] JavaScript carrega no frontend
- [ ] AJAX responde corretamente
- [ ] Cliques são registrados
- [ ] Relatórios funcionam
- [ ] Export CSV funciona

---

## 🆘 **Suporte e Contato**

### **Informações para Reportar Problema:**
1. **Versão do plugin:** 1.3.0
2. **Versão do WordPress:** [sua versão]
3. **Versão do PHP:** [sua versão]
4. **Servidor web:** Apache/Nginx/XAMPP
5. **Logs de erro:** [conteúdo dos logs]
6. **Passos para reproduzir:** [descrição detalhada]
7. **Screenshots:** [se aplicável]

### **Arquivos Importantes para Debug:**
- `wp-content/plugins/iw8-wa-click-tracker/logs/plugin.log`
- `wp-content/debug.log`
- `wp-content/plugins/iw8-wa-click-tracker/test-*-temp.php` (se existir)

---

## ✨ **Dicas de Performance**

### **Otimizações Recomendadas:**
1. **Habilitar cache** do WordPress
2. **Usar CDN** para assets estáticos
3. **Otimizar banco de dados** regularmente
4. **Limpar logs antigos** (automático após 7 dias)
5. **Monitorar uso de memória** em sites com muitos cliques

---

**🔍 Para mais informações, consulte a documentação completa do plugin ou entre em contato com o suporte.**
