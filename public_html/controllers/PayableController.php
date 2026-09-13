<?php
declare(strict_types=1);

final class PayableController extends BaseController
{
    private array $methods = ['pix', 'boleto', 'cartao', 'dinheiro', 'debito_automatico', 'transferencia'];
    private array $recurrences = ['nenhuma', 'mensal', 'quinzenal', 'semanal'];

    public function index(): void
    {
        $where = ['1=1'];
        $params = [];
        $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        if ($search !== '') {
            $where[] = '(p.description LIKE ? OR p.notes LIKE ? OR ct.name LIKE ? OR ct.document LIKE ? OR c.name LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term, $term);
        }
        foreach (['status' => 'p.status', 'category' => 'p.category_id', 'contact' => 'p.contact_id', 'payment_method' => 'p.payment_method'] as $input => $column) {
            if (($_GET[$input] ?? '') !== '') {
                $where[] = "$column=?";
                $params[] = $_GET[$input];
            }
        }
        if (($_GET['status'] ?? '') === '') $where[] = "p.status<>'cancelado'";
        if (!empty($_GET['start'])) {
            $where[] = 'p.due_date>=?';
            $params[] = $_GET['start'];
        }
        if (!empty($_GET['end'])) {
            $where[] = 'p.due_date<=?';
            $params[] = $_GET['end'];
        }

        $stmt = db()->prepare("SELECT p.*,c.name category_name,ct.name contact_name,
            (SELECT MIN(a.id) FROM attachments a WHERE a.entity_type='payable' AND a.entity_id=p.id) attachment_id
            FROM payables p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN contacts ct ON ct.id=p.contact_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY p.due_date,p.id');
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $categories = select_options("SELECT id,name FROM categories WHERE classification LIKE 'despesa%' OR classification='investimento' ORDER BY name");
        $contacts = select_options("SELECT id,name FROM contacts WHERE type IN ('fornecedor','ambos') ORDER BY name");
        $this->render('payables/index', compact('items', 'categories', 'contacts', 'search') + ['pageTitle' => 'Contas a pagar']);
    }

    public function form(): void
    {
        $item = [
            'id' => '', 'description' => '', 'contact_id' => '', 'category_id' => '', 'amount' => '',
            'due_date' => date('Y-m-d'), 'payment_method' => 'pix', 'recurrence' => 'mensal', 'notes' => '',
            'series_id' => null, 'installment_number' => null, 'installment_count' => null, 'is_recurring' => 0,
        ];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) {
            $stmt = db()->prepare('SELECT * FROM payables WHERE id=?');
            $stmt->execute([$id]);
            $item = $stmt->fetch() ?: $item;
        }
        $categories = select_options("SELECT id,name FROM categories WHERE classification LIKE 'despesa%' OR classification='investimento' ORDER BY name");
        $contacts = select_options("SELECT id,name FROM contacts WHERE type IN ('fornecedor','ambos') ORDER BY name");
        $this->render('payables/form', compact('item', 'categories', 'contacts') + ['pageTitle' => $item['id'] ? 'Editar conta' : 'Nova conta a pagar']);
    }

    public function save(): void
    {
        verify_csrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $description = trim((string) ($_POST['description'] ?? ''));
        $informedCents = decimal_cents($_POST['amount'] ?? '');
        $due = (string) ($_POST['due_date'] ?? '');
        $method = (string) ($_POST['payment_method'] ?? '');
        $recurrence = (string) ($_POST['recurrence'] ?? 'mensal');
        $contactId = (int) ($_POST['contact_id'] ?? 0) ?: null;
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($description === '' || $informedCents <= 0 || !is_valid_iso_date($due) || !in_array($method, $this->methods, true) || !in_array($recurrence, $this->recurrences, true)) {
            throw new InvalidArgumentException('Preencha os dados da conta corretamente.');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                $currentStmt = $pdo->prepare('SELECT * FROM payables WHERE id=? FOR UPDATE');
                $currentStmt->execute([$id]);
                $current = $currentStmt->fetch();
                if (!$current) throw new RuntimeException('Conta não encontrada.');
                $paidCents = decimal_cents($current['paid_amount']);
                if ($informedCents < $paidCents) throw new InvalidArgumentException('O valor da conta não pode ser menor que o total já pago.');

                $status = $current['status'] === 'cancelado'
                    ? 'cancelado'
                    : ($paidCents === $informedCents ? 'pago' : ($paidCents > 0 ? 'parcial' : ($due < date('Y-m-d') ? 'vencido' : 'pendente')));
                $stmt = $pdo->prepare('UPDATE payables SET description=?,contact_id=?,category_id=?,amount=?,due_date=?,payment_method=?,status=?,recurrence=?,notes=? WHERE id=?');
                $stmt->execute([$description, $contactId, $categoryId, cents_decimal($informedCents), $due, $method, $status, $recurrence, $notes, $id]);
                Attachment::store('payable', (int) $id, $_FILES['attachment'] ?? []);
                audit_log('payable', (int) $id, 'updated', 'Dados da conta a pagar alterados.', changed_fields([
                    'Descrição' => [$current['description'], $description],
                    'Fornecedor' => [$current['contact_id'], $contactId],
                    'Categoria' => [$current['category_id'], $categoryId],
                    'Valor' => [$current['amount'], cents_decimal($informedCents)],
                    'Vencimento' => [$current['due_date'], $due],
                    'Forma' => [$current['payment_method'], $method],
                    'Intervalo' => [$current['recurrence'], $recurrence],
                    'Observações' => [$current['notes'], $notes],
                ]));
                $message = 'Conta a pagar atualizada.';
            } else {
                $installmentCount = filter_var($_POST['installment_count'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 600],
                ]);
                if ($installmentCount === false) throw new InvalidArgumentException('A quantidade de parcelas deve estar entre 1 e 600.');
                if ($installmentCount > 1 && $recurrence === 'nenhuma') throw new InvalidArgumentException('Selecione um intervalo para gerar mais de uma parcela.');

                $isRecurring = ($_POST['is_recurring'] ?? '') === '1';
                $amounts = installment_amounts($informedCents, $installmentCount, $isRecurring);
                $seriesId = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("INSERT INTO payables
                    (description,contact_id,category_id,amount,due_date,payment_method,status,recurrence,notes,series_id,installment_number,installment_count,is_recurring)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $firstId = 0;

                foreach ($amounts as $index => $amountCents) {
                    $installmentDue = installment_due_date($due, $recurrence, $index);
                    $status = $installmentDue < date('Y-m-d') ? 'vencido' : 'pendente';
                    $stmt->execute([
                        $description, $contactId, $categoryId, cents_decimal($amountCents), $installmentDue, $method,
                        $status, $recurrence, $notes, $seriesId, $index + 1, $installmentCount, (int) $isRecurring,
                    ]);
                    $entityId = (int) $pdo->lastInsertId();
                    if ($index === 0) $firstId = $entityId;
                    audit_log('payable', $entityId, 'created', 'Conta a pagar cadastrada.');
                }

                Attachment::store('payable', $firstId, $_FILES['attachment'] ?? []);
                $message = $installmentCount === 1 ? 'Conta a pagar salva.' : $installmentCount . ' parcelas geradas com sucesso.';
            }

            $pdo->commit();
            flash('success', $message);
            redirect('payables');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function pay(): void
    {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $paymentCents = decimal_cents($_POST['payment_amount'] ?? '');
        $date = (string) ($_POST['payment_date'] ?? date('Y-m-d'));
        $notes = trim((string) ($_POST['payment_notes'] ?? ''));
        if (!is_valid_iso_date($date)) throw new InvalidArgumentException('Data de pagamento inválida.');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM payables WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            if (!$item || $item['status'] === 'cancelado') throw new RuntimeException('Conta não encontrada ou cancelada.');

            $totalCents = decimal_cents($item['amount']);
            $paidCents = decimal_cents($item['paid_amount']);
            $remainingCents = $totalCents - $paidCents;
            if ($paymentCents <= 0 || $paymentCents > $remainingCents) throw new InvalidArgumentException('O pagamento deve ser maior que zero e não pode ultrapassar o saldo restante.');

            $newPaidCents = $paidCents + $paymentCents;
            $newRemainingCents = $totalCents - $newPaidCents;
            $status = $newRemainingCents === 0 ? 'pago' : 'parcial';
            $pdo->prepare('UPDATE payables SET paid_amount=?,status=?,payment_date=? WHERE id=?')
                ->execute([cents_decimal($newPaidCents), $status, $date, $id]);
            transaction_record('payable', $id, cents_decimal($paymentCents), $date, $notes);
            audit_log('payable', $id, 'payment', 'Pagamento de ' . money(cents_decimal($paymentCents)) . ' registrado. Saldo restante: ' . money(cents_decimal($newRemainingCents)) . '.');

            // Compatibilidade: recorrências antigas só geram a próxima conta após a quitação integral.
            if ($status === 'pago' && $item['recurrence'] !== 'nenhuma' && empty($item['series_id'])) {
                $next = next_due_date($item['due_date'], $item['recurrence']);
                $sql = "INSERT IGNORE INTO payables
                    (description,contact_id,category_id,amount,due_date,payment_method,status,recurrence,notes,recurrence_parent_id)
                    VALUES (?,?,?,?,?,?,'pendente',?,?,?)";
                $legacyStmt = $pdo->prepare($sql);
                $legacyStmt->execute([
                    $item['description'], $item['contact_id'], $item['category_id'], $item['amount'], $next,
                    $item['payment_method'], $item['recurrence'], $item['notes'], $id,
                ]);
                if ($legacyStmt->rowCount()) audit_log('payable', (int) $pdo->lastInsertId(), 'created', 'Próxima ocorrência recorrente gerada após a quitação.');
            }

            $pdo->commit();
            flash('success', $status === 'pago' ? 'Pagamento concluído.' : 'Pagamento parcial registrado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        redirect('payables');
    }

    public function schedule(): void
    {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE payables SET is_scheduled=1 WHERE id=? AND status NOT IN ('pago','cancelado')");
            $stmt->execute([$id]);
            if ($stmt->rowCount()) audit_log('payable', $id, 'scheduled', 'Pagamento marcado como agendado.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        flash('success', 'Pagamento marcado como agendado.');
        redirect('payables');
    }

    public function delete(): void
    {
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE payables SET status='cancelado',is_scheduled=0 WHERE id=?");
            $stmt->execute([$id]);
            if ($stmt->rowCount()) audit_log('payable', $id, 'cancelled', 'Conta a pagar cancelada.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        flash('success', 'Conta a pagar cancelada.');
        redirect('payables');
    }
}
