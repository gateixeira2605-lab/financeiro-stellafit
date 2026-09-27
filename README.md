# FinanControl — Gestão Financeira Empresarial

Sistema em PHP puro + MySQL para contas a pagar, contas a receber, conciliação, dashboard e relatórios. O conteúdo pronto para publicação está em `public_html/`.

## Instalação na Hostinger

1. Crie um banco MySQL no hPanel e importe `public_html/schema.sql` pelo phpMyAdmin.
2. Edite `public_html/config.php` com host, nome, usuário e senha do banco. Ajuste também nome, documento e endereço da empresa (eles aparecem nos PDFs).
3. Para habilitar PDF, execute `composer install --no-dev --optimize-autoloader` dentro de `public_html`. Se o plano não tiver terminal, rode o Composer localmente e envie também a pasta `vendor/`.
4. Envie **o conteúdo** da pasta `public_html/` para a `public_html` da hospedagem.
5. Garanta permissão de escrita na pasta `uploads/` (normalmente `755`; use `775` se necessário no servidor).
6. Acesse o domínio e entre com `admin` / `admin123`. No primeiro login, a senha inicial é automaticamente convertida para `password_hash` do PHP.

### Atualização de uma instalação existente

Ao abrir uma rota autenticada depois do deploy, o sistema verifica o banco e aplica automaticamente apenas as alterações pendentes. Antes dos ajustes, ele preserva cópias em `schema_backup_payables_20260914` e `schema_backup_receivables_20260914` e registra a versão em `schema_migrations`.

As migrações manuais continuam disponíveis para manutenção, nesta ordem:

1. `public_html/migrations/20260912_payable_installments.sql`
2. `public_html/migrations/20260913_quick_actions_and_partial_payments.sql`
3. `public_html/migrations/20260914_schema_alignment.sql`

Instalações novas que usam o `schema.sql` já incluem os campos necessários.

Se instalar em uma subpasta, preencha `base_url` no `config.php`, por exemplo `/financeiro`.

## Importação e exportação de contatos

Na tela de Contatos, é possível exportar os registros para CSV e importar contatos de outro sistema. A importação reconhece arquivos separados por ponto e vírgula, vírgula ou tabulação, valida cada linha e permite ignorar ou atualizar registros repetidos por CPF/CNPJ ou e-mail.

## Personalização visual

Na opção **Personalização** do menu lateral, o nome e o logotipo da empresa podem ser alterados pelo próprio sistema. A identidade é armazenada no banco de dados para permanecer disponível após novos deploys. O logotipo aceita JPG, PNG ou WEBP de até 2 MB.

## Plano de contas e escrituração

O sistema inclui o plano mestre `2026.09-v1`, com 211 contas hierárquicas, categorias financeiras separadas, de-para das 55 contas legadas, linhas de DRE/BP e modelos de partidas. A migração automática cria o razão (`journal_entries` e `journal_lines`) e preserva títulos anteriores como `LEGACY_UNPOSTED`: nenhum saldo ou lançamento histórico é convertido sem conciliação.

Novas receitas e despesas configuradas são reconhecidas pelo regime de competência. Pagamentos e recebimentos geram somente a liquidação contra bancos, sem duplicar receita ou despesa. Na área **Plano de contas** é possível consultar a hierarquia, revisar o de-para legado e executar verificações de consistência. Em **Categorias**, cada opção amigável da interface é vinculada a uma conta analítica e a uma categoria financeira/DFC.

Após o primeiro deploy, configure em **Personalização** o regime tributário, a data inicial da escrituração e as contas de controle de clientes, fornecedores e bancos. Os 27 modelos avançados permanecem desativados até parametrização e revisão contábil. O plano é uma base operacional e deve ser homologado pelo contador responsável antes de demonstrações formais.

## Requisitos

- PHP 8.1 ou superior com extensões PDO MySQL, Fileinfo e Mbstring
- MySQL 8.0 ou MariaDB 10.4+
- Composer 2 (somente para instalar o Dompdf)

## Segurança e operação

- Consultas com prepared statements, tokens CSRF, cookies de sessão HttpOnly/SameSite e validação real do MIME dos anexos.
- O acesso direto a configuração e listagem de diretórios é bloqueado por `.htaccess`.
- Troque a senha inicial após o primeiro acesso. Para isso, gere um hash com `password_hash()` e atualize o usuário no banco; uma tela de administração de usuários pode ser adicionada conforme a política da empresa.
- Faça backup periódico do banco e de `uploads/`.
