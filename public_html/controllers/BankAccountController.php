<?php
declare(strict_types=1);

final class BankAccountController extends BaseController
{
    public function index(): void
    {
        $accounts = db()->query("SELECT b.*,
            b.opening_balance
            + COALESCE((SELECT SUM(CASE WHEN t.entity_type='receivable' THEN t.amount ELSE -t.amount END) FROM financial_transactions t WHERE t.bank_account_id=b.id),0)
            + COALESCE((SELECT SUM(a.amount) FROM bank_balance_adjustments a WHERE a.bank_account_id=b.id),0) balance
            FROM bank_accounts b ORDER BY b.active DESC,b.name")->fetchAll();
        $totalBalance = array_sum(array_map(static fn(array $account): float => $account['active'] ? (float) $account['balance'] : 0.0, $accounts));
        $adjustmentCategories = select_options("SELECT id,name FROM categories WHERE classification='ajuste_saldo' ORDER BY name");
        $adjustments = db()->query("SELECT a.*,b.name bank_name,c.name category_name,COALESCE(u.name,'Sistema') user_name
            FROM bank_balance_adjustments a
            JOIN bank_accounts b ON b.id=a.bank_account_id
            JOIN categories c ON c.id=a.category_id
            LEFT JOIN users u ON u.id=a.created_by
            ORDER BY a.adjustment_date DESC,a.id DESC LIMIT 100")->fetchAll();
        $this->render('banks/index', compact('accounts', 'totalBalance', 'adjustmentCategories', 'adjustments') + ['pageTitle' => 'Contas bancárias']);
    }

    public function save(): void
    {
        verify_csrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $openingBalance = decimal_value($_POST['opening_balance'] ?? '0');
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255);
        if ($name === '') throw new InvalidArgumentException('Informe o nome da conta bancária.');

        try {
            if ($id) {
                db()->prepare('UPDATE bank_accounts SET name=?,notes=? WHERE id=?')
                    ->execute([$name, $notes !== '' ? $notes : null, $id]);
                flash('success', 'Conta bancária atualizada.');
            } else {
                db()->prepare('INSERT INTO bank_accounts (name,opening_balance,notes) VALUES (?,?,?)')
                    ->execute([$name, number_format($openingBalance, 2, '.', ''), $notes !== '' ? $notes : null]);
                flash('success', 'Conta bancária cadastrada.');
            }
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') throw new InvalidArgumentException('Já existe uma conta bancária com esse nome.');
            throw $exception;
        }
        redirect('banks');
    }

    public function adjust(): void
    {
        verify_csrf();
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $targetBalance = decimal_value($_POST['target_balance'] ?? '');
        $date = (string) ($_POST['adjustment_date'] ?? date('Y-m-d'));
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255);
        if (!is_valid_iso_date($date)) throw new InvalidArgumentException('Data do ajuste inválida.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $account = require_active_bank_account($pdo, $bankAccountId);
            $category = $pdo->prepare("SELECT id FROM categories WHERE id=? AND classification='ajuste_saldo'");
            $category->execute([$categoryId]);
            if (!$category->fetch()) throw new InvalidArgumentException('Selecione uma categoria de ajuste de saldo.');

            $statement = $pdo->prepare("SELECT b.opening_balance
                + COALESCE((SELECT SUM(CASE WHEN t.entity_type='receivable' THEN t.amount ELSE -t.amount END) FROM financial_transactions t WHERE t.bank_account_id=b.id),0)
                + COALESCE((SELECT SUM(a.amount) FROM bank_balance_adjustments a WHERE a.bank_account_id=b.id),0) balance
                FROM bank_accounts b WHERE b.id=?");
            $statement->execute([$bankAccountId]);
            $currentBalance = (float) $statement->fetchColumn();
            $difference = round($targetBalance - $currentBalance, 2);
            if (abs($difference) < 0.005) throw new InvalidArgumentException('O saldo informado já é o saldo atual da conta.');

            $pdo->prepare('INSERT INTO bank_balance_adjustments (bank_account_id,category_id,amount,adjustment_date,notes,created_by) VALUES (?,?,?,?,?,?)')
                ->execute([$bankAccountId, $categoryId, number_format($difference, 2, '.', ''), $date, $notes !== '' ? $notes : null, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
            $pdo->commit();
            flash('success', 'Saldo de ' . $account['name'] . ' ajustado em ' . money($difference) . '.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        redirect('banks');
    }

    public function toggle(): void
    {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE bank_accounts SET active=IF(active=1,0,1) WHERE id=?')->execute([$id]);
        flash('success', 'Situação da conta bancária atualizada.');
        redirect('banks');
    }
}
