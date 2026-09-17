SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  type ENUM('fixa','variavel') NOT NULL,
  classification ENUM('despesa_operacional','despesa_administrativa','investimento','receita_operacional','receita_nao_operacional','ajuste_saldo') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category_type (type), INDEX idx_category_class (classification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  document VARCHAR(20) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(160) NULL,
  type ENUM('fornecedor','cliente','ambos') NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_name (name), INDEX idx_contact_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payables (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(200) NOT NULL,
  contact_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  remaining_amount DECIMAL(10,2) GENERATED ALWAYS AS (amount - paid_amount) STORED,
  due_date DATE NOT NULL,
  payment_date DATE NULL,
  payment_method ENUM('pix','boleto','cartao','dinheiro','debito_automatico','transferencia') NOT NULL,
  status ENUM('pendente','parcial','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente',
  recurrence ENUM('nenhuma','mensal','quinzenal','semanal') NOT NULL DEFAULT 'nenhuma',
  is_scheduled TINYINT(1) NOT NULL DEFAULT 0,
  recurrence_parent_id INT UNSIGNED NULL,
  series_id CHAR(32) NULL,
  installment_number SMALLINT UNSIGNED NULL,
  installment_count SMALLINT UNSIGNED NULL,
  is_recurring TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payable_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payable_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payable_parent FOREIGN KEY (recurrence_parent_id) REFERENCES payables(id) ON DELETE SET NULL,
  UNIQUE KEY uq_payable_recurrence_parent (recurrence_parent_id),
  INDEX idx_payable_series (series_id,installment_number),
  INDEX idx_payable_due (due_date), INDEX idx_payable_status (status), INDEX idx_payable_due_status (due_date,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receivables (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(200) NOT NULL,
  contact_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  expected_amount DECIMAL(10,2) NOT NULL,
  received_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paid_amount DECIMAL(10,2) GENERATED ALWAYS AS (received_amount) STORED,
  remaining_amount DECIMAL(10,2) GENERATED ALWAYS AS (expected_amount - received_amount) STORED,
  due_date DATE NOT NULL,
  receipt_date DATE NULL,
  receipt_method ENUM('pix','cartao_credito','cartao_debito','boleto','dinheiro','transferencia') NOT NULL,
  status ENUM('pendente','recebido','vencido','parcial','cancelado') NOT NULL DEFAULT 'pendente',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_receivable_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_receivable_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_receivable_due (due_date), INDEX idx_receivable_status (status), INDEX idx_receivable_due_status (due_date,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('payable','receivable') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL UNIQUE,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attachment_entity (entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE financial_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('payable','receivable') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  transaction_date DATE NOT NULL,
  bank_account_id INT UNSIGNED NULL,
  payment_method VARCHAR(30) NULL,
  notes VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_transaction_entity (entity_type,entity_id),
  INDEX idx_transaction_bank (bank_account_id),
  INDEX idx_transaction_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bank_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bank_balance_adjustments (
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
  ADD CONSTRAINT fk_transaction_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL;

CREATE TABLE audit_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schema_migrations (
  version VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('20260914_schema_alignment_v1'),('20260917_bank_accounts_v1');

INSERT INTO users (name,username,password) VALUES ('Administrador','admin','{SHA256}240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9');

INSERT INTO categories (name,type,classification) VALUES
('Aluguel','fixa','despesa_operacional'),('Energia','variavel','despesa_operacional'),('Água','variavel','despesa_operacional'),
('Internet','fixa','despesa_operacional'),('Folha de Pagamento','fixa','despesa_administrativa'),('Material de Limpeza','variavel','despesa_operacional'),
('Manutenção','variavel','despesa_operacional'),('Equipamentos','variavel','investimento'),('Marketing','variavel','despesa_administrativa'),
('Mensalidades','fixa','receita_operacional'),('Day Use','variavel','receita_operacional'),('Personal Trainer','variavel','receita_operacional'),
('Outros','variavel','despesa_operacional'),('Ajuste de saldo','variavel','ajuste_saldo');
