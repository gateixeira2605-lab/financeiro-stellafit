<?php
declare(strict_types=1);

final class DashboardController extends BaseController
{
    public function index(): void
    {
        [$start, $end] = period_range();
        validate_date_range($start, $end);
        $pdo = db();

        $summarySql = "SELECT
            COALESCE(SUM(CASE WHEN kind='payable' THEN amount ELSE 0 END),0) payables,
            COALESCE(SUM(CASE WHEN kind='receivable' THEN amount ELSE 0 END),0) receivables
            FROM (
                SELECT 'payable' kind, (amount-paid_amount) amount FROM payables WHERE due_date BETWEEN ? AND ? AND status IN ('pendente','vencido','parcial')
                UNION ALL
                SELECT 'receivable', (expected_amount-received_amount) FROM receivables WHERE due_date BETWEEN ? AND ? AND status IN ('pendente','vencido','parcial')
            ) x";
        $stmt = $pdo->prepare($summarySql);
        $stmt->execute([$start, $end, $start, $end]);
        $totals = $stmt->fetch();

        $cards = [];
        foreach (['payables' => ['payables', '(amount-paid_amount)'], 'receivables' => ['receivables', '(expected_amount-received_amount)']] as $key => [$table, $amount]) {
            $stmt = $pdo->query("SELECT
                COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status IN ('pendente','vencido','parcial') THEN $amount END),0) overdue,
                COALESCE(SUM(CASE WHEN due_date = CURDATE() AND status IN ('pendente','vencido','parcial') THEN $amount END),0) today,
                COALESCE(SUM(CASE WHEN due_date BETWEEN DATE_ADD(CURDATE(),INTERVAL 1 DAY) AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status IN ('pendente','vencido','parcial') THEN $amount END),0) next7,
                COALESCE(SUM(CASE WHEN due_date BETWEEN DATE_ADD(CURDATE(),INTERVAL 1 DAY) AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status IN ('pendente','vencido','parcial') THEN $amount END),0) next30
                FROM $table");
            $cards[$key] = $stmt->fetch();
        }

        $cashStmt = $pdo->query("SELECT day,
            SUM(realized) realized, SUM(projected) projected FROM (
            SELECT transaction_date day, CASE WHEN entity_type='payable' THEN -amount ELSE amount END realized, 0 projected FROM financial_transactions WHERE transaction_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()
            UNION ALL SELECT due_date, 0, -(amount-paid_amount) FROM payables WHERE status IN ('pendente','vencido','parcial') AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            UNION ALL SELECT due_date, 0, (expected_amount-received_amount) FROM receivables WHERE status IN ('pendente','vencido','parcial') AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ) f GROUP BY day ORDER BY day");
        $cashflow = $cashStmt->fetchAll();

        $catStmt = $pdo->prepare("SELECT c.type, COALESCE(SUM(p.amount),0) total FROM categories c LEFT JOIN payables p ON p.category_id=c.id AND p.due_date BETWEEN ? AND ? AND p.status<>'cancelado' GROUP BY c.type");
        $catStmt->execute([$start, $end]);
        $categorySummary = array_column($catStmt->fetchAll(), 'total', 'type');

        $alerts = $pdo->query("SELECT 'Saída' kind, id, description, due_date, (amount-paid_amount) amount FROM payables WHERE status IN ('vencido','pendente','parcial') AND due_date <= CURDATE()
            UNION ALL SELECT 'Entrada', id, description, due_date, (expected_amount-received_amount) FROM receivables WHERE status IN ('vencido','pendente','parcial') AND due_date <= CURDATE()
            ORDER BY due_date LIMIT 8")->fetchAll();

        $this->render('dashboard/index', compact('start', 'end', 'totals', 'cards', 'cashflow', 'categorySummary', 'alerts') + ['pageTitle' => 'Dashboard']);
    }
}
