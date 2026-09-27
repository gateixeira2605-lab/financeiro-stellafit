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
        $purposes=accounting_category_purposes();
        foreach($categories as &$category){
            $key=accounting_category_purpose_for($category);
            $category['purpose_label']=$key!==null?$purposes[$key]['label']:'Classificação personalizada';
            $category['purpose_group']=$key!==null?$purposes[$key]['group']:'Personalizada';
        }
        unset($category);
        $this->render('categories/index', compact('categories') + ['pageTitle' => 'Categorias']);
    }

    public function form(): void
    {
        $category = ['id' => '', 'name' => '', 'type' => 'variavel', 'classification' => 'despesa_operacional', 'financial_category_id'=>'', 'account_id'=>'', 'control_account_id'=>'', 'accounting_enabled'=>1, 'active'=>1];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) {
            $stmt = db()->prepare('SELECT * FROM categories WHERE id=?'); $stmt->execute([$id]);
            $category = $stmt->fetch() ?: $category;
        }
        $purposes=accounting_category_purposes();
        $selectedPurpose=accounting_category_purpose_for($category);
        $this->render('categories/form', compact('category','purposes','selectedPurpose') + ['pageTitle' => $category['id'] ? 'Editar categoria' : 'Nova categoria']);
    }

    public function save(): void
    {
        verify_csrf();
        $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
        $name=mb_substr(trim((string)($_POST['name']??'')),0,120);
        $purposeKey=(string)($_POST['purpose']??'');
        $active=($_POST['active']??'')==='1';
        if($name==='')throw new InvalidArgumentException('Informe um nome para a categoria.');

        $current=null;
        if($id){
            $currentStmt=db()->prepare('SELECT * FROM categories WHERE id=?');
            $currentStmt->execute([$id]);
            $current=$currentStmt->fetch();
            if(!$current)throw new RuntimeException('Categoria não encontrada.');
        }

        $purposes=accounting_category_purposes();
        if($purposeKey==='keep_current'&&$current){
            $type=$current['type'];
            $classification=$current['classification'];
            $financialCategoryId=$current['financial_category_id'];
            $accountId=$current['account_id'];
            $controlAccountId=$current['control_account_id'];
        }else{
            if(!isset($purposes[$purposeKey]))throw new InvalidArgumentException('Escolha o que esta categoria representa.');
            $purpose=$purposes[$purposeKey];
            $type=$purpose['type'];
            $classification=$purpose['classification'];
            $financialCategoryId=$purpose['financial_category_id'];
            $accountId=$purpose['account_id'];
            $controlAccountId=$purpose['control_account_id']??null;
        }

        $check=db()->prepare("SELECT normal_side FROM chart_accounts WHERE account_id=? AND active=1 AND account_type='ANALITICA' AND accepts_posting=1");
        $check->execute([$accountId]);
        $side=$check->fetchColumn();
        $expected=str_starts_with($classification,'receita')?'C':'D';
        if(!$side||$side!==$expected)throw new InvalidArgumentException('A classificação automática selecionada não está disponível.');
        if($controlAccountId){
            $check->execute([$controlAccountId]);
            if(!$check->fetchColumn())throw new InvalidArgumentException('A conta de controle automática não está disponível.');
        }

        if($id){
            $stmt=db()->prepare('UPDATE categories SET name=?,type=?,classification=?,financial_category_id=?,account_id=?,control_account_id=?,accounting_enabled=1,active=?,migration_status=? WHERE id=?');
            $stmt->execute([$name,$type,$classification,$financialCategoryId,$accountId,$controlAccountId,(int)$active,'CONFIGURADA_SIMPLIFICADA',$id]);
        }else{
            $stmt=db()->prepare('INSERT INTO categories (name,type,classification,financial_category_id,account_id,control_account_id,accounting_enabled,active,migration_status) VALUES (?,?,?,?,?,?,1,?,?)');
            $stmt->execute([$name,$type,$classification,$financialCategoryId,$accountId,$controlAccountId,(int)$active,'CONFIGURADA_SIMPLIFICADA']);
        }
        flash('success','Categoria pronta para ser usada nos lançamentos.');
        redirect('categories');
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
