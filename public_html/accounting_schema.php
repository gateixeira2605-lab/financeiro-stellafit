<?php
declare(strict_types=1);

function ensure_accounting_schema(PDO $pdo): void
{
    $version = '20260927_accounting_v1';
    if (schema_table_exists($pdo, 'schema_migrations') && schema_version_exists($pdo, $version)) {
        ensure_accounting_category_purpose_schema($pdo);
        return;
    }

    $locked = (int) $pdo->query("SELECT GET_LOCK('financontrol_accounting_migration', 30)")->fetchColumn() === 1;
    if (!$locked) throw new RuntimeException('O plano de contas está sendo preparado. Aguarde alguns segundos e tente novamente.');

    try {
        if (schema_version_exists($pdo, $version)) {
            ensure_accounting_category_purpose_schema($pdo);
            return;
        }

        schema_create_backup($pdo,'categories','schema_backup_categories_20260927');
        schema_create_backup($pdo,'payables','schema_backup_payables_20260927');
        schema_create_backup($pdo,'receivables','schema_backup_receivables_20260927');
        schema_create_backup($pdo,'financial_transactions','schema_backup_transactions_20260927');

        $pdo->exec("CREATE TABLE IF NOT EXISTS accounting_report_lines (
            line_id VARCHAR(80) PRIMARY KEY,
            company_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            report_type ENUM('BP','DRE') NOT NULL,
            description VARCHAR(200) NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL,
            calculation_rule TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            version VARCHAR(30) NOT NULL DEFAULT '2026.09-v1',
            INDEX idx_report_type_order (report_type,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS financial_categories (
            category_id VARCHAR(80) PRIMARY KEY,
            company_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            name VARCHAR(180) NOT NULL,
            dfc_default VARCHAR(40) NOT NULL,
            note TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_financial_category_name (company_id,name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS chart_accounts (
            account_id VARCHAR(80) PRIMARY KEY,
            company_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            code VARCHAR(30) NOT NULL,
            parent_id VARCHAR(80) NULL,
            name VARCHAR(200) NOT NULL,
            account_type ENUM('SINTETICA','ANALITICA') NOT NULL,
            level TINYINT UNSIGNED NOT NULL,
            account_class ENUM('ATIVO','PASSIVO_PL','RESULTADO') NOT NULL,
            normal_side ENUM('D','C') NOT NULL,
            contra_account TINYINT(1) NOT NULL DEFAULT 0,
            accepts_posting TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            show_in_entry TINYINT(1) NOT NULL DEFAULT 0,
            tax_regime VARCHAR(40) NOT NULL DEFAULT 'TODOS',
            segment VARCHAR(80) NOT NULL DEFAULT 'TODOS',
            report_type ENUM('BP','DRE') NOT NULL,
            report_line_id VARCHAR(80) NULL,
            required_auxiliary VARCHAR(80) NULL,
            notes TEXT NULL,
            version VARCHAR(30) NOT NULL,
            valid_from DATE NOT NULL DEFAULT '2026-01-01',
            valid_to DATE NULL,
            UNIQUE KEY uq_chart_code_version (company_id,code,version),
            INDEX idx_chart_parent (parent_id),
            INDEX idx_chart_posting (active,accepts_posting,show_in_entry),
            CONSTRAINT fk_chart_parent FOREIGN KEY (parent_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT,
            CONSTRAINT fk_chart_report_line FOREIGN KEY (report_line_id) REFERENCES accounting_report_lines(line_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS legacy_account_mappings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            legacy_id INT NOT NULL,
            legacy_code VARCHAR(40) NOT NULL,
            legacy_name VARCHAR(180) NOT NULL,
            legacy_group VARCHAR(180) NULL,
            legacy_indicator VARCHAR(30) NULL,
            legacy_active TINYINT(1) NOT NULL DEFAULT 1,
            migration_status VARCHAR(40) NOT NULL,
            automatic_import TINYINT(1) NOT NULL DEFAULT 0,
            target_account_id VARCHAR(80) NULL,
            candidate_accounts TEXT NULL,
            financial_category_id VARCHAR(80) NULL,
            guidance TEXT NULL,
            review_status ENUM('PENDENTE','APROVADO','BLOQUEADO') NOT NULL DEFAULT 'PENDENTE',
            reviewed_at DATETIME NULL,
            reviewed_by INT UNSIGNED NULL,
            UNIQUE KEY uq_legacy_account (company_id,legacy_id,legacy_code),
            CONSTRAINT fk_legacy_target FOREIGN KEY (target_account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT,
            CONSTRAINT fk_legacy_fin_category FOREIGN KEY (financial_category_id) REFERENCES financial_categories(category_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS posting_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            event_id VARCHAR(80) NOT NULL,
            phase VARCHAR(80) NOT NULL,
            debit_account_id VARCHAR(80) NOT NULL,
            credit_account_id VARCHAR(80) NOT NULL,
            amount_basis VARCHAR(80) NOT NULL,
            cashflow_class VARCHAR(40) NOT NULL,
            conditions TEXT NULL,
            automatic_allowed TINYINT(1) NOT NULL DEFAULT 0,
            configured TINYINT(1) NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_posting_template (company_id,event_id,phase,debit_account_id,credit_account_id),
            CONSTRAINT fk_template_debit FOREIGN KEY (debit_account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT,
            CONSTRAINT fk_template_credit FOREIGN KEY (credit_account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS journal_entries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            event_key VARCHAR(190) NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            phase VARCHAR(40) NOT NULL,
            source_type VARCHAR(40) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            financial_category_id VARCHAR(80) NULL,
            competence_date DATE NOT NULL,
            posting_date DATE NOT NULL,
            document_ref VARCHAR(120) NULL,
            description VARCHAR(255) NOT NULL,
            status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
            reversal_of_id BIGINT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_journal_event_key (company_id,event_key),
            INDEX idx_journal_period (competence_date,posting_date,status),
            INDEX idx_journal_source (source_type,source_id),
            CONSTRAINT fk_journal_fin_category FOREIGN KEY (financial_category_id) REFERENCES financial_categories(category_id) ON DELETE RESTRICT,
            CONSTRAINT fk_journal_reversal FOREIGN KEY (reversal_of_id) REFERENCES journal_entries(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS journal_lines (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            account_id VARCHAR(80) NOT NULL,
            side ENUM('D','C') NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            bank_account_id INT UNSIGNED NULL,
            contact_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NULL,
            cost_center VARCHAR(120) NULL,
            project VARCHAR(120) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_journal_line_account (account_id),
            INDEX idx_journal_line_dimensions (bank_account_id,contact_id,category_id),
            CONSTRAINT fk_journal_line_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT,
            CONSTRAINT fk_journal_line_account FOREIGN KEY (account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT,
            CONSTRAINT fk_journal_line_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE RESTRICT,
            CONSTRAINT fk_journal_line_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE RESTRICT,
            CONSTRAINT fk_journal_line_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            ['company_settings','tax_regime',"VARCHAR(30) NOT NULL DEFAULT 'NAO_CONFIGURADO'"],
            ['company_settings','business_activity','VARCHAR(160) NULL'],
            ['company_settings','accounting_policy','VARCHAR(160) NULL'],
            ['company_settings','accounting_start_date','DATE NULL'],
            ['company_settings','payable_control_account_id','VARCHAR(80) NULL'],
            ['company_settings','receivable_control_account_id','VARCHAR(80) NULL'],
            ['company_settings','bank_control_account_id','VARCHAR(80) NULL'],
            ['categories','financial_category_id','VARCHAR(80) NULL'],
            ['categories','account_id','VARCHAR(80) NULL'],
            ['categories','control_account_id','VARCHAR(80) NULL'],
            ['categories','legacy_account_id','INT NULL'],
            ['categories','migration_status',"VARCHAR(40) NOT NULL DEFAULT 'NAO_MIGRADA'"],
            ['categories','accounting_enabled','TINYINT(1) NOT NULL DEFAULT 0'],
            ['categories','active','TINYINT(1) NOT NULL DEFAULT 1'],
            ['payables','document_date','DATE NULL'],
            ['payables','competence_date','DATE NULL'],
            ['payables','document_ref','VARCHAR(120) NULL'],
            ['payables','journal_entry_id','BIGINT UNSIGNED NULL'],
            ['payables','accounting_status',"VARCHAR(30) NOT NULL DEFAULT 'LEGACY_UNPOSTED'"],
            ['receivables','document_date','DATE NULL'],
            ['receivables','competence_date','DATE NULL'],
            ['receivables','document_ref','VARCHAR(120) NULL'],
            ['receivables','journal_entry_id','BIGINT UNSIGNED NULL'],
            ['receivables','accounting_status',"VARCHAR(30) NOT NULL DEFAULT 'LEGACY_UNPOSTED'"],
            ['financial_transactions','journal_entry_id','BIGINT UNSIGNED NULL'],
            ['bank_balance_adjustments','account_id','VARCHAR(80) NULL'],
            ['bank_balance_adjustments','journal_entry_id','BIGINT UNSIGNED NULL'],
        ] as [$table,$column,$definition]) schema_add_column($pdo,$table,$column,$definition);

        foreach ([
            ['categories','idx_category_accounting','(active,accounting_enabled)'],
            ['payables','idx_payable_journal','(journal_entry_id)'],
            ['receivables','idx_receivable_journal','(journal_entry_id)'],
            ['financial_transactions','idx_transaction_journal','(journal_entry_id)'],
        ] as [$table,$name,$columns]) if (!schema_index_exists($pdo,$table,$name)) $pdo->exec("ALTER TABLE $table ADD INDEX $name $columns");

        foreach ([
            ['categories','fk_category_financial_master','FOREIGN KEY (financial_category_id) REFERENCES financial_categories(category_id) ON DELETE RESTRICT'],
            ['categories','fk_category_chart_account','FOREIGN KEY (account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT'],
            ['categories','fk_category_control_account','FOREIGN KEY (control_account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT'],
            ['payables','fk_payable_journal','FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT'],
            ['receivables','fk_receivable_journal','FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT'],
            ['financial_transactions','fk_transaction_journal','FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT'],
            ['bank_balance_adjustments','fk_adjustment_account','FOREIGN KEY (account_id) REFERENCES chart_accounts(account_id) ON DELETE RESTRICT'],
            ['bank_balance_adjustments','fk_adjustment_journal','FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT'],
        ] as [$table,$name,$definition]) if(!schema_constraint_exists($pdo,$table,$name)) $pdo->exec("ALTER TABLE $table ADD CONSTRAINT $name $definition");

        accounting_seed_master_data($pdo);
        accounting_seed_category_links($pdo);
        $pdo->exec("UPDATE company_settings SET payable_control_account_id=COALESCE(payable_control_account_id,'PC_2_1_01_002'),receivable_control_account_id=COALESCE(receivable_control_account_id,'PC_1_1_02_002'),bank_control_account_id=COALESCE(bank_control_account_id,'PC_1_1_01_002') WHERE id=1");
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    } catch (Throwable $exception) {
        error_log('Falha ao instalar o plano de contas: '.$exception->getMessage());
        throw new RuntimeException('Não foi possível preparar o novo plano de contas.',0,$exception);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('financontrol_accounting_migration')");
    }
    ensure_accounting_category_purpose_schema($pdo);
}

function ensure_accounting_category_purpose_schema(PDO $pdo): void
{
    $version='20260927_accounting_purposes_v1';
    if(schema_version_exists($pdo,$version))return;
    $locked=(int)$pdo->query("SELECT GET_LOCK('financontrol_accounting_purposes',30)")->fetchColumn()===1;
    if(!$locked)throw new RuntimeException('As categorias simplificadas estão sendo preparadas. Aguarde alguns segundos e tente novamente.');
    try{
        if(schema_version_exists($pdo,$version))return;
        schema_add_column($pdo,'categories','purpose_key','VARCHAR(80) NULL');
        if(!schema_index_exists($pdo,'categories','uq_category_purpose'))$pdo->exec('ALTER TABLE categories ADD UNIQUE INDEX uq_category_purpose (purpose_key)');

        $find=$pdo->prepare("SELECT id FROM categories WHERE purpose_key=? OR name=? OR (purpose_key IS NULL AND account_id=?) ORDER BY CASE WHEN purpose_key=? THEN 0 WHEN name=? THEN 1 ELSE 2 END,id LIMIT 1");
        $update=$pdo->prepare("UPDATE categories SET purpose_key=?,type=?,classification=?,financial_category_id=?,account_id=?,control_account_id=?,accounting_enabled=1,active=1,migration_status='CONFIGURADA_SIMPLIFICADA' WHERE id=?");
        $insert=$pdo->prepare("INSERT INTO categories (name,type,classification,financial_category_id,account_id,control_account_id,purpose_key,accounting_enabled,active,migration_status) VALUES (?,?,?,?,?,?,?,1,1,'CONFIGURADA_SIMPLIFICADA')");
        foreach(accounting_category_purposes() as $key=>$purpose){
            $find->execute([$key,$purpose['label'],$purpose['account_id'],$key,$purpose['label']]);
            $id=$find->fetchColumn();
            if($id){
                $update->execute([$key,$purpose['type'],$purpose['classification'],$purpose['financial_category_id'],$purpose['account_id'],$purpose['control_account_id']??null,(int)$id]);
            }else{
                $insert->execute([$purpose['label'],$purpose['type'],$purpose['classification'],$purpose['financial_category_id'],$purpose['account_id'],$purpose['control_account_id']??null,$key]);
            }
        }
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    }finally{
        $pdo->query("SELECT RELEASE_LOCK('financontrol_accounting_purposes')");
    }
}

function accounting_csv_rows(string $file): array
{
    $path=__DIR__.'/data/accounting/'.$file;
    $handle=fopen($path,'rb');
    if($handle===false) throw new RuntimeException('Arquivo contábil não encontrado: '.$file);
    $header=fgetcsv($handle,0,';','"','\\');
    if($header===false){fclose($handle);throw new RuntimeException('Arquivo contábil vazio: '.$file);}
    $header[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$header[0])??$header[0];
    $rows=[];
    while(($values=fgetcsv($handle,0,';','"','\\'))!==false){
        if(count($values)!==count($header)) continue;
        $rows[]=array_combine($header,$values);
    }
    fclose($handle);
    return $rows;
}

function accounting_yes(?string $value): int { return mb_strtolower(trim((string)$value),'UTF-8')==='sim'?1:0; }

function accounting_seed_master_data(PDO $pdo): void
{
    $report=$pdo->prepare("INSERT INTO accounting_report_lines (line_id,report_type,description,sort_order,calculation_rule) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE report_type=VALUES(report_type),description=VALUES(description),sort_order=VALUES(sort_order),calculation_rule=VALUES(calculation_rule)");
    foreach(accounting_csv_rows('04_mapeamento_relatorios.csv') as $r) $report->execute([$r['linha_id'],$r['relatorio'],$r['descricao'],(int)$r['ordem'],$r['formula_ou_regra']]);

    $financial=$pdo->prepare("INSERT INTO financial_categories (category_id,name,dfc_default,note) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),dfc_default=VALUES(dfc_default),note=VALUES(note)");
    foreach(accounting_csv_rows('03_categorias_financeiras.csv') as $r) $financial->execute([$r['category_id'],$r['nome'],$r['dfc_default'],$r['nota']]);

    $account=$pdo->prepare("INSERT INTO chart_accounts (account_id,code,parent_id,name,account_type,level,account_class,normal_side,contra_account,accepts_posting,active,show_in_entry,tax_regime,segment,report_type,report_line_id,required_auxiliary,notes,version) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE code=VALUES(code),parent_id=VALUES(parent_id),name=VALUES(name),account_type=VALUES(account_type),level=VALUES(level),account_class=VALUES(account_class),normal_side=VALUES(normal_side),contra_account=VALUES(contra_account),accepts_posting=VALUES(accepts_posting),show_in_entry=VALUES(show_in_entry),tax_regime=VALUES(tax_regime),segment=VALUES(segment),report_type=VALUES(report_type),report_line_id=VALUES(report_line_id),required_auxiliary=VALUES(required_auxiliary),notes=VALUES(notes)");
    foreach(accounting_csv_rows('01_plano_contas_mestre.csv') as $r) $account->execute([$r['account_id'],$r['codigo'],$r['parent_id']?:null,$r['nome'],$r['tipo'],(int)$r['nivel'],$r['classe'],$r['natureza_normal'],accounting_yes($r['conta_redutora']),accounting_yes($r['aceita_lancamento']),accounting_yes($r['ativa']),accounting_yes($r['exibir_no_lancamento']),$r['regime_tributario'],$r['segmento'],$r['relatorio'],$r['linha_relatorio']?:null,$r['exige_auxiliar']?:null,$r['observacoes']?:null,$r['versao']]);

    $findAccount=$pdo->prepare('SELECT account_id FROM chart_accounts WHERE code=? ORDER BY version DESC LIMIT 1');
    $legacy=$pdo->prepare("INSERT INTO legacy_account_mappings (legacy_id,legacy_code,legacy_name,legacy_group,legacy_indicator,legacy_active,migration_status,automatic_import,target_account_id,candidate_accounts,financial_category_id,guidance,review_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE legacy_name=VALUES(legacy_name),legacy_group=VALUES(legacy_group),legacy_indicator=VALUES(legacy_indicator),legacy_active=VALUES(legacy_active),migration_status=VALUES(migration_status),automatic_import=VALUES(automatic_import),target_account_id=VALUES(target_account_id),candidate_accounts=VALUES(candidate_accounts),financial_category_id=VALUES(financial_category_id),guidance=VALUES(guidance)");
    foreach(accounting_csv_rows('02_migracao_55_contas.csv') as $r){$target=null;if($r['codigo_novo_principal']!==''){$findAccount->execute([$r['codigo_novo_principal']]);$target=$findAccount->fetchColumn()?:null;}$auto=accounting_yes($r['importacao_automatica']);$legacy->execute([(int)$r['id_legado'],$r['codigo_legado'],$r['nome_legado'],$r['grupo_legado'],$r['indicador_legado'],accounting_yes($r['ativo_legado']),$r['status_migracao'],$auto,$target,$r['contas_destino_candidatas']?:null,$r['categoria_financeira']?:null,$r['orientacao']?:null,$auto?'APROVADO':($r['status_migracao']==='BLOQUEADO'?'BLOQUEADO':'PENDENTE')]);}

    $template=$pdo->prepare("INSERT INTO posting_templates (event_id,phase,debit_account_id,credit_account_id,amount_basis,cashflow_class,conditions,automatic_allowed,configured,enabled) VALUES (?,?,?,?,?,?,?,0,0,0) ON DUPLICATE KEY UPDATE amount_basis=VALUES(amount_basis),cashflow_class=VALUES(cashflow_class),conditions=VALUES(conditions),automatic_allowed=0");
    foreach(accounting_csv_rows('05_templates_lancamentos.csv') as $r){$findAccount->execute([$r['debito_codigo']]);$debit=$findAccount->fetchColumn();$findAccount->execute([$r['credito_codigo']]);$credit=$findAccount->fetchColumn();if($debit&&$credit)$template->execute([$r['evento_id'],$r['fase'],$debit,$credit,$r['base_valor'],$r['classe_fluxo_caixa'],$r['condicoes']]);}
}

function accounting_seed_category_links(PDO $pdo): void
{
    $classification=function(string $code,string $financial):string{
        if(str_starts_with($code,'3.1.01')||str_starts_with($code,'3.4.01')||str_starts_with($code,'3.5.01')) return str_starts_with($code,'3.1.01')?'receita_operacional':'receita_nao_operacional';
        if(str_starts_with($code,'1.2.')) return 'investimento';
        return in_array($financial,['FIN_ADMIN','FIN_COMERCIAL','FIN_PESSOAL','FIN_FIN_RESULT'],true)?'despesa_administrativa':'despesa_operacional';
    };
    $find=$pdo->prepare('SELECT account_id,code FROM chart_accounts WHERE account_id=?');
    $upsert=$pdo->prepare("INSERT INTO categories (name,type,classification,financial_category_id,account_id,legacy_account_id,migration_status,accounting_enabled,active) VALUES (?,'variavel',?,?,?,?,'MIGRADA_AUTOMATICAMENTE',1,1) ON DUPLICATE KEY UPDATE financial_category_id=IFNULL(financial_category_id,VALUES(financial_category_id)),accounting_enabled=IF(account_id IS NULL,1,accounting_enabled),migration_status=IF(account_id IS NULL,VALUES(migration_status),migration_status),account_id=IFNULL(account_id,VALUES(account_id)),legacy_account_id=IFNULL(legacy_account_id,VALUES(legacy_account_id))");
    foreach($pdo->query("SELECT legacy_id,legacy_name,target_account_id,financial_category_id FROM legacy_account_mappings WHERE automatic_import=1 AND target_account_id IS NOT NULL")->fetchAll() as $row){$find->execute([$row['target_account_id']]);$a=$find->fetch();if($a)$upsert->execute([$row['legacy_name'],$classification($a['code'],$row['financial_category_id']),$row['financial_category_id'],$row['target_account_id'],(int)$row['legacy_id']]);}

    $known=[
        'Aluguel'=>['PC_3_3_04_001','FIN_OCUPACAO'],'Energia'=>['PC_3_3_04_008','FIN_OCUPACAO'],'Água'=>['PC_3_3_04_004','FIN_OCUPACAO'],'Internet'=>['PC_3_3_04_007','FIN_ADMIN'],
        'Folha de Pagamento'=>['PC_3_3_03_002','FIN_PESSOAL'],'Material de Limpeza'=>['PC_3_3_04_005','FIN_OCUPACAO'],'Manutenção'=>['PC_3_3_04_009','FIN_OCUPACAO'],'Equipamentos'=>['PC_1_2_03_001','FIN_CAPEX'],
        'Marketing'=>['PC_3_3_01_004','FIN_COMERCIAL'],'Mensalidades'=>['PC_3_1_01_003','FIN_REC_OPER'],'Day Use'=>['PC_3_1_01_004','FIN_REC_OPER'],'Personal Trainer'=>['PC_3_1_01_002','FIN_REC_OPER'],
    ];
    $update=$pdo->prepare("UPDATE categories SET account_id=?,financial_category_id=?,migration_status='CONFIGURADA_SISTEMA',accounting_enabled=1 WHERE name=? AND account_id IS NULL");
    foreach($known as $name=>[$accountId,$financialId]) $update->execute([$accountId,$financialId,$name]);
}
