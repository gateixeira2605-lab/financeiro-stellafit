<?php
declare(strict_types=1);

final class AccountingController extends BaseController
{
    public function index(): void
    {
        $q=mb_substr(trim((string)($_GET['q']??'')),0,100);
        $params=[];$where='1=1';if($q!==''){$where='(a.code LIKE ? OR a.name LIKE ?)';$params=['%'.$q.'%','%'.$q.'%'];}
        $stmt=db()->prepare("SELECT a.*,p.code parent_code,r.description report_line FROM chart_accounts a LEFT JOIN chart_accounts p ON p.account_id=a.parent_id LEFT JOIN accounting_report_lines r ON r.line_id=a.report_line_id WHERE $where ORDER BY a.code");$stmt->execute($params);$accounts=$stmt->fetchAll();
        $legacy=db()->query("SELECT l.*,a.code target_code,a.name target_name,f.name financial_name FROM legacy_account_mappings l LEFT JOIN chart_accounts a ON a.account_id=l.target_account_id LEFT JOIN financial_categories f ON f.category_id=l.financial_category_id ORDER BY l.legacy_id")->fetchAll();
        $templates=db()->query("SELECT t.*,d.code debit_code,d.name debit_name,c.code credit_code,c.name credit_name FROM posting_templates t JOIN chart_accounts d ON d.account_id=t.debit_account_id JOIN chart_accounts c ON c.account_id=t.credit_account_id ORDER BY t.event_id,t.phase,t.id")->fetchAll();
        $postingAccounts=accounting_posting_accounts();$financialCategories=db()->query('SELECT category_id,name FROM financial_categories WHERE active=1 ORDER BY name')->fetchAll();
        $stats=[
            'accounts'=>(int)db()->query('SELECT COUNT(*) FROM chart_accounts')->fetchColumn(),
            'analytical'=>(int)db()->query("SELECT COUNT(*) FROM chart_accounts WHERE account_type='ANALITICA'")->fetchColumn(),
            'pending_legacy'=>(int)db()->query("SELECT COUNT(*) FROM legacy_account_mappings WHERE review_status='PENDENTE'")->fetchColumn(),
            'enabled_categories'=>(int)db()->query('SELECT COUNT(*) FROM categories WHERE active=1 AND accounting_enabled=1')->fetchColumn(),
            'entries'=>(int)db()->query('SELECT COUNT(*) FROM journal_entries')->fetchColumn(),
            'unposted_titles'=>(int)db()->query("SELECT (SELECT COUNT(*) FROM payables WHERE accounting_status='LEGACY_UNPOSTED')+(SELECT COUNT(*) FROM receivables WHERE accounting_status='LEGACY_UNPOSTED')")->fetchColumn(),
        ];
        $unbalanced=db()->query("SELECT COUNT(*) FROM (SELECT e.id FROM journal_entries e JOIN journal_lines l ON l.entry_id=e.id GROUP BY e.id HAVING ABS(SUM(CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END))>0.004) x")->fetchColumn();
        $settings=db()->query('SELECT tax_regime,accounting_start_date FROM company_settings WHERE id=1')->fetch();
        $this->render('accounting/index',compact('accounts','legacy','templates','postingAccounts','financialCategories','stats','unbalanced','settings','q')+['pageTitle'=>'Classificação financeira']);
    }

    public function toggleAccount(): void
    {
        verify_csrf();$id=(string)($_POST['account_id']??'');$field=(string)($_POST['field']??'active');
        if(!in_array($field,['active','show_in_entry'],true))throw new InvalidArgumentException('Opção inválida.');
        $stmt=db()->prepare("UPDATE chart_accounts SET $field=IF($field=1,0,1) WHERE account_id=?");$stmt->execute([$id]);
        flash('success','Situação da conta atualizada.');redirect('accounting');
    }

    public function reviewLegacy(): void
    {
        verify_csrf();$mappingId=(int)($_POST['mapping_id']??0);$accountId=trim((string)($_POST['account_id']??''));$financialId=trim((string)($_POST['financial_category_id']??''));
        if($mappingId<=0||$accountId===''||$financialId==='')throw new InvalidArgumentException('Selecione o vínculo legado, a conta e a categoria financeira.');
        $account=db()->prepare("SELECT COUNT(*) FROM chart_accounts WHERE account_id=? AND active=1 AND account_type='ANALITICA' AND accepts_posting=1");$account->execute([$accountId]);if(!(int)$account->fetchColumn())throw new InvalidArgumentException('Selecione uma conta analítica ativa.');
        $stmt=db()->prepare("UPDATE legacy_account_mappings SET target_account_id=?,financial_category_id=?,review_status='APROVADO',reviewed_at=NOW(),reviewed_by=? WHERE id=? AND automatic_import=0");$stmt->execute([$accountId,$financialId,(int)($_SESSION['user_id']??0)?:null,$mappingId]);
        if(!$stmt->rowCount())throw new InvalidArgumentException('O vínculo selecionado não exige revisão manual ou não foi encontrado.');
        flash('success','De-para aprovado. Nenhum lançamento histórico foi importado.');redirect('accounting');
    }
}
