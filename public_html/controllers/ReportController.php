<?php
declare(strict_types=1);

final class ReportController extends BaseController
{
    public function index(): void
    {
        $type=(string)($_GET['type']??'realizado');if(!in_array($type,['realizado','projetado','dre','comparativo'],true))$type='realizado';[$start,$end]=period_range();validate_date_range($start,$end);
        [$title,$columns,$rows]=$this->data($type,$start,$end);
        $this->render('reports/index',compact('type','start','end','title','columns','rows')+['pageTitle'=>'Relatórios']);
    }

    public function export(): void
    {
        $type=(string)($_GET['type']??'realizado');if(!in_array($type,['realizado','projetado','dre','comparativo'],true))$type='realizado';$format=(string)($_GET['format']??'csv');$start=(string)($_GET['start']??date('Y-m-01'));$end=(string)($_GET['end']??date('Y-m-t'));validate_date_range($start,$end);
        [$title,$columns,$rows]=$this->data($type,$start,$end);$date=date('Y-m-d');
        if($format==='csv'){
            header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="relatorio-'.$type.'-'.$date.'.csv"');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,array_values($columns),';');foreach($rows as $row)fputcsv($out,array_values($row),';');fclose($out);exit;
        }
        if($format!=='pdf')throw new InvalidArgumentException('Formato inválido.');
        $autoload=__DIR__.'/../vendor/autoload.php';if(!is_file($autoload))throw new RuntimeException('Dompdf não instalado. Execute composer install na pasta public_html.');require_once $autoload;
        $html='<style>body{font-family:DejaVu Sans;font-size:11px;color:#1e293b}h1{color:#0f766e}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #cbd5e1;text-align:left}th{background:#f1f5f9}</style>';
        $html.='<h1>'.e(config('company_name')).'</h1><p>'.e(config('company_document')).' · '.e(config('company_address')).'</p><h2>'.e($title).'</h2><p>Período: '.br_date($start).' a '.br_date($end).'</p><table><thead><tr>';
        foreach($columns as $label)$html.='<th>'.e($label).'</th>';$html.='</tr></thead><tbody>';
        foreach($rows as $row){$html.='<tr>';foreach($row as $value)$html.='<td>'.e($value).'</td>';$html.='</tr>';}$html.='</tbody></table>';
        $dompdf=new Dompdf\Dompdf(['isRemoteEnabled'=>false]);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','landscape');$dompdf->render();$dompdf->stream('relatorio-'.$type.'-'.$date.'.pdf',['Attachment'=>true]);exit;
    }

    private function data(string $type,string $start,string $end): array
    {
        $pdo=db();
        if($type==='projetado'){
            $stmt=$pdo->prepare("SELECT DATE_FORMAT(day,'%d/%m/%Y') data,entrada,saida,(entrada-saida) saldo FROM (
              SELECT day,SUM(entrada) entrada,SUM(saida) saida FROM (
                SELECT due_date day,expected_amount-received_amount entrada,0 saida FROM receivables WHERE status IN ('pendente','vencido','parcial') AND due_date BETWEEN ? AND ?
                UNION ALL SELECT due_date,0,amount FROM payables WHERE status IN ('pendente','vencido') AND due_date BETWEEN ? AND ?
              ) x GROUP BY day) y ORDER BY STR_TO_DATE(data,'%d/%m/%Y')");$stmt->execute([$start,$end,$start,$end]);$raw=$stmt->fetchAll();
            return ['Fluxo de Caixa Projetado',['data'=>'Data','entrada'=>'Entradas','saida'=>'Saídas','saldo'=>'Saldo'],array_map(fn($r)=>[$r['data'],money($r['entrada']),money($r['saida']),money($r['saldo'])],$raw)];
        }
        if($type==='dre'){
            $stmt=$pdo->prepare("SELECT DATE_FORMAT(MIN(day),'%m/%Y') mes,category,classification,nature,SUM(amount) total
              FROM (
                SELECT r.receipt_date day,r.received_amount amount,'receita' nature,COALESCE(c.name,'Sem categoria') category,COALESCE(c.classification,'receita_nao_operacional') classification FROM receivables r LEFT JOIN categories c ON c.id=r.category_id WHERE r.status IN ('recebido','parcial') AND r.receipt_date BETWEEN ? AND ?
                UNION ALL SELECT p.payment_date,p.amount,'despesa',COALESCE(c.name,'Sem categoria'),COALESCE(c.classification,'despesa_administrativa') FROM payables p LEFT JOIN categories c ON c.id=p.category_id WHERE p.status='pago' AND p.payment_date BETWEEN ? AND ?
              ) x GROUP BY YEAR(day),MONTH(day),category,classification,nature ORDER BY MIN(day),nature DESC,category");$stmt->execute([$start,$end,$start,$end]);$raw=$stmt->fetchAll();
            $rows=[];$month='';$revenue=$operatingExpense=$investment=0;
            $appendTotal=function()use(&$rows,&$month,&$revenue,&$operatingExpense,&$investment){if($month!==''){$rows[]=[$month,'RESULTADO OPERACIONAL','Receitas menos despesas operacionais',money($revenue),money($operatingExpense),money($revenue-$operatingExpense)];$rows[]=[$month,'RESULTADO LÍQUIDO','Operacional menos investimentos',money($revenue),money($operatingExpense+$investment),money($revenue-$operatingExpense-$investment)];}};
            foreach($raw as $r){if($month!==$r['mes']){$appendTotal();$month=$r['mes'];$revenue=$operatingExpense=$investment=0;}$isRevenue=$r['nature']==='receita';if($isRevenue)$revenue+=(float)$r['total'];elseif($r['classification']==='investimento')$investment+=(float)$r['total'];else $operatingExpense+=(float)$r['total'];$rows[]=[$r['mes'],$r['category'],ucwords(str_replace('_',' ',$r['classification'])),$isRevenue?money($r['total']):'—',$isRevenue?'—':money($r['total']),money($isRevenue?$r['total']:-$r['total'])];}
            $appendTotal();
            return ['DRE Simplificado',['mes'=>'Mês','categoria'=>'Categoria','classificacao'=>'Classificação','receitas'=>'Receitas','despesas'=>'Despesas','resultado'=>'Resultado'],$rows];
        }
        if($type==='comparativo'){
            $first=(new DateTimeImmutable('first day of this month'))->modify('-5 months')->format('Y-m-d');$last=date('Y-m-t');
            $stmt=$pdo->prepare("SELECT DATE_FORMAT(MIN(day),'%m/%Y') mes,SUM(entrada) receitas,SUM(saida) despesas FROM (
              SELECT receipt_date day,received_amount entrada,0 saida FROM receivables WHERE status IN ('recebido','parcial') AND receipt_date BETWEEN ? AND ?
              UNION ALL SELECT payment_date,0,amount FROM payables WHERE status='pago' AND payment_date BETWEEN ? AND ?
            ) x GROUP BY YEAR(day),MONTH(day) ORDER BY MIN(day)");$stmt->execute([$first,$last,$first,$last]);$raw=$stmt->fetchAll();
            $byMonth=array_column($raw,null,'mes');$rows=[];
            for($i=5;$i>=0;$i--){$month=(new DateTimeImmutable('first day of this month'))->modify("-$i months")->format('m/Y');$r=$byMonth[$month]??['receitas'=>0,'despesas'=>0];$rows[]=[$month,money($r['receitas']),money($r['despesas']),money((float)$r['receitas']-(float)$r['despesas'])];}
            return ['Comparativo Mensal (6 meses)',['mes'=>'Mês','receitas'=>'Receitas','despesas'=>'Despesas','resultado'=>'Resultado'],$rows];
        }
        $stmt=$pdo->prepare("SELECT DATE_FORMAT(day,'%d/%m/%Y') data,entrada,saida,(entrada-saida) saldo FROM (
          SELECT day,SUM(entrada) entrada,SUM(saida) saida FROM (
            SELECT receipt_date day,received_amount entrada,0 saida FROM receivables WHERE status IN ('recebido','parcial') AND receipt_date BETWEEN ? AND ?
            UNION ALL SELECT payment_date,0,amount FROM payables WHERE status='pago' AND payment_date BETWEEN ? AND ?
          ) x GROUP BY day) y ORDER BY STR_TO_DATE(data,'%d/%m/%Y')");$stmt->execute([$start,$end,$start,$end]);$raw=$stmt->fetchAll();$acc=0;$rows=[];
        foreach($raw as $r){$acc+=(float)$r['saldo'];$rows[]=[$r['data'],money($r['entrada']),money($r['saida']),money($r['saldo']),money($acc)];}
        return ['Fluxo de Caixa Realizado',['data'=>'Data','entrada'=>'Entradas','saida'=>'Saídas','saldo'=>'Saldo do dia','acumulado'=>'Acumulado'],$rows];
    }
}
