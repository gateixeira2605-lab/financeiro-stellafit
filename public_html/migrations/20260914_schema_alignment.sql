-- Execute uma única vez em instalações existentes, depois das migrações anteriores.
-- As colunas geradas mantêm os saldos sempre sincronizados com os valores realizados.
ALTER TABLE payables
  ADD COLUMN remaining_amount DECIMAL(10,2)
    GENERATED ALWAYS AS (amount - paid_amount) STORED AFTER paid_amount;

ALTER TABLE receivables
  ADD COLUMN paid_amount DECIMAL(10,2)
    GENERATED ALWAYS AS (received_amount) STORED AFTER received_amount,
  ADD COLUMN remaining_amount DECIMAL(10,2)
    GENERATED ALWAYS AS (expected_amount - received_amount) STORED AFTER paid_amount;
