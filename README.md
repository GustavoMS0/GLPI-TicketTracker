# Ticket Tracker

> [!WARNING]
> **Status: Em Desenvolvimento (Beta / Pré-homologação)**
> Este plugin teve a arquitetura, a segurança e os fluxos validados por revisão de código, auditoria de segurança e testes unitários automatizados em CI (`PHP 7.4–8.3` com mocks de GLPI 10/11/12), mas **ainda não foi testado em uma instância GLPI ao vivo**. Valide em ambiente de homologação antes de usar em produção e reporte eventuais ajustes via Issues.

Plugin para o GLPI que registra quem visualizou cada chamado, quantas vezes acessou e as datas da primeira e da última visualização.

| Versão do GLPI | PHP mínimo | Suporte |
|---|---|---|
| **12.0.x** | 7.4+ | ✅ |
| **11.0.x** | 7.4+ | ✅ |
| **10.0.x** | 7.4+ | ✅ |

---

## Funcionalidades

- **Registro automático por chamado e usuário:** armazena em `glpi_plugin_tickettracker_views` o usuário, o contador de acessos, a primeira visualização (`first_seen`) e a última visualização (`last_seen`).
- **Deduplicação na mesma requisição:** evita contagem dupla quando múltiplos hooks de exibição são disparados no mesmo carregamento da página.
- **Janela de cooldown configurável (0 a 1440 minutos):** acessos repetidos dentro da janela atualizam apenas a última visualização sem inflar o contador; após o término do cooldown, o contador é incrementado.
- **Opção para ignorar o próprio requerente:** permite desconsiderar acessos do criador ou requerente do chamado, mantendo o registro quando o usuário também estiver atribuído como técnico.
- **Aba Visualizações no chamado:** exibe o total de visualizadores únicos no título da aba e uma tabela responsiva ordenada pela visualização mais recente.
- **Controle por interface e permissões:** respeita `canViewItem()`, controle de entidades e permissão de leitura em usuários para exibir links; no autoatendimento (Self-Service), a aba fica oculta por padrão até ser habilitada nas configurações.
- **Limpeza automática:** remove os registros do chamado ao expurgá-lo (`item_purge`) e limpa tabela e configurações na desinstalação.

---

## Instalação

1. Baixe o arquivo **`tickettracker-x.y.z.zip`** na página de [Releases](https://github.com/GustavoMS0/GLPI-TicketTracker/releases/latest).
2. Extraia dentro do diretório de plugins do GLPI:
   ```
   glpi/plugins/tickettracker/setup.php
   ```
3. No GLPI, acesse **Configurar › Plugins**, clique em **Instalar** e depois em **Ativar** em **Ticket Tracker**.

### Pelo terminal (Linux)

```bash
cd /var/www/glpi/plugins
VERSAO=$(curl -fsSL https://api.github.com/repos/GustavoMS0/GLPI-TicketTracker/releases/latest | grep -oP '"tag_name": "v\K[^"]+')
sudo curl -fsSL -o tickettracker.zip "https://github.com/GustavoMS0/GLPI-TicketTracker/releases/download/v$VERSAO/tickettracker-$VERSAO.zip"
sudo unzip -o tickettracker.zip && rm tickettracker.zip
sudo chown -R www-data:www-data tickettracker

sudo -u www-data php ../bin/console plugin:install --username=glpi tickettracker
sudo -u www-data php ../bin/console plugin:activate tickettracker
```

---

## Configuração

Acesse **Configurar › Plugins › Ticket Tracker** (ícone de chave):

| Opção | Padrão | Descrição |
|---|---|---|
| **Janela de cooldown (minutos)** | `5` | Intervalo mínimo (`0` a `1440` minutos) para incrementar o contador de acessos do mesmo usuário no mesmo chamado. Com `0`, todo novo carregamento incrementa. |
| **Ignorar visualizações do próprio requerente** | Desligado | Não registra visualizações do usuário que abriu ou consta como requerente do chamado, salvo se ele também estiver atribuído como técnico. |
| **Exibir aba Visualizações no autoatendimento** | Desligado | Controla se a aba **Visualizações** aparece no perfil simplificado (Self-Service). |

As opções são salvas em `glpi_configs` sob o contexto `plugin:tickettracker` e exigem permissão de atualização em configuração (`config`, `UPDATE`).

---

## Testes

O projeto inclui testes unitários independentes em `tests/`:

```bash
php tests/run.php
```

---

## Licença

Copyright (C) 2026 by G. Martins.

Distribuído sob os termos da licença **GPLv3+** (`GNU General Public License v3 or later`). Consulte [LICENSE](LICENSE).
