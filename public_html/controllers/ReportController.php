<?php
declare(strict_types=1);

final class ReportController extends BaseController
{
    public function index(): void
    {
        $type=(string)($_GET['type']??'realizado');if(!in_array($type,['realizado','projetado','dre','comparativo','dre_contabil','balanco','balancete','dfc_contabil'],true))$type='realizado';[$start,$end]=period_range();validate_date_range($start,$end);
        [$title,$columns,$rows]=$this->data($type,$start,$end);
        $this->render('reports/index',compact('type','start','end','title','columns','rows')+['pageTitle'=>'Relatórios']);
    }

    public function export(): void
    {
        $type=(string)($_GET['type']??'realizado');if(!in_array($type,['realizado','projetado','dre','comparativo','dre_contabil','balanco','balancete','dfc_contabil'],true))$type='realizado';$format=(string)($_GET['format']??'csv');$start=(string)($_GET['start']??date('Y-m-01'));$end=(string)($_GET['end']??date('Y-m-t'));validate_date_range($start,$end);
        [$title,$columns,$rows]=$this->data($type,$start,$end);$date=date('Y-m-d');
        if($format==='csv'){
            header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="relatorio-'.$type.'-'.$date.'.csv"');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,array_values($columns),';');foreach($rows as $row)fputcsv($out,array_values($row),';');fclose($out);exit;
        }
        if($format!=='pdf')throw new InvalidArgumentException('Formato inválido.');
        $autoload=__DIR__.'/../vendor/autoload.php';if(!is_file($autoload))throw new RuntimeException('Dompdf não instalado. Execute composer install na pasta public_html.');require_once $autoload;
        $html='<style>body{font-family:DejaVu Sans;font-size:11px;color:#1e293b}h1{color:#0f766e}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #cbd5e1;text-align:left}th{background:#f1f5f9}</style>';
        $html.='<h1>'.e(company_branding()['company_name']).'</h1><p>'.e(config('company_document')).' · '.e(config('company_address')).'</p><h2>'.e($title).'</h2><p>Período: '.br_date($start).' a '.br_date($end).'</p><table><thead><tr>';
        foreach($columns as $label)$html.='<th>'.e($label).'</th>';$html.='</tr></thead><tbody>';
        foreach($rows as $row){$html.='<tr>';foreach($row as $value)$html.='<td>'.e($value).'</td>';$html.='</tr>';}$html.='</tbody></table>';
        $dompdf=new Dompdf\Dompdf(['isRemoteEnabled'=>false]);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','landscape');$dompdf->render();$dompdf->stream('relatorio-'.$type.'-'.$date.'.pdf',['Attachment'=>true]);exit;
    }

    private function data(string $type,string $start,string $end): array
    {
        $pdo=db();
        if($type==='dre_contabil')return $this->accountingDre($pdo,$start,$end);
        if($type==='balanco')return $this->balanceSheet($pdo,$end);
        if($type==='balancete')return $this->trialBalance($pdo,$start,$end);
        if($type==='dfc_contabil')return $this->accountingCashFlow($pdo,$start,$end);
        if($type==='projetado'){
            $stmt=$pdo->prepare("SELECT DATE_FORMAT(day,'%d/%m/%Y') data,entrada,saida,(entrada-saida) saldo FROM (
              SELECT day,SUM(entrada) entrada,SUM(saida) saida FROM (
                SELECT due_date day,remaining_amount entrada,0 saida FROM receivables WHERE status IN ('pendente','vencido','parcial') AND due_date BETWEEN ? AND ?
                UNION ALL SELECT due_date,0,remaining_amount FROM payables WHERE status IN ('pendente','vencido','parcial') AND due_date BETWEEN ? AND ?
              ) x GROUP BY day) y ORDER BY STR_TO_DATE(data,'%d/%m/%Y')");$stmt->execute([$start,$end,$start,$end]);$raw=$stmt->fetchAll();
            return ['Fluxo de Caixa Projetado',['data'=>'Data','entrada'=>'Entradas','saida'=>'Saídas','saldo'=>'Saldo'],array_map(fn($r)=>[$r['data'],money($r['entrada']),money($r['saida']),money($r['saldo'])],$raw)];
        }
        if($type==='dre'){
            $stmt=$pdo->prepare("SELECT DATE_FORMAT(MIN(day),'%m/%Y') mes,category,classification,nature,SUM(amount) total
              FROM (
                SELECT t.transaction_date day,t.amount,'receita' nature,COALESCE(c.name,'Sem categoria') category,COALESCE(c.classification,'receita_nao_operacional') classification FROM financial_transactions t JOIN receivables r ON t.entity_type='receivable' AND r.id=t.entity_id LEFT JOIN categories c ON c.id=r.category_id WHERE t.transaction_date BETWEEN ? AND ?
                UNION ALL SELECT t.transaction_date,t.amount,'despesa',COALESCE(c.name,'Sem categoria'),COALESCE(c.classification,'despesa_administrativa') FROM financial_transactions t JOIN payables p ON t.entity_type='payable' AND p.id=t.entity_id LEFT JOIN categories c ON c.id=p.category_id WHERE t.transaction_date BETWEEN ? AND ?
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
              SELECT transaction_date day,amount entrada,0 saida FROM financial_transactions WHERE entity_type='receivable' AND transaction_date BETWEEN ? AND ?
              UNION ALL SELECT transaction_date,0,amount FROM financial_transactions WHERE entity_type='payable' AND transaction_date BETWEEN ? AND ?
            ) x GROUP BY YEAR(day),MONTH(day) ORDER BY MIN(day)");$stmt->execute([$first,$last,$first,$last]);$raw=$stmt->fetchAll();
            $byMonth=array_column($raw,null,'mes');$rows=[];
            for($i=5;$i>=0;$i--){$month=(new DateTimeImmutable('first day of this month'))->modify("-$i months")->format('m/Y');$r=$byMonth[$month]??['receitas'=>0,'despesas'=>0];$rows[]=[$month,money($r['receitas']),money($r['despesas']),money((float)$r['receitas']-(float)$r['despesas'])];}
            return ['Comparativo Mensal (6 meses)',['mes'=>'Mês','receitas'=>'Receitas','despesas'=>'Despesas','resultado'=>'Resultado'],$rows];
        }
        $stmt=$pdo->prepare("SELECT DATE_FORMAT(day,'%d/%m/%Y') data,entrada,saida,(entrada-saida) saldo FROM (
          SELECT day,SUM(entrada) entrada,SUM(saida) saida FROM (
            SELECT transaction_date day,amount entrada,0 saida FROM financial_transactions WHERE entity_type='receivable' AND transaction_date BETWEEN ? AND ?
            UNION ALL SELECT transaction_date,0,amount FROM financial_transactions WHERE entity_type='payable' AND transaction_date BETWEEN ? AND ?
          ) x GROUP BY day) y ORDER BY STR_TO_DATE(data,'%d/%m/%Y')");$stmt->execute([$start,$end,$start,$end]);$raw=$stmt->fetchAll();$acc=0;$rows=[];
        foreach($raw as $r){$acc+=(float)$r['saldo'];$rows[]=[$r['data'],money($r['entrada']),money($r['saida']),money($r['saldo']),money($acc)];}
        return ['Fluxo de Caixa Realizado',['data'=>'Data','entrada'=>'Entradas','saida'=>'Saídas','saldo'=>'Saldo do dia','acumulado'=>'Acumulado'],$rows];
    }

    private function accountingDre(PDO $pdo,string $start,string $end): array
    {
        $stmt=$pdo->prepare("SELECT r.line_id,r.description,r.sort_order,COALESCE(SUM(CASE WHEN e.id IS NULL THEN 0 WHEN a.normal_side='D' THEN CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END ELSE CASE WHEN l.side='C' THEN l.amount ELSE -l.amount END END),0) total FROM accounting_report_lines r LEFT JOIN chart_accounts a ON a.report_line_id=r.line_id AND a.account_type='ANALITICA' LEFT JOIN journal_lines l ON l.account_id=a.account_id LEFT JOIN journal_entries e ON e.id=l.entry_id AND e.competence_date BETWEEN ? AND ? WHERE r.report_type='DRE' AND r.active=1 GROUP BY r.line_id,r.description,r.sort_order ORDER BY r.sort_order");
        $stmt->execute([$start,$end]);$lines=$stmt->fetchAll();$values=[];$rows=[];
        foreach($lines as $line){$cents=decimal_cents($line['total']);$values[$line['line_id']]=$cents;$rows[]=[$line['description'],money(cents_decimal($cents))];}
        $revenue=$values['DRE_RECEITA_BRUTA']??0;$net=$revenue-($values['DRE_DEDUCOES']??0);$gross=$net-($values['DRE_CUSTOS']??0);
        $operating=$gross;foreach(['DRE_DESP_COMERCIAIS','DRE_DESP_ADMIN','DRE_DESP_PESSOAL','DRE_DESP_OCUPACAO','DRE_OUTRAS_DESP_OP','DRE_DEPR_AMORT'] as $key)$operating-=($values[$key]??0);
        $beforeTax=$operating+($values['DRE_RECEITAS_FIN']??0)-($values['DRE_DESP_FIN']??0)+($values['DRE_OUTRAS_RECEITAS']??0)-($values['DRE_OUTRAS_DESPESAS']??0);$netIncome=$beforeTax-($values['DRE_TRIBUTOS_LUCRO']??0);
        $rows[]=['= Receita líquida',money(cents_decimal($net))];$rows[]=['= Lucro bruto',money(cents_decimal($gross))];$rows[]=['= Resultado operacional',money(cents_decimal($operating))];$rows[]=['= Resultado antes dos tributos sobre lucro',money(cents_decimal($beforeTax))];$rows[]=['= Resultado líquido',money(cents_decimal($netIncome))];
        return ['DRE por competência',['linha'=>'Linha','valor'=>'Valor'],$rows];
    }

    private function balanceSheet(PDO $pdo,string $end): array
    {
        $stmt=$pdo->prepare("SELECT r.line_id,r.description,r.sort_order,COALESCE(SUM(CASE WHEN e.id IS NULL THEN 0 WHEN a.normal_side='D' THEN CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END ELSE CASE WHEN l.side='C' THEN l.amount ELSE -l.amount END END),0) total FROM accounting_report_lines r LEFT JOIN chart_accounts a ON a.report_line_id=r.line_id AND a.account_type='ANALITICA' LEFT JOIN journal_lines l ON l.account_id=a.account_id LEFT JOIN journal_entries e ON e.id=l.entry_id AND e.posting_date<=? WHERE r.report_type='BP' AND r.active=1 GROUP BY r.line_id,r.description,r.sort_order ORDER BY r.sort_order");$stmt->execute([$end]);$lines=$stmt->fetchAll();$rows=[];$assets=$liabilities=$equity=0;
        foreach($lines as $line){$cents=decimal_cents($line['total']);if($line['sort_order']<=9)$assets+=$cents;elseif($line['sort_order']<=15)$liabilities+=$cents;else$equity+=$cents;$rows[]=[$line['description'],money(cents_decimal($cents))];}
        $resultStmt=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN a.normal_side='C' THEN CASE WHEN l.side='C' THEN l.amount ELSE -l.amount END ELSE -(CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END) END),0) FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_accounts a ON a.account_id=l.account_id WHERE a.report_type='DRE' AND e.posting_date<=?");$resultStmt->execute([$end]);$currentResult=decimal_cents($resultStmt->fetchColumn());$equity+=$currentResult;
        $rows[]=['Resultado acumulado ainda não encerrado',money(cents_decimal($currentResult))];$rows[]=['TOTAL DO ATIVO',money(cents_decimal($assets))];$rows[]=['TOTAL DO PASSIVO + PL',money(cents_decimal($liabilities+$equity))];$rows[]=['DIFERENÇA DE CONFERÊNCIA',money(cents_decimal($assets-$liabilities-$equity))];
        return ['Balanço patrimonial em '.br_date($end),['linha'=>'Linha','valor'=>'Saldo'],$rows];
    }

    private function trialBalance(PDO $pdo,string $start,string $end): array
    {
        $stmt=$pdo->prepare("SELECT a.code,a.name,a.normal_side,COALESCE(SUM(CASE WHEN e.posting_date BETWEEN ? AND ? AND l.side='D' THEN l.amount ELSE 0 END),0) debits,COALESCE(SUM(CASE WHEN e.posting_date BETWEEN ? AND ? AND l.side='C' THEN l.amount ELSE 0 END),0) credits,COALESCE(SUM(CASE WHEN e.posting_date<=? THEN CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END ELSE 0 END),0) balance FROM chart_accounts a LEFT JOIN journal_lines l ON l.account_id=a.account_id LEFT JOIN journal_entries e ON e.id=l.entry_id WHERE a.account_type='ANALITICA' GROUP BY a.account_id,a.code,a.name,a.normal_side HAVING debits<>0 OR credits<>0 OR balance<>0 ORDER BY a.code");$stmt->execute([$start,$end,$start,$end,$end]);$rows=[];
        foreach($stmt->fetchAll() as $r){$balance=decimal_cents($r['balance']);$rows[]=[$r['code'],$r['name'],money($r['debits']),money($r['credits']),money(cents_decimal(abs($balance))),$balance===0?'—':($balance>0?'D':'C')];}
        return ['Balancete de verificação',['codigo'=>'Código','conta'=>'Conta','debitos'=>'Débitos','creditos'=>'Créditos','saldo'=>'Saldo','natureza'=>'D/C'],$rows];
    }

    private function accountingCashFlow(PDO $pdo,string $start,string $end): array
    {
        $stmt=$pdo->prepare("SELECT f.dfc_default,f.name,COALESCE(SUM(CASE WHEN l.side='D' THEN l.amount ELSE -l.amount END),0) total FROM journal_lines l JOIN journal_entries e ON e.id=l.entry_id JOIN chart_accounts a ON a.account_id=l.account_id LEFT JOIN financial_categories f ON f.category_id=e.financial_category_id WHERE l.bank_account_id IS NOT NULL AND e.posting_date BETWEEN ? AND ? AND COALESCE(f.dfc_default,'REVISAR')<>'INTERNA_EXCLUIR_DFC' GROUP BY f.dfc_default,f.name ORDER BY f.dfc_default,f.name");$stmt->execute([$start,$end]);$rows=[];$total=0;foreach($stmt->fetchAll() as $r){$cents=decimal_cents($r['total']);$total+=$cents;$rows[]=[$r['dfc_default']?:'REVISAR',$r['name']?:'Sem classificação',money(cents_decimal($cents))];}$rows[]=['TOTAL','Variação líquida de caixa',money(cents_decimal($total))];
        return ['Fluxo de caixa contábil',['classe'=>'Classe DFC','categoria'=>'Categoria','valor'=>'Valor'],$rows];
    }
}
