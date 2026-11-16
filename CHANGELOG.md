# Changelog

## [1.0.0] - 2026-11-16

### Adicionado
- Versão inicial do plugin **Ticket Tracker** com compatibilidade única para GLPI 10.0.0 a 12.0.99 e PHP 7.4+.
- Tabela dedicada `glpi_plugin_tickettracker_views` com chave única por chamado e usuário (`uniq_ticket_user`) e índice de ordenação por data (`idx_tickets_last`).
- Registro automático de visualizações via hooks `post_show_item` e `post_item_form` com deduplicação por requisição HTTP.
- Janela de cooldown configurável (`0` a `1440` minutos, padrão `5`) para evitar incremento repetido dentro do intervalo.
- Opção para ignorar visualizações do próprio requerente/criador do chamado (mantendo contagem quando também atribuído como técnico).
- Aba **Visualizações** nos chamados exibindo contagem de visualizadores únicos, usuário, total de acessos, primeira e última visualização, com opção de visibilidade no autoatendimento.
- Página de configuração em `Configurar › Plugins › Ticket Tracker` protegida por permissões do GLPI, CSRF nativo e validação de mesma origem.
- Limpeza automática de registros ao expurgar chamados (`item_purge`) e remoção de esquema e configurações na desinstalação.
- Suíte de testes unitários automatizados em `tests/run.php` cobrindo deduplicação, cooldown, filtro de requerente, autoatendimento, expurgo e tipagem de `$rightname`.
