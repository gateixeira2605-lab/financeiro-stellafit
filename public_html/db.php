<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/**
 * Mantém instalações existentes compatíveis com a versão publicada do código.
 *
 * O schema.sql atende instalações novas. Esta rotina existe para ambientes em
 * que o deploy substitui os arquivos da aplicação, mas preserva o banco MySQL.
 */
function ensure_database_schema(): void
{
    $pdo = db();
    $version = '20260914_schema_alignment_v1';

    if (schema_table_exists($pdo, 'schema_migrations') && schema_version_exists($pdo, $version)) {
        ensure_bank_accounts_schema($pdo);
        return;
    }

    $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('financontrol_schema_migration', 15)")->fetchColumn() === 1;
    if (!$lockAcquired) {
        throw new RuntimeException('O banco de dados está sendo atualizado. Aguarde alguns segundos e tente novamente.');
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (schema_version_exists($pdo, $version)) {
            // Outro processo pode ter concluído esta etapa enquanto aguardávamos
            // o lock. Garanta também a migração dependente antes de atender a rota.
            ensure_bank_accounts_schema($pdo);
            return;
        }

        // Cópias de segurança recuperáveis, criadas uma única vez antes dos ALTER TABLE.
        schema_create_backup($pdo, 'payables', 'schema_backup_payables_20260914');
        schema_create_backup($pdo, 'receivables', 'schema_backup_receivables_20260914');

        schema_add_column($pdo, 'payables', 'series_id', 'CHAR(32) NULL');
        schema_add_column($pdo, 'payables', 'installment_number', 'SMALLINT UNSIGNED NULL');
        schema_add_column($pdo, 'payables', 'installment_count', 'SMALLINT UNSIGNED NULL');
        schema_add_column($pdo, 'payables', 'is_recurring', 'TINYINT(1) NOT NULL DEFAULT 0');
        schema_add_column($pdo, 'payables', 'paid_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');

        if (!schema_index_exists($pdo, 'payables', 'idx_payable_series')) {
            $pdo->exec('ALTER TABLE payables ADD INDEX idx_payable_series (series_id, installment_number)');
        }

        $payableStatus = schema_column_type($pdo, 'payables', 'status');
        if (!str_contains($payableStatus, "'parcial'") || !str_contains($payableStatus, "'cancelado'")) {
            $pdo->exec("ALTER TABLE payables MODIFY COLUMN status ENUM('pendente','parcial','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente'");
        }

        $receivableStatus = schema_column_type($pdo, 'receivables', 'status');
        if (!str_contains($receivableStatus, "'parcial'") || !str_contains($receivableStatus, "'cancelado'")) {
            $pdo->exec("ALTER TABLE receivables MODIFY COLUMN status ENUM('pendente','recebido','vencido','parcial','cancelado') NOT NULL DEFAULT 'pendente'");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS financial_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('payable','receivable') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            transaction_date DATE NOT NULL,
            notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_transaction_entity (entity_type,entity_id),
            INDEX idx_transaction_date (transaction_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('payable','receivable') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            action VARCHAR(40) NOT NULL,
            description VARCHAR(255) NOT NULL,
            changes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_entity (entity_type,entity_id),
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Converte baixas antigas em saldo e histórico sem duplicar transações existentes.
        $pdo->exec("UPDATE payables SET paid_amount = amount WHERE status = 'pago' AND paid_amount = 0");
        $pdo->exec("INSERT INTO financial_transactions (entity_type, entity_id, amount, transaction_date, notes)
            SELECT 'payable', p.id, p.paid_amount - COALESCE(t.total, 0), COALESCE(p.payment_date, p.due_date),
                   'Baixa anterior à implantação do histórico'
            FROM payables p
            LEFT JOIN (
                SELECT entity_id, SUM(amount) AS total
                FROM financial_transactions
                WHERE entity_type = 'payable'
                GROUP BY entity_id
            ) t ON t.entity_id = p.id
            WHERE p.paid_amount > COALESCE(t.total, 0)");
        $pdo->exec("INSERT INTO financial_transactions (entity_type, entity_id, amount, transaction_date, notes)
            SELECT 'receivable', r.id, r.received_amount - COALESCE(t.total, 0), COALESCE(r.receipt_date, r.due_date),
                   'Recebimento anterior à implantação do histórico'
            FROM receivables r
            LEFT JOIN (
                SELECT entity_id, SUM(amount) AS total
                FROM financial_transactions
                WHERE entity_type = 'receivable'
                GROUP BY entity_id
            ) t ON t.entity_id = r.id
            WHERE r.received_amount > COALESCE(t.total, 0)");

        schema_add_column(
            $pdo,
            'payables',
            'remaining_amount',
            'DECIMAL(10,2) GENERATED ALWAYS AS (amount - paid_amount) STORED'
        );
        schema_add_column(
            $pdo,
            'receivables',
            'paid_amount',
            'DECIMAL(10,2) GENERATED ALWAYS AS (received_amount) STORED'
        );
        schema_add_column(
            $pdo,
            'receivables',
            'remaining_amount',
            'DECIMAL(10,2) GENERATED ALWAYS AS (expected_amount - received_amount) STORED'
        );

        $statement = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $statement->execute([$version]);
    } catch (Throwable $exception) {
        error_log('Falha na atualização automática do banco: ' . $exception->getMessage());
        throw new RuntimeException(
            'Não foi possível concluir a atualização automática do banco de dados. Consulte o log da aplicação.',
            0,
            $exception
        );
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('financontrol_schema_migration')");
    }

    ensure_bank_accounts_schema($pdo);
}

function ensure_bank_accounts_schema(PDO $pdo): void
{
    $version = '20260917_bank_accounts_v1';
    if (schema_table_exists($pdo, 'schema_migrations') && schema_version_exists($pdo, $version)) return;

    $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('financontrol_bank_accounts_migration', 15)")->fetchColumn() === 1;
    if (!$lockAcquired) throw new RuntimeException('O cadastro de bancos está sendo atualizado. Aguarde alguns segundos e tente novamente.');

    try {
        if (schema_version_exists($pdo, $version)) return;

        schema_create_backup($pdo, 'categories', 'schema_backup_categories_20260917');
        schema_create_backup($pdo, 'financial_transactions', 'schema_backup_financial_transactions_20260917');

        $classification = schema_column_type($pdo, 'categories', 'classification');
        if (!str_contains($classification, "'ajuste_saldo'")) {
            $pdo->exec("ALTER TABLE categories MODIFY COLUMN classification ENUM('despesa_operacional','despesa_administrativa','investimento','receita_operacional','receita_nao_operacional','ajuste_saldo') NOT NULL");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE,
            opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bank_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bank_balance_adjustments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_account_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            adjustment_date DATE NOT NULL,
            notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_adjustment_bank_date (bank_account_id,adjustment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        schema_add_column($pdo, 'financial_transactions', 'bank_account_id', 'INT UNSIGNED NULL');
        schema_add_column($pdo, 'financial_transactions', 'payment_method', 'VARCHAR(30) NULL');
        if (!schema_index_exists($pdo, 'financial_transactions', 'idx_transaction_bank')) {
            $pdo->exec('ALTER TABLE financial_transactions ADD INDEX idx_transaction_bank (bank_account_id)');
        }

        if (!schema_constraint_exists($pdo, 'financial_transactions', 'fk_transaction_bank')) {
            $pdo->exec('ALTER TABLE financial_transactions ADD CONSTRAINT fk_transaction_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL');
        }
        if (!schema_constraint_exists($pdo, 'bank_balance_adjustments', 'fk_adjustment_bank')) {
            $pdo->exec('ALTER TABLE bank_balance_adjustments ADD CONSTRAINT fk_adjustment_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT');
        }
        if (!schema_constraint_exists($pdo, 'bank_balance_adjustments', 'fk_adjustment_category')) {
            $pdo->exec('ALTER TABLE bank_balance_adjustments ADD CONSTRAINT fk_adjustment_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT');
        }

        $pdo->exec("INSERT INTO categories (name,type,classification) VALUES ('Ajuste de saldo','variavel','ajuste_saldo') ON DUPLICATE KEY UPDATE type='variavel',classification='ajuste_saldo'");
        $statement = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $statement->execute([$version]);
    } catch (Throwable $exception) {
        error_log('Falha ao criar o módulo de contas bancárias: ' . $exception->getMessage());
        throw new RuntimeException('Não foi possível atualizar o banco para o módulo de contas bancárias.', 0, $exception);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('financontrol_bank_accounts_migration')");
    }
}

function schema_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);
    return (int) $statement->fetchColumn() > 0;
}

function schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);
    return (int) $statement->fetchColumn() > 0;
}

function schema_column_type(PDO $pdo, string $table, string $column): string
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);
    return strtolower((string) $statement->fetchColumn());
}

function schema_index_exists(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->execute([$table, $index]);
    return (int) $statement->fetchColumn() > 0;
}

function schema_constraint_exists(PDO $pdo, string $table, string $constraint): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $statement->execute([$table, $constraint]);
    return (int) $statement->fetchColumn() > 0;
}

function schema_version_exists(PDO $pdo, string $version): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = ?');
    $statement->execute([$version]);
    return (int) $statement->fetchColumn() > 0;
}

function schema_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!schema_column_exists($pdo, $table, $column)) {
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
    }
}

function schema_create_backup(PDO $pdo, string $source, string $backup): void
{
    if (!schema_table_exists($pdo, $backup)) {
        $pdo->exec(sprintf('CREATE TABLE `%s` AS SELECT * FROM `%s`', $backup, $source));
    }
}
