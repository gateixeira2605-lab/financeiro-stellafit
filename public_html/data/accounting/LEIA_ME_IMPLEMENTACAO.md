# KIT DE IMPORTAÇÃO — PLANO DE CONTAS 2026.09-v1

Versão de referência para software de gestão, baseada nas 55 categorias legadas. O modelo é genérico e precisa de configuração por empresa e revisão do contador habilitado antes de escrituração/demonstrações formais. NÃO IMPORTA SALDOS OU LANÇAMENTOS HISTÓRICOS; o CSV legado contém somente cadastro de categorias.

## Arquivos
1. `01_plano_contas_mestre.csv`: 211 contas, incluindo hierarquia de Ativo, Passivo, Patrimônio Líquido e Resultado. Importar por `nivel` ascendente e código. `account_id` é chave estável; `codigo` não é PK; `parent_id` FK nullable. Somente `tipo=ANALITICA`/`aceita_lancamento=Sim` recebe lançamentos. `regime_tributario`, `segmento` e `exibir_no_lancamento` são condições da interface, não exclusão física.
2. `02_migracao_55_contas.csv`: de-para completo das 55 contas, preservando ID e código legados; somente `importacao_automatica=Sim` pode migrar a categoria diretamente. `contas_destino_candidatas` é um texto humano, NÃO lista de contas para lançamento automático.
3. `03_categorias_financeiras.csv`: categorização para interface e relatórios gerenciais, separada do plano contábil.
4. `04_mapeamento_relatorios.csv`: definição das linhas para BP e DRE. As folhas referenciam `linha_relatorio`; NÃO somar folha e sintética em paralelo.
5. `05_templates_lancamentos.csv`: modelos de partidas para o motor de regras; `automatico=Não` indica que deve parametrizar cada operação antes de ativar. O mesmo evento pode ter múltiplas partidas.
6. `plano_contas_mestre.json` / `migracao_55_contas.json`: alternativa para integração via API.
7. `Plano_Contas_Importacao.xlsx`: versão legível para revisão e homologação.

## Ordem de importação
1. Cadastrar empresa, regime tributário (SIMPLES / PRESUMIDO / REAL), atividade, política contábil, competência inicial e centros de custo.
2. Importar as linhas de relatório e categorias financeiras.
3. Importar contas sintéticas/analíticas por nível (1,2,3,4) com validação da FK `parent_id`.
4. Importar o de-para legado e bloquear associações com `importacao_automatica=Não` até decisão humana.
5. Habilitar apenas contas pertinentes à empresa. Não transformar outras em inexistentes: a vigência histórica deve ser mantida.
6. Parametrizar regras de lançamento e contas financeiras/terceiros/itens do razão auxiliar.
7. Migrar transações com identificação do evento de origem, competência, vencimento, liquidação, documento, valor bruto e líquido, tributo e contrapartida, depois importar saldos iniciais conciliados e conferir balancete.

## Padrões contábeis de implementação
- Registrar cada fato em partidas dobradas; débitos = créditos; conta sintética não recebe partidas.
- Datas diferentes: data do documento, competência, vencimento, baixa/caixa e data de contabilização.
- Baixa de títulos não reconhece receita/despesa novamente.
- Fluxo de caixa considera apenas pernas de caixa e regra do evento; transferências entre contas que compõem caixa e equivalentes de caixa não integram a DFC externa.
- Em venda por cartão, recebível bruto é liquidado por caixa líquido + taxa; conciliação obrigatória.
- Compra de estoque e CMV são eventos diferentes; aquisição de ativo e depreciação são eventos diferentes; principal e juros de empréstimos também.
- Regime tributário anterior à reforma permanece configurável. DAS no Simples não deve ser duplicado por lançamentos de seus tributos integrantes. Tributos apurados/recolhidos à parte são identificados individualmente.
- Os tributos de transição da reforma (IBS/CBS) não fazem parte deste cadastro operacional legado; implementar módulo fiscal versionado quando necessário. Isso NÃO significa ignorar obrigações legais vigentes em 2026.
- Para balanço e DRE, calcular saldo por conta analítica e mapear a `linha_relatorio`; redutoras têm sinal invertido, e saldos anormais não devem ser eliminados silenciosamente.
- Fechamento: apropriar resultado conforme política contábil, preservar razão/histórico e impedir exclusões retroativas; estorno auditável.
- Centro de custo/unidade/projeto são dimensões de cada partida, não novos códigos contábeis por projeto.
- Conta 'Bancos conta movimento' exige identificação da conta financeira (auxiliar ou subconta única por banco); transferência exige origem diferente de destino.
- Categorias antigas `DARF`, `Casa`, `Ajuste saldo`, `Impostos & Taxas` e `Investimentos gerais` ficam bloqueadas para conversão automática. Conta inativa de acordos continua inativa no legado, mesmo que a conta contábil de destino exista para novos eventos documentados.

## Chave sugerida das tabelas
`accounts(account_id PK, company_id, code, parent_id, name, type, normal_side, contra_account, active, report_key, valid_from, valid_to)`; unique(company_id,code,valid_from).
`journal_entries(id,company_id,event_type,source_id,competence_date,posting_date,document_ref,status)`.
`journal_lines(id,entry_id,account_id,side,amount,financial_account_id,party_id,cost_center_id,project_id)`.
`financial_titles` e `settlements` com `journal_entry_id`; `bank_transactions` com chave externa de importação idempotente; `legacy_account_links` com `status_migracao`/ID legado.
Crie entidades por empresa e controles de permissão. Valores monetários como DECIMAL, nunca float.

## Relatórios
- DRE: receitas brutas menos deduções e custos => lucro bruto; depois despesas operacionais, outros resultados e componente financeiro, com modelo de apresentação conforme norma aplicável; tributos sobre lucro separados quando houver apuração específica. Não gerar DRE só com saldo bancário.
- BP: somar ativos, passivos e PL, considerando redutoras e encerramento do período; reconciliar A=P+PL.
- DFC: classificar eventos caixa operacionais/investimento/financiamento; não inferir fluxo só da conta de resultado.

## Testes mínimos de homologação
1. Débitos = créditos por lançamento e no balancete.
2. A = P + PL após apuração de resultados.
3. Compra de estoque -> estoque; venda -> CMV distinto.
4. Compra de ativo -> ativo, pagamento -> investimento na DFC, depreciação -> resultado sem caixa.
5. DAS apurado -> obrigação; DAS pago -> baixa de obrigação; não duplicar componentes.
6. Transferência bancária -> efeito líquido zero em caixa consolidado e zero em DRE.
7. Cartão: recebido líquido + taxa = recebível bruto.
8. Contas de origem bloqueadas não migram automaticamente.

## Referências oficiais
- ITG 1000 — modelos de plano de contas e demonstrações: https://cfc.org.br/wp-content/uploads/2023/01/ITG-1000.pdf
- ITG 2000 (R1) — escrituração contábil: https://cfc.org.br/tecnica/normas-brasileiras-de-contabilidade/normas-especificas/
- Receita Federal — reforma tributária do consumo: https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/entenda
- Receita Federal — Simples e IBS/CBS a partir de 2027: https://www.gov.br/receitafederal/pt-br/assuntos/noticias/2026/agosto/cgsn-atualiza-regras-do-simples-nacional-para-adequacao-a-reforma-tributaria-do-consumo
