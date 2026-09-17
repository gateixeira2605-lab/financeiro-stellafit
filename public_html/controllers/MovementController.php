<?php
declare(strict_types=1);

final class MovementController extends BaseController
{
    public function details(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $entityType = (string) ($_GET['entity'] ?? '');
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || !in_array($entityType, ['payable', 'receivable'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Movimentação inválida.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($entityType === 'payable') {
            $stmt = db()->prepare("SELECT p.*,c.name category_name,ct.name contact_name
                FROM payables p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN contacts ct ON ct.id=p.contact_id
                WHERE p.id=?");
        } else {
            $stmt = db()->prepare("SELECT r.*,c.name category_name,ct.name contact_name
                FROM receivables r LEFT JOIN categories c ON c.id=r.category_id LEFT JOIN contacts ct ON ct.id=r.contact_id
                WHERE r.id=?");
        }
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Movimentação não encontrada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $transactions = db()->prepare("SELECT t.amount,t.transaction_date,t.payment_method,t.notes,t.created_at,
            COALESCE(b.name,'Não informado') bank_name,COALESCE(u.name,'Sistema') user_name
            FROM financial_transactions t
            LEFT JOIN bank_accounts b ON b.id=t.bank_account_id
            LEFT JOIN users u ON u.id=t.created_by
            WHERE t.entity_type=? AND t.entity_id=? ORDER BY t.transaction_date DESC,t.id DESC");
        $transactions->execute([$entityType, $id]);

        $logs = db()->prepare("SELECT l.action,l.description,l.changes,l.created_at,COALESCE(u.name,'Sistema') user_name
            FROM audit_logs l LEFT JOIN users u ON u.id=l.created_by
            WHERE l.entity_type=? AND l.entity_id=? ORDER BY l.id DESC");
        $logs->execute([$entityType, $id]);

        echo json_encode([
            'entity_type' => $entityType,
            'item' => $item,
            'transactions' => $transactions->fetchAll(),
            'logs' => $logs->fetchAll(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
