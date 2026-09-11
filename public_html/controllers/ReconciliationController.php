<?php
declare(strict_types=1);

final class ReconciliationController extends BaseController
{
    public function index(): void
    {
        if (!isset($_GET['period'])) $_GET['period']='week';
        [$start,$end]=period_range();validate_date_range($start,$end);
        $stmt=db()->prepare("SELECT due_date,kind,description,expected,realized,status FROM (
            SELECT due_date,'Saída' kind,description,-amount expected,CASE WHEN status='pago' THEN -amount ELSE 0 END realized,status FROM payables WHERE due_date BETWEEN ? AND ?
            UNION ALL
            SELECT due_date,'Entrada',description,expected_amount,received_amount,status FROM receivables WHERE due_date BETWEEN ? AND ?
        ) m ORDER BY due_date,kind");$stmt->execute([$start,$end,$start,$end]);$movements=$stmt->fetchAll();
        $totals=['payable'=>0,'receivable'=>0,'realized_out'=>0,'realized_in'=>0];$balance=0;
        foreach($movements as &$m){if($m['kind']==='Saída'){$totals['payable']+=abs((float)$m['expected']);$totals['realized_out']+=abs((float)$m['realized']);}else{$totals['receivable']+=(float)$m['expected'];$totals['realized_in']+=(float)$m['realized'];}$balance+=(float)$m['expected'];$m['balance']=$balance;}unset($m);
        $payables=array_values(array_filter($movements,fn($m)=>$m['kind']==='Saída'));
        $receivables=array_values(array_filter($movements,fn($m)=>$m['kind']==='Entrada'));
        $this->render('reconciliation/index',compact('start','end','movements','payables','receivables','totals')+['pageTitle'=>'Conciliação financeira']);
    }
}
