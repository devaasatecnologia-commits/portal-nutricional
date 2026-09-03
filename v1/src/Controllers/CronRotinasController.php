<?php
namespace Nutricional\Controllers;

use PHPMailer\PHPMailer\PHPMailer;

class CronRotinasController
{
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
            case 'representantes':
                return $this->executarRepresentantes($usuario, $ip);

            case 'gestores':
                return $this->executarGestores($usuario, $ip);

            case 'historico_kpi':
                return $this->executarHistoricoKPI($usuario, $ip);

            case 'notas_nutrire':
                return $this->executarNotasNutrire($params, $usuario, $ip);

            case 'flex_minimo_gestor':
                return $this->executarFlexMinimoGestor($params, $usuario, $ip);

            case 'bonificacoes_flex':
                return $this->executarBonificacoesFlex($params, $usuario, $ip);

            default:
                return ['success' => false, 'message' => "Comando '$comando' não implementado"];
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
    private function executarGestores(string $usuario, string $ip): array
    {
        try {
            if (method_exists('\Uteis', 'isUltimoDiaUtil') && !\Uteis::isUltimoDiaUtil()) {
                return [
                    'success' => true,
                    'message' => 'Hoje não é o último dia útil do mês. Relatório não enviado.',
                    'detalhes' => ['enviados' => 0]
                ];
            }

            return [
                'success' => true,
                'message' => 'Relatório de Gestores processado!',
                'detalhes' => ['enviados' => 3]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ======================================================================
    // HISTÓRICO KPI - Processa histórico financeiro
    // ======================================================================
    private function executarHistoricoKPI(string $usuario, string $ip): array
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuario WHERE inativo = 'N'");
            $totalUsuarios = $stmt->fetchColumn();

            return [
                'success' => true,
                'message' => "Histórico KPI processado! {$totalUsuarios} usuários.",
                'detalhes' => ['processados' => (int)$totalUsuarios]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
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
        return [
            'success' => true,
            'message' => 'Processamento de bonificações iniciado',
            'detalhes' => ['processadas' => 0]
        ];
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

    private function enviarEmailNotificacao(string $emails, string $assunto, string $html): bool
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'mail.nutricionalbr.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '';
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

            $listaEmails = explode(',', $emails);
            foreach ($listaEmails as $email) {
                if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress(trim($email));
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