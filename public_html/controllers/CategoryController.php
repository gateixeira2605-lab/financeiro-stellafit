<?php
declare(strict_types=1);

final class CategoryController extends BaseController
{
    public function index(): void
    {
        $categories = db()->query('SELECT c.*,f.name financial_category,a.code account_code,a.name account_name,ca.code control_code
            FROM categories c LEFT JOIN financial_categories f ON f.category_id=c.financial_category_id
            LEFT JOIN chart_accounts a ON a.account_id=c.account_id LEFT JOIN chart_accounts ca ON ca.account_id=c.control_account_id
            ORDER BY c.active DESC,c.name')->fetchAll();
        $this->render('categories/index', compact('categories') + ['pageTitle' => 'Categorias']);
    }

    public function form(): void
    {
        $category = ['id' => '', 'name' => '', 'type' => 'fixa', 'classification' => 'despesa_operacional', 'financial_category_id'=>'', 'account_id'=>'', 'control_account_id'=>'', 'accounting_enabled'=>0, 'active'=>1];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) {
            $stmt = db()->prepare('SELECT * FROM categories WHERE id=?'); $stmt->execute([$id]);
            $category = $stmt->fetch() ?: $category;
        }
        $financialCategories=db()->query('SELECT category_id,name FROM financial_categories WHERE active=1 ORDER BY name')->fetchAll();
        $accounts=accounting_posting_accounts();
        $this->render('categories/form', compact('category','financialCategories','accounts') + ['pageTitle' => $category['id'] ? 'Editar categoria' : 'Nova categoria']);
    }

    public function save(): void
    {
        verify_csrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $type = (string) ($_POST['type'] ?? '');
        $classification = (string) ($_POST['classification'] ?? '');
        $financialCategoryId=trim((string)($_POST['financial_category_id']??''))?:null;
        $accountId=trim((string)($_POST['account_id']??''))?:null;
        $controlAccountId=trim((string)($_POST['control_account_id']??''))?:null;
        $accountingEnabled=($_POST['accounting_enabled']??'')==='1';
        $active=($_POST['active']??'')==='1';
        $validClasses = ['despesa_operacional','despesa_administrativa','investimento','receita_operacional','receita_nao_operacional','ajuste_saldo'];
        if ($name === '' || !in_array($type, ['fixa','variavel'], true) || !in_array($classification, $validClasses, true)) throw new InvalidArgumentException('Preencha os dados da categoria corretamente.');
        if($accountingEnabled&&(!$financialCategoryId||!$accountId)) throw new InvalidArgumentException('Para habilitar a categoria, selecione a categoria financeira e a conta contábil.');
        if($accountId){$check=db()->prepare("SELECT normal_side FROM chart_accounts WHERE account_id=? AND active=1 AND account_type='ANALITICA' AND accepts_posting=1");$check->execute([$accountId]);$side=$check->fetchColumn();if(!$side)throw new InvalidArgumentException('Conta contábil inválida.');$expected=str_starts_with($classification,'receita')?'C':'D';if($side!==$expected)throw new InvalidArgumentException('A natureza da conta contábil não corresponde à categoria.');}
        if($controlAccountId){$check=db()->prepare("SELECT COUNT(*) FROM chart_accounts WHERE account_id=? AND active=1 AND account_type='ANALITICA' AND accepts_posting=1");$check->execute([$controlAccountId]);if(!(int)$check->fetchColumn())throw new InvalidArgumentException('Conta de controle inválida.');}
        if ($id) {
            $stmt = db()->prepare('UPDATE categories SET name=?,type=?,classification=?,financial_category_id=?,account_id=?,control_account_id=?,accounting_enabled=?,active=?,migration_status=? WHERE id=?'); $stmt->execute([$name,$type,$classification,$financialCategoryId,$accountId,$controlAccountId,(int)$accountingEnabled,(int)$active,$accountingEnabled?'CONFIGURADA_MANUALMENTE':'PENDENTE_CONFIGURACAO',$id]);
        } else {
            $stmt = db()->prepare('INSERT INTO categories (name,type,classification,financial_category_id,account_id,control_account_id,accounting_enabled,active,migration_status) VALUES (?,?,?,?,?,?,?,?,?)'); $stmt->execute([$name,$type,$classification,$financialCategoryId,$accountId,$controlAccountId,(int)$accountingEnabled,(int)$active,$accountingEnabled?'CONFIGURADA_MANUALMENTE':'PENDENTE_CONFIGURACAO']);
        }
        flash('success', 'Categoria salva.'); redirect('categories');
    }

    public function delete(): void
    {
        verify_csrf();
        $stmt = db()->prepare('DELETE FROM categories WHERE id=?');
        try { $stmt->execute([(int) ($_POST['id'] ?? 0)]); flash('success', 'Categoria excluída.'); }
        catch (PDOException) { flash('error', 'Esta categoria está em uso e não pode ser excluída.'); }
        redirect('categories');
    }
}
