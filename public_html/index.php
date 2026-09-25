<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    foreach (['controllers', 'models'] as $dir) {
        $file = __DIR__ . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) { require $file; return; }
    }
});

$route = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
$publicRoutes = ['login'];

try {
    if (!in_array($route, $publicRoutes, true)) {
        require_auth();
        ensure_database_schema();
        sync_overdue_statuses();
    }

    $routes = [
        'login' => [AuthController::class, 'login'],
        'logout' => [AuthController::class, 'logout'],
        'dashboard' => [DashboardController::class, 'index'],
        'categories' => [CategoryController::class, 'index'],
        'categories/form' => [CategoryController::class, 'form'],
        'categories/save' => [CategoryController::class, 'save'],
        'categories/delete' => [CategoryController::class, 'delete'],
        'contacts' => [ContactController::class, 'index'],
        'contacts/export' => [ContactController::class, 'export'],
        'contacts/import' => [ContactController::class, is_post() ? 'import' : 'importForm'],
        'contacts/template' => [ContactController::class, 'template'],
        'contacts/form' => [ContactController::class, 'form'],
        'contacts/save' => [ContactController::class, 'save'],
        'contacts/delete' => [ContactController::class, 'delete'],
        'payables' => [PayableController::class, 'index'],
        'payables/form' => [PayableController::class, 'form'],
        'payables/save' => [PayableController::class, 'save'],
        'payables/pay' => [PayableController::class, 'pay'],
        'payables/bulk-pay' => [PayableController::class, 'bulkPay'],
        'payables/bulk-edit' => [PayableController::class, 'bulkEdit'],
        'payables/bulk-delete' => [PayableController::class, 'bulkDelete'],
        'payables/schedule' => [PayableController::class, 'schedule'],
        'payables/delete' => [PayableController::class, 'delete'],
        'receivables' => [ReceivableController::class, 'index'],
        'receivables/form' => [ReceivableController::class, 'form'],
        'receivables/save' => [ReceivableController::class, 'save'],
        'receivables/receive' => [ReceivableController::class, 'receive'],
        'receivables/bulk-receive' => [ReceivableController::class, 'bulkReceive'],
        'receivables/bulk-edit' => [ReceivableController::class, 'bulkEdit'],
        'receivables/bulk-delete' => [ReceivableController::class, 'bulkDelete'],
        'receivables/delete' => [ReceivableController::class, 'delete'],
        'banks' => [BankAccountController::class, 'index'],
        'banks/save' => [BankAccountController::class, 'save'],
        'banks/adjust' => [BankAccountController::class, 'adjust'],
        'banks/toggle' => [BankAccountController::class, 'toggle'],
        'movement/details' => [MovementController::class, 'details'],
        'reconciliation' => [ReconciliationController::class, 'index'],
        'reports' => [ReportController::class, 'index'],
        'reports/export' => [ReportController::class, 'export'],
        'attachment' => [AttachmentController::class, 'download'],
    ];
    if (!isset($routes[$route])) { http_response_code(404); throw new RuntimeException('Página não encontrada.'); }
    [$class, $method] = $routes[$route];
    (new $class())->$method();
} catch (Throwable $e) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    if (in_array($route, $publicRoutes, true)) exit(e($e->getMessage()));
    $pageTitle = 'Erro';
    require __DIR__ . '/views/layouts/header.php';
    echo '<div class="rounded-xl bg-red-50 p-5 text-red-700">' . e($e->getMessage()) . '</div>';
    require __DIR__ . '/views/layouts/footer.php';
}
