<?php
declare(strict_types=1);

final class PayableController extends BaseController
{
    private array $methods=['pix','boleto','cartao','dinheiro','debito_automatico','transferencia'];
    private array $recurrences=['nenhuma','mensal','quinzenal','semanal'];

    public function index(): void
    {
        $where=['1=1']; $params=[];
        foreach (['status'=>'p.status','category'=>'p.category_id','contact'=>'p.contact_id','payment_method'=>'p.payment_method'] as $input=>$column) {
            if (($_GET[$input]??'')!=='') { $where[]="$column=?"; $params[]=$_GET[$input]; }
        }
        if (!empty($_GET['start'])) { $where[]='p.due_date>=?'; $params[]=$_GET['start']; }
        if (!empty($_GET['end'])) { $where[]='p.due_date<=?'; $params[]=$_GET['end']; }
        $stmt=db()->prepare("SELECT p.*,c.name category_name,ct.name contact_name,(SELECT MIN(a.id) FROM attachments a WHERE a.entity_type='payable' AND a.entity_id=p.id) attachment_id FROM payables p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN contacts ct ON ct.id=p.contact_id WHERE ".implode(' AND ',$where).' ORDER BY p.due_date,p.id');
        $stmt->execute($params); $items=$stmt->fetchAll();
        $categories=select_options("SELECT id,name FROM categories WHERE classification LIKE 'despesa%' OR classification='investimento' ORDER BY name");
        $contacts=select_options("SELECT id,name FROM contacts WHERE type IN ('fornecedor','ambos') ORDER BY name");
        $this->render('payables/index',compact('items','categories','contacts')+['pageTitle'=>'Contas a pagar']);
    }

    public function form(): void
    {
        $item=['id'=>'','description'=>'','contact_id'=>'','category_id'=>'','amount'=>'','due_date'=>date('Y-m-d'),'payment_method'=>'pix','recurrence'=>'nenhuma','notes'=>''];
        if ($id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)) { $stmt=db()->prepare('SELECT * FROM payables WHERE id=?');$stmt->execute([$id]);$item=$stmt->fetch()?:$item; }
        $categories=select_options("SELECT id,name FROM categories WHERE classification LIKE 'despesa%' OR classification='investimento' ORDER BY name");
        $contacts=select_options("SELECT id,name FROM contacts WHERE type IN ('fornecedor','ambos') ORDER BY name");
        $this->render('payables/form',compact('item','categories','contacts')+['pageTitle'=>$item['id']?'Editar conta':'Nova conta a pagar']);
    }

    public function save(): void
    {
        verify_csrf(); $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
        $description=trim((string)($_POST['description']??'')); $amount=decimal_value($_POST['amount']??''); $due=(string)($_POST['due_date']??'');
        $method=(string)($_POST['payment_method']??''); $recurrence=(string)($_POST['recurrence']??'nenhuma');
        if ($description===''||$amount<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$due)||!in_array($method,$this->methods,true)||!in_array($recurrence,$this->recurrences,true)) throw new InvalidArgumentException('Preencha os dados da conta corretamente.');
        $status=$due<date('Y-m-d')?'vencido':'pendente';
        $values=[$description,(int)($_POST['contact_id']??0)?:null,(int)($_POST['category_id']??0)?:null,$amount,$due,$method,$status,$recurrence,trim((string)($_POST['notes']??''))];
        $pdo=db(); $pdo->beginTransaction();
        try {
            if ($id) { $values[]=$id; $stmt=$pdo->prepare("UPDATE payables SET description=?,contact_id=?,category_id=?,amount=?,due_date=?,payment_method=?,status=IF(status='pago',status,?),recurrence=?,notes=? WHERE id=?"); }
            else $stmt=$pdo->prepare('INSERT INTO payables (description,contact_id,category_id,amount,due_date,payment_method,status,recurrence,notes) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute($values); $entityId=$id ?: (int)$pdo->lastInsertId();
            Attachment::store('payable',$entityId,$_FILES['attachment']??[]);
            $pdo->commit(); flash('success','Conta a pagar salva.'); redirect('payables');
        } catch (Throwable $e) { if ($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }

    public function pay(): void
    {
        verify_csrf(); $id=(int)($_POST['id']??0); $date=(string)($_POST['payment_date']??date('Y-m-d'));
        $pdo=db(); $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare('SELECT * FROM payables WHERE id=? FOR UPDATE');$stmt->execute([$id]);$item=$stmt->fetch(); if(!$item)throw new RuntimeException('Conta não encontrada.');
            $pdo->prepare("UPDATE payables SET status='pago',payment_date=? WHERE id=?")->execute([$date,$id]);
            if ($item['recurrence']!=='nenhuma') {
                $next=next_due_date($item['due_date'],$item['recurrence']);
                $sql="INSERT IGNORE INTO payables (description,contact_id,category_id,amount,due_date,payment_method,status,recurrence,notes,recurrence_parent_id) VALUES (?,?,?,?,?,?,'pendente',?,?,?)";
                $pdo->prepare($sql)->execute([$item['description'],$item['contact_id'],$item['category_id'],$item['amount'],$next,$item['payment_method'],$item['recurrence'],$item['notes'],$id]);
            }
            $pdo->commit(); flash('success','Pagamento confirmado. Próxima ocorrência gerada quando aplicável.');
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        redirect('payables');
    }

    public function schedule(): void
    {
        verify_csrf(); $stmt=db()->prepare('UPDATE payables SET is_scheduled=1 WHERE id=? AND status<>\'pago\'');$stmt->execute([(int)($_POST['id']??0)]);flash('success','Pagamento marcado como agendado.');redirect('payables');
    }

    public function delete(): void
    {
        verify_csrf();$id=(int)($_POST['id']??0);Attachment::deleteFor('payable',$id);$stmt=db()->prepare('DELETE FROM payables WHERE id=?');$stmt->execute([$id]);flash('success','Conta excluída.');redirect('payables');
    }
}
