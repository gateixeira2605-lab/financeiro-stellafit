<?php
declare(strict_types=1);

function config(?string $key = null, mixed $default = null): mixed
{
    static $config;
    $config ??= require __DIR__ . '/config.php';
    if ($key === null) return $config;
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function url(string $route = ''): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    return $base . '/index.php' . ($route !== '' ? '?route=' . rawurlencode($route) : '');
}

function asset(string $path): string
{
    return rtrim((string) config('base_url', ''), '/') . '/assets/' . ltrim($path, '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|string|null $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function decimal_value(mixed $value): float
{
    $value = trim((string) $value);
    if (str_contains($value, ',')) $value = str_replace(['.', ','], ['', '.'], $value);
    if (!is_numeric($value)) throw new InvalidArgumentException('Valor monetário inválido.');
    return round((float) $value, 2);
}

function decimal_cents(mixed $value): int
{
    $value = trim((string) $value);
    if (str_contains($value, ',')) $value = str_replace(['.', ','], ['', '.'], $value);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        throw new InvalidArgumentException('Valor monetário inválido. Use no máximo duas casas decimais.');
    }

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = ltrim($whole, '0') ?: '0';
    if (strlen($whole) > 8) throw new InvalidArgumentException('O valor informado excede o limite permitido.');
    $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    return $cents;
}

function cents_decimal(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function installment_amounts(int $informedCents, int $count, bool $isRecurring): array
{
    if ($informedCents <= 0 || $count < 1) throw new InvalidArgumentException('Valor e quantidade de parcelas devem ser maiores que zero.');
    if ($isRecurring) return array_fill(0, $count, $informedCents);
    if ($informedCents < $count) throw new InvalidArgumentException('O valor total deve ser de pelo menos R$ 0,01 por parcela.');

    $base = intdiv($informedCents, $count);
    $remainder = $informedCents % $count;
    $amounts = [];
    for ($index = 0; $index < $count; $index++) {
        $amounts[] = $base + ($index < $remainder ? 1 : 0);
    }
    return $amounts;
}

function select_options(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function br_date(?string $date): string
{
    if (!$date) return '—';
    return (new DateTimeImmutable($date))->format('d/m/Y');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = compact('type', 'message');
}

function audit_log(string $entityType, int $entityId, string $action, string $description, array $changes = []): void
{
    if (!in_array($entityType, ['payable', 'receivable'], true) || $entityId <= 0) return;
    $payload = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $stmt = db()->prepare('INSERT INTO audit_logs (entity_type,entity_id,action,description,changes,created_by) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$entityType, $entityId, $action, $description, $payload, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
}

function transaction_record(string $entityType, int $entityId, string $amount, string $date, string $notes = ''): void
{
    $stmt = db()->prepare('INSERT INTO financial_transactions (entity_type,entity_id,amount,transaction_date,notes,created_by) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$entityType, $entityId, $amount, $date, $notes !== '' ? $notes : null, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
}

function changed_fields(array $fields): array
{
    $changes = [];
    foreach ($fields as $label => $values) {
        [$before, $after] = $values;
        if ((string) $before !== (string) $after) $changes[$label] = ['from' => $before, 'to' => $after];
    }
    return $changes;
}

function redirect(string $route, array $query = []): never
{
    $location = url($route);
    if ($query) $location .= '&' . http_build_query($query);
    header('Location: ' . $location);
    exit;
}

function require_auth(): void
{
    if (empty($_SESSION['user_id'])) redirect('login');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function period_range(): array
{
    $period = $_GET['period'] ?? 'current';
    $today = new DateTimeImmutable('today');
    return match ($period) {
        'previous' => [$today->modify('first day of last month')->format('Y-m-d'), $today->modify('last day of last month')->format('Y-m-d')],
        'week' => [$today->modify('monday this week')->format('Y-m-d'), $today->modify('sunday this week')->format('Y-m-d')],
        'fortnight' => [$today->format('Y-m-d'), $today->modify('+14 days')->format('Y-m-d')],
        'custom' => [$_GET['start'] ?? $today->format('Y-m-01'), $_GET['end'] ?? $today->format('Y-m-t')],
        default => [$today->format('Y-m-01'), $today->format('Y-m-t')],
    };
}

function status_badge(string $status, string $dueDate = ''): array
{
    $today = date('Y-m-d');
    if ($status === 'cancelado') return ['Cancelado', 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300'];
    if (in_array($status, ['pago', 'recebido'], true)) return ['Concluído', 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200'];
    if ($status === 'parcial') return ['Parcial', 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'];
    if ($dueDate < $today) return ['Vencido', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'];
    if ($dueDate === $today) return ['Vence hoje', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'];
    return ['Pendente', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'];
}

function sync_overdue_statuses(): void
{
    db()->exec("UPDATE payables SET status='vencido' WHERE due_date < CURDATE() AND status='pendente'");
    db()->exec("UPDATE receivables SET status='vencido' WHERE due_date < CURDATE() AND status='pendente'");
}

function is_valid_iso_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function installment_due_date(string $date, string $recurrence, int $offset): string
{
    if (!is_valid_iso_date($date) || $offset < 0) throw new InvalidArgumentException('Data de vencimento inválida.');
    $current = new DateTimeImmutable($date);
    if ($offset === 0) return $date;

    if ($recurrence === 'mensal') {
        $monthIndex = ((int) $current->format('Y') * 12) + (int) $current->format('n') - 1 + $offset;
        $year = intdiv($monthIndex, 12);
        $month = ($monthIndex % 12) + 1;
        $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $day = min((int) $current->format('d'), (int) $firstDay->format('t'));
        return $firstDay->setDate($year, $month, $day)->format('Y-m-d');
    }
    if (!in_array($recurrence, ['quinzenal', 'semanal'], true)) throw new InvalidArgumentException('Intervalo de parcelas inválido.');
    $days = $recurrence === 'quinzenal' ? 15 : 7;
    return $current->modify('+' . ($days * $offset) . ' days')->format('Y-m-d');
}

function next_due_date(string $date, string $recurrence): string
{
    return installment_due_date($date, $recurrence, 1);
}

function validate_date_range(string $start, string $end): void
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $start > $end) {
        throw new InvalidArgumentException('Período inválido.');
    }
}
