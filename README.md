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

Faça um backup e, antes de publicar esta versão sobre um sistema que já possui banco de dados, importe as migrações que ainda não tiver executado, nesta ordem:

1. `public_html/migrations/20260912_payable_installments.sql`
2. `public_html/migrations/20260913_quick_actions_and_partial_payments.sql`
3. `public_html/migrations/20260914_schema_alignment.sql`

Instalações novas que usam o `schema.sql` já incluem os campos necessários.

Se instalar em uma subpasta, preencha `base_url` no `config.php`, por exemplo `/financeiro`.

## Requisitos

- PHP 8.1 ou superior com extensões PDO MySQL, Fileinfo e Mbstring
- MySQL 8.0 ou MariaDB 10.4+
- Composer 2 (somente para instalar o Dompdf)

## Segurança e operação

- Consultas com prepared statements, tokens CSRF, cookies de sessão HttpOnly/SameSite e validação real do MIME dos anexos.
- O acesso direto a configuração e listagem de diretórios é bloqueado por `.htaccess`.
- Troque a senha inicial após o primeiro acesso. Para isso, gere um hash com `password_hash()` e atualize o usuário no banco; uma tela de administração de usuários pode ser adicionada conforme a política da empresa.
- Faça backup periódico do banco e de `uploads/`.
