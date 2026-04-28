# 📦 Insumos T.I. — Plugin GLPI 10

![Version](https://img.shields.io/badge/versão-1.0.0-blue)
![GLPI](https://img.shields.io/badge/GLPI-10.0.x-orange)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)
![License](https://img.shields.io/badge/licença-GPL%20v2-green)

**Sistema de controle de insumos de impressão integrado ao GLPI 10 como plugin nativo.**

*Desenvolvido para o setor de T.I. do Hospital da Mulher Nossa Senhora de Nazaré — Belém/PA*

---

## 📋 Índice

- [Visão Geral](#-visão-geral)
- [Funcionalidades](#-funcionalidades)
- [Arquitetura](#-arquitetura)
- [Requisitos](#-requisitos)
- [Instalação Rápida](#-instalação-rápida)
- [Instalação Manual](#-instalação-manual)
- [Configuração](#-configuração)
- [Estrutura de Arquivos](#-estrutura-de-arquivos)
- [Banco de Dados](#-banco-de-dados)
- [API REST](#-api-rest)
- [SSO Como Funciona](#-sso--como-funciona)
- [Segurança](#-segurança)
- [Solução de Problemas](#-solução-de-problemas)
- [Changelog](#-changelog)

---

## 🎯 Visão Geral

O plugin aparece no menu **Assistência** do GLPI. Ao clicar em "Insumos T.I.", o usuário é autenticado automaticamente via SSO sem necessidade de login separado.

O sistema controla etiquetas, cupons, ribbons, pulseiras e outros consumíveis de impressão do setor de T.I., com integração completa à API REST do GLPI para busca de chamados e localização automática.

### Tecnologias

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.0+ |
| Banco de dados | MySQL / MariaDB |
| Frontend | HTML5 + CSS3 + JavaScript Vanilla |
| Gráficos | Chart.js 4.4 |
| Integração | GLPI 10.x Plugin + API REST |

---

## ✨ Funcionalidades

### Dashboard
- Métricas de estoque em tempo real
- Gráfico comparativo estoque atual vs mínimo
- Ranking dos insumos mais consumidos nos últimos 30 dias
- Últimas 5 movimentações

### Estoque
- Posição atual com barra de progresso visual
- Status: Normal, Atenção, Crítico, Zerado

### Painel de Monitoramento
- Nova aba com tema escuro
- Atualização automática a cada 60 segundos
- Ideal para TV ou monitor de plantão

### Entrada de Insumos
- Insumo, quantidade e descrição
- Responsável preenchido automaticamente pelo usuário logado

### Saída de Insumos
- Campo ID do Chamado integrado à API REST do GLPI
- Busca automática de localização do chamado
- Limpeza automática de espaços ao colar o número
- Debounce de 800ms na busca
- Responsável preenchido automaticamente

### Inventário Físico
- Contagem com todos os insumos listados
- Ajuste automático do estoque ao finalizar
- Histórico completo de inventários

### Histórico
- Filtros por tipo, insumo e período
- Paginação com 20 registros por página

### Relatórios
- 4 tipos com exportação CSV

### Usuários
- 3 níveis de perfil com 8 permissões granulares
- Criação automática via SSO do GLPI

### Recursos Adicionais
- Memorização da última aba visitada
- Highlight correto da aba ativa ao retornar
- Tema claro e escuro alternável
- URL dinâmica com IP ou domínio

---

## 🏗️ Arquitetura
---

## 📋 Requisitos

| Componente | Versão |
|-----------|--------|
| Ubuntu / Debian | 20.04+ |
| Apache | 2.4+ |
| PHP | 8.0+ |
| MySQL / MariaDB | 8.0+ / 10.6+ |
| GLPI | 10.0.0+ |

Extensões PHP: pdo_mysql, json, mbstring

---

## 🚀 Instalação Rápida

```bash
git clone https://github.com/Hmsn-pa/InsumosTI.git
cd InsumosTI
cp includes/config.example.php includes/config.php
nano includes/config.php
sudo bash install.sh
```

---

## 🔧 Instalação Manual

### 1. Banco de dados

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS insumos_ti CHARACTER SET utf8mb4;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'insumos'@'localhost' IDENTIFIED BY 'Insumos@2025';"
sudo mysql -e "GRANT ALL PRIVILEGES ON insumos_ti.* TO 'insumos'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql -u insumos -p'Insumos@2025' insumos_ti < banco.sql
```

### 2. Arquivos do plugin

```bash
sudo cp -r . /var/www/html/ti/plugins/insumos/
sudo chown -R www-data:www-data /var/www/html/ti/plugins/insumos/
sudo chmod -R 755 /var/www/html/ti/plugins/insumos/
```

### 3. Configurar credenciais

```bash
sudo cp includes/config.example.php includes/config.php
sudo nano /var/www/html/ti/plugins/insumos/includes/config.php
```

### 4. Instalar no GLPI

```bash
sudo php /var/www/html/ti/bin/console glpi:plugin:install insumos --username=SEU_ADMIN
sudo php /var/www/html/ti/bin/console glpi:plugin:activate insumos
sudo php /var/www/html/ti/bin/console cache:clear
```

### 5. Índices de performance

```bash
sudo mysql -u insumos -p'Insumos@2025' insumos_ti -e "
ALTER TABLE inventario_itens ADD INDEX IF NOT EXISTS idx_inv_id (inventario_id);
ALTER TABLE movimentacoes    ADD INDEX IF NOT EXISTS idx_insumo_id (insumo_id);
ALTER TABLE inventarios      ADD INDEX IF NOT EXISTS idx_status (status);
"
```

---

## ⚙️ Configuração

### config.php

```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'insumos_ti');
define('DB_USER',    'insumos');
define('DB_PASS',    'SUA_SENHA');
date_default_timezone_set('America/Belem');
```

### Chave SSO

Deve ser a mesma em setup.php e sso.php:

```php
define('PLUGIN_INSUMOS_SSO_SECRET', 'SUA_CHAVE_SECRETA');
define('SSO_SECRET', 'SUA_CHAVE_SECRETA');
```

### API REST do GLPI

Em api.php, função actionChamadoInfo:

```php
$glpiUrl   = 'http://SEU_SERVIDOR/ti/apirest.php';
$appToken  = 'SEU_APP_TOKEN';
$userLogin = 'USUARIO_ADMIN';
$userPass  = 'SENHA_ADMIN';
```

---

## 📁 Estrutura de Arquivos
---

## 🗄️ Banco de Dados

| Tabela | Descrição |
|--------|-----------|
| perfis | Níveis de acesso |
| usuarios | Usuários espelho do GLPI |
| permissoes | 8 permissões por usuário |
| sessoes | Sessões ativas (8h) |
| insumos | Cadastro de insumos |
| responsaveis | Responsáveis pelas movs. |
| setores | Setores do hospital |
| movimentacoes | Entradas e saídas |
| inventarios | Cabeçalho dos inventários |
| inventario_itens | Itens contados |

---

## 🔌 API REST

Autenticação: Authorization: Bearer TOKEN

| Action | Método | Descrição |
|--------|--------|-----------|
| me | GET | Usuário logado |
| dashboard | GET | Métricas e gráficos |
| estoque | GET | Posição atual |
| insumos | GET | Lista de insumos |
| entrada | POST | Registrar entrada |
| saida | POST | Registrar saída |
| historico | GET | Histórico paginado |
| relatorio | GET | Relatórios CSV |
| inventario_abrir | POST | Abrir inventário |
| inventario_finalizar | POST | Finalizar inventário |
| chamado_info | GET | Busca chamado GLPI |
| usuarios | GET | Listar usuários |

---

## 🔐 SSO Como Funciona
---

## 🔒 Segurança

- Token Bearer com expiração de 8 horas
- SSO assinado com HMAC-SHA256, TTL 5 minutos
- Senhas com password_hash bcrypt
- Validação de permissões em todas as rotas
- Sanitização de inputs com htmlspecialchars
- Acesso direto redireciona para o GLPI

---

## 🐛 Solução de Problemas

### Plugin não aparece em Assistência

```bash
sudo mysql ti_db -e "DELETE FROM glpi_plugins WHERE directory='insumos';"
sudo php /var/www/html/ti/bin/console glpi:plugin:install insumos --username=ADMIN
sudo php /var/www/html/ti/bin/console glpi:plugin:activate insumos
sudo php /var/www/html/ti/bin/console cache:clear
```

### Tela em branco

```bash
sudo tail -20 /var/log/apache2/error.log
```

### SSO expirando

```php
define('SSO_TTL', 600); // 10 minutos
```

---

## 🔐 Credenciais Padrão

> Altere imediatamente após a instalação!

| Sistema | Usuário | Senha |
|---------|---------|-------|
| Insumos T.I. | superadmin@insumos.ti | Admin@2025 |
| MySQL | insumos | Insumos@2025 |

---

## 📝 Changelog

### v1.0.0 - Abril/2026

- Sistema completo de controle de insumos
- Integração SSO com GLPI 10
- Menu nativo em Assistência
- Dashboard com gráficos Chart.js
- Painel de monitoramento tema escuro
- Integração API REST GLPI para chamados
- Inventário físico com ajuste automático
- Relatórios com exportação CSV
- Responsável automático pelo usuário logado
- Memorização da última aba visitada
- Debounce e limpeza no campo ID do Chamado
- URL dinâmica para IP ou domínio

---

## 👨‍💻 Desenvolvido por

**Setor de T.I.**
Hospital da Mulher Nossa Senhora de Nazaré
Belém — PA, 2026
