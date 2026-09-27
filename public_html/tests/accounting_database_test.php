<?php
declare(strict_types=1);

$pdo=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',getenv('TEST_DB_HOST')?:'127.0.0.1',getenv('TEST_DB_PORT')?:'3306',getenv('TEST_DB_NAME')?:'financial_test'),getenv('TEST_DB_USER')?:'root',getenv('TEST_DB_PASS')?:'root',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS=>true,
]);
$pdo->exec((string)file_get_contents(dirname(__DIR__).'/schema.sql'));

require dirname(__DIR__).'/db.php';
require dirname(__DIR__).'/accounting_schema.php';
require dirname(__DIR__).'/helpers.php';
require dirname(__DIR__).'/models/AccountingEngine.php';

$assert=static function(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}};

$legacyCategory=(int)$pdo->query("SELECT id FROM categories WHERE name='Aluguel'")->fetchColumn();
$pdo->prepare("INSERT INTO payables (description,category_id,amount,due_date,payment_method,status,recurrence) VALUES ('Título anterior à implantação',?,100,'2026-09-30','pix','pendente','nenhuma')")->execute([$legacyCategory]);
$legacyPayable=(int)$pdo->lastInsertId();

ensure_accounting_schema($pdo);
$assert((int)$pdo->query('SELECT COUNT(*) FROM chart_accounts')->fetchColumn()===211,'migração deve importar 211 contas');
$assert((int)$pdo->query('SELECT COUNT(*) FROM financial_categories')->fetchColumn()===18,'migração deve importar 18 categorias financeiras');
$assert((int)$pdo->query('SELECT COUNT(*) FROM accounting_report_lines')->fetchColumn()===32,'migração deve importar 32 linhas de relatório');
$assert((int)$pdo->query('SELECT COUNT(*) FROM posting_templates')->fetchColumn()===27,'migração deve importar 27 modelos');
$assert((int)$pdo->query('SELECT COUNT(*) FROM legacy_account_mappings')->fetchColumn()===55,'migração deve importar 55 vínculos legados');
$legacyStatus=$pdo->query("SELECT accounting_status FROM payables WHERE id=$legacyPayable")->fetchColumn();
$assert($legacyStatus==='LEGACY_UNPOSTED','título histórico não pode ser contabilizado automaticamente');

$category=(int)$pdo->query("SELECT id FROM categories WHERE name='Mensalidades' AND accounting_enabled=1")->fetchColumn();
$assert($category>0,'categoria operacional deve estar configurada');
$pdo->prepare("INSERT INTO receivables (description,category_id,expected_amount,document_date,competence_date,document_ref,due_date,receipt_method,status,accounting_status) VALUES ('Mensalidade teste',?,100,'2026-09-01','2026-09-01','TESTE-1','2026-09-10','pix','pendente','LEGACY_UNPOSTED')")->execute([$category]);
$receivable=(int)$pdo->lastInsertId();
AccountingEngine::recognizeReceivable($pdo,$receivable);
$item=$pdo->query("SELECT * FROM receivables WHERE id=$receivable")->fetch();
$assert($item['accounting_status']==='POSTED','nova receita deve ser contabilizada');

$pdo->exec("INSERT INTO bank_accounts (name,opening_balance) VALUES ('Banco teste',0)");$bank=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO financial_transactions (entity_type,entity_id,amount,transaction_date,bank_account_id,payment_method) VALUES ('receivable',?,40,'2026-09-10',?,'pix')")->execute([$receivable,$bank]);$transaction=(int)$pdo->lastInsertId();
AccountingEngine::settle($pdo,'receivable',$item,$transaction,'40.00','2026-09-10',$bank);

$unbalanced=(int)$pdo->query("SELECT COUNT(*) FROM (SELECT e.id FROM journal_entries e JOIN journal_lines l ON l.entry_id=e.id GROUP BY e.id HAVING ABS(SUM(CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END))>0.004) x")->fetchColumn();
$assert($unbalanced===0,'todas as partidas devem estar balanceadas');
$result=$pdo->query("SELECT COALESCE(SUM(CASE WHEN a.normal_side='C' THEN CASE WHEN l.side='C' THEN l.amount ELSE -l.amount END ELSE -(CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END) END),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_accounts a ON a.account_id=l.account_id WHERE a.report_type='DRE'")->fetchColumn();
$assert(decimal_cents($result)===10000,'liquidação não pode reconhecer a receita novamente');
$assert((int)$pdo->query("SELECT COUNT(*) FROM posting_templates WHERE enabled=1 OR configured=1")->fetchColumn()===0,'modelos parametrizáveis devem permanecer desativados');

echo "Migração MySQL e partidas dobradas validadas.\n";
