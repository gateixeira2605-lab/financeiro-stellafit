<?php
declare(strict_types=1);

final class ReceivableController extends BaseController
{
    private array $methods = ['pix', 'cartao_credito', 'cartao_debito', 'boleto', 'dinheiro', 'transferencia'];

    public function index(): void
    {
        $where = ['1=1'];
        $params = [];
        foreach (['status' => 'r.status', 'category' => 'r.category_id', 'contact' => 'r.contact_id', 'payment_method' => 'r.receipt_method'] as $input => $column) {
            if (($_GET[$input] ?? '') !== '') {
                $where[] = "$column=?";
                $params[] = $_GET[$input];
            }
        }
        if (($_GET['status'] ?? '') === '') $where[] = "r.status<>'cancelado'";
        if (!empty($_GET['start'])) {
            $where[] = 'r.due_date>=?';
            $params[] = $_GET['start'];
        }
        if (!empty($_GET['end'])) {
            $where[] = 'r.due_date<=?';
            $params[] = $_GET['end'];
        }

        $stmt = db()->prepare("SELECT r.*,c.name category_name,ct.name contact_name
            FROM receivables r LEFT JOIN categories c ON c.id=r.category_id LEFT JOIN contacts ct ON ct.id=r.contact_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY r.due_date,r.id');
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $categories = select_options("SELECT id,name FROM categories WHERE classification LIKE 'receita%' ORDER BY name");
        $contacts = select_options("SELECT id,name FROM contacts WHERE type IN ('cliente','ambos') ORDER BY name");
        $this->render('receivables/index', compact('items', 'categories', 'contacts') + ['pageTitle' => 'Contas a receber']);
    }

    public function form(): void
    {
        $item = ['id' => '', 'description' => '', 'contact_id' => '', 'category_id' => '', 'expected_amount' => '', 'due_date' => date('Y-m-d'), 'receipt_method' => 'pix', 'notes' => ''];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) {
            $stmt = db()->prepare('SELECT * FROM receivables WHERE id=?');
            $stmt->execute([$id]);
            $item = $stmt->fetch() ?: $item;
        }
        $categories = select_options("SELECT id,name FROM categories WHERE classification LIKE 'receita%' ORDER BY name");
        $contacts = select_options("SELECT id,name FROM contacts WHERE type IN ('cliente','ambos') ORDER BY name");
        $this->render('receivables/form', compact('item', 'categories', 'contacts') + ['pageTitle' => $item['id'] ? 'Editar conta' : 'Nova conta a receber']);
    }

    public function save(): void
    {
        verify_csrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $description = trim((string) ($_POST['description'] ?? ''));
        $amountCents = decimal_cents($_POST['expected_amount'] ?? '');
        $due = (string) ($_POST['due_date'] ?? '');
        $method = (string) ($_POST['receipt_method'] ?? '');
        $contactId = (int) ($_POST['contact_id'] ?? 0) ?: null;
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($description === '' || $amountCents <= 0 || !is_valid_iso_date($due) || !in_array($method, $this->methods, true)) {
            throw new InvalidArgumentException('Preencha os dados da conta corretamente.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare('SELECT * FROM receivables WHERE id=? FOR UPDATE');
                $stmt->execute([$id]);
                $current = $stmt->fetch();
                if (!$current) throw new RuntimeException('Conta não encontrada.');
                $receivedCents = decimal_cents($current['received_amount']);
                if ($amountCents < $receivedCents) throw new InvalidArgumentException('O valor esperado não pode ser menor que o total já recebido.');
                $status = $current['status'] === 'cancelado'
                    ? 'cancelado'
                    : ($amountCents === $receivedCents ? 'recebido' : ($receivedCents > 0 ? 'parcial' : ($due < date('Y-m-d') ? 'vencido' : 'pendente')));
                $pdo->prepare('UPDATE receivables SET description=?,contact_id=?,category_id=?,expected_amount=?,due_date=?,receipt_method=?,status=?,notes=? WHERE id=?')
                    ->execute([$description, $contactId, $categoryId, cents_decimal($amountCents), $due, $method, $status, $notes, $id]);
                audit_log('receivable', (int) $id, 'updated', 'Dados da conta a receber alterados.', changed_fields([
                    'Descrição' => [$current['description'], $description],
                    'Cliente' => [$current['contact_id'], $contactId],
                    'Categoria' => [$current['category_id'], $categoryId],
                    'Valor esperado' => [$current['expected_amount'], cents_decimal($amountCents)],
                    'Vencimento' => [$current['due_date'], $due],
                    'Forma' => [$current['receipt_method'], $method],
                    'Observações' => [$current['notes'], $notes],
                ]));
                $message = 'Conta a receber atualizada.';
            } else {
                $status = $due < date('Y-m-d') ? 'vencido' : 'pendente';
                $stmt = $pdo->prepare('INSERT INTO receivables (description,contact_id,category_id,expected_amount,due_date,receipt_method,status,notes) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$description, $contactId, $categoryId, cents_decimal($amountCents), $due, $method, $status, $notes]);
                $id = (int) $pdo->lastInsertId();
                audit_log('receivable', $id, 'created', 'Conta a receber cadastrada.');
                $message = 'Conta a receber salva.';
            }
            $pdo->commit();
            flash('success', $message);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('receivables');
    }

    public function receive(): void
    {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $receivedCents = decimal_cents($_POST['received_amount'] ?? '');
        $date = (string) ($_POST['receipt_date'] ?? date('Y-m-d'));
        $notes = trim((string) ($_POST['receipt_notes'] ?? ''));
        if (!is_valid_iso_date($date)) throw new InvalidArgumentException('Data de recebimento inválida.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM receivables WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if (!$item || $item['status'] === 'cancelado') throw new RuntimeException('Conta não encontrada ou cancelada.');
            $expectedCents = decimal_cents($item['expected_amount']);
            $previousCents = decimal_cents($item['received_amount']);
            $remainingCents = $expectedCents - $previousCents;
            if ($receivedCents <= 0 || $receivedCents > $remainingCents) throw new InvalidArgumentException('O recebimento deve ser maior que zero e não pode ultrapassar o saldo restante.');

            $newTotalCents = $previousCents + $receivedCents;
            $newRemainingCents = $expectedCents - $newTotalCents;
            $status = $newRemainingCents === 0 ? 'recebido' : 'parcial';
            $pdo->prepare('UPDATE receivables SET received_amount=?,receipt_date=?,status=? WHERE id=?')
                ->execute([cents_decimal($newTotalCents), $date, $status, $id]);
            transaction_record('receivable', $id, cents_decimal($receivedCents), $date, $notes);
            audit_log('receivable', $id, 'receipt', 'Recebimento de ' . money(cents_decimal($receivedCents)) . ' registrado. Saldo restante: ' . money(cents_decimal($newRemainingCents)) . '.');
            $pdo->commit();
            flash('success', $status === 'recebido' ? 'Recebimento concluído.' : 'Recebimento parcial registrado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('receivables');
    }

    public function delete(): void
    {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE receivables SET status='cancelado' WHERE id=?");
            $stmt->execute([$id]);
            if ($stmt->rowCount()) audit_log('receivable', $id, 'cancelled', 'Conta a receber cancelada.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        flash('success', 'Conta a receber cancelada.');
        redirect('receivables');
    }
}
