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
    $normalizedPath = ltrim($path, '/');
    $url = rtrim((string) config('base_url', ''), '/') . '/assets/' . $normalizedPath;
    $file = __DIR__ . '/assets/' . $normalizedPath;

    // Evita que um deploy novo continue usando JavaScript ou CSS antigo do cache.
    if (is_file($file)) {
        $version = substr((string) hash_file('sha256', $file), 0, 12);
        $url .= '?v=' . $version;
    }

    return $url;
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

function transaction_record(string $entityType, int $entityId, string $amount, string $date, string $notes = '', ?int $bankAccountId = null, ?string $paymentMethod = null): int
{
    $stmt = db()->prepare('INSERT INTO financial_transactions (entity_type,entity_id,amount,transaction_date,notes,bank_account_id,payment_method,created_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$entityType, $entityId, $amount, $date, $notes !== '' ? $notes : null, $bankAccountId, $paymentMethod, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
    return (int) db()->lastInsertId();
}

/**
 * Opções amigáveis exibidas ao usuário. Cada opção resolve, internamente, a
 * conta contábil e a classificação financeira necessárias aos relatórios.
 */
function accounting_category_purposes(): array
{
    return [
        'revenue_memberships' => ['group'=>'Receitas','label'=>'Mensalidades e assinaturas','classification'=>'receita_operacional','financial_category_id'=>'FIN_REC_OPER','account_id'=>'PC_3_1_01_003','type'=>'fixa'],
        'revenue_services' => ['group'=>'Receitas','label'=>'Serviços, aulas ou atendimentos','classification'=>'receita_operacional','financial_category_id'=>'FIN_REC_OPER','account_id'=>'PC_3_1_01_002','type'=>'variavel'],
        'revenue_products' => ['group'=>'Receitas','label'=>'Venda de produtos','classification'=>'receita_operacional','financial_category_id'=>'FIN_REC_OPER','account_id'=>'PC_3_1_01_001','type'=>'variavel'],
        'revenue_other_operating' => ['group'=>'Receitas','label'=>'Outras receitas do negócio','classification'=>'receita_operacional','financial_category_id'=>'FIN_REC_OPER','account_id'=>'PC_3_1_01_004','type'=>'variavel'],
        'revenue_financial' => ['group'=>'Receitas','label'=>'Rendimentos e receitas financeiras','classification'=>'receita_nao_operacional','financial_category_id'=>'FIN_FIN_RESULT','account_id'=>'PC_3_4_01_001','type'=>'variavel'],
        'revenue_other' => ['group'=>'Receitas','label'=>'Outras receitas eventuais','classification'=>'receita_nao_operacional','financial_category_id'=>'FIN_OUTROS','account_id'=>'PC_3_5_01_001','type'=>'variavel'],

        'expense_inventory' => ['group'=>'Compras e operação','label'=>'Compra de mercadorias ou estoque','classification'=>'despesa_operacional','financial_category_id'=>'FIN_ESTOQUE','account_id'=>'PC_1_1_03_001','type'=>'variavel'],
        'expense_direct_services' => ['group'=>'Compras e operação','label'=>'Prestadores e serviços diretamente ligados à operação','classification'=>'despesa_operacional','financial_category_id'=>'FIN_CUSTOS','account_id'=>'PC_3_2_03_010','type'=>'variavel'],
        'expense_operational_materials' => ['group'=>'Compras e operação','label'=>'Materiais usados nos serviços','classification'=>'despesa_operacional','financial_category_id'=>'FIN_CUSTOS','account_id'=>'PC_3_2_03_003','type'=>'variavel'],
        'expense_card_fees' => ['group'=>'Vendas e divulgação','label'=>'Taxas de cartão e meios de pagamento','classification'=>'despesa_operacional','financial_category_id'=>'FIN_COMERCIAL','account_id'=>'PC_3_3_01_001','type'=>'variavel'],
        'expense_commissions' => ['group'=>'Vendas e divulgação','label'=>'Comissões de vendas','classification'=>'despesa_operacional','financial_category_id'=>'FIN_COMERCIAL','account_id'=>'PC_3_3_01_003','type'=>'variavel'],
        'expense_marketing' => ['group'=>'Vendas e divulgação','label'=>'Marketing, anúncios e publicidade','classification'=>'despesa_operacional','financial_category_id'=>'FIN_COMERCIAL','account_id'=>'PC_3_3_01_004','type'=>'variavel'],

        'expense_payroll' => ['group'=>'Equipe','label'=>'Salários e folha de pagamento','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_PESSOAL','account_id'=>'PC_3_3_03_002','control_account_id'=>'PC_2_1_02_001','type'=>'fixa'],
        'expense_prolabore' => ['group'=>'Equipe','label'=>'Pró-labore','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_PESSOAL','account_id'=>'PC_3_3_03_001','control_account_id'=>'PC_2_1_02_001','type'=>'fixa'],
        'expense_fgts' => ['group'=>'Equipe','label'=>'FGTS','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_PESSOAL','account_id'=>'PC_3_3_03_005','control_account_id'=>'PC_2_1_02_005','type'=>'fixa'],
        'expense_inss' => ['group'=>'Equipe','label'=>'INSS e encargos trabalhistas','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_PESSOAL','account_id'=>'PC_3_3_03_004','control_account_id'=>'PC_2_1_02_006','type'=>'fixa'],
        'expense_benefits' => ['group'=>'Equipe','label'=>'Benefícios da equipe','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_PESSOAL','account_id'=>'PC_3_3_03_011','type'=>'fixa'],

        'expense_rent' => ['group'=>'Estrutura e manutenção','label'=>'Aluguel','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_001','type'=>'fixa'],
        'expense_condo' => ['group'=>'Estrutura e manutenção','label'=>'Condomínio','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_002','type'=>'fixa'],
        'expense_iptu' => ['group'=>'Estrutura e manutenção','label'=>'IPTU','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_003','type'=>'fixa'],
        'expense_water' => ['group'=>'Estrutura e manutenção','label'=>'Água','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_004','type'=>'fixa'],
        'expense_energy' => ['group'=>'Estrutura e manutenção','label'=>'Energia elétrica','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_008','type'=>'fixa'],
        'expense_internet' => ['group'=>'Estrutura e manutenção','label'=>'Internet e telefone','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_ADMIN','account_id'=>'PC_3_3_02_005','type'=>'fixa'],
        'expense_cleaning' => ['group'=>'Estrutura e manutenção','label'=>'Limpeza e conservação','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_005','type'=>'fixa'],
        'expense_maintenance' => ['group'=>'Estrutura e manutenção','label'=>'Manutenção e reparos','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OCUPACAO','account_id'=>'PC_3_3_04_009','type'=>'variavel'],

        'expense_software' => ['group'=>'Administração','label'=>'Sistemas, aplicativos e assinaturas','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_ADMIN','account_id'=>'PC_3_3_02_004','type'=>'fixa'],
        'expense_accounting' => ['group'=>'Administração','label'=>'Contabilidade','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_ADMIN','account_id'=>'PC_3_3_02_001','type'=>'fixa'],
        'expense_legal' => ['group'=>'Administração','label'=>'Serviços jurídicos','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_ADMIN','account_id'=>'PC_3_3_02_002','type'=>'variavel'],
        'expense_office' => ['group'=>'Administração','label'=>'Material de escritório e consumo','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_ADMIN','account_id'=>'PC_3_3_05_001','type'=>'variavel'],
        'expense_travel' => ['group'=>'Administração','label'=>'Viagens e deslocamentos','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_ADMIN','account_id'=>'PC_3_3_05_002','type'=>'variavel'],
        'expense_bank_fees' => ['group'=>'Financeiro','label'=>'Tarifas bancárias','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_FIN_RESULT','account_id'=>'PC_3_4_02_002','type'=>'variavel'],
        'expense_interest' => ['group'=>'Financeiro','label'=>'Juros e multas','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_FIN_RESULT','account_id'=>'PC_3_4_02_001','type'=>'variavel'],

        'expense_das' => ['group'=>'Impostos','label'=>'DAS do Simples Nacional','classification'=>'despesa_operacional','financial_category_id'=>'FIN_TRIBUTO','account_id'=>'PC_3_1_02_001','control_account_id'=>'PC_2_1_03_001','type'=>'fixa'],
        'expense_iss' => ['group'=>'Impostos','label'=>'ISS','classification'=>'despesa_operacional','financial_category_id'=>'FIN_TRIBUTO','account_id'=>'PC_3_1_02_004','control_account_id'=>'PC_2_1_03_004','type'=>'variavel'],
        'expense_pis' => ['group'=>'Impostos','label'=>'PIS sobre faturamento','classification'=>'despesa_operacional','financial_category_id'=>'FIN_TRIBUTO','account_id'=>'PC_3_1_02_002','control_account_id'=>'PC_2_1_03_002','type'=>'variavel'],
        'expense_cofins' => ['group'=>'Impostos','label'=>'COFINS sobre faturamento','classification'=>'despesa_operacional','financial_category_id'=>'FIN_TRIBUTO','account_id'=>'PC_3_1_02_003','control_account_id'=>'PC_2_1_03_003','type'=>'variavel'],
        'expense_icms' => ['group'=>'Impostos','label'=>'ICMS sobre vendas','classification'=>'despesa_operacional','financial_category_id'=>'FIN_TRIBUTO','account_id'=>'PC_3_1_02_005','control_account_id'=>'PC_2_1_03_005','type'=>'variavel'],

        'investment_equipment' => ['group'=>'Investimentos','label'=>'Máquinas e equipamentos','classification'=>'investimento','financial_category_id'=>'FIN_CAPEX','account_id'=>'PC_1_2_03_001','type'=>'variavel'],
        'investment_computers' => ['group'=>'Investimentos','label'=>'Computadores e informática','classification'=>'investimento','financial_category_id'=>'FIN_CAPEX','account_id'=>'PC_1_2_03_004','type'=>'variavel'],
        'investment_furniture' => ['group'=>'Investimentos','label'=>'Móveis e utensílios','classification'=>'investimento','financial_category_id'=>'FIN_CAPEX','account_id'=>'PC_1_2_03_002','type'=>'variavel'],
        'investment_improvements' => ['group'=>'Investimentos','label'=>'Obras, instalações e benfeitorias','classification'=>'investimento','financial_category_id'=>'FIN_CAPEX','account_id'=>'PC_1_2_03_003','type'=>'variavel'],
        'investment_vehicles' => ['group'=>'Investimentos','label'=>'Veículos','classification'=>'investimento','financial_category_id'=>'FIN_CAPEX','account_id'=>'PC_1_2_03_005','type'=>'variavel'],
        'expense_other' => ['group'=>'Outros','label'=>'Outra despesa identificada','classification'=>'despesa_administrativa','financial_category_id'=>'FIN_OUTROS','account_id'=>'PC_3_5_02_001','type'=>'variavel'],
    ];
}

function accounting_category_purpose_for(array $category): ?string
{
    foreach (accounting_category_purposes() as $key => $purpose) {
        if (($category['account_id'] ?? null) === $purpose['account_id']) return $key;
    }
    return null;
}

function accounting_category_options(string $nature): array
{
    $classes=$nature==='receivable'?["receita_operacional","receita_nao_operacional"]:["despesa_operacional","despesa_administrativa","investimento"];
    $placeholders=implode(',',array_fill(0,count($classes),'?'));
    $statement=db()->prepare("SELECT c.id,c.name,f.name financial_category,a.code account_code
        FROM categories c JOIN financial_categories f ON f.category_id=c.financial_category_id
        JOIN chart_accounts a ON a.account_id=c.account_id
        WHERE c.active=1 AND c.accounting_enabled=1 AND a.active=1 AND a.accepts_posting=1
          AND c.classification IN ($placeholders) ORDER BY c.name");
    $statement->execute($classes);
    return $statement->fetchAll();
}

function accounting_posting_accounts(?string $normalSide = null): array
{
    $sql="SELECT account_id,code,name,account_class,normal_side FROM chart_accounts WHERE active=1 AND account_type='ANALITICA' AND accepts_posting=1";
    $params=[];
    if($normalSide!==null){$sql.=' AND normal_side=?';$params[]=$normalSide;}
    $sql.=' ORDER BY code';$statement=db()->prepare($sql);$statement->execute($params);return $statement->fetchAll();
}

function active_bank_accounts(): array
{
    return select_options("SELECT b.id,b.name,b.opening_balance
        + COALESCE((SELECT SUM(CASE WHEN t.entity_type='receivable' THEN t.amount ELSE -t.amount END) FROM financial_transactions t WHERE t.bank_account_id=b.id),0)
        + COALESCE((SELECT SUM(a.amount) FROM bank_balance_adjustments a WHERE a.bank_account_id=b.id),0) balance
        FROM bank_accounts b WHERE b.active=1 ORDER BY b.name");
}

function require_active_bank_account(PDO $pdo, int $bankAccountId): array
{
    if ($bankAccountId <= 0) throw new InvalidArgumentException('Selecione a conta bancária da movimentação.');
    $statement = $pdo->prepare('SELECT id,name FROM bank_accounts WHERE id=? AND active=1 FOR UPDATE');
    $statement->execute([$bankAccountId]);
    $account = $statement->fetch();
    if (!$account) throw new InvalidArgumentException('A conta bancária selecionada não está disponível.');
    return $account;
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

function company_branding(): array
{
    static $branding = null;
    if (is_array($branding)) return $branding;

    $fallbackName = (string) config('company_name', 'Minha Empresa');
    try {
        $row = db()->query('SELECT company_name,logo_mime,logo_updated_at FROM company_settings WHERE id=1')->fetch();
        if (!$row) return $branding = ['company_name' => $fallbackName, 'has_logo' => false, 'logo_url' => ''];
        $hasLogo = !empty($row['logo_mime']);
        $version = $row['logo_updated_at'] ? strtotime((string) $row['logo_updated_at']) : 0;
        return $branding = [
            'company_name' => (string) ($row['company_name'] ?: $fallbackName),
            'has_logo' => $hasLogo,
            'logo_url' => $hasLogo ? url('settings/logo') . '&v=' . $version : '',
        ];
    } catch (PDOException) {
        return $branding = ['company_name' => $fallbackName, 'has_logo' => false, 'logo_url' => ''];
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function selected_ids_from_post(int $limit = 200): array
{
    $values = $_POST['ids'] ?? [];
    if (!is_array($values)) throw new InvalidArgumentException('Seleção inválida.');

    $ids = [];
    foreach ($values as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false) $ids[(int) $id] = (int) $id;
    }
    $ids = array_values($ids);
    if (!$ids) throw new InvalidArgumentException('Selecione pelo menos um lançamento.');
    if (count($ids) > $limit) throw new InvalidArgumentException('Selecione no máximo ' . $limit . ' lançamentos por operação.');
    return $ids;
}

function sql_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function route_query_url(string $route, array $overrides = []): string
{
    $query = $_GET;
    unset($query['route']);
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') unset($query[$key]);
        else $query[$key] = $value;
    }
    return url($route) . ($query ? '&' . http_build_query($query) : '');
}

function pagination_pages(int $current, int $total): array
{
    if ($total <= 1) return [1];
    $pages = array_unique(array_filter([1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $total], fn(int $page): bool => $page >= 1 && $page <= $total));
    sort($pages);
    $result = [];
    $previous = 0;
    foreach ($pages as $page) {
        if ($previous && $page > $previous + 1) $result[] = null;
        $result[] = $page;
        $previous = $page;
    }
    return $result;
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
    if ($status === 'pago') return ['Pago', 'bg-emerald-500 text-white shadow-sm'];
    if ($status === 'recebido') return ['Recebido', 'bg-emerald-500 text-white shadow-sm'];
    if ($status === 'vencido' || ($dueDate !== '' && $dueDate < $today)) return ['Vencido', 'bg-red-500 text-white shadow-sm'];
    return ['Em aberto', 'bg-blue-500 text-white shadow-sm'];
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

function append_account_date_filters(array &$where, array &$params, string $column, string $startKey, string $endKey, bool $timestamp = false): void
{
    $start = trim((string) ($_GET[$startKey] ?? ''));
    $end = trim((string) ($_GET[$endKey] ?? ''));

    if ($start !== '' && !is_valid_iso_date($start)) throw new InvalidArgumentException('Data inicial inválida.');
    if ($end !== '' && !is_valid_iso_date($end)) throw new InvalidArgumentException('Data final inválida.');
    if ($start !== '' && $end !== '' && $start > $end) throw new InvalidArgumentException('A data inicial não pode ser posterior à data final.');

    if ($start !== '') {
        $where[] = $column . ' >= ?';
        $params[] = $timestamp ? $start . ' 00:00:00' : $start;
    }
    if ($end !== '') {
        if ($timestamp) {
            $where[] = $column . ' < ?';
            $params[] = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        } else {
            $where[] = $column . ' <= ?';
            $params[] = $end;
        }
    }
}
