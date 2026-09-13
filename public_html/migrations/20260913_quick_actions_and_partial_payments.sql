-- Execute uma única vez em instalações existentes, depois da migração de parcelamento.
ALTER TABLE payables
  ADD COLUMN paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount,
  MODIFY COLUMN status ENUM('pendente','parcial','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente';

ALTER TABLE receivables
  MODIFY COLUMN status ENUM('pendente','recebido','vencido','parcial','cancelado') NOT NULL DEFAULT 'pendente';

CREATE TABLE financial_transactions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Preserva como histórico as baixas que já existiam antes desta atualização.
INSERT INTO financial_transactions (entity_type,entity_id,amount,transaction_date,notes)
SELECT 'payable',id,amount,COALESCE(payment_date,due_date),'Baixa anterior à implantação do histórico'
FROM payables WHERE status='pago' AND amount>0;

UPDATE payables SET paid_amount=amount WHERE status='pago';

INSERT INTO financial_transactions (entity_type,entity_id,amount,transaction_date,notes)
SELECT 'receivable',id,received_amount,COALESCE(receipt_date,due_date),'Recebimento anterior à implantação do histórico'
FROM receivables WHERE received_amount>0;
