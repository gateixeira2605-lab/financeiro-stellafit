<?php
declare(strict_types=1);

final class AccountingEngine
{
    public static function recognizePayable(PDO $pdo, int $id, bool $replace = false): int
    {
        $item=self::title($pdo,'payables',$id);
        $category=self::category($pdo,(int)$item['category_id'],'payable');
        $settings=self::settings($pdo);
        $control=$category['control_account_id'] ?: $settings['payable_control_account_id'];
        if(!$control) throw new InvalidArgumentException('Configure a conta contábil de fornecedores nas configurações.');
        if($replace&&!empty($item['journal_entry_id'])) self::reverse($pdo,(int)$item['journal_entry_id'],'Alteração da despesa '.$id);
        elseif(!empty($item['journal_entry_id'])) return (int)$item['journal_entry_id'];
        $revision=self::revision($pdo,'payable',$id,'RECONHECIMENTO_DESPESA');
        $entry=self::post($pdo,[
            'event_key'=>"payable:$id:recognition:r$revision",'event_type'=>'RECONHECIMENTO_DESPESA','phase'=>'RECONHECIMENTO',
            'source_type'=>'payable','source_id'=>$id,'financial_category_id'=>$category['financial_category_id'],
            'competence_date'=>$item['competence_date'],'posting_date'=>$item['document_date'],'document_ref'=>$item['document_ref'],
            'description'=>'Reconhecimento: '.$item['description'],
        ],[
            ['account_id'=>$category['account_id'],'side'=>'D','amount'=>$item['amount'],'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
            ['account_id'=>$control,'side'=>'C','amount'=>$item['amount'],'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
        ]);
        $pdo->prepare("UPDATE payables SET journal_entry_id=?,accounting_status='POSTED' WHERE id=?")->execute([$entry,$id]);
        return $entry;
    }

    public static function recognizeReceivable(PDO $pdo, int $id, bool $replace = false): int
    {
        $item=self::title($pdo,'receivables',$id);
        $category=self::category($pdo,(int)$item['category_id'],'receivable');
        $settings=self::settings($pdo);
        $control=$category['control_account_id'] ?: $settings['receivable_control_account_id'];
        if(!$control) throw new InvalidArgumentException('Configure a conta contábil de clientes nas configurações.');
        if($replace&&!empty($item['journal_entry_id'])) self::reverse($pdo,(int)$item['journal_entry_id'],'Alteração da receita '.$id);
        elseif(!empty($item['journal_entry_id'])) return (int)$item['journal_entry_id'];
        $revision=self::revision($pdo,'receivable',$id,'RECONHECIMENTO_RECEITA');
        $entry=self::post($pdo,[
            'event_key'=>"receivable:$id:recognition:r$revision",'event_type'=>'RECONHECIMENTO_RECEITA','phase'=>'RECONHECIMENTO',
            'source_type'=>'receivable','source_id'=>$id,'financial_category_id'=>$category['financial_category_id'],
            'competence_date'=>$item['competence_date'],'posting_date'=>$item['document_date'],'document_ref'=>$item['document_ref'],
            'description'=>'Reconhecimento: '.$item['description'],
        ],[
            ['account_id'=>$control,'side'=>'D','amount'=>$item['expected_amount'],'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
            ['account_id'=>$category['account_id'],'side'=>'C','amount'=>$item['expected_amount'],'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
        ]);
        $pdo->prepare("UPDATE receivables SET journal_entry_id=?,accounting_status='POSTED' WHERE id=?")->execute([$entry,$id]);
        return $entry;
    }

    public static function settle(PDO $pdo, string $entityType, array $item, int $transactionId, string $amount, string $date, int $bankAccountId): ?int
    {
        if(($item['accounting_status']??'')!=='POSTED') return null;
        $category=self::category($pdo,(int)$item['category_id'],$entityType==='payable'?'payable':'receivable');
        $settings=self::settings($pdo);
        $control=$category['control_account_id'] ?: ($entityType==='payable'?$settings['payable_control_account_id']:$settings['receivable_control_account_id']);
        $bank=$settings['bank_control_account_id'];
        if(!$control||!$bank) throw new InvalidArgumentException('Configure as contas contábeis de controle e bancos.');
        $payable=$entityType==='payable';
        $entry=self::post($pdo,[
            'event_key'=>"financial_transaction:$transactionId",'event_type'=>$payable?'PAGAMENTO_FORNECEDOR':'RECEBIMENTO_CLIENTE','phase'=>'LIQUIDACAO',
            'source_type'=>'financial_transaction','source_id'=>$transactionId,'financial_category_id'=>$category['financial_category_id'],
            'competence_date'=>$date,'posting_date'=>$date,'document_ref'=>$item['document_ref']??null,
            'description'=>($payable?'Pagamento: ':'Recebimento: ').$item['description'],
        ],$payable?[
            ['account_id'=>$control,'side'=>'D','amount'=>$amount,'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
            ['account_id'=>$bank,'side'=>'C','amount'=>$amount,'bank_account_id'=>$bankAccountId,'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
        ]:[
            ['account_id'=>$bank,'side'=>'D','amount'=>$amount,'bank_account_id'=>$bankAccountId,'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
            ['account_id'=>$control,'side'=>'C','amount'=>$amount,'contact_id'=>$item['contact_id'],'category_id'=>$item['category_id']],
        ]);
        $pdo->prepare('UPDATE financial_transactions SET journal_entry_id=? WHERE id=?')->execute([$entry,$transactionId]);
        return $entry;
    }

    public static function reverseTitle(PDO $pdo, string $entityType, int $id, string $reason): void
    {
        $table=$entityType==='payable'?'payables':'receivables';
        $item=self::title($pdo,$table,$id);
        if(!empty($item['journal_entry_id'])) self::reverse($pdo,(int)$item['journal_entry_id'],$reason);
    }

    public static function postAdjustment(PDO $pdo, int $adjustmentId, string $amount, string $date, int $bankAccountId, string $counterpartAccountId, string $description): int
    {
        $settings=self::settings($pdo);$bank=$settings['bank_control_account_id'];
        if(!$bank) throw new InvalidArgumentException('Configure a conta contábil de bancos.');
        $positive=decimal_cents($amount)>0;$absolute=cents_decimal(abs(decimal_cents($amount)));
        $entry=self::post($pdo,[
            'event_key'=>"bank_adjustment:$adjustmentId",'event_type'=>'AJUSTE_CONCILIATORIO','phase'=>'AJUSTE','source_type'=>'bank_adjustment','source_id'=>$adjustmentId,
            'financial_category_id'=>'FIN_AJUSTE','competence_date'=>$date,'posting_date'=>$date,'document_ref'=>null,'description'=>$description,
        ],$positive?[
            ['account_id'=>$bank,'side'=>'D','amount'=>$absolute,'bank_account_id'=>$bankAccountId],['account_id'=>$counterpartAccountId,'side'=>'C','amount'=>$absolute],
        ]:[
            ['account_id'=>$counterpartAccountId,'side'=>'D','amount'=>$absolute],['account_id'=>$bank,'side'=>'C','amount'=>$absolute,'bank_account_id'=>$bankAccountId],
        ]);
        $pdo->prepare('UPDATE bank_balance_adjustments SET account_id=?,journal_entry_id=? WHERE id=?')->execute([$counterpartAccountId,$entry,$adjustmentId]);
        return $entry;
    }

    private static function post(PDO $pdo,array $entry,array $lines): int
    {
        $existing=$pdo->prepare('SELECT id FROM journal_entries WHERE company_id=1 AND event_key=?');$existing->execute([$entry['event_key']]);
        if($id=$existing->fetchColumn()) return (int)$id;
        $debit=$credit=0;
        foreach($lines as $line){
            $cents=decimal_cents($line['amount']);if($cents<=0) throw new InvalidArgumentException('O lançamento contábil deve possuir valor positivo.');
            self::postingAccount($pdo,$line['account_id']);
            if($line['side']==='D')$debit+=$cents;elseif($line['side']==='C')$credit+=$cents;else throw new InvalidArgumentException('Natureza de partida inválida.');
        }
        if($debit!==$credit) throw new RuntimeException('Lançamento contábil desbalanceado.');
        $stmt=$pdo->prepare('INSERT INTO journal_entries (event_key,event_type,phase,source_type,source_id,financial_category_id,competence_date,posting_date,document_ref,description,created_by,reversal_of_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$entry['event_key'],$entry['event_type'],$entry['phase'],$entry['source_type'],$entry['source_id'],$entry['financial_category_id']?:null,$entry['competence_date'],$entry['posting_date'],$entry['document_ref']?:null,mb_substr($entry['description'],0,255),(int)($_SESSION['user_id']??0)?:null,$entry['reversal_of_id']??null]);
        $id=(int)$pdo->lastInsertId();
        $lineStmt=$pdo->prepare('INSERT INTO journal_lines (entry_id,account_id,side,amount,bank_account_id,contact_id,category_id,cost_center,project) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach($lines as $line)$lineStmt->execute([$id,$line['account_id'],$line['side'],cents_decimal(decimal_cents($line['amount'])),$line['bank_account_id']??null,$line['contact_id']??null,$line['category_id']??null,$line['cost_center']??null,$line['project']??null]);
        return $id;
    }

    private static function reverse(PDO $pdo,int $entryId,string $reason): ?int
    {
        $stmt=$pdo->prepare('SELECT * FROM journal_entries WHERE id=? FOR UPDATE');$stmt->execute([$entryId]);$entry=$stmt->fetch();
        if(!$entry||$entry['status']==='REVERSED') return null;
        $linesStmt=$pdo->prepare('SELECT * FROM journal_lines WHERE entry_id=? ORDER BY id');$linesStmt->execute([$entryId]);$lines=[];
        foreach($linesStmt->fetchAll() as $line){$line['side']=$line['side']==='D'?'C':'D';$lines[]=$line;}
        $reversal=self::post($pdo,[
            'event_key'=>$entry['event_key'].':reversal','event_type'=>$entry['event_type'],'phase'=>'ESTORNO','source_type'=>$entry['source_type'],'source_id'=>$entry['source_id'],
            'financial_category_id'=>$entry['financial_category_id'],'competence_date'=>$entry['competence_date'],'posting_date'=>date('Y-m-d'),'document_ref'=>$entry['document_ref'],
            'description'=>'Estorno: '.$reason,'reversal_of_id'=>$entryId,
        ],$lines);
        $pdo->prepare("UPDATE journal_entries SET status='REVERSED' WHERE id=?")->execute([$entryId]);
        return $reversal;
    }

    private static function category(PDO $pdo,int $categoryId,string $nature): array
    {
        if($categoryId<=0) throw new InvalidArgumentException('Selecione uma categoria financeira configurada.');
        $stmt=$pdo->prepare('SELECT c.*,a.account_class,a.normal_side,a.active account_active,a.accepts_posting,f.active financial_active FROM categories c JOIN chart_accounts a ON a.account_id=c.account_id JOIN financial_categories f ON f.category_id=c.financial_category_id WHERE c.id=? AND c.active=1 AND c.accounting_enabled=1');$stmt->execute([$categoryId]);$category=$stmt->fetch();
        if(!$category) throw new InvalidArgumentException('A categoria selecionada ainda não possui configuração contábil válida.');
        if($nature==='receivable'&&$category['normal_side']!=='C') throw new InvalidArgumentException('A categoria da receita deve estar vinculada a uma conta contábil credora.');
        if($nature==='payable'&&$category['normal_side']!=='D') throw new InvalidArgumentException('A categoria da despesa deve estar vinculada a uma conta contábil devedora.');
        return $category;
    }

    private static function postingAccount(PDO $pdo,string $accountId): array
    {
        $stmt=$pdo->prepare('SELECT * FROM chart_accounts WHERE account_id=? AND active=1 AND account_type=\'ANALITICA\' AND accepts_posting=1');$stmt->execute([$accountId]);$account=$stmt->fetch();
        if(!$account) throw new InvalidArgumentException('Uma conta contábil do lançamento não aceita partidas.');
        $tax=(string)self::settings($pdo)['tax_regime'];$rule=$account['tax_regime'];
        if($rule==='SIMPLES'&&$tax!=='SIMPLES') throw new InvalidArgumentException('A conta selecionada é exclusiva do Simples Nacional.');
        if($rule==='APURACAO_SEPARADA'&&$tax==='SIMPLES') throw new InvalidArgumentException('A conta selecionada exige apuração separada e não pode compor o DAS.');
        if($rule==='REGIME_COM_CREDITO'&&$tax!=='REAL') throw new InvalidArgumentException('A conta selecionada exige regime com direito a crédito tributário.');
        return $account;
    }

    private static function settings(PDO $pdo): array
    {
        $settings=$pdo->query('SELECT tax_regime,payable_control_account_id,receivable_control_account_id,bank_control_account_id FROM company_settings WHERE id=1')->fetch();
        if(!$settings) throw new RuntimeException('Configurações contábeis da empresa não encontradas.');
        return $settings;
    }

    private static function title(PDO $pdo,string $table,int $id): array
    {
        if(!in_array($table,['payables','receivables'],true)) throw new InvalidArgumentException('Origem contábil inválida.');
        $stmt=$pdo->prepare("SELECT * FROM $table WHERE id=? FOR UPDATE");$stmt->execute([$id]);$item=$stmt->fetch();
        if(!$item) throw new RuntimeException('Título financeiro não encontrado.');
        return $item;
    }

    private static function revision(PDO $pdo,string $sourceType,int $sourceId,string $eventType): int
    {
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM journal_entries WHERE source_type=? AND source_id=? AND event_type=? AND phase=\'RECONHECIMENTO\'');$stmt->execute([$sourceType,$sourceId,$eventType]);return (int)$stmt->fetchColumn()+1;
    }
}
