<?php
declare(strict_types=1);

final class ReceivableController extends BaseController
{
    private array $methods = ['pix', 'cartao_credito', 'cartao_debito', 'boleto', 'dinheiro', 'transferencia'];

    public function index(): void
    {
        $where = ['1=1'];
        $params = [];
        $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        if ($search !== '') {
            $where[] = '(r.description LIKE ? OR r.notes LIKE ? OR ct.name LIKE ? OR ct.document LIKE ? OR c.name LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term, $term);
        }
        foreach (['category' => 'r.category_id', 'contact' => 'r.contact_id', 'payment_method' => 'r.receipt_method'] as $input => $column) {
            if (($_GET[$input] ?? '') !== '') {
                $where[] = "$column=?";
                $params[] = $_GET[$input];
            }
        }
        $status = (string) ($_GET['status'] ?? '');
        if (in_array($status, ['pendente', 'vencido', 'parcial', 'recebido', 'cancelado'], true)) {
            $where[] = 'r.status=?';
            $params[] = $status;
        } else {
            $view = (string) ($_GET['view'] ?? 'all');
            if ($view === 'open') $where[] = "r.status NOT IN ('recebido','cancelado')";
            elseif ($view === 'settled') $where[] = "r.status='recebido'";
            elseif ($view === 'overdue') $where[] = "r.status NOT IN ('recebido','cancelado') AND r.due_date<CURDATE()";
            else $where[] = "r.status<>'cancelado'";
        }
        append_account_date_filters($where, $params, 'r.created_at', 'issue_start', 'issue_end', true);
        append_account_date_filters($where, $params, 'r.receipt_date', 'settlement_start', 'settlement_end');
        append_account_date_filters($where, $params, 'r.due_date', 'due_start', 'due_end');

        $perPageOptions = [10, 25, 50, 100];
        $perPage = (int) ($_GET['per_page'] ?? 10);
        if (!in_array($perPage, $perPageOptions, true)) $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $whereSql = implode(' AND ', $where);

        $summaryStmt = db()->prepare("SELECT COUNT(*) total_records,
            COALESCE(SUM(r.expected_amount),0) total_amount,
            COALESCE(SUM(r.received_amount),0) total_paid,
            COALESCE(SUM(CASE WHEN r.status NOT IN ('recebido','cancelado') THEN r.remaining_amount ELSE 0 END),0) total_open
            FROM receivables r LEFT JOIN categories c ON c.id=r.category_id LEFT JOIN contacts ct ON ct.id=r.contact_id
            WHERE $whereSql");
        $summaryStmt->execute($params);
        $summary = $summaryStmt->fetch();
        $totalRecords = (int) $summary['total_records'];
        $totalPages = max(1, (int) ceil($totalRecords / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = db()->prepare("SELECT r.*,c.name category_name,ct.name contact_name
            FROM receivables r LEFT JOIN categories c ON c.id=r.category_id LEFT JOIN contacts ct ON ct.id=r.contact_id
            WHERE $whereSql ORDER BY r.due_date,r.id LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'from' => $totalRecords ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $totalRecords),
        ];
        $totals = [
            'total_amount' => $summary['total_amount'],
            'paid_label' => 'Total recebido',
            'paid_amount' => $summary['total_paid'],
            'open_amount' => $summary['total_open'],
        ];
        $categories = select_options("SELECT id,name FROM categories WHERE classification LIKE 'receita%' ORDER BY name");
        $contacts = select_options("SELECT id,name FROM contacts WHERE type IN ('cliente','ambos') ORDER BY name");
        $bankAccounts = active_bank_accounts();
        $this->render('receivables/index', compact('items', 'categories', 'contacts', 'bankAccounts', 'search', 'pagination', 'totals') + ['pageTitle' => 'Contas a receber']);
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
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $method = (string) ($_POST['receipt_method'] ?? '');
        if (!is_valid_iso_date($date)) throw new InvalidArgumentException('Data de recebimento inválida.');
        if (!in_array($method, $this->methods, true)) throw new InvalidArgumentException('Forma de recebimento inválida.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $bankAccount = require_active_bank_account($pdo, $bankAccountId);
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
            $pdo->prepare('UPDATE receivables SET received_amount=?,receipt_date=?,status=?,receipt_method=? WHERE id=?')
                ->execute([cents_decimal($newTotalCents), $date, $status, $method, $id]);
            transaction_record('receivable', $id, cents_decimal($receivedCents), $date, $notes, $bankAccountId, $method);
            audit_log('receivable', $id, 'receipt', 'Recebimento de ' . money(cents_decimal($receivedCents)) . ' registrado em ' . $bankAccount['name'] . '. Saldo restante: ' . money(cents_decimal($newRemainingCents)) . '.');
            $pdo->commit();
            flash('success', $status === 'recebido' ? 'Recebimento concluído.' : 'Recebimento parcial registrado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('receivables');
    }

    public function bulkReceive(): void
    {
        verify_csrf();
        $ids = selected_ids_from_post();
        $date = (string) ($_POST['receipt_date'] ?? date('Y-m-d'));
        $notes = mb_substr(trim((string) ($_POST['receipt_notes'] ?? '')), 0, 255);
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $method = (string) ($_POST['receipt_method'] ?? '');
        if (!is_valid_iso_date($date)) throw new InvalidArgumentException('Data de recebimento inválida.');
        if (!in_array($method, $this->methods, true)) throw new InvalidArgumentException('Forma de recebimento inválida.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $bankAccount = require_active_bank_account($pdo, $bankAccountId);
            $statement = $pdo->prepare('SELECT * FROM receivables WHERE id IN (' . sql_placeholders($ids) . ') FOR UPDATE');
            $statement->execute($ids);
            $update = $pdo->prepare("UPDATE receivables SET received_amount=expected_amount,status='recebido',receipt_date=?,receipt_method=? WHERE id=?");
            $processed = 0;
            foreach ($statement->fetchAll() as $item) {
                if (in_array($item['status'], ['recebido', 'cancelado'], true)) continue;
                $remainingCents = decimal_cents($item['expected_amount']) - decimal_cents($item['received_amount']);
                if ($remainingCents <= 0) continue;
                $id = (int) $item['id'];
                $update->execute([$date, $method, $id]);
                transaction_record('receivable', $id, cents_decimal($remainingCents), $date, $notes, $bankAccountId, $method);
                audit_log('receivable', $id, 'receipt', 'Quitação em massa de ' . money(cents_decimal($remainingCents)) . ' registrada em ' . $bankAccount['name'] . '.');
                $processed++;
            }
            $pdo->commit();
            flash($processed ? 'success' : 'error', $processed ? $processed . ' receita(s) recebida(s).' : 'Nenhuma receita selecionada estava disponível para baixa.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('receivables');
    }

    public function bulkEdit(): void
    {
        verify_csrf();
        $ids = selected_ids_from_post();
        $requested = [
            'category_id' => (string) ($_POST['category_id'] ?? '__keep__'),
            'contact_id' => (string) ($_POST['contact_id'] ?? '__keep__'),
            'due_date' => trim((string) ($_POST['due_date'] ?? '')),
            'receipt_method' => (string) ($_POST['receipt_method'] ?? '__keep__'),
        ];
        if ($requested['due_date'] !== '' && !is_valid_iso_date($requested['due_date'])) throw new InvalidArgumentException('Data de vencimento inválida.');
        if ($requested['receipt_method'] !== '__keep__' && !in_array($requested['receipt_method'], $this->methods, true)) throw new InvalidArgumentException('Forma de recebimento inválida.');
        foreach (['category_id', 'contact_id'] as $field) {
            if ($requested[$field] !== '__keep__' && $requested[$field] !== '0' && filter_var($requested[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new InvalidArgumentException('Opção de edição inválida.');
            }
        }
        if ($requested['category_id'] === '__keep__' && $requested['contact_id'] === '__keep__' && $requested['due_date'] === '' && $requested['receipt_method'] === '__keep__') {
            throw new InvalidArgumentException('Escolha pelo menos um campo para alterar.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT * FROM receivables WHERE id IN (' . sql_placeholders($ids) . ') FOR UPDATE');
            $statement->execute($ids);
            $processed = 0;
            foreach ($statement->fetchAll() as $item) {
                if ($item['status'] === 'cancelado') continue;
                $sets = [];
                $params = [];
                $changes = [];
                foreach (['category_id' => 'Categoria', 'contact_id' => 'Cliente'] as $field => $label) {
                    if ($requested[$field] === '__keep__') continue;
                    $value = $requested[$field] === '0' ? null : (int) $requested[$field];
                    $sets[] = $field . '=?';
                    $params[] = $value;
                    $changes[$label] = [$item[$field], $value];
                }
                if ($requested['due_date'] !== '') {
                    $sets[] = 'due_date=?';
                    $params[] = $requested['due_date'];
                    $changes['Vencimento'] = [$item['due_date'], $requested['due_date']];
                    if (in_array($item['status'], ['pendente', 'vencido'], true)) {
                        $sets[] = 'status=?';
                        $params[] = $requested['due_date'] < date('Y-m-d') ? 'vencido' : 'pendente';
                    }
                }
                if ($requested['receipt_method'] !== '__keep__') {
                    $sets[] = 'receipt_method=?';
                    $params[] = $requested['receipt_method'];
                    $changes['Forma'] = [$item['receipt_method'], $requested['receipt_method']];
                }
                $changes = changed_fields($changes);
                if (!$changes) continue;
                $params[] = (int) $item['id'];
                $pdo->prepare('UPDATE receivables SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
                audit_log('receivable', (int) $item['id'], 'bulk_updated', 'Conta alterada em edição em massa.', $changes);
                $processed++;
            }
            $pdo->commit();
            flash($processed ? 'success' : 'error', $processed ? $processed . ' receita(s) atualizada(s).' : 'Nenhuma receita precisou ser alterada.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('receivables');
    }

    public function bulkDelete(): void
    {
        verify_csrf();
        $ids = selected_ids_from_post();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT id,status FROM receivables WHERE id IN (' . sql_placeholders($ids) . ') FOR UPDATE');
            $statement->execute($ids);
            $update = $pdo->prepare("UPDATE receivables SET status='cancelado' WHERE id=?");
            $processed = 0;
            foreach ($statement->fetchAll() as $item) {
                if ($item['status'] === 'cancelado') continue;
                $update->execute([(int) $item['id']]);
                audit_log('receivable', (int) $item['id'], 'cancelled', 'Conta a receber cancelada em massa.');
                $processed++;
            }
            $pdo->commit();
            flash($processed ? 'success' : 'error', $processed ? $processed . ' receita(s) cancelada(s).' : 'Nenhuma receita selecionada estava disponível para cancelamento.');
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
