-- Execute uma única vez em instalações existentes, antes de publicar o novo código.
ALTER TABLE payables
  ADD COLUMN series_id CHAR(32) NULL AFTER recurrence_parent_id,
  ADD COLUMN installment_number SMALLINT UNSIGNED NULL AFTER series_id,
  ADD COLUMN installment_count SMALLINT UNSIGNED NULL AFTER installment_number,
  ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0 AFTER installment_count,
  ADD INDEX idx_payable_series (series_id, installment_number);
