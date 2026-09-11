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

function next_due_date(string $date, string $recurrence): string
{
    $current = new DateTimeImmutable($date);
    if ($recurrence === 'mensal') {
        $firstNext = $current->modify('first day of next month');
        $day = min((int) $current->format('d'), (int) $firstNext->format('t'));
        return $firstNext->setDate((int) $firstNext->format('Y'), (int) $firstNext->format('m'), $day)->format('Y-m-d');
    }
    return $current->modify($recurrence === 'quinzenal' ? '+15 days' : '+7 days')->format('Y-m-d');
}

function validate_date_range(string $start, string $end): void
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $start > $end) {
        throw new InvalidArgumentException('Período inválido.');
    }
}
