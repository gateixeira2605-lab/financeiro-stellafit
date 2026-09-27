<?php
declare(strict_types=1);

require dirname(__DIR__).'/accounting_schema.php';

$master=accounting_csv_rows('01_plano_contas_mestre.csv');
$legacy=accounting_csv_rows('02_migracao_55_contas.csv');
$financial=accounting_csv_rows('03_categorias_financeiras.csv');
$reports=accounting_csv_rows('04_mapeamento_relatorios.csv');
$templates=accounting_csv_rows('05_templates_lancamentos.csv');

$assert=static function(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}};
$assert(count($master)===211,'o plano mestre deve conter 211 contas');
$assert(count($legacy)===55,'o de-para deve conter 55 contas legadas');
$assert(count($financial)===18,'devem existir 18 categorias financeiras');
$assert(count($reports)===32,'devem existir 32 linhas de relatório');
$assert(count($templates)===27,'devem existir 27 modelos de partidas');

$ids=array_column($master,null,'account_id');$codes=array_column($master,null,'codigo');$reportIds=array_column($reports,null,'linha_id');
foreach($master as $account){
    $assert($account['parent_id']===''||isset($ids[$account['parent_id']]),'conta pai inexistente em '.$account['account_id']);
    $posting=$account['aceita_lancamento']==='Sim';$assert($posting===($account['tipo']==='ANALITICA'),'tipo/aceite divergente em '.$account['account_id']);
    if($account['tipo']==='ANALITICA')$assert($account['linha_relatorio']!==''&&isset($reportIds[$account['linha_relatorio']]),'linha de relatório inválida em '.$account['account_id']);
}
foreach($templates as $template){
    $assert(isset($codes[$template['debito_codigo']]),'débito desconhecido em '.$template['evento_id']);
    $assert(isset($codes[$template['credito_codigo']]),'crédito desconhecido em '.$template['evento_id']);
    $assert(!str_starts_with($template['automatico'],'Sim'),'modelo não pode ser ativado automaticamente');
}
foreach($legacy as $mapping)if($mapping['importacao_automatica']==='Sim')$assert($mapping['codigo_novo_principal']!==''&&isset($codes[$mapping['codigo_novo_principal']]),'de-para automático sem destino: '.$mapping['id_legado']);

echo "Plano de contas validado: 211 contas, 55 vínculos, 18 categorias, 32 linhas e 27 modelos.\n";
