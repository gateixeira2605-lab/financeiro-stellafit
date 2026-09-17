ALTER TABLE categories
  MODIFY COLUMN classification ENUM('despesa_operacional','despesa_administrativa','investimento','receita_operacional','receita_nao_operacional','ajuste_saldo') NOT NULL;

CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bank_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_balance_adjustments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bank_account_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  adjustment_date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_adjustment_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_adjustment_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_adjustment_bank_date (bank_account_id,adjustment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE financial_transactions
  ADD COLUMN bank_account_id INT UNSIGNED NULL,
  ADD COLUMN payment_method VARCHAR(30) NULL,
  ADD INDEX idx_transaction_bank (bank_account_id),
  ADD CONSTRAINT fk_transaction_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL;

INSERT INTO categories (name,type,classification) VALUES ('Ajuste de saldo','variavel','ajuste_saldo')
  ON DUPLICATE KEY UPDATE type='variavel',classification='ajuste_saldo';
INSERT IGNORE INTO schema_migrations (version) VALUES ('20260917_bank_accounts_v1');
