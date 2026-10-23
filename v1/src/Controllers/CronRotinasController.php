<?php
namespace Nutricional\Controllers;

use PHPMailer\PHPMailer\PHPMailer;

class CronRotinasController
{
    /** Rotinas implementadas: comando => [nome, descricao]. Base do seletor de "Novo Job" no Admin. */
    public const ROTINAS = [
        'representantes'        => ['Representantes (Alterações)', 'Envia relatório diário de alterações em pedidos'],
        'representantes_mensal' => ['Representantes (Mensal)', 'Envia relatório mensal de inadimplência individual'],
        'gestores'              => ['Gestores (Inadimplência)', 'Envia relatório de inadimplência no último dia útil'],
        'historico_kpi'         => ['Histórico KPI Financeiro', 'Registra histórico diário de performance e inadimplência'],
        'clientes_isentos'      => ['Clientes Isentos ST', 'Gera planilha e envia relatório de clientes isentos de ST'],
        'notas_nutrire'         => ['Notas Nutrire (GoFind)', 'Envia notas fiscais (marca 8 Nutrire) para a API GoFind'],
        'flex_minimo_gestor'    => ['Flex Mínimo Gestor', 'Gera empréstimo automático de verba flex quando saldo baixo'],
        'bonificacoes_flex'     => ['Bonificações Flex', 'Processa débito de notas bonificadas na API Mercos Flex'],
        'alerta_certificado'    => ['Alerta Certificado Digital', 'Verifica vencimento dos certificados digitais NFe das filiais'],
        'refresh_cubo_vendas'   => ['Atualizar Cubo de Vendas', 'Executa refresh_cubo_vendas() no banco'],
    ];

    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    /**
     * Dispara a rotina pelo comando
     */
    public function executarRotina(string $comando, array $parametros = [], string $usuario = 'SISTEMA', string $ip = 'CLI'): array
    {
        $params = $parametros;

        switch ($comando) {
            case 'refresh_cubo_vendas':
                try {
                    $msg = $this->pdo->query("SELECT refresh_cubo_vendas()")->fetchColumn();
                    return ['success' => true, 'message' => is_string($msg) && $msg !== '' ? $msg : 'Cubo de vendas atualizado'];
                } catch (\Throwable $e) {
                    return ['success' => false, 'message' => 'Falha ao atualizar cubo: ' . $e->getMessage()];
                }

            case 'representantes':
                return $this->executarRepresentantes($usuario, $ip);

            case 'representantes_mensal':
                return $this->executarRepresentantesMensal($params, $usuario, $ip);

            case 'gestores':
                return $this->executarGestores($usuario, $ip, $params);

            case 'historico_kpi':
                return $this->executarHistoricoKPI($usuario, $ip);

            case 'clientes_isentos':
                return $this->executarClientesIsentos($params, $usuario, $ip);

            case 'notas_nutrire':
                return $this->executarNotasNutrire($params, $usuario, $ip);

            case 'flex_minimo_gestor':
                return $this->executarFlexMinimoGestor($params, $usuario, $ip);

            case 'bonificacoes_flex':
                return $this->executarBonificacoesFlex($params, $usuario, $ip);

            case 'alerta_certificado':
                return $this->executarAlertaCertificado($params, $usuario, $ip);

            default:
                return ['success' => false, 'message' => "Comando '$comando' não implementado"];
        }
    }

    /**
     * Simula / Testa uma rotina em modo 100% SOMENTE LEITURA (Dry-Run)
     * Sem descontar saldos, sem gravar logs destrutivos e sem disparar e-mails para clientes.
     */
    public function testarRotina(string $comando, array $parametros = []): array
    {
        $checks = [];

        switch ($comando) {
            case 'bonificacoes_flex':
                return $this->simularBonificacoesFlex($parametros);

            case 'flex_minimo_gestor':
                try {
                    $api1 = new \Nutricional\Services\MercosFlexService(1);
                    $sonda1 = $api1->sonda();
                    $checks[] = [
                        'etapa' => 'API Mercos Flex (Filial 1)',
                        'status' => !empty($sonda1['ok']) ? 'ok' : 'error',
                        'detalhe' => !empty($sonda1['ok'])
                            ? "Online ({$sonda1['ms']}ms, " . count($sonda1['saldos'] ?? []) . " colaboradores)"
                            : "Falha: " . ($sonda1['erro'] ?? 'Indisponível')
                    ];
                    $gestorId = (int)($parametros['gestor_id'] ?? 12335);
                    $valorMin = (float)($parametros['valor_minimo'] ?? 5000);
                    $valorEmp = (float)($parametros['valor_emprestimo'] ?? 5000);
                    $saldo = isset($sonda1['saldos'][$gestorId]['saldo_atual'])
                        ? round((float)$sonda1['saldos'][$gestorId]['saldo_atual'], 2)
                        : $api1->saldoDe($gestorId, true);
                    $precisa = ($saldo !== null && $saldo < $valorMin);

                    $checks[] = [
                        'etapa' => "Saldo Gestor ({$gestorId})",
                        'status' => $saldo !== null ? 'ok' : 'error',
                        'detalhe' => $saldo !== null
                            ? "Saldo atual: " . $this->moedaBonif((float)$saldo) . " | Mínimo configurado: " . $this->moedaBonif($valorMin)
                            : "Gestor {$gestorId} não localizado na API Mercos"
                    ];

                    $htmlSim = "<div style='font-family:Inter,Arial,sans-serif;font-size:13px;line-height:1.6'>"
                        . "<b>Simulação Flex Mínimo Gestor (Dry-Run):</b><ul>"
                        . "<li>Gestor ID: <b>{$gestorId}</b></li>"
                        . "<li>Saldo Atual na Mercos: <b>" . $this->moedaBonif((float)$saldo) . "</b></li>"
                        . "<li>Mínimo Exigido: <b>" . $this->moedaBonif($valorMin) . "</b></li>"
                        . "<li>Ação Simulada: <b>" . ($precisa ? "Faria empréstimo de " . $this->moedaBonif($valorEmp) : "Nenhuma ação necessária (saldo suficiente)") . "</b></li>"
                        . "</ul></div>";

                    return [
                        'success' => !empty($sonda1['ok']) && $saldo !== null,
                        'message' => $precisa
                            ? "Simulação concluída: Gestor com saldo abaixo do mínimo (" . $this->moedaBonif((float)$saldo) . "). Seria gerado empréstimo de " . $this->moedaBonif($valorEmp) . "."
                            : "Simulação concluída: Saldo do gestor (" . $this->moedaBonif((float)$saldo) . ") está acima do mínimo.",
                        'checks' => $checks,
                        'detalhes' => [
                            'gestor_id' => $gestorId,
                            'saldo_atual' => $saldo,
                            'valor_minimo' => $valorMin,
                            'acao_prevista' => $precisa ? 'emprestimo' : 'nenhuma'
                        ],
                        'preview_html' => $htmlSim
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'message' => 'Erro na simulação: ' . $e->getMessage(),
                        'checks' => [['etapa' => 'Simulação Flex Mínimo', 'status' => 'error', 'detalhe' => $e->getMessage()]]
                    ];
                }

            case 'representantes':
                try {
                    $sql = "SELECT COUNT(DISTINCT pedido.idvendrepre) AS total_repres, COUNT(*) AS total_itens
                            FROM PEDIDO_ITEM_LOG l
                            LEFT JOIN item ON (l.iditemold = item.iditem)
                            LEFT JOIN pedido ON (l.idpedido = pedido.idpedido)
                            WHERE l.motivo LIKE '%u item%'
                              AND l.quant_old <> l.quant_new
                              AND l.datahora >= CURRENT_DATE - INTERVAL '1 days'
                              AND pedido.status IN (5)
                              AND item.iditem NOT IN (2181, 1552, 3058)
                              AND item.tipo IN (0, 2, 11)
                              AND pedido.idvendrepre NOT IN (10119)
                              AND pedido.idfilial IN (1, 6)";
                    $row = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
                    $totRep = (int)($row['total_repres'] ?? 0);
                    $totItens = (int)($row['total_itens'] ?? 0);
                    $checks[] = [
                        'etapa' => 'Consulta PEDIDO_ITEM_LOG (Últimas 24h)',
                        'status' => 'ok',
                        'detalhe' => "Encontrados {$totItens} itens alterados distribuídos em {$totRep} representante(s)."
                    ];
                    return [
                        'success' => true,
                        'message' => "Simulação OK: {$totRep} representante(s) e {$totItens} alteração(ões) de pedido encontradas nas últimas 24h.",
                        'checks' => $checks,
                        'detalhes' => ['representantes_afetados' => $totRep, 'itens_alterados' => $totItens]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'message' => 'Falha ao consultar PEDIDO_ITEM_LOG: ' . $e->getMessage(),
                        'checks' => [['etapa' => 'Banco de Dados', 'status' => 'error', 'detalhe' => $e->getMessage()]]
                    ];
                }

            case 'gestores':
                try {
                    $ehUltimoDia = method_exists('\Uteis', 'isUltimoDiaUtil') ? \Uteis::isUltimoDiaUtil() : false;
                    $checks[] = [
                        'etapa' => 'Regra de Calendário (Último Dia Útil)',
                        'status' => 'ok',
                        'detalhe' => $ehUltimoDia
                            ? 'Hoje É o último dia útil do mês (o envio seria disparado).'
                            : 'Hoje NÃO é o último dia útil do mês (a rotina encerraria sem enviar e-mails).'
                    ];
                    return [
                        'success' => true,
                        'message' => 'Configuração validada com sucesso.',
                        'checks' => $checks,
                        'detalhes' => ['hoje_ultimo_dia_util' => $ehUltimoDia]
                    ];
                } catch (\Throwable $e) {
                    return ['success' => false, 'message' => $e->getMessage(), 'checks' => $checks];
                }

            case 'historico_kpi':
                try {
                    $jaExiste = (int)$this->pdo->query("SELECT COUNT(*) FROM analise_kpi_historico WHERE data_registro = CURRENT_DATE")->fetchColumn();
                    $checks[] = [
                        'etapa' => 'Tabela analise_kpi_historico',
                        'status' => 'ok',
                        'detalhe' => $jaExiste > 0
                            ? "Já existem {$jaExiste} registros gravados na data de hoje (CURRENT_DATE)."
                            : "Ainda não há registros para hoje — a execução gravará a fotografia diária."
                    ];
                    return [
                        'success' => true,
                        'message' => 'Simulação do Histórico KPI validada com sucesso.',
                        'checks' => $checks,
                        'detalhes' => ['registros_hoje' => $jaExiste]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'message' => 'Erro ao verificar analise_kpi_historico: ' . $e->getMessage(),
                        'checks' => [['etapa' => 'Tabela analise_kpi_historico', 'status' => 'error', 'detalhe' => $e->getMessage()]]
                    ];
                }

            case 'notas_nutrire':
                try {
                    $dias = (int)($parametros['dias_retroativos'] ?? 5);
                    $sql = "SELECT COUNT(DISTINCT nota.numnota) AS total_notas
                            FROM nota
                            WHERE nota.idfilial IN (1)
                              AND nota.status IN (2)
                              AND nota.idtiponota IN (6, 37, 2)
                              AND nota.emissao >= CURRENT_DATE - INTERVAL '{$dias} days'";
                    $totNotas = (int)$this->pdo->query($sql)->fetchColumn();
                    $checks[] = [
                        'etapa' => "Consulta Notas Fiscais (Últimos {$dias} dias)",
                        'status' => 'ok',
                        'detalhe' => "Localizadas {$totNotas} nota(s) emitidas nos últimos {$dias} dias."
                    ];
                    return [
                        'success' => true,
                        'message' => "Simulação concluída: {$totNotas} nota(s) encontradas no período de {$dias} dias.",
                        'checks' => $checks,
                        'detalhes' => ['notas_periodo' => $totNotas, 'dias_retroativos' => $dias]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'message' => 'Erro ao consultar notas: ' . $e->getMessage(),
                        'checks' => [['etapa' => 'Consulta Notas Nutrire', 'status' => 'error', 'detalhe' => $e->getMessage()]]
                    ];
                }

            case 'refresh_cubo_vendas':
                try {
                    $fnExists = (int)$this->pdo->query("SELECT COUNT(*) FROM pg_proc WHERE proname = 'refresh_cubo_vendas'")->fetchColumn();
                    $checks[] = [
                        'etapa' => 'Função PostgreSQL refresh_cubo_vendas()',
                        'status' => $fnExists > 0 ? 'ok' : 'error',
                        'detalhe' => $fnExists > 0
                            ? 'Função refresh_cubo_vendas() encontrada no banco de dados.'
                            : 'Função refresh_cubo_vendas() NÃO encontrada no banco.'
                    ];
                    return [
                        'success' => $fnExists > 0,
                        'message' => $fnExists > 0 ? 'Função refresh_cubo_vendas() pronta para execução.' : 'Função refresh_cubo_vendas() ausente.',
                        'checks' => $checks
                    ];
                } catch (\Throwable $e) {
                    return ['success' => false, 'message' => $e->getMessage(), 'checks' => $checks];
                }

            case 'alerta_certificado':
                return $this->simularAlertaCertificado($parametros);

            case 'clientes_isentos':
                try {
                    $sql = "SELECT COUNT(*) FROM cliforemp cli 
                            WHERE (cli.ie LIKE '%ISENTO%' OR cli.ie = '' OR cli.ie IS NULL)
                              AND cli.tipocliforemp = 0 
                              AND cli.idfilial IN (1, 6)
                              AND cli.inativo = 'N'";
                    $tot = (int)$this->pdo->query($sql)->fetchColumn();
                    $checks[] = [
                        'etapa' => 'Clientes Isentos no ERP',
                        'status' => 'ok',
                        'detalhe' => "Localizados {$tot} clientes com IE Isento/Nulo ativos nas filiais 1 e 6."
                    ];
                    return [
                        'success' => true,
                        'message' => "Simulação concluída: {$tot} clientes seriam incluídos na planilha de isentos.",
                        'checks' => $checks,
                        'detalhes' => ['total_isentos' => $tot]
                    ];
                } catch (\Throwable $e) {
                    return ['success' => false, 'message' => 'Erro: ' . $e->getMessage(), 'checks' => [['etapa' => 'Consulta Isentos', 'status' => 'error', 'detalhe' => $e->getMessage()]]];
                }

            case 'representantes_mensal':
                try {
                    $sql = "SELECT COUNT(DISTINCT c.idcliforemp) FROM cliforemp c
                            JOIN fornecedor f ON f.idcliforemp = c.idcliforemp
                            WHERE f.representante = 'S' AND c.inativo = 'N' AND c.email IS NOT NULL AND c.email != ''";
                    $tot = (int)$this->pdo->query($sql)->fetchColumn();
                    $checks[] = [
                        'etapa' => 'Representantes Ativos com E-mail',
                        'status' => 'ok',
                        'detalhe' => "Encontrados {$tot} representantes ativos elegíveis para o relatório mensal."
                    ];
                    return [
                        'success' => true,
                        'message' => "Simulação concluída: {$tot} representantes seriam processados.",
                        'checks' => $checks,
                        'detalhes' => ['total_representantes' => $tot]
                    ];
                } catch (\Throwable $e) {
                    return ['success' => false, 'message' => 'Erro: ' . $e->getMessage(), 'checks' => [['etapa' => 'Consulta Representantes', 'status' => 'error', 'detalhe' => $e->getMessage()]]];
                }

            default:
                return [
                    'success' => false,
                    'message' => "Rotina '{$comando}' não possui simulador configurado."
                ];
        }
    }

    /**
     * Simulação completa (Dry-Run 100% leitura) da rotina bonificacoes_flex
     */
    private function simularBonificacoesFlex(array $params = []): array
    {
        $checks = [];
        try {
            // 1. Verificar View verificabonificacao e tabelas de log
            $rows = $this->pdo->query("
                SELECT DISTINCT idfilial, idvendrepre, vendedor, valor, status, motivo
                FROM verificabonificacao
                ORDER BY idvendrepre ASC
            ")->fetchAll(\PDO::FETCH_ASSOC);

            $checks[] = [
                'etapa' => 'View verificabonificacao (PostgreSQL)',
                'status' => 'ok',
                'detalhe' => count($rows) . ' registro(s) encontrado(s) na view no momento.'
            ];

            $totLogs = (int)$this->pdo->query("SELECT COUNT(*) FROM log_flex_bonificacao")->fetchColumn();
            $checks[] = [
                'etapa' => 'Tabela log_flex_bonificacao',
                'status' => 'ok',
                'detalhe' => "Tabela acessível ({$totLogs} bonificações históricas registradas)."
            ];

            // 2. Testar conectividade com a API Mercos (Filial 1 e Filial 6) e ler saldo dos Gestores
            $api1 = new \Nutricional\Services\MercosFlexService(1);
            $api6 = new \Nutricional\Services\MercosFlexService(6);
            $sonda1 = $api1->sonda();
            $sonda6 = $api6->sonda();

            $saldoGestor1 = null;
            $saldoGestor6 = null;

            if (!empty($sonda1['ok'])) {
                $saldoGestor1 = isset($sonda1['saldos'][12335]['saldo_atual'])
                    ? round((float)$sonda1['saldos'][12335]['saldo_atual'], 2)
                    : null;
                $checks[] = [
                    'etapa' => 'API Mercos Filial 1 (Gestor 12335)',
                    'status' => 'ok',
                    'detalhe' => "Online ({$sonda1['ms']}ms) | Saldo Gestor 12335: " . ($saldoGestor1 !== null ? $this->moedaBonif($saldoGestor1) : 'N/A')
                ];
            } else {
                $checks[] = [
                    'etapa' => 'API Mercos Filial 1',
                    'status' => 'error',
                    'detalhe' => 'Offline: ' . ($sonda1['erro'] ?? 'Falha de conexão')
                ];
            }

            if (!empty($sonda6['ok'])) {
                $saldoGestor6 = isset($sonda6['saldos'][15816]['saldo_atual'])
                    ? round((float)$sonda6['saldos'][15816]['saldo_atual'], 2)
                    : null;
                $checks[] = [
                    'etapa' => 'API Mercos Filial 6 (Gestor 15816)',
                    'status' => 'ok',
                    'detalhe' => "Online ({$sonda6['ms']}ms) | Saldo Gestor 15816: " . ($saldoGestor6 !== null ? $this->moedaBonif($saldoGestor6) : 'N/A')
                ];
            } else {
                $checks[] = [
                    'etapa' => 'API Mercos Filial 6',
                    'status' => 'warn',
                    'detalhe' => 'Indisponível: ' . ($sonda6['erro'] ?? 'Falha de conexão')
                ];
            }

            // 3. Montar simulação passo a passo dos itens da view (ou exibir últimos processados se vazia)
            $html = "<div style='font-family:Inter,Arial,sans-serif;font-size:12.5px;line-height:1.55;color:#1e293b'>";
            $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;margin-bottom:12px'>"
                . "<b>🔍 MODO SIMULAÇÃO (DRY-RUN):</b> Nenhum valor foi descontado ou transferido. Esta prévia consulta os saldos reais na API Mercos e mostra exatamente o que aconteceria se o job rodasse agora."
                . "</div>";

            $itensSimulados = [];

            if (empty($rows)) {
                $ultimos = $this->pdo->query("
                    SELECT id, idvendrepre, idfilial, numero_nota, valor, valor_tirado, observacao
                    FROM log_flex_bonificacao
                    ORDER BY id DESC
                    LIMIT 3
                ")->fetchAll(\PDO::FETCH_ASSOC);

                $html .= "<p style='color:#059669;font-weight:700;margin:8px 0'>✅ Nenhuma bonificação pendente na view <code>verificabonificacao</code> no momento.</p>";
                if (!empty($ultimos)) {
                    $html .= "<p style='font-size:11.5px;color:#64748b;margin:8px 0 4px'>Últimos 3 registros já processados em <code>log_flex_bonificacao</code>:</p>";
                    $html .= "<ul style='margin:0;padding-left:18px;font-size:11.5px;color:#334155'>";
                    foreach ($ultimos as $u) {
                        $html .= "<li><b>#{$u['id']} — Nota {$u['numero_nota']}</b> (Rep {$u['idvendrepre']} | Filial {$u['idfilial']}): "
                            . "Valor <b>" . $this->moedaBonif((float)$u['valor']) . "</b> | "
                            . "Tirado do Gestor: <b>" . $this->moedaBonif((float)$u['valor_tirado']) . "</b></li>";
                    }
                    $html .= "</ul>";
                }
                $html .= "</div>";

                return [
                    'success' => !empty($sonda1['ok']),
                    'message' => 'Configuração 100% operacional! View verificabonificacao sem pendências no momento e API Mercos conectada.',
                    'checks' => $checks,
                    'detalhes' => [
                        'pendentes_na_view' => 0,
                        'saldo_gestor_filial_1' => $saldoGestor1,
                        'saldo_gestor_filial_6' => $saldoGestor6
                    ],
                    'preview_html' => $html
                ];
            }

            // Agrupa por representante e ordena do menor desconto para o maior
            $porRep = [];
            foreach ($rows as $r) {
                $porRep[(int)$r['idvendrepre']][] = $r;
            }
            foreach ($porRep as &$listaRep) {
                usort($listaRep, static function (array $a, array $b): int {
                    return abs((float)$a['valor']) <=> abs((float)$b['valor']);
                });
            }
            unset($listaRep);

            foreach ($porRep as $idvendrepre => $bonificacoes) {
                $vendedor = (string)($bonificacoes[0]['vendedor'] ?? 'Representante');
                $idfilialRep = (int)($bonificacoes[0]['idfilial'] ?? 1);
                $apiRep = new \Nutricional\Services\MercosFlexService($idfilialRep);
                $saldoSimuladoRep = round((float)($apiRep->saldoDe($idvendrepre, true) ?? 0.0), 2);

                $html .= "<h4 style='margin:10px 0 4px;color:#0f172a;font-weight:700'>👤 {$vendedor} (ID: {$idvendrepre}) — Saldo Atual Mercos: " . $this->moedaBonif($saldoSimuladoRep) . "</h4><ul style='margin:0 0 10px;padding-left:18px'>";

                foreach ($bonificacoes as $item) {
                    $idfilial = (int)$item['idfilial'];
                    $valor = round((float)$item['valor'], 2);
                    $valorAbs = round(abs($valor), 2);
                    $motivo = trim((string)$item['motivo']);
                    $nota = $this->extrairNotaBonificacao($motivo);
                    $cdgestor = $this->obterGestorBonificacaoPorFilial($idfilial);

                    if ($this->jaProcessouNotaBonificacao($nota, $valor > 0)) {
                        $html .= "<li style='color:#d97706'><b>Nota {$nota}</b> ({$this->moedaBonif($valor)}): ⚠️ JÁ PROCESSADA em <code>log_flex_bonificacao</code> (será ignorada).</li>";
                        $itensSimulados[] = ['rep' => $idvendrepre, 'nota' => $nota, 'valor' => $valor, 'cenario' => 'IGNORA_JA_PROCESSADA'];
                        continue;
                    }

                    if ($valor > 0) {
                        $novoSaldo = round($saldoSimuladoRep + $valor, 2);
                        $html .= "<li style='color:#059669'><b>Nota {$nota}</b> (+{$this->moedaBonif($valor)}): 🟢 <b>CRÉDITO / ESTORNO</b> direto no representante. Saldo iria de {$this->moedaBonif($saldoSimuladoRep)} para <b>{$this->moedaBonif($novoSaldo)}</b>.</li>";
                        $saldoSimuladoRep = $novoSaldo;
                        $itensSimulados[] = ['rep' => $idvendrepre, 'nota' => $nota, 'valor' => $valor, 'cenario' => 'CREDITO_ESTORNO'];
                    } elseif ($saldoSimuladoRep < -0.004) {
                        $neg = round(abs($saldoSimuladoRep), 2);
                        $totalGestor = round($neg + $valorAbs, 2);
                        $html .= "<li style='color:#dc2626'><b>Nota {$nota}</b> (-{$this->moedaBonif($valorAbs)}): 🔴 <b>CENÁRIO 1 (Saldo Negativo: {$this->moedaBonif($saldoSimuladoRep)})</b>"
                            . "<br>• 1ª Etapa: Gestor {$cdgestor} transfere <b>{$this->moedaBonif($neg)}</b> para zerar saldo negativo."
                            . "<br>• 2ª Etapa: Gestor {$cdgestor} transfere <b>{$this->moedaBonif($valorAbs)}</b> e desconta bonificação integral."
                            . "<br>• Total tirado do Gestor: <b>-{$this->moedaBonif($totalGestor)}</b> | Saldo final Rep: <b>R$ 0,00</b>.</li>";
                        $saldoSimuladoRep = 0.00;
                        $itensSimulados[] = ['rep' => $idvendrepre, 'nota' => $nota, 'valor' => $valor, 'cenario' => 'CENARIO_1_SALDO_NEGATIVO', 'tirado_gestor' => -$totalGestor];
                    } elseif ($saldoSimuladoRep >= $valorAbs - 0.004) {
                        $novoSaldo = round($saldoSimuladoRep - $valorAbs, 2);
                        $html .= "<li style='color:#059669'><b>Nota {$nota}</b> (-{$this->moedaBonif($valorAbs)}): 🟢 <b>CENÁRIO 2 (Saldo Suficiente: {$this->moedaBonif($saldoSimuladoRep)})</b>"
                            . "<br>• Débito direto de <b>-{$this->moedaBonif($valorAbs)}</b> no representante (Gestor não afetado). Saldo final Rep: <b>{$this->moedaBonif($novoSaldo)}</b>.</li>";
                        $saldoSimuladoRep = $novoSaldo;
                        $itensSimulados[] = ['rep' => $idvendrepre, 'nota' => $nota, 'valor' => $valor, 'cenario' => 'CENARIO_2_DEBITO_DIRETO', 'tirado_gestor' => 0.0];
                    } else {
                        $faltante = round($valorAbs - $saldoSimuladoRep, 2);
                        $html .= "<li style='color:#d97706'><b>Nota {$nota}</b> (-{$this->moedaBonif($valorAbs)}): 🟠 <b>CENÁRIO 3 (Saldo Insuficiente: {$this->moedaBonif($saldoSimuladoRep)}, faltam {$this->moedaBonif($faltante)})</b>"
                            . "<br>• Gestor {$cdgestor} transfere bonificação integral de <b>{$this->moedaBonif($valorAbs)}</b> e desconta <b>-{$this->moedaBonif($valorAbs)}</b> do representante."
                            . "<br>• Total tirado do Gestor: <b>-{$this->moedaBonif($valorAbs)}</b> | Saldo final Rep: <b>{$this->moedaBonif($saldoSimuladoRep)}</b>.</li>";
                        $itensSimulados[] = ['rep' => $idvendrepre, 'nota' => $nota, 'valor' => $valor, 'cenario' => 'CENARIO_3_SALDO_INSUFICIENTE', 'tirado_gestor' => -$valorAbs];
                    }
                }
                $html .= "</ul>";
            }

            $html .= "</div>";

            return [
                'success' => !empty($sonda1['ok']),
                'message' => count($rows) . ' bonificação(ões) simulada(s) com sucesso (nenhum saldo foi alterado).',
                'checks' => $checks,
                'detalhes' => [
                    'pendentes_na_view' => count($rows),
                    'saldo_gestor_filial_1' => $saldoGestor1,
                    'saldo_gestor_filial_6' => $saldoGestor6,
                    'itens_simulados' => $itensSimulados
                ],
                'preview_html' => $html
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erro ao simular bonificacoes_flex: ' . $e->getMessage(),
                'checks' => [
                    ['etapa' => 'Simulação Bonificações Flex', 'status' => 'error', 'detalhe' => $e->getMessage()]
                ]
            ];
        }
    }

    // ======================================================================
    // REPRESENTANTES - Relatório de alterações em pedidos
    // ======================================================================
    private function executarRepresentantes(string $usuario, string $ip): array
    {
        try {
            $sql = "SELECT DISTINCT 
                        pedido.idvendrepre,
                        COALESCE(vend.fantasia, 'Representante não encontrado') AS repre,
                        vend.email AS email_repre,
                        COALESCE(cli.fantasia, 'Cliente não informado') AS cliente, 
                        l.datahora AS data_pedido,
                        l.idpedido,
                        CASE WHEN pp.nomecliente = '' THEN 'Pedido Digitado Internamente' 
                             ELSE REPLACE(REPLACE(REPLACE(pp.nomecliente, 'MERCOS', ''), '[', ''), ']', '') END AS numero_pedido,
                        COALESCE(item.descricao, 'Produto não encontrado') AS produto,
                        l.quant_old AS qt_anterior, 
                        l.quant_new AS qt_nova,
                        CASE WHEN l.quant_new = 0 THEN 'Item Excluído' ELSE 'Item Editado' END AS motivo
                    FROM PEDIDO_ITEM_LOG l
                    LEFT JOIN item ON (l.iditemold = item.iditem)
                    LEFT JOIN pedido ON (l.idpedido = pedido.idpedido) 
                    LEFT JOIN palmtop_pedido pp ON (pp.idpedidopda = pedido.idpedidopda)
                    LEFT JOIN cliforemp cli ON cli.idcliforemp = pedido.idcliforemp 
                    LEFT JOIN cliforemp vend ON vend.idcliforemp = pedido.idvendrepre 
                    WHERE l.motivo LIKE '%u item%'
                    AND l.quant_old <> l.quant_new 
                    AND l.datahora >= CURRENT_DATE - INTERVAL '1 days'
                    AND pedido.status IN (5)
                    AND item.iditem NOT IN (2181, 1552, 3058)
                    AND item.tipo IN (0, 2, 11) 
                    AND pedido.idvendrepre NOT IN (10119)
                    AND pedido.idfilial IN (1, 6)
                    ORDER BY repre, cliente, produto ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($resultados)) {
                return [
                    'success' => true,
                    'message' => 'Nenhum pedido alterado encontrado nas últimas 24 horas',
                    'detalhes' => ['enviados' => 0]
                ];
            }

            $representantes = [];
            foreach ($resultados as $row) {
                $repId = $row['idvendrepre'];
                if (!isset($representantes[$repId])) {
                    $representantes[$repId] = [
                        'nome' => $row['repre'],
                        'email' => $row['email_repre'],
                        'pedidos' => []
                    ];
                }
                $representantes[$repId]['pedidos'][] = $row;
            }

            return [
                'success' => true,
                'message' => 'Relatório de Representantes processado! ' . count($representantes) . ' representantes com alterações.',
                'detalhes' => ['total_representantes' => count($representantes)]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ======================================================================
    // GESTORES - Relatório de inadimplência
    // ======================================================================
    private function executarGestores(string $usuario, string $ip, array $params = []): array
    {
        try {
            $forcar = !empty($params['forcar']);
            if (!$forcar && method_exists('\Uteis', 'isUltimoDiaUtil') && !\Uteis::isUltimoDiaUtil()) {
                return [
                    'success' => true,
                    'message' => 'Hoje não é o último dia útil do mês. Aguardando a data certa.',
                    'detalhes' => ['enviados' => 0, 'motivo' => 'Nao e ultimo dia util']
                ];
            }

            $emailsGestores = function_exists('getEmails') ? getEmails('gestores_destino') : [];
            if (empty($emailsGestores)) {
                $emailsGestores = ['alan@nutricionalbr.com'];
            }

            $gestoresArray = [];
            foreach ($emailsGestores as $email) {
                $stmt = $this->pdo->prepare("
                    SELECT u.idcliforemp, c.fantasia 
                    FROM usuario u
                    JOIN cliforemp c ON c.idcliforemp = u.idcliforemp
                    WHERE u.email = :email AND u.inativo = 'N'
                ");
                $stmt->execute(['email' => $email]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $id = (int)$row['idcliforemp'];
                    $gestoresArray[$id] = [
                        'email' => $email,
                        'nome' => $row['fantasia'] ?: 'Gestor'
                    ];
                }
            }

            if (empty($gestoresArray)) {
                $gestoresArray[11258] = ['email' => 'alan@nutricionalbr.com', 'nome' => 'Administrador'];
            }

            $listaIdsStr = implode(',', array_keys($gestoresArray));

            $sql = "SELECT
                        user_id_param.out_id as idsupervisor,
                        MAX(varg.nomegestor) AS nomegestor,
                        SUM(varg.vencidos) AS vencidos,
                        SUM(varg.dias_30) AS dias_30,
                        SUM(varg.dias_60) AS dias_60,
                        SUM(varg.mais_60_dias) AS mais_60_dias,
                        SUM(varg.a_vencer) AS a_vencer,
                        SUM(varg.prox_30_dias) AS prox_30_dias,
                        SUM(varg.total_inadimplencia) AS valor_inadimplencia,
                        ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) AS percentual_geral,
                        SUM(varg.total_cliente) AS total_clientes,
                        SUM(varg.total_titulos) AS total_titulos,
                        SUM(varg.total_clientes_com_vencidos) AS total_clientes_vencidos,
                        SUM(varg.total_titulos_vencidos) AS total_titulos_vencidos,
                        ROUND(SUM(varg.dias_30) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_30,
                        ROUND(SUM(varg.dias_60) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_60,
                        ROUND(SUM(varg.mais_60_dias) * 100.0 / NULLIF(SUM(varg.vencidos), 0), 2) AS percentual_mais_60,
                        avg(varg.prazo_medio) as prazo_medio
                    FROM vw_analise_receber_geral varg 
                    CROSS JOIN LATERAL pkg_ema.retorna_lista(:lista_ids) AS user_id_param(out_id)
                    WHERE (
                        (user_id_param.out_id = 11258 AND varg.idvendrepre IN (SELECT DISTINCT idrepresentante FROM vw_gestor_repre WHERE idfilial = 1 AND idgestor NOT IN (5297,11371)) and varg.idfilial = 1)
                        OR (user_id_param.out_id = 11371 AND varg.idsupervisor = 11371 and varg.idfilial = 6)
                        OR (user_id_param.out_id = 15520 AND varg.idsupervisor = 15520)
                        OR (user_id_param.out_id = 13878 AND varg.idsupervisor = 13878)
                        OR (user_id_param.out_id = 5297 AND varg.idvendrepre IN (SELECT idrepresentante FROM vw_gestor_repre WHERE inativo = 'N'))
                    )
                    GROUP BY user_id_param.out_id
                    ORDER BY nomegestor";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['lista_ids' => $listaIdsStr]);
            $dadosGestores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $enviados = 0;
            foreach ($dadosGestores as $g) {
                $idSup = (int)$g['idsupervisor'];
                $info = $gestoresArray[$idSup] ?? null;
                if (!$info) continue;

                $htmlGestor = "<div style='font-family:Arial,sans-serif;max-width:650px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:10px;'>"
                    . "<h2 style='color:#1e3a8a;'>Relatório Mensal de Inadimplência - " . htmlspecialchars($info['nome']) . "</h2>"
                    . "<p><b>Total a Vencer:</b> " . $this->moedaBonif((float)$g['a_vencer']) . "<br>"
                    . "<b>Total Vencidos:</b> <span style='color:#dc2626;font-weight:bold;'>" . $this->moedaBonif((float)$g['vencidos']) . "</span><br>"
                    . "<b>Inadimplência Geral:</b> <b>{$g['percentual_geral']}%</b></p>"
                    . "<ul>"
                    . "<li>Até 30 dias: " . $this->moedaBonif((float)$g['dias_30']) . " ({$g['percentual_30']}%)</li>"
                    . "<li>31 a 60 dias: " . $this->moedaBonif((float)$g['dias_60']) . " ({$g['percentual_60']}%)</li>"
                    . "<li>Mais de 60 dias: " . $this->moedaBonif((float)$g['mais_60_dias']) . " ({$g['percentual_mais_60']}%)</li>"
                    . "</ul></div>";

                $this->enviarEmailNotificacao($info['email'], "Relatório de Inadimplência Gestor - " . $info['nome'] . " - " . date('m/Y'), $htmlGestor);
                $enviados++;
            }

            return [
                'success' => true,
                'message' => "Relatório de inadimplência processado para {$enviados} gestores.",
                'detalhes' => ['enviados' => $enviados]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao processar gestores: ' . $e->getMessage()];
        }
    }

    // ======================================================================
    // HISTÓRICO KPI - Gravação Diária no Banco de Dados
    // ======================================================================
    private function executarHistoricoKPI(string $usuario, string $ip): array
    {
        try {
            $stmtUsers = $this->pdo->query("SELECT idcliforemp as uid, dash_filiais, dash_gestores FROM usuario WHERE inativo = 'N'");
            $usuariosPermissoes = $stmtUsers->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($usuariosPermissoes)) {
                return ['success' => true, 'message' => 'Nenhum usuário ativo para registrar histórico KPI.', 'detalhes' => ['processados' => 0]];
            }

            $sql = "INSERT INTO kpi_financeiro_historico 
                    (data_registro, idusuario, idfilial, nivel_visao, id_referencia, vencidos, valor_iap, total_receber, iag_calculado, taxa_recuperacao, iap_calculado, qtd_trabalhados, qtd_recuperados)
                    SELECT 
                        CURRENT_DATE, 
                        :uid_int,
                        varg.idfilial,
                        CASE 
                            WHEN :tem_filial = 1 THEN 'filial'
                            WHEN :tem_gestor = 1 THEN 'gestor'
                            ELSE 'representante'
                        END as nivel_visao,
                        CASE 
                            WHEN :tem_filial = 1 THEN varg.idfilial
                            WHEN :tem_gestor = 1 THEN varg.idsupervisor
                            ELSE varg.idvendrepre
                        END as id_referencia,
                        SUM(COALESCE(varg.vencidos,0))::float,
                        SUM(COALESCE(varg.dias_60 + varg.mais_60_dias, 0))::float, 
                        SUM(COALESCE(varg.total_receber,0))::float,
                        ROUND(SUM(COALESCE(varg.vencidos,0)) * 100 / NULLIF(SUM(COALESCE(varg.total_receber,0)), 0), 2),
                        COALESCE((
                            SELECT ROUND(
                                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END) * 100.0 / 
                                NULLIF(
                                    SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= 8 AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                                    SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                                    SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END)
                                , 0)
                            , 2)
                            FROM vw_financeiro_eventos_geral vfe 
                            WHERE vfe.idfilial = varg.idfilial 
                            AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '120 days')
                            AND (
                                (:tem_filial = 1 AND vfe.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int)) OR
                                (:tem_gestor = 1 AND vfe.idgestor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int)) OR
                                (:tem_filial = 0 AND :tem_gestor = 0 AND vfe.idrepresentante = :uid_int)
                            )
                        ), 0),
                        ROUND(SUM(COALESCE(varg.dias_60 + varg.mais_60_dias, 0)) * 100 / NULLIF(SUM(COALESCE(varg.total_receber,0)), 0), 2),
                        COALESCE((
                            SELECT
                                SUM(CASE WHEN vfe.ultimo_evento IS NULL AND vfe.dias_atraso >= 8 AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo > 0 THEN 1 ELSE 0 END) +
                                SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END)
                            FROM vw_financeiro_eventos_geral vfe 
                            WHERE vfe.idfilial = varg.idfilial 
                            AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '120 days')
                            AND (
                                (:tem_filial = 1 AND vfe.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int)) OR
                                (:tem_gestor = 1 AND vfe.idgestor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int)) OR
                                (:tem_filial = 0 AND :tem_gestor = 0 AND vfe.idrepresentante = :uid_int)
                            )
                        ), 0),
                        COALESCE((
                            SELECT SUM(CASE WHEN vfe.ultimo_evento IS NOT NULL AND vfe.valorsaldo <= 0.01 THEN 1 ELSE 0 END)
                            FROM vw_financeiro_eventos_geral vfe 
                            WHERE vfe.idfilial = varg.idfilial 
                            AND vfe.vencimento >= (CURRENT_DATE - INTERVAL '120 days')
                            AND (
                                (:tem_filial = 1 AND vfe.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int)) OR
                                (:tem_gestor = 1 AND vfe.idgestor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int)) OR
                                (:tem_filial = 0 AND :tem_gestor = 0 AND vfe.idrepresentante = :uid_int)
                            )
                        ), 0)
                    FROM vw_analise_receber_geral_cliente varg
                    WHERE (
                        (:tem_filial = 1 AND varg.idfilial IN (SELECT unnest(string_to_array(:list_filiais, ','))::int))
                        OR 
                        (:tem_gestor = 1 AND varg.idsupervisor IN (SELECT unnest(string_to_array(:list_gestores, ','))::int))
                        OR 
                        (:tem_filial = 0 AND :tem_gestor = 0 AND varg.idvendrepre = :uid_int)
                    )
                    GROUP BY varg.idfilial, 
                             CASE 
                                WHEN :tem_filial = 1 THEN varg.idfilial
                                WHEN :tem_gestor = 1 THEN varg.idsupervisor
                                ELSE varg.idvendrepre
                             END
                    ON CONFLICT (data_registro, idusuario, idfilial) DO UPDATE SET
                        nivel_visao = EXCLUDED.nivel_visao,
                        id_referencia = EXCLUDED.id_referencia,
                        vencidos = EXCLUDED.vencidos,
                        valor_iap = EXCLUDED.valor_iap,
                        total_receber = EXCLUDED.total_receber,
                        iag_calculado = EXCLUDED.iag_calculado,
                        taxa_recuperacao = EXCLUDED.taxa_recuperacao,
                        iap_calculado = EXCLUDED.iap_calculado,
                        qtd_trabalhados = EXCLUDED.qtd_trabalhados,
                        qtd_recuperados = EXCLUDED.qtd_recuperados";

            $st = $this->pdo->prepare($sql);
            $this->pdo->beginTransaction();
            $processados = 0;

            foreach ($usuariosPermissoes as $u) {
                $temFilial = !empty($u['dash_filiais']) ? 1 : 0;
                $temGestor = !empty($u['dash_gestores']) ? 1 : 0;

                $st->execute([
                    'uid_int'        => (int)$u['uid'],
                    'tem_filial'     => $temFilial,
                    'tem_gestor'     => $temGestor,
                    'list_filiais'   => (string)($u['dash_filiais'] ?? ''),
                    'list_gestores'  => (string)($u['dash_gestores'] ?? '')
                ]);
                $processados++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Histórico KPI gravado com sucesso para {$processados} usuários.",
                'detalhes' => ['processados' => $processados]
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Erro ao processar Histórico KPI: ' . $e->getMessage()];
        }
    }

    // ======================================================================
    // CLIENTES ISENTOS ST - Relatório com Planilha Excel
    // ======================================================================
    private function executarClientesIsentos(array $params, string $usuario, string $ip): array
    {
        try {
            $destinosGeral = function_exists('getEmails') ? (getEmails('clientes_isento')['geral'] ?? []) : [];

            $sqlGeral = "SELECT DISTINCT
                            cli.idcliforemp AS cd_cliente,
                            cli.fantasia,
                            cli.razao,
                            cli.cnpj,
                            cli.cpf,
                            cli.ie,
                            cli.idvendedor,
                            (SELECT vend.fantasia FROM cliforemp vend WHERE vend.idcliforemp = cli.idvendedor) AS representante,
                            (SELECT descricao FROM cidade WHERE cidade.idcidade = cli.idcidade) AS cidade,
                            cli.uf,
                            cli.email,
                            cli.fone
                        FROM cliforemp cli 
                        WHERE (cli.ie LIKE '%ISENTO%' OR cli.ie = '' OR cli.ie IS NULL)
                          AND cli.tipocliforemp = 0 
                          AND cli.idfilial IN (1, 6)
                          AND cli.inativo = 'N' 
                        ORDER BY cli.uf ASC, cli.fantasia ASC";

            $clientesGeral = $this->pdo->query($sqlGeral)->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($clientesGeral)) {
                return ['success' => true, 'message' => 'Nenhum cliente isento encontrado.', 'detalhes' => ['total' => 0]];
            }

            $anexoExcel = null;
            if (method_exists('\Uteis', 'gerarExcelClientesIsentos')) {
                $anexoExcel = \Uteis::gerarExcelClientesIsentos($clientesGeral);
            }

            $htmlGeral = "<div style='font-family:Arial,sans-serif;padding:20px;border:1px solid #e2e8f0;border-radius:10px;'>"
                . "<h3 style='color:#0369a1;'>Relatório Diário de Clientes Isentos de Inscrição Estadual</h3>"
                . "<p>Total de clientes isentos cadastrados e ativos: <b>" . count($clientesGeral) . "</b></p>"
                . "<p>Segue em anexo a planilha detalhada com todos os dados cadastrais atualizados.</p>"
                . "</div>";

            $enviados = 0;
            if (!empty($destinosGeral)) {
                $anexos = ($anexoExcel && file_exists($anexoExcel)) ? [$anexoExcel] : [];
                $this->enviarEmailNotificacao($destinosGeral, "📋 Relatório de Clientes Isentos - Geral - " . date('d/m/Y'), $htmlGeral, $anexos);
                $enviados++;
            }

            if ($anexoExcel && file_exists($anexoExcel)) {
                @unlink($anexoExcel);
            }

            return [
                'success' => true,
                'message' => "Relatório de clientes isentos processado (" . count($clientesGeral) . " clientes).",
                'detalhes' => ['clientes' => count($clientesGeral), 'emails_enviados' => $enviados]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao processar clientes isentos: ' . $e->getMessage()];
        }
    }

    // ======================================================================
    // REPRESENTANTES MENSAL - Relatório de Inadimplência Individual
    // ======================================================================
    private function executarRepresentantesMensal(array $params, string $usuario, string $ip): array
    {
        try {
            $sqlReps = "SELECT DISTINCT 
                            c.idcliforemp,
                            c.fantasia as nome,
                            c.email,
                            COALESCE(c.email, '') as email_rep
                        FROM cliforemp c
                        JOIN fornecedor ON (fornecedor.idcliforemp = c.idcliforemp)
                        WHERE fornecedor.representante = 'S'
                          AND c.inativo = 'N'
                          AND c.email IS NOT NULL 
                          AND c.email != ''
                          AND c.idcliforemp NOT IN (10119)
                        ORDER BY c.fantasia";

            $reps = $this->pdo->query($sqlReps)->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($reps)) {
                return ['success' => true, 'message' => 'Nenhum representante ativo com e-mail encontrado.', 'detalhes' => ['enviados' => 0]];
            }

            $sqlDadosRep = "SELECT 
                                varg.idvendrepre,
                                varg.nomerepresentante,
                                SUM(varg.vencidos) AS vencidos,
                                SUM(varg.dias_30) AS dias_30,
                                SUM(varg.dias_60) AS dias_60,
                                SUM(varg.mais_60_dias) AS mais_60_dias,
                                SUM(varg.a_vencer) AS a_vencer,
                                SUM(varg.prox_30_dias) AS prox_30_dias,
                                SUM(varg.total_inadimplencia) AS valor_inadimplencia,
                                ROUND(SUM(varg.vencidos) * 100.0 / NULLIF(SUM(varg.total_receber), 0), 2) AS percentual_geral
                            FROM vw_analise_receber_geral_cliente varg
                            WHERE varg.idvendrepre = :idrep
                            GROUP BY varg.idvendrepre, varg.nomerepresentante";

            $stmtDados = $this->pdo->prepare($sqlDadosRep);
            $enviados = 0;

            foreach ($reps as $rep) {
                $idRep = (int)$rep['idcliforemp'];
                $stmtDados->execute(['idrep' => $idRep]);
                $dados = $stmtDados->fetch(\PDO::FETCH_ASSOC);

                if (!$dados || (empty($dados['vencidos']) && empty($dados['a_vencer']))) {
                    continue;
                }

                $emailRep = trim(explode(';', str_replace(',', ';', strtolower($rep['email_rep'])))[0]);
                if (!filter_var($emailRep, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $html = "<div style='font-family:Arial,sans-serif;padding:20px;border:1px solid #ddd;border-radius:10px;max-width:600px;margin:auto;'>"
                    . "<h3 style='color:#1e3a8a;'>Relatório Mensal de Carteira & Inadimplência</h3>"
                    . "<p>Olá, <b>" . htmlspecialchars($rep['nome']) . "</b>,</p>"
                    . "<p>Segue o resumo da sua carteira no fechamento mensal:</p>"
                    . "<ul>"
                    . "<li><b>A Vencer:</b> " . $this->moedaBonif((float)$dados['a_vencer']) . "</li>"
                    . "<li><b>Total Vencido:</b> <span style='color:#dc2626;font-weight:bold;'>" . $this->moedaBonif((float)$dados['vencidos']) . "</span></li>"
                    . "<li><b>Índice de Inadimplência:</b> <b>{$dados['percentual_geral']}%</b></li>"
                    . "</ul></div>";

                $this->enviarEmailNotificacao($emailRep, "Relatório Mensal de Carteira - " . $rep['nome'] . " - " . date('m/Y'), $html);
                $enviados++;
            }

            return [
                'success' => true,
                'message' => "Relatório mensal enviado para {$enviados} representantes.",
                'detalhes' => ['total_reps' => count($reps), 'enviados' => $enviados]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao processar representantes mensal: ' . $e->getMessage()];
        }
    }

    // ======================================================================
    // ALERTA CERTIFICADO DIGITAL - Vencimento NFe das Filiais
    // ======================================================================
    private function executarAlertaCertificado(array $params, string $usuario, string $ip): array
    {
        try {
            $diasAlerta = (int)($params['dias_alerta'] ?? 30);
            $emailDestino = $params['email_destino'] ?? null;
            if (!$emailDestino) {
                $emailDestino = $_ENV['MAIL_ALERTA_CERTIFICADO'] ?? 'alan@nutricionalbr.com';
            }

            $sql = "SELECT idfilial, nome, razao, cnpj, nf_e_certificado_vencimento,
                           (nf_e_certificado_vencimento - CURRENT_DATE) AS dias_restantes
                    FROM filial
                    WHERE idfilial IN (1, 2, 6)
                      AND nf_e_certificado_vencimento IS NOT NULL
                      AND nf_e_certificado_vencimento > '2000-01-01'
                    ORDER BY dias_restantes ASC";

            $filiais = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            $criticos = [];
            $emAlerta = [];

            foreach ($filiais as $f) {
                $dias = (int)$f['dias_restantes'];
                if ($dias <= $diasAlerta) {
                    if ($dias <= 7) {
                        $criticos[] = $f;
                    } else {
                        $emAlerta[] = $f;
                    }
                }
            }

            $totalNotificados = count($criticos) + count($emAlerta);
            if ($totalNotificados === 0) {
                return [
                    'success' => true,
                    'message' => "Todos os certificados digitais estão com validade em dia (> {$diasAlerta} dias).",
                    'detalhes' => ['filiais_verificadas' => count($filiais), 'em_alerta' => 0]
                ];
            }

            $corPrincipal = count($criticos) > 0 ? '#dc2626' : '#f59e0b';
            $tituloAlerta = count($criticos) > 0 ? 'URGENTE: Certificado Digital Prestes a Vencer / Vencido' : 'AVISO: Vencimento de Certificado Digital Próximo';

            $linhasTabela = '';
            foreach (array_merge($criticos, $emAlerta) as $f) {
                $dias = (int)$f['dias_restantes'];
                $statusBadge = $dias < 0 
                    ? '<span style="background:#fee2e2;color:#991b1b;padding:4px 8px;border-radius:6px;font-weight:bold;">VENCIDO (' . abs($dias) . 'd atrás)</span>'
                    : ($dias <= 7 
                        ? '<span style="background:#fee2e2;color:#991b1b;padding:4px 8px;border-radius:6px;font-weight:bold;">CRÍTICO (' . $dias . ' dias)</span>'
                        : '<span style="background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:6px;font-weight:bold;">ATENÇÃO (' . $dias . ' dias)</span>');

                $vencBr = date('d/m/Y', strtotime($f['nf_e_certificado_vencimento']));
                $linhasTabela .= "
                <tr style='border-bottom:1px solid #e2e8f0;'>
                    <td style='padding:12px 10px;font-weight:bold;'>{$f['nome']} (Filial {$f['idfilial']})</td>
                    <td style='padding:12px 10px;color:#475569;'>{$f['razao']}<br><span style='font-size:11px;color:#94a3b8;'>CNPJ: {$this->formatarCNPJ($f['cnpj'])}</span></td>
                    <td style='padding:12px 10px;text-align:center;font-weight:bold;'>{$vencBr}</td>
                    <td style='padding:12px 10px;text-align:center;'>{$statusBadge}</td>
                </tr>";
            }

            $html = "
            <div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;'>
                <div style='background:{$corPrincipal};color:#ffffff;padding:20px;text-align:center;'>
                    <h2 style='margin:0;font-size:20px;'>{$tituloAlerta}</h2>
                    <p style='margin:5px 0 0;font-size:13px;opacity:0.9;'>Monitoramento Automático de Certificados Digitais NFe - Nutricional</p>
                </div>
                <div style='padding:24px;'>
                    <p style='color:#334155;font-size:14px;line-height:1.6;margin-top:0;'>
                        Detectamos que <b>{$totalNotificados}</b> filial(is) possui(em) certificado digital NFe com vencimento próximo ou expirado. 
                        A renovação antecipada é essencial para evitar o bloqueio imediato na emissão de notas fiscais (NFe, NFCe e MDFe).
                    </p>
                    <table style='width:100%;border-collapse:collapse;font-size:13px;margin:20px 0;'>
                        <thead>
                            <tr style='background:#f8fafc;border-bottom:2px solid #cbd5e1;text-align:left;color:#475569;'>
                                <th style='padding:10px;'>Filial</th>
                                <th style='padding:10px;'>Razão Social</th>
                                <th style='padding:10px;text-align:center;'>Vencimento</th>
                                <th style='padding:10px;text-align:center;'>Status</th>
                            </tr>
                        </thead>
                        <tbody>{$linhasTabela}</tbody>
                    </table>
                    <div style='background:#f1f5f9;border-left:4px solid #0284c7;padding:12px 16px;border-radius:4px;font-size:12px;color:#334155;'>
                        <b>Ação Necessária:</b> Contate a autoridade certificadora para emissão do novo arquivo .PFX (A1) e faça a instalação no servidor do ERP e filiais.
                    </div>
                </div>
                <div style='background:#f8fafc;padding:12px 20px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;'>
                    Disparado automaticamente pelo Sistema de Crons Nutricional em " . date('d/m/Y H:i:s') . "
                </div>
            </div>";

            $enviado = $this->enviarEmailNotificacao($emailDestino, "⚠️ Alerta: Vencimento de Certificado Digital NFe - Nutricional", $html);

            return [
                'success' => $enviado,
                'message' => $enviado 
                    ? "Alerta enviado para {$emailDestino} ({$totalNotificados} filial(is) em atenção)." 
                    : "Falha ao enviar e-mail de alerta de certificado.",
                'detalhes' => [
                    'destinatarios' => $emailDestino,
                    'total_em_alerta' => $totalNotificados,
                    'filiais' => array_merge($criticos, $emAlerta)
                ],
                'email_html' => $html
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao verificar certificados: ' . $e->getMessage()];
        }
    }

    private function simularAlertaCertificado(array $params = []): array
    {
        try {
            $sql = "SELECT idfilial, nome, razao, cnpj, nf_e_certificado_vencimento,
                           (nf_e_certificado_vencimento - CURRENT_DATE) AS dias_restantes
                    FROM filial
                    WHERE idfilial IN (1, 2, 6)
                      AND nf_e_certificado_vencimento IS NOT NULL
                      AND nf_e_certificado_vencimento > '2000-01-01'
                    ORDER BY dias_restantes ASC";

            $filiais = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            $checks = [];

            foreach ($filiais as $f) {
                $dias = (int)$f['dias_restantes'];
                $venc = date('d/m/Y', strtotime($f['nf_e_certificado_vencimento']));
                $status = $dias < 0 ? 'error' : ($dias <= 15 ? 'warning' : 'ok');
                $checks[] = [
                    'etapa' => "Filial {$f['idfilial']} - {$f['nome']}",
                    'status' => $status,
                    'detalhe' => "Vencimento: {$venc} (" . ($dias < 0 ? "Vencido há " . abs($dias) . " dias" : "Faltam {$dias} dias") . ")"
                ];
            }

            return [
                'success' => true,
                'message' => "Simulação concluída: " . count($filiais) . " filiais verificadas.",
                'checks' => $checks,
                'detalhes' => ['filiais' => $filiais]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage(), 'checks' => [['etapa' => 'Verificação Certificado', 'status' => 'error', 'detalhe' => $e->getMessage()]]];
        }
    }

    // ======================================================================
    // NOTAS NUTRIRE - Envia notas para API GOFI
    // ======================================================================
    private function executarNotasNutrire(array $params, string $usuario, string $ip): array
    {
        try {
            $marcaId = $params['marca_id'] ?? 8;
            $apiKey = $params['api_key'] ?? 'D52nVqCP3R5vXep4SyknH2ngfisuTrIU8Zj1Krb4';
            $apiUrl = $params['api_url'] ?? 'https://app.gofind.online/api/subscribe/invoice';
            $emailNotificacao = $params['email_notificacao'] ?? null;

            $sql = "SELECT DISTINCT 
                        nfs.numero_nf_e AS id, 
                        nfs.datahora_operacao AS dhemi, 
                        nfs.datahora_nf_e AS dhsaient, 
                        nfs.numeronf AS nnf, 
                        nfs.idserie AS serie, 
                        REPLACE(REPLACE(REPLACE(emissor.cnpj, '.', ''), '/', ''), '-', '') AS cnpj, 
                        emissor.ie AS ie, 
                        emissor.razao AS xnome, 
                        emissor.nome AS xfant, 
                        CASE WHEN dest.cnpj = '.' 
                            THEN REPLACE(REPLACE(REPLACE(dest.cpf, '.', ''), '/', ''), '-', '')
                            ELSE REPLACE(REPLACE(REPLACE(dest.cnpj, '.', ''), '/', ''), '-', '') 
                        END AS dest_cnpj,
                        dest.nf_e_email AS email, 
                        REPLACE(REPLACE(REPLACE(dest.cep, '-', ''), '.', ''), ' ', '') AS dest_cep, 
                        REPLACE(REPLACE(REPLACE(dest.fone, '(', ''), ')', ''), '-', '') AS dest_fone, 
                        dest.numero AS dest_nro, 
                        dest.uf AS dest_uf, 
                        dest.bairro AS dest_xbairro, 
                        dest.complemento AS dest_xcpl, 
                        dest.endereco AS dest_xlgr, 
                        (SELECT descricao FROM cidade WHERE idcidade = dest.idcidade) AS dest_xmun, 
                        'BR' AS dest_xpais, 
                        dest.ie AS dest_ie, 
                        dest.razao AS dest_xnome, 
                        dest.fantasia AS dest_xfant, 
                        ni.iditemnfs AS nitem, 
                        cb.idbarra AS cean, 
                        ni.idcfop AS cfop, 
                        item.referencia, 
                        (SELECT DISTINCT classificacao FROM classificacaofiscal WHERE idclassificacaofiscal = item.idclassfiscal) AS ncm, 
                        CAST(ni.qt AS INT) AS qcom, 
                        (SELECT sigla FROM unidade WHERE idunidade = ni.idunidade) AS ucom, 
                        CAST(SUM((ni.valor) - (((ni.valor)*15)/100)) AS numeric(10,2)) AS vuncom, 
                        CAST(SUM(((ni.valor) - (((ni.valor)*15)/100)) * (ni.qt)) AS numeric(10,2)) AS vprod, 
                        item.descricao AS xprod 
                    FROM nfs 
                    JOIN filial emissor ON emissor.idfilial = nfs.idfilial 
                    JOIN cliforemp dest ON dest.idcliforemp = nfs.idcliforemp 
                    JOIN nfs_item ni ON ni.idnfs = nfs.idnfs 
                    JOIN item ON item.iditem = ni.iditem 
                    JOIN marca ON marca.idmarca = item.idmarca 
                    JOIN codigo_barra cb ON cb.iditem = ni.iditem AND cb.principal = 'S' 
                    WHERE nfs.idtransacao = 1 
                        AND marca.idmarca = ?
                        AND nfs.status = 2 
                        AND nfs.dataemissao = CURRENT_DATE
                    GROUP BY 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 33";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$marcaId]);
            $resultado = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($resultado)) {
                $html = "<html><body><p>Nenhuma nota fiscal encontrada.</p></body></html>";
                if ($emailNotificacao) {
                    $this->enviarEmailNotificacao($emailNotificacao, 'Notas Nutrire - Nenhuma nota', $html);
                }
                return [
                    'success' => true,
                    'message' => 'Nenhuma nota fiscal encontrada',
                    'detalhes' => ['enviadas' => 0],
                    'email_html' => $html
                ];
            }

            $notas = [];
            $clientes = [];

            foreach ($resultado as $item) {
                $notaId = $item['id'];

                if (!isset($notas[$notaId])) {
                    $notas[$notaId] = [
                        'Id' => '',
                        'ide' => [
                            'dhEmi' => $item['dhsaient'] ? date('c', strtotime($item['dhsaient'])) : null,
                            'dhSaiEnt' => $item['dhsaient'] ? date('c', strtotime($item['dhsaient'])) : null,
                            'nNF' => (string)$item['nnf'],
                            'serie' => (string)$item['serie']
                        ],
                        'emit' => [
                            'CNPJ' => $item['cnpj'],
                            'IE' => $item['ie'],
                            'xNome' => $item['xnome'],
                            'xFant' => ''
                        ],
                        'dest' => [
                            'CNPJ' => $item['dest_cnpj'],
                            'RUC' => '',
                            'email' => '',
                            'enderDest' => [
                                'CEP' => $item['dest_cep'],
                                'fone' => '',
                                'nro' => $item['dest_nro'],
                                'UF' => $item['dest_uf'],
                                'xBairro' => $item['dest_xbairro'],
                                'xCpl' => '',
                                'xLgr' => $item['dest_xlgr'],
                                'xMun' => $item['dest_xmun'],
                                'xPais' => 'BR'
                            ],
                            'IE' => $item['dest_ie'] ?? '',
                            'xNome' => $item['dest_xnome'],
                            'xFant' => $item['dest_xfant'] ?? ''
                        ],
                        'entrega' => [
                            'CEP' => $item['dest_cep'],
                            'fone' => '',
                            'nro' => $item['dest_nro'],
                            'UF' => $item['dest_uf'],
                            'xBairro' => $item['dest_xbairro'],
                            'xCpl' => '',
                            'xLgr' => $item['dest_xlgr'],
                            'xMun' => $item['dest_xmun'],
                            'xPais' => 'BR'
                        ],
                        'det' => []
                    ];
                }

                $notas[$notaId]['det'][] = [
                    'nItem' => (string)$item['nitem'],
                    'prod' => [
                        'cEAN' => $item['cean'],
                        'CFOP' => '',
                        'cProd' => (string)$item['referencia'],
                        'NCM' => '',
                        'qCom' => (string)$item['qcom'],
                        'uCom' => $item['ucom'] ?? 'UN',
                        'vUnCom' => $item['vuncom'] ? (string)$item['vuncom'] : '0,00',
                        'vProd' => $item['vprod'] ? (string)$item['vprod'] : '0,00',
                        'xProd' => $item['xprod'] ?? 'Sem descrição'
                    ]
                ];

                if (!in_array($item['dest_xnome'], $clientes)) {
                    $clientes[] = $item['dest_xnome'];
                }
            }

            $body = array_values($notas);
            $html = $this->gerarHtmlNotasNutrireCompleto($notas, $clientes);

            $chunks = array_chunk($body, 100);

            foreach ($chunks as $chunk) {
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($chunk));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Api-Key: ' . $apiKey
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);

                $response = curl_exec($ch);
                curl_close($ch);

                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['invoicesResult'])) {
                        foreach ($data['invoicesResult'] as $invoice) {
                            $html .= "<li>ID: {$invoice['id']}, Status: {$invoice['status']}, Message: {$invoice['message']}</li>";
                        }
                    }
                }
            }

            $html .= "</ul></body></html>";

            if ($emailNotificacao) {
                $this->enviarEmailNotificacao($emailNotificacao, 'Processamento de Notas Nutrire - ' . date('d/m/Y H:i'), $html);
            }

            return [
                'success' => true,
                'message' => count($body) . ' notas processadas em ' . count($chunks) . ' lotes',
                'detalhes' => [
                    'total_notas' => count($body),
                    'clientes' => $clientes,
                    'lotes' => count($chunks)
                ],
                'email_html' => $html
            ];
        } catch (\Exception $e) {
            error_log("Erro no Notas Nutrire: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }

    // ======================================================================
    // FLEX MÍNIMO GESTOR
    // ======================================================================
    private function executarFlexMinimoGestor(array $params, string $usuario, string $ip): array
    {
        try {
            $gestorId = $params['gestor_id'] ?? 12335;
            $nomeGestor = $params['gestor_nome'] ?? 'Gestor';
            $valorMinimo = $params['valor_minimo'] ?? 3000;
            $valorEmprestimo = $params['valor_emprestimo'] ?? 3000;
            $motivo = $params['motivo'] ?? 'Empréstimo para suprir desfalque de Verba Flex';
            $idFilial = $params['id_filial'] ?? 1;
            $urlApi = $params['url_api'] ?? 'http://mercosf1.nutricionalbr.com:8081/saldo_flex';

            $ch = curl_init($urlApi);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            curl_close($ch);

            $dados = json_decode($response, true);
            if (!is_array($dados)) {
                throw new \Exception('Resposta inválida da API');
            }

            $gestor = null;
            foreach ($dados as $item) {
                if ($item['cliforemp_id'] == $gestorId) {
                    $gestor = $item;
                    break;
                }
            }

            if (!$gestor) {
                throw new \Exception("Gestor $gestorId não encontrado");
            }

            $saldoAtual = floatval($gestor['saldo_atual'] ?? 0);

            if ($saldoAtual >= $valorMinimo) {
                return [
                    'success' => true,
                    'message' => "Saldo suficiente (R$ " . number_format($saldoAtual, 2, ',', '.') . "). Nenhuma ação necessária.",
                    'detalhes' => ['saldo_atual' => $saldoAtual, 'emprestimo_realizado' => false]
                ];
            }

            $ch = curl_init($urlApi);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'colaborador_id' => $gestorId,
                'valor_movimentado' => $valorEmprestimo,
                'observacao' => $motivo
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_exec($ch);
            curl_close($ch);

            $stmt = $this->pdo->prepare("
                INSERT INTO log_debito_flex_gestor (cdgestor, valor_emprestimo, motivo, data, idfilial)
                VALUES (?, ?, ?, CURRENT_DATE, ?)
            ");
            $stmt->execute([$gestorId, $valorEmprestimo, $motivo, $idFilial]);

            $html = "<h2>✅ Empréstimo Realizado com Sucesso!</h2>
                     <p><strong>Gestor:</strong> $nomeGestor</p>
                     <p><strong>Valor:</strong> R$ " . number_format($valorEmprestimo, 2, ',', '.') . "</p>
                     <p><strong>Saldo Anterior:</strong> R$ " . number_format($saldoAtual, 2, ',', '.') . "</p>
                     <p><strong>Motivo:</strong> $motivo</p>";

            if (!empty($params['email_notificacao'])) {
                $this->enviarEmailNotificacao($params['email_notificacao'], 'Flex Mínimo Gestor - ' . date('d/m/Y'), $html);
            }

            return [
                'success' => true,
                'message' => "Empréstimo de R$ $valorEmprestimo realizado com sucesso!",
                'detalhes' => [
                    'saldo_anterior' => $saldoAtual,
                    'valor_emprestimo' => $valorEmprestimo,
                    'emprestimo_realizado' => true
                ],
                'email_html' => $html
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ======================================================================
    // BONIFICAÇÕES FLEX
    // ======================================================================
    private function executarBonificacoesFlex(array $params, string $usuario, string $ip): array
    {
        $lock = $this->pdo->prepare('SELECT pg_try_advisory_lock(7102, 1)');
        $lock->execute();
        if (!$lock->fetchColumn()) {
            return [
                'success' => false,
                'message' => 'Já existe um processamento de bonificações em andamento.',
                'detalhes' => ['processadas' => 0, 'erros' => 0]
            ];
        }

        try {
            // Ordena por representante e pelo MENOR valor absoluto a ser descontado primeiro
            $sql = "
                SELECT DISTINCT idfilial, idvendrepre, vendedor, valor, status, motivo
                FROM verificabonificacao
                ORDER BY idvendrepre ASC
            ";
            $rows = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $htmlVazio = "<html><body><p>Nenhuma Bonificação encontrada.</p></body></html>";
                if (!empty($params['email_notificacao']) && !empty($params['notificar_sucesso'])) {
                    $this->enviarEmailNotificacao($params['email_notificacao'], 'Processamento de Bonificações - ' . date('d/m/Y H:i'), $htmlVazio);
                }
                return [
                    'success' => true,
                    'message' => 'Nenhuma bonificação pendente na view verificaBonificacao.',
                    'detalhes' => ['processadas' => 0, 'ignoradas' => 0, 'erros' => 0],
                    'email_html' => $htmlVazio
                ];
            }

            // Agrupa por representante e ordena internamente do MENOR valor a ser descontado para o maior
            $porRep = [];
            foreach ($rows as $r) {
                $repId = (int)$r['idvendrepre'];
                $porRep[$repId][] = $r;
            }
            foreach ($porRep as &$listaRep) {
                usort($listaRep, static function (array $a, array $b): int {
                    return abs((float)$a['valor']) <=> abs((float)$b['valor']);
                });
            }
            unset($listaRep);

            $html = "<html><head><style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #007BFF; color: white; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .success { color: green; font-weight: bold; }
                .warning { color: #d97706; font-weight: bold; }
                .error { color: #dc2626; font-weight: bold; }
            </style></head><body><h2>Processamento de Bonificações</h2><ul>";

            $processadas = 0;
            $ignoradas = 0;
            $erros = 0;
            $resumoItens = [];

            foreach ($porRep as $idvendrepre => $bonificacoes) {
                $vendedor = (string)($bonificacoes[0]['vendedor'] ?? 'Representante');
                $html .= "<h3>Processando Representante: " . htmlspecialchars($vendedor) . " (ID: {$idvendrepre})</h3><ul>";

                foreach ($bonificacoes as $item) {
                    $idfilial = (int)$item['idfilial'];
                    $valor = round((float)$item['valor'], 2);
                    $valorAbsoluto = round(abs($valor), 2);
                    $motivo = trim((string)$item['motivo']);
                    $numeroNota = $this->extrairNotaBonificacao($motivo);
                    $cdgestor = $this->obterGestorBonificacaoPorFilial($idfilial);

                    $html .= "<li>Processando: " . htmlspecialchars($vendedor) . " - " . $this->moedaBonif($valor) . "</li>";
                    $html .= "<li>Motivo: " . htmlspecialchars($motivo) . "</li>";
                    $html .= "<li>Filial: {$idfilial} | Gestor: {$cdgestor} | Nota: {$numeroNota}</li>";

                    try {
                        if ($this->jaProcessouNotaBonificacao($numeroNota, $valor > 0)) {
                            $html .= "<li class=\"warning\">Nota {$numeroNota} já processada anteriormente.</li>";
                            $ignoradas++;
                            continue;
                        }

                        $api = new \Nutricional\Services\MercosFlexService($idfilial);

                        if ($valor > 0) {
                            // CENÁRIO CRÉDITO: valor positivo = cancelamento/estorno
                            $html .= "<li>Processando CRÉDITO por cancelamento/estorno</li>";
                            $motivoCompleto = "CANCELAMENTO/ESTORNO: {$motivo}";

                            $this->movimentarFlexSeguro($api, $idvendrepre, $valor, $motivoCompleto, $html);
                            $novoSaldo = $this->lerSaldoConfirmado($api, $idvendrepre, $html);
                            $this->inserirLogBonificacao($idvendrepre, $idfilial, $numeroNota, $valor, $motivoCompleto, $cdgestor, 0.00);

                            $html .= "<li class=\"success\">Crédito de " . $this->moedaBonif($valor) . " aplicado com sucesso (Saldo atual: " . $this->moedaBonif($novoSaldo) . ")</li>";
                            $processadas++;
                            $resumoItens[] = ['rep' => $idvendrepre, 'vendedor' => $vendedor, 'nota' => $numeroNota, 'valor' => $valor, 'valor_tirado_gestor' => 0.0, 'status' => 'sucesso'];
                        } else {
                            // CENÁRIO DÉBITO: valor negativo = bonificação a descontar
                            $html .= "<li>Processando DÉBITO de bonificação (" . $this->moedaBonif($valorAbsoluto) . ")</li>";
                            $valorTiradoGestor = $this->processarDebitoBonificacao(
                                $api,
                                $idfilial,
                                $idvendrepre,
                                $cdgestor,
                                $valorAbsoluto,
                                $motivo,
                                $numeroNota,
                                $html
                            );
                            $processadas++;
                            $resumoItens[] = [
                                'rep' => $idvendrepre,
                                'vendedor' => $vendedor,
                                'nota' => $numeroNota,
                                'valor' => -$valorAbsoluto,
                                'valor_tirado_gestor' => -$valorTiradoGestor,
                                'status' => 'sucesso'
                            ];
                        }
                    } catch (\Throwable $e) {
                        $erros++;
                        $html .= "<li class=\"error\">[BONIFICAÇÃO] Erro na nota {$numeroNota}: " . htmlspecialchars($e->getMessage()) . "</li>";
                        $resumoItens[] = [
                            'rep' => $idvendrepre,
                            'vendedor' => $vendedor,
                            'nota' => $numeroNota,
                            'valor' => $valor,
                            'status' => 'erro',
                            'erro' => $e->getMessage()
                        ];
                    }
                }

                $html .= "</ul>";
            }

            $html .= "</ul></body></html>";

            $emailDestino = $params['email_notificacao'] ?? null;
            $deveEnviarEmail = $emailDestino && (
                ($erros > 0 && ($params['notificar_falha'] ?? true)) ||
                ($erros === 0 && ($params['notificar_sucesso'] ?? true))
            );
            if ($deveEnviarEmail) {
                $assunto = ($erros > 0 ? '[ALERTA] ' : '') . 'Processamento de Bonificações Flex - ' . date('d/m/Y H:i');
                $this->enviarEmailNotificacao($emailDestino, $assunto, $html);
            }

            $sucessoGeral = $erros === 0;
            return [
                'success' => $sucessoGeral,
                'message' => sprintf(
                    'Bonificações processadas: %d sucesso(s), %d ignorada(s), %d erro(s).',
                    $processadas,
                    $ignoradas,
                    $erros
                ),
                'detalhes' => [
                    'processadas' => $processadas,
                    'ignoradas' => $ignoradas,
                    'erros' => $erros,
                    'itens' => $resumoItens
                ],
                'email_html' => $html
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar bonificações: ' . $e->getMessage(),
                'detalhes' => ['processadas' => 0, 'erros' => 1]
            ];
        } finally {
            $unlock = $this->pdo->prepare('SELECT pg_advisory_unlock(7102, 1)');
            $unlock->execute();
        }
    }

    private function processarDebitoBonificacao(
        \Nutricional\Services\MercosFlexService $api,
        int $idfilial,
        int $idvendrepre,
        int $cdgestor,
        float $valorBonificacao,
        string $motivo,
        string $numeroNota,
        string &$html
    ): float {
        $valorBonificacao = round($valorBonificacao, 2);
        $saldoAtualRep = $this->lerSaldoConfirmado($api, $idvendrepre, $html);

        // CENÁRIO 1: Representante com saldo negativo
        if ($saldoAtualRep < -0.004) {
            $valorNegativo = round(abs($saldoAtualRep), 2);
            $html .= "<li class=\"warning\">[SALDO NEGATIVO] Valor negativo: " . $this->moedaBonif($saldoAtualRep) . "</li>";
            $html .= "<li>PRIMEIRA ETAPA: Cobrir APENAS saldo negativo de " . $this->moedaBonif($valorNegativo) . "</li>";

            // 1.1 Garante que o gestor tenha saldo para cobrir o negativo
            $this->garantirSaldoGestor($api, $idfilial, $cdgestor, $valorNegativo, "Cobrir APENAS saldo negativo rep {$idvendrepre}", $html);

            // 1.2 Transfere exatamente o valorNegativo (arredondado em 2 casas) do gestor para o representante
            $motivoCobrirNegativo = "Cobertura APENAS saldo negativo: {$motivo}";
            $this->transferirFlexSeguro($api, $cdgestor, $idvendrepre, $valorNegativo, $motivoCobrirNegativo, $html);
            $html .= "<li>Transferência APENAS para negativo realizada: " . $this->moedaBonif($valorNegativo) . "</li>";

            // 1.3 Nova consulta de saldo do representante para confirmar que zerou
            $novoSaldoRep = $this->lerSaldoConfirmado($api, $idvendrepre, $html);
            if ($novoSaldoRep < -1.00) {
                throw new \RuntimeException("Falha ao cobrir saldo negativo! Saldo atual após transferência: " . $this->moedaBonif($novoSaldoRep));
            }
            $html .= "<li class=\"success\">Saldo negativo coberto com sucesso! (Saldo atual: " . $this->moedaBonif($novoSaldoRep) . ")</li>";

            // 2ª Etapa: Processar bonificação INTEGRAL
            $html .= "<li>SEGUNDA ETAPA: Processar bonificação INTEGRAL de " . $this->moedaBonif($valorBonificacao) . "</li>";
            $totalTiradoGestor = round($valorNegativo + $valorBonificacao, 2);

            $this->processarBonificacaoViaGestor(
                $api,
                $idfilial,
                $idvendrepre,
                $cdgestor,
                $valorBonificacao,
                $motivo,
                $numeroNota,
                $totalTiradoGestor,
                $html
            );

            return $totalTiradoGestor;
        }

        // CENÁRIO 2: Representante tem saldo suficiente
        if ($saldoAtualRep >= $valorBonificacao - 0.004) {
            $html .= "<li>Representante tem saldo suficiente (" . $this->moedaBonif($saldoAtualRep) . ") - Débito direto</li>";
            $this->movimentarFlexSeguro($api, $idvendrepre, -$valorBonificacao, $motivo, $html);
            $this->inserirLogBonificacao($idvendrepre, $idfilial, $numeroNota, -$valorBonificacao, $motivo, $cdgestor, 0.00);
            $html .= "<li class=\"success\">Bonificação descontada diretamente do representante com sucesso</li>";
            return 0.00;
        }

        // CENÁRIO 3: Representante tem saldo >= 0, porém insuficiente para a bonificação
        $saldoFaltante = round(max(0.0, $valorBonificacao - $saldoAtualRep), 2);
        $html .= "<li>Saldo insuficiente (" . $this->moedaBonif($saldoAtualRep) . ") - Faltam " . $this->moedaBonif($saldoFaltante) . "</li>";

        $this->processarBonificacaoViaGestor(
            $api,
            $idfilial,
            $idvendrepre,
            $cdgestor,
            $valorBonificacao,
            $motivo,
            $numeroNota,
            $valorBonificacao,
            $html
        );

        return $valorBonificacao;
    }

    private function processarBonificacaoViaGestor(
        \Nutricional\Services\MercosFlexService $api,
        int $idfilial,
        int $idvendrepre,
        int $cdgestor,
        float $valorBonificacao,
        string $motivo,
        string $numeroNota,
        float $valorTiradoDoGestor,
        string &$html
    ): void {
        $valorBonificacao = round($valorBonificacao, 2);
        $html .= "<li>Verificando gestor {$cdgestor} para bonificação INTEGRAL de " . $this->moedaBonif($valorBonificacao) . "</li>";

        $this->garantirSaldoGestor($api, $idfilial, $cdgestor, $valorBonificacao, "Bonificação INTEGRAL rep {$idvendrepre}: {$motivo}", $html);

        $motivoTransferencia = "Bonificação INTEGRAL: {$motivo}";
        $this->transferirFlexSeguro($api, $cdgestor, $idvendrepre, $valorBonificacao, $motivoTransferencia, $html);
        $html .= "<li>Transferência INTEGRAL do gestor realizada: " . $this->moedaBonif($valorBonificacao) . "</li>";

        $saldoRepPos = $this->lerSaldoConfirmado($api, $idvendrepre, $html);
        if ($saldoRepPos < $valorBonificacao - 1.00) {
            throw new \RuntimeException("Saldo do representante após transferência (" . $this->moedaBonif($saldoRepPos) . ") menor que a bonificação (" . $this->moedaBonif($valorBonificacao) . ")");
        }

        $this->movimentarFlexSeguro($api, $idvendrepre, -$valorBonificacao, $motivo, $html);
        $this->inserirLogBonificacao($idvendrepre, $idfilial, $numeroNota, -$valorBonificacao, $motivo, $cdgestor, $valorTiradoDoGestor);
        $html .= "<li class=\"success\">Bonificação INTEGRAL processada com sucesso</li>";
    }

    private function garantirSaldoGestor(
        \Nutricional\Services\MercosFlexService $api,
        int $idfilial,
        int $cdgestor,
        float $valorNecessario,
        string $motivo,
        string &$html,
        int $maxTentativas = 10
    ): void {
        $valorNecessario = round($valorNecessario, 2);
        $tentativa = 0;

        while ($tentativa < $maxTentativas) {
            $saldoAtual = $this->lerSaldoConfirmado($api, $cdgestor, $html);
            if ($saldoAtual >= $valorNecessario - 0.004) {
                $html .= "<li class=\"success\">Saldo do gestor suficiente: " . $this->moedaBonif($saldoAtual) . "</li>";
                return;
            }

            $tentativa++;
            $valorEmprestimo = $idfilial === 1 ? 3000.00 : 1000.00;
            $motivoEmprestimo = $tentativa === 1 ? $motivo : "{$motivo} (tentativa adicional {$tentativa})";
            $html .= "<li class=\"warning\">Saldo do gestor insuficiente (" . $this->moedaBonif($saldoAtual) . ") - Fazendo empréstimo PADRÃO de " . $this->moedaBonif($valorEmprestimo) . "</li>";

            $this->movimentarFlexSeguro($api, $cdgestor, $valorEmprestimo, $motivoEmprestimo, $html);

            try {
                $stmt = $this->pdo->prepare('INSERT INTO log_debito_flex_gestor (cdgestor, valor_emprestimo, motivo, data, idfilial) VALUES (?, ?, ?, CURRENT_DATE, ?)');
                $stmt->execute([$cdgestor, $valorEmprestimo, $motivoEmprestimo, $idfilial]);
            } catch (\Throwable $e) {
                error_log('[bonificacoes_flex] falha ao gravar log_debito_flex_gestor: ' . $e->getMessage());
            }
        }

        $saldoFinal = $this->lerSaldoConfirmado($api, $cdgestor, $html);
        if ($saldoFinal < $valorNecessario - 0.004) {
            throw new \RuntimeException("Saldo do gestor insuficiente mesmo após {$maxTentativas} empréstimos! Necessário: " . $this->moedaBonif($valorNecessario) . ", Disponível: " . $this->moedaBonif($saldoFinal));
        }
    }

    private function lerSaldoConfirmado(\Nutricional\Services\MercosFlexService $api, int $idColaborador, string &$html): float
    {
        $saldo = $api->saldoDe($idColaborador, true);
        if ($saldo === null || !is_finite($saldo)) {
            throw new \RuntimeException("Colaborador {$idColaborador} não encontrado ou com saldo inválido na API Mercos.");
        }
        $saldoArredondado = round($saldo, 2);
        $html .= "<li>Saldo verificado (ID {$idColaborador}): " . $this->moedaBonif($saldoArredondado) . "</li>";
        return $saldoArredondado;
    }

    private function movimentarFlexSeguro(
        \Nutricional\Services\MercosFlexService $api,
        int $idColaborador,
        float $valor,
        string $observacao,
        string &$html
    ): void {
        $valor = round($valor, 2);
        $observacao = mb_substr(trim($observacao), 0, 250);

        $saldoInicial = $api->saldoDe($idColaborador, true);
        if ($saldoInicial === null) {
            throw new \RuntimeException("Colaborador {$idColaborador} sem saldo na API Mercos.");
        }
        $saldoInicial = round($saldoInicial, 2);
        $esperado = round($saldoInicial + $valor, 2);

        $ultimoErro = null;
        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            try {
                if ($tentativa > 1) {
                    usleep(1500000);
                    $saldoCheck = $api->saldoDe($idColaborador, true);
                    if ($saldoCheck !== null && abs(round($saldoCheck, 2) - $esperado) <= 0.05) {
                        return;
                    }
                }

                $api->movimentar($idColaborador, $valor, $observacao);
                usleep(1200000);

                $saldoFinal = $api->saldoDe($idColaborador, true);
                if ($saldoFinal !== null && abs(round($saldoFinal, 2) - $esperado) <= 0.05) {
                    return;
                }
                $ultimoErro = sprintf(
                    'Saldo após movimentação (%s) divergente do esperado (%s)',
                    $this->moedaBonif((float)$saldoFinal),
                    $this->moedaBonif($esperado)
                );
            } catch (\Throwable $e) {
                $ultimoErro = $e->getMessage();
            }
        }

        throw new \RuntimeException("Falha na movimentação de " . $this->moedaBonif($valor) . " no colaborador {$idColaborador}: {$ultimoErro}");
    }

    private function transferirFlexSeguro(
        \Nutricional\Services\MercosFlexService $api,
        int $idOrigem,
        int $idDestino,
        float $valor,
        string $motivo,
        string &$html
    ): void {
        $valor = round(abs($valor), 2);
        $obsSaida = mb_substr("Transferência para {$idDestino}: {$motivo}", 0, 250);
        $obsEntrada = mb_substr("Crédito recebido: {$motivo}", 0, 250);

        $this->movimentarFlexSeguro($api, $idOrigem, -$valor, $obsSaida, $html);
        $this->movimentarFlexSeguro($api, $idDestino, $valor, $obsEntrada, $html);
    }

    private function extrairNotaBonificacao(string $motivo): string
    {
        if (preg_match('/Nota:\s*([\w-]+)/i', $motivo, $m)) {
            return $m[1];
        }
        return 'Nota não identificada';
    }

    private function obterGestorBonificacaoPorFilial(int $idfilial): int
    {
        return $idfilial === 1 ? 12335 : 15816;
    }

    private function jaProcessouNotaBonificacao(string $numeroNota, bool $ehEstorno = false): bool
    {
        if ($ehEstorno) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM log_flex_bonificacao WHERE numero_nota = ? AND observacao ILIKE '%Estorno%'");
            $stmt->execute([$numeroNota]);
            return (int)$stmt->fetchColumn() > 0;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM log_flex_bonificacao WHERE numero_nota = ?");
        $stmt->execute([$numeroNota]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function inserirLogBonificacao(
        int $idvendrepre,
        int $idfilial,
        string $numeroNota,
        float $valor,
        string $observacao,
        int $codgestor,
        float $valorTirado = 0.00
    ): void {
        $valor = round($valor, 2);
        $valorTiradoCorrigido = $valorTirado > 0 ? -round(abs($valorTirado), 2) : round($valorTirado, 2);

        $stmt = $this->pdo->prepare("
            INSERT INTO log_flex_bonificacao (idvendrepre, idfilial, numero_nota, valor, observacao, codgestor, valor_tirado)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $idvendrepre,
            $idfilial,
            $numeroNota,
            $valor,
            $observacao,
            $codgestor,
            $valorTiradoCorrigido
        ]);
    }

    private function moedaBonif(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    // ======================================================================
    // MÉTODOS AUXILIARES
    // ======================================================================

    private function gerarHtmlNotasNutrireCompleto(array $notas, array $clientes): string
    {
        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #007BFF; color: white; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style></head><body>';

        $html .= '<h2>Divisão das Notas Fiscais</h2><ul>';
        $html .= '<li>Clientes: ' . implode(', ', $clientes) . '</li>';
        $html .= '<li>Números das Notas: ' . implode(', ', array_keys($notas)) . '</li>';

        $body = array_values($notas);
        $chunkSize = 100;
        for ($i = 0; $i < count($body); $i += $chunkSize) {
            $chunkNum = floor($i / $chunkSize) + 1;
            $chunkCount = min($chunkSize, count($body) - $i);
            $html .= "<li>Envio {$chunkNum}: {$chunkCount} notas</li>";
        }
        $html .= '</ul>';

        $primeiraNota = reset($notas);
        if ($primeiraNota) {
            $html .= '<h2>Relatório de Notas Fiscais</h2><table>';
            $html .= '<tr><th>Data de Emissão</th><td>' . $this->formatarData($primeiraNota['ide']['dhEmi']) . '</td></tr>';
            $html .= '<tr><th>Data de Saída/Entrada</th><td>' . $this->formatarData($primeiraNota['ide']['dhSaiEnt']) . '</td></tr>';
            $html .= '<tr><th>Número da NF</th><td>' . $primeiraNota['ide']['nNF'] . '</td></tr>';
            $html .= '<tr><th>Série</th><td>' . $primeiraNota['ide']['serie'] . '</td></tr>';
            $html .= '<tr><th>CNPJ Emissor</th><td>' . $this->formatarCNPJ($primeiraNota['emit']['CNPJ']) . '</td></tr>';
            $html .= '<tr><th>Nome Emissor</th><td>' . $primeiraNota['emit']['xNome'] . '</td></tr>';
            $html .= '<tr><th>CNPJ Destinatário</th><td>' . $this->formatarCNPJ($primeiraNota['dest']['CNPJ']) . '</td></tr>';
            $html .= '<tr><th>Nome Destinatário</th><td>' . $primeiraNota['dest']['xNome'] . '</td></tr>';
            $html .= '<tr><th>Endereço</th><td>' . $primeiraNota['dest']['enderDest']['xLgr'] . ', ' . $primeiraNota['dest']['enderDest']['nro'] . '</td></tr>';
            $html .= '<tr><th>CEP</th><td>' . $this->formatarCEP($primeiraNota['dest']['enderDest']['CEP']) . '</td></tr>';
            $html .= '</table>';

            $html .= '<h4>Itens</h4><table>';
            $html .= '<tr><th>Item</th><th>Descrição</th><th>cEAN</th><th>cProd</th><th>UN</th><th>Qtd</th><th>Valor UN</th><th>Valor Total</th></tr>';
            foreach ($primeiraNota['det'] as $item) {
                $vUnCom = number_format((float)$item['prod']['vUnCom'], 2, ',', '.');
                $vProd = number_format((float)$item['prod']['vProd'], 2, ',', '.');
                $html .= '<tr>';
                $html .= '<td>' . $item['nItem'] . '</td>';
                $html .= '<td>' . $item['prod']['xProd'] . '</td>';
                $html .= '<td>' . ($item['prod']['cEAN'] ?: 'N/A') . '</td>';
                $html .= '<td>' . ($item['prod']['cProd'] ?: 'N/A') . '</td>';
                $html .= '<td>' . $item['prod']['uCom'] . '</td>';
                $html .= '<td>' . $item['prod']['qCom'] . '</td>';
                $html .= '<td>R$ ' . $vUnCom . '</td>';
                $html .= '<td>R$ ' . $vProd . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '<h3>Respostas da API</h3><ul>';
        return $html;
    }

    private function enviarEmailNotificacao($emails, string $assunto, string $html, array $anexos = []): bool
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'mail.nutricionalbr.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com';
            $mail->Password = \Nutricional\Services\SecretVault::get('MAIL_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->setFrom($_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com', 'Nutricional');

            $listaEmails = is_array($emails) ? $emails : explode(',', (string)$emails);
            foreach ($listaEmails as $email) {
                $email = trim((string)$email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($email);
                }
            }

            foreach ($anexos as $anexo) {
                if (is_string($anexo) && file_exists($anexo)) {
                    $mail->addAttachment($anexo);
                }
            }

            $mail->Subject = $assunto;
            $mail->Body = $html;
            $mail->send();

            return true;
        } catch (\Exception $e) {
            error_log("Erro ao enviar email: " . $e->getMessage());
            return false;
        }
    }

    private function formatarCNPJ(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) == 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cnpj);
        } elseif (strlen($cnpj) == 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
        }
        return $cnpj;
    }

    private function formatarCEP(string $cep): string
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) == 8) {
            return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
        }
        return $cep;
    }

    private function formatarData(?string $data): string
    {
        if (!$data) return '';
        $timestamp = strtotime($data);
        return date('d/m/Y', $timestamp);
    }
}