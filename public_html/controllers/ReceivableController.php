<?php
declare(strict_types=1);

final class ReceivableController extends BaseController
{
    private array $methods=['pix','cartao_credito','cartao_debito','boleto','dinheiro','transferencia'];
    public function index(): void
    {
        $where=['1=1'];$params=[];
        foreach(['status'=>'r.status','category'=>'r.category_id','contact'=>'r.contact_id','payment_method'=>'r.receipt_method'] as $input=>$column){if(($_GET[$input]??'')!==''){$where[]="$column=?";$params[]=$_GET[$input];}}
        if(!empty($_GET['start'])){$where[]='r.due_date>=?';$params[]=$_GET['start'];}if(!empty($_GET['end'])){$where[]='r.due_date<=?';$params[]=$_GET['end'];}
        $stmt=db()->prepare("SELECT r.*,c.name category_name,ct.name contact_name FROM receivables r LEFT JOIN categories c ON c.id=r.category_id LEFT JOIN contacts ct ON ct.id=r.contact_id WHERE ".implode(' AND ',$where).' ORDER BY r.due_date,r.id');$stmt->execute($params);$items=$stmt->fetchAll();
        $categories=select_options("SELECT id,name FROM categories WHERE classification LIKE 'receita%' ORDER BY name");$contacts=select_options("SELECT id,name FROM contacts WHERE type IN ('cliente','ambos') ORDER BY name");
        $this->render('receivables/index',compact('items','categories','contacts')+['pageTitle'=>'Contas a receber']);
    }
    public function form(): void
    {
        $item=['id'=>'','description'=>'','contact_id'=>'','category_id'=>'','expected_amount'=>'','due_date'=>date('Y-m-d'),'receipt_method'=>'pix','notes'=>''];
        if($id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)){$stmt=db()->prepare('SELECT * FROM receivables WHERE id=?');$stmt->execute([$id]);$item=$stmt->fetch()?:$item;}
        $categories=select_options("SELECT id,name FROM categories WHERE classification LIKE 'receita%' ORDER BY name");$contacts=select_options("SELECT id,name FROM contacts WHERE type IN ('cliente','ambos') ORDER BY name");
        $this->render('receivables/form',compact('item','categories','contacts')+['pageTitle'=>$item['id']?'Editar conta':'Nova conta a receber']);
    }
    public function save(): void
    {
        verify_csrf();$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);$description=trim((string)($_POST['description']??''));$amount=decimal_value($_POST['expected_amount']??'');$due=(string)($_POST['due_date']??'');$method=(string)($_POST['receipt_method']??'');
        if($description===''||$amount<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$due)||!in_array($method,$this->methods,true))throw new InvalidArgumentException('Preencha os dados da conta corretamente.');
        $status=$due<date('Y-m-d')?'vencido':'pendente';$values=[$description,(int)($_POST['contact_id']??0)?:null,(int)($_POST['category_id']??0)?:null,$amount,$due,$method,$status,trim((string)($_POST['notes']??''))];
        if($id){$values[]=$id;$stmt=db()->prepare("UPDATE receivables SET description=?,contact_id=?,category_id=?,expected_amount=?,due_date=?,receipt_method=?,status=IF(status IN ('recebido','parcial'),status,?),notes=? WHERE id=?");}else $stmt=db()->prepare('INSERT INTO receivables (description,contact_id,category_id,expected_amount,due_date,receipt_method,status,notes) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute($values);flash('success','Conta a receber salva.');redirect('receivables');
    }
    public function receive(): void
    {
        verify_csrf();$id=(int)($_POST['id']??0);$amount=decimal_value($_POST['received_amount']??'');$date=(string)($_POST['receipt_date']??date('Y-m-d'));
        $stmt=db()->prepare('SELECT expected_amount,received_amount FROM receivables WHERE id=?');$stmt->execute([$id]);$item=$stmt->fetch();if(!$item)throw new RuntimeException('Conta não encontrada.');
        if($amount<=0)throw new InvalidArgumentException('Informe o valor recebido.');$newTotal=(float)$item['received_amount']+$amount;$status=$newTotal<(float)$item['expected_amount']?'parcial':'recebido';
        db()->prepare('UPDATE receivables SET received_amount=?,receipt_date=?,status=? WHERE id=?')->execute([$newTotal,$date,$status,$id]);flash('success',$status==='parcial'?'Recebimento parcial registrado.':'Recebimento confirmado.');redirect('receivables');
    }
    public function delete(): void
    {
        verify_csrf();db()->prepare('DELETE FROM receivables WHERE id=?')->execute([(int)($_POST['id']??0)]);flash('success','Conta excluída.');redirect('receivables');
    }
}
