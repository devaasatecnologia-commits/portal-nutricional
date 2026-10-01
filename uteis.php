<?php
require_once('sql.php');


class Uteis {
    
    /**
     * Formata um número de telefone.
     * 
     * @param string $n Número do telefone.
     * @return string Telefone formatado.
     * 
     * Exemplo de uso:
     * echo Uteis::telefone("5511999999999"); // Saída: +55 (11) 99999-9999
     */
    public static function telefone($n) {
        $tam = strlen(preg_replace("/[^0-9]/", "", $n));
        $n = preg_replace("/[^0-9]/", "", $n); // Limpa a string

        switch ($tam) {
            case 13:
                return "+".substr($n, 0, $tam-11)." (".substr($n, $tam-11, 2).") ".substr($n, $tam-9, 5)."-".substr($n, -4);
            case 12:
                return "+".substr($n, 0, $tam-10)." (".substr($n, $tam-10, 2).") ".substr($n, $tam-8, 4)."-".substr($n, -4);
            case 11:
                return " (".substr($n, 0, 2).") ".substr($n, 2, 5)."-".substr($n, 7);
            case 10:
                return " (".substr($n, 0, 2).") ".substr($n, 2, 4)."-".substr($n, 6);
            case ($tam <= 9):
                return substr($n, 0, $tam-4)."-".substr($n, -4);
            default:
                return $n; // Retorna o número original se não se encaixar em nenhum formato
        }
    }

/**
 * Gera planilha Excel de clientes isentos - VERSÃO FINAL
 */
public static function gerarExcelClientesIsentos($clientes) {
    if (empty($clientes)) {
        return false;
    }
    
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    
    $nomeArquivo = $tempDir . '/clientes_isentos_' . date('Y-m-d_His') . '.xlsx';
    
    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clientes Isentos');
        
        // ============================================================
        // CABEÇALHOS
        // ============================================================
        $headers = [
            'A1' => 'Código',
            'B1' => 'Fantasia',
            'C1' => 'Razão Social',
            'D1' => 'CNPJ',
            'E1' => 'CPF',
            'F1' => 'IE',
            'G1' => 'Representante',
            'H1' => 'Cidade',
            'I1' => 'UF',
            'J1' => 'E-mail',
            'K1' => 'Telefone'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF0066CC');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }
        
        // ============================================================
        // DADOS
        // ============================================================
        $row = 2;
        foreach ($clientes as $cli) {
            // Código (número)
            $sheet->setCellValue('A' . $row, (int)$cli['cd_cliente']);
            
            // Textos normais
            $sheet->setCellValue('B' . $row, $cli['fantasia'] ?? '');
            $sheet->setCellValue('C' . $row, $cli['razao'] ?? '');
            
            // CNPJ e CPF - FORÇA COMO TEXTO (evita notação científica)
            $cnpj = trim((string)($cli['cnpj'] ?? ''));
            $cpf  = trim((string)($cli['cpf'] ?? ''));
            
            $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
            $cpfLimpo  = preg_replace('/[^0-9]/', '', $cpf);
            
            // Formata o CNPJ: 00.000.000/0000-00
            if (strlen($cnpjLimpo) === 14) {
                $cnpjFormatado = substr($cnpjLimpo, 0, 2) . '.' .
                                 substr($cnpjLimpo, 2, 3) . '.' .
                                 substr($cnpjLimpo, 5, 3) . '/' .
                                 substr($cnpjLimpo, 8, 4) . '-' .
                                 substr($cnpjLimpo, 12, 2);
            } else {
                $cnpjFormatado = $cnpjLimpo;
            }
            
            // Formata o CPF: 000.000.000-00
            if (strlen($cpfLimpo) === 11) {
                $cpfFormatado = substr($cpfLimpo, 0, 3) . '.' .
                                substr($cpfLimpo, 3, 3) . '.' .
                                substr($cpfLimpo, 6, 3) . '-' .
                                substr($cpfLimpo, 9, 2);
            } else {
                $cpfFormatado = $cpfLimpo;
            }
            
            // FORÇA COMO TEXTO (evita notação científica)
            $sheet->setCellValueExplicit('D' . $row, $cnpjFormatado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E' . $row, $cpfFormatado,  \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            
            // IE
            $sheet->setCellValue('F' . $row, $cli['ie'] ?: 'ISENTO');
            
            // Demais campos
            $sheet->setCellValue('G' . $row, $cli['representante'] ?? '');
            $sheet->setCellValue('H' . $row, $cli['cidade'] ?? '');
            $sheet->setCellValue('I' . $row, $cli['uf'] ?? '');
            $sheet->setCellValue('J' . $row, $cli['email'] ?? '');
            $sheet->setCellValueExplicit('K' . $row, (string)($cli['fone'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            
            $row++;
        }
        
        // ============================================================
        // AJUSTES FINAIS
        // ============================================================
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $lastRow = $row - 1;
        $sheet->getStyle('D2:E' . $lastRow)->getAlignment()->setHorizontal('center');
        $sheet->getStyle('I2:I' . $lastRow)->getAlignment()->setHorizontal('center');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:K1');
        
        // Salva
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($nomeArquivo);
        
        return $nomeArquivo;
    }
    
    // ============================================================
    // FALLBACK: CSV
    // ============================================================
    $nomeArquivo = str_replace('.xlsx', '.csv', $nomeArquivo);
    $fp = fopen($nomeArquivo, 'w');
    
    fwrite($fp, "\xEF\xBB\xBF"); // BOM UTF-8
    
    fputcsv($fp, ['Código', 'Fantasia', 'Razão Social', 'CNPJ', 'CPF', 'IE', 'Representante', 'Cidade', 'UF', 'E-mail', 'Telefone'], ';');
    
    foreach ($clientes as $cli) {
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cli['cnpj'] ?? '');
        $cpfLimpo  = preg_replace('/[^0-9]/', '', $cli['cpf'] ?? '');
        
        $cnpjFormatado = (strlen($cnpjLimpo) === 14) 
            ? substr($cnpjLimpo, 0, 2) . '.' . substr($cnpjLimpo, 2, 3) . '.' . substr($cnpjLimpo, 5, 3) . '/' . substr($cnpjLimpo, 8, 4) . '-' . substr($cnpjLimpo, 12, 2)
            : $cnpjLimpo;
        
        $cpfFormatado = (strlen($cpfLimpo) === 11)
            ? substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2)
            : $cpfLimpo;
        
        fputcsv($fp, [
            $cli['cd_cliente'],
            $cli['fantasia'],
            $cli['razao'],
            '="' . $cnpjFormatado . '"',
            '="' . $cpfFormatado . '"',
            $cli['ie'] ?: 'ISENTO',
            $cli['representante'],
            $cli['cidade'],
            $cli['uf'],
            $cli['email'],
            $cli['fone']
        ], ';');
    }
    
    fclose($fp);
    return $nomeArquivo;
}

/**
 * Constrói o corpo HTML do e-mail de clientes isentos - DASHBOARD SIMPLIFICADO
 * @param array $clientes   Lista de clientes
 * @param int|null $idFilial  Se null, é visão geral. Se int, é da filial específica.
 */
public static function construirEmailClientesIsentos($clientes, $idFilial = null) {
    $totalClientes = count($clientes);
    
    // ============================================================
    // 1. CALCULAR ESTATÍSTICAS PARA OS CARDS
    // ============================================================
    $totalCNPJ = 0;
    $totalCPF = 0;
    $totalSemDoc = 0;
    
    foreach ($clientes as $cli) {
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cli['cnpj'] ?? '');
        $cpfLimpo  = preg_replace('/[^0-9]/', '', $cli['cpf'] ?? '');
        
        if (strlen($cnpjLimpo) === 14) {
            $totalCNPJ++;
        } elseif (strlen($cpfLimpo) === 11) {
            $totalCPF++;
        } else {
            $totalSemDoc++;
        }
    }
    
    // Estatísticas por UF
    $statsUF = [];
    foreach ($clientes as $cli) {
        $uf = $cli['uf'] ?: 'N/A';
        if (!isset($statsUF[$uf])) {
            $statsUF[$uf] = 0;
        }
        $statsUF[$uf]++;
    }
    arsort($statsUF);
    
    // ============================================================
    // 2. CALCULAR PERCENTUAIS
    // ============================================================
    $percCNPJ = $totalClientes > 0 ? round(($totalCNPJ * 100) / $totalClientes, 1) : 0;
    $percCPF  = $totalClientes > 0 ? round(($totalCPF * 100) / $totalClientes, 1) : 0;
    
    // ============================================================
    // 3. DEFINIR CONTEXTO (Geral ou Filial)
    // ============================================================
    if ($idFilial) {
        $subtitulo = '🏢 Filial ' . $idFilial . ' — Análise consolidada de clientes sem Inscrição Estadual';
    } else {
        $subtitulo = '🌐 Visão Geral — Análise consolidada de clientes sem Inscrição Estadual';
    }
    
    // ============================================================
    // 4. MONTAR HTML
    // ============================================================
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: "Segoe UI", Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
            .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            
            /* HEADER */
            .header { background: linear-gradient(135deg, #0066cc 0%, #003d7a 100%); color: white; padding: 35px 30px; text-align: center; position: relative; }
            .header::after { content: ""; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #ffc107, #28a745, #0066cc); }
            .header h1 { margin: 0; font-size: 26px; font-weight: 600; }
            .header .subtitle { margin: 8px 0 0; opacity: 0.9; font-size: 14px; }
            .header .date-badge { display: inline-block; background: rgba(255,255,255,0.15); padding: 5px 15px; border-radius: 20px; font-size: 12px; margin-top: 12px; }
            
            /* SEÇÕES */
            .content { padding: 30px; }
            .section-title { font-size: 14px; font-weight: 700; color: #0066cc; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 15px; padding-bottom: 8px; border-bottom: 2px solid #e8f0f8; }
            
            /* CARDS PRINCIPAIS */
            .cards-grid { display: table; width: 100%; margin-bottom: 30px; border-spacing: 10px 0; }
            .card-row { display: table-row; }
            .card { display: table-cell; width: 50%; padding: 20px 15px; background: #f8fafc; border-radius: 10px; border-left: 4px solid #0066cc; vertical-align: top; text-align: center; }
            .card-value { font-size: 32px; font-weight: 700; color: #0066cc; line-height: 1; }
            .card-label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px; }
            .card-sub { font-size: 10px; color: #999; margin-top: 4px; }
            
            /* CARDS DE MÉTRICAS SECUNDÁRIAS */
            .metrics-grid { display: table; width: 100%; margin-bottom: 30px; border-spacing: 8px 0; }
            .metric { display: table-cell; width: 50%; padding: 15px 10px; background: white; border-radius: 8px; border: 1px solid #e5e9f0; text-align: center; }
            .metric-icon { font-size: 20px; margin-bottom: 6px; }
            .metric-value { font-size: 22px; font-weight: 700; color: #1a1a1a; }
            .metric-label { font-size: 10px; color: #888; text-transform: uppercase; margin-top: 3px; }
            .metric-bar { height: 4px; background: #e5e9f0; border-radius: 2px; margin-top: 8px; overflow: hidden; }
            .metric-bar-fill { height: 100%; background: linear-gradient(90deg, #0066cc, #00a8e8); border-radius: 2px; }
            
            /* LISTA DE UFs */
            .uf-list { display: table; width: 100%; border-spacing: 8px 0; margin-top: 15px; }
            .uf-item { display: table-cell; padding: 15px 10px; background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%); border-radius: 8px; text-align: center; border-top: 3px solid #0066cc; }
            .uf-code { font-size: 22px; font-weight: 700; color: #0066cc; }
            .uf-count { font-size: 11px; color: #666; margin-top: 4px; }
            .uf-perc { font-size: 10px; color: #999; margin-top: 2px; }
            
            /* TABELA RESUMO */
            .table-resumo { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
            .table-resumo th { background: #f0f4f8; padding: 10px; text-align: left; font-size: 11px; color: #555; text-transform: uppercase; border-bottom: 2px solid #e0e5eb; }
            .table-resumo td { padding: 10px; border-bottom: 1px solid #f0f4f8; }
            .table-resumo tr:last-child td { border-bottom: none; }
            .table-resumo tr:hover { background: #f9fbfd; }
            
            /* ALERTA */
            .alert { padding: 15px 20px; border-radius: 8px; font-size: 13px; margin-top: 25px; }
            .alert-info { background: #e8f4fd; border-left: 4px solid #0066cc; color: #004085; }
            .alert-icon { font-size: 24px; float: left; margin-right: 12px; }
            
            /* FOOTER */
            .footer { background: #f8fafc; padding: 25px; text-align: center; color: #888; font-size: 12px; border-top: 1px solid #e5e9f0; }
            .footer strong { color: #555; }
            .footer .logo { font-size: 16px; font-weight: 700; color: #0066cc; margin-bottom: 8px; }
            
            @media (max-width: 600px) {
                .card, .metric, .uf-item { display: block; width: 100%; margin-bottom: 10px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            
            <!-- HEADER -->
            <div class="header">
                <h1>📋 Relatório de Clientes Isentos de IE</h1>
                <div class="subtitle">' . $subtitulo . '</div>
                <div class="date-badge">🗓️ ' . date('d/m/Y \à\s H:i') . '</div>
            </div>
            
            <div class="content">
            
                <!-- CARDS PRINCIPAIS -->
                <div class="section-title">📊 Visão Geral</div>
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-value">' . number_format($totalClientes, 0, ',', '.') . '</div>
                        <div class="card-label">Total de Clientes</div>
                        <div class="card-sub">isentos de IE</div>
                    </div>
                    <div class="card" style="border-left-color: #28a745;">
                        <div class="card-value" style="color: #28a745;">' . count($statsUF) . '</div>
                        <div class="card-label">Estados</div>
                        <div class="card-sub">com clientes isentos</div>
                    </div>
                </div>
                
                <!-- MÉTRICAS DE DOCUMENTO -->
                <div class="section-title">🎯 Tipo de Documento</div>
                <div class="metrics-grid">
                    <div class="metric">
                        <div class="metric-icon">🏢</div>
                        <div class="metric-value">' . number_format($totalCNPJ, 0, ',', '.') . '</div>
                        <div class="metric-label">Clientes com CNPJ</div>
                        <div class="metric-bar"><div class="metric-bar-fill" style="width: ' . $percCNPJ . '%;"></div></div>
                    </div>
                    <div class="metric">
                        <div class="metric-icon">👤</div>
                        <div class="metric-value">' . number_format($totalCPF, 0, ',', '.') . '</div>
                        <div class="metric-label">Clientes com CPF</div>
                        <div class="metric-bar"><div class="metric-bar-fill" style="width: ' . $percCPF . '%; background: linear-gradient(90deg, #28a745, #5cdb7f);"></div></div>
                    </div>
                </div>
                
                <!-- DISTRIBUIÇÃO POR UF -->
                <div class="section-title">📍 Distribuição por Estado</div>
                <div class="uf-list">';
    
    // ============================================================
    // RENDERIZA CARDS DE UFs (até 5 maiores)
    // ============================================================
    $contadorUF = 0;
    foreach ($statsUF as $uf => $qtd) {
        if ($contadorUF >= 5) break;
        $perc = $totalClientes > 0 ? round(($qtd * 100) / $totalClientes, 1) : 0;
        
        $html .= '<div class="uf-item">
            <div class="uf-code">' . htmlspecialchars($uf) . '</div>
            <div class="uf-count"><strong>' . $qtd . '</strong> clientes</div>
            <div class="uf-perc">' . $perc . '% do total</div>
        </div>';
        $contadorUF++;
    }
    
    $html .= '</div>';
    
    // ============================================================
    // TABELA RESUMO POR UF
    // ============================================================
    $html .= '
                <div class="section-title" style="margin-top: 30px;">📋 Resumo Detalhado por Estado</div>
                <table class="table-resumo">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th style="text-align: center;">Total</th>
                            <th style="text-align: center;">Com CNPJ</th>
                            <th style="text-align: center;">Com CPF</th>
                            <th style="text-align: center;">% do Total</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($statsUF as $uf => $qtd) {
        // Conta CNPJ e CPF por UF
        $cnpjUF = 0;
        $cpfUF = 0;
        foreach ($clientes as $cli) {
            if (($cli['uf'] ?: 'N/A') !== $uf) continue;
            $cnpjLimpo = preg_replace('/[^0-9]/', '', $cli['cnpj'] ?? '');
            $cpfLimpo  = preg_replace('/[^0-9]/', '', $cli['cpf'] ?? '');
            if (strlen($cnpjLimpo) === 14) $cnpjUF++;
            elseif (strlen($cpfLimpo) === 11) $cpfUF++;
        }
        
        $perc = $totalClientes > 0 ? round(($qtd * 100) / $totalClientes, 1) : 0;
        
        $html .= '<tr>
            <td><strong>' . htmlspecialchars($uf) . '</strong></td>
            <td style="text-align: center;"><span style="background: #e8f4fd; color: #0066cc; padding: 2px 10px; border-radius: 10px; font-weight: 600; font-size: 12px;">' . $qtd . '</span></td>
            <td style="text-align: center; color: #0066cc; font-weight: 600;">' . $cnpjUF . '</td>
            <td style="text-align: center; color: #28a745; font-weight: 600;">' . $cpfUF . '</td>
            <td style="text-align: center; color: #666;">' . $perc . '%</td>
        </tr>';
    }
    
    $html .= '
                    </tbody>
                </table>
                
                <!-- ALERTA FINAL -->
                <div class="alert alert-info">
                    <div class="alert-icon">📎</div>
                    <div>
                        <strong>Planilha completa em anexo</strong><br>
                        <span style="font-size: 12px;">O arquivo Excel contém todos os ' . number_format($totalClientes, 0, ',', '.') . ' clientes ' . ($idFilial ? 'da <strong>Filial ' . $idFilial . '</strong>' : 'da <strong>visão geral</strong>') . ' com CNPJ/CPF, representante, cidade, e-mail e telefone.</span>
                    </div>
                </div>
                
            </div>
            
            <!-- FOOTER -->
            <div class="footer">
                <div class="logo">🥗 Nutricional Distribuidora</div>
                <p><strong>Relatório automático</strong> gerado pelo Portal Nutricional</p>
                <p style="margin-top: 10px; font-size: 11px;">Para dúvidas ou correções no cadastro, entre em contato com o departamento responsável.</p>
            </div>
            
        </div>
    </body>
    </html>';
    
    return $html;
}
    /**
     * Gera link baseado no ambiente
     */
    public static function geralink() {
        if($_SERVER['HTTP_HOST'] == 'localhost'){
            return "http://" . $_SERVER['HTTP_HOST'] . "/";
        } else {
            return "https://" . $_SERVER['HTTP_HOST'] . "/";  
        }
    }

    public static function salvarLink($linkCompleto, $tipo = 'default') {
        // Sanitiza o tipo para evitar path traversal
        $tipo = preg_replace('/[^a-zA-Z0-9_-]/', '', $tipo);
        $arquivo = __DIR__ . "/link_info_{$tipo}.json";
        
        // Verifica se pode escrever no diretório
        if (!is_writable(dirname($arquivo))) {
            throw new Exception("Não é possível escrever no diretório: " . dirname($arquivo));
        }
        
        $dados = [
            'link' => $linkCompleto,
            'usado' => false,
            'data_criacao' => date('Y-m-d H:i:s'),
            'data_uso' => null
        ];
        
        $resultado = file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT));
        
        if ($resultado === false) {
            throw new Exception("Erro ao salvar arquivo: {$arquivo}");
        }
        
        return true;
    }
/**
     * Verifica se hoje é o último dia útil do mês (considerando Seg-Sex)
     * @return bool
     */
    public static function isUltimoDiaUtil() {
        $hoje = new DateTime();
        $dia = (int)$hoje->format('d');
        $diaSemana = (int)$hoje->format('N'); // 1 (Seg) a 7 (Dom)
        $ultimoDiaMes = (int)$hoje->format('t');
        
        // Se for fim de semana, não é dia útil de disparo
        if ($diaSemana >= 6) {
            return false;
        }

        // Cenário 1: Hoje é o último dia do mês e é dia útil
        if ($dia === $ultimoDiaMes) {
            return true;
        }

        // Cenário 2: O último dia do mês cai no fim de semana, 
        // então o último dia útil é a sexta-feira anterior.
        $dataUltimo = new DateTime($hoje->format('Y-m-t'));
        $uDiaSemana = (int)$dataUltimo->format('N');

        // Se o último dia do mês for Sábado(6), o útil é o dia anterior(Sexta)
        if ($uDiaSemana === 6 && $dia === ($ultimoDiaMes - 1)) {
            return true;
        }

        // Se o último dia do mês for Domingo(7), o útil é 2 dias antes(Sexta)
        if ($uDiaSemana === 7 && $dia === ($ultimoDiaMes - 2)) {
            return true;
        }

        return false;
    }

public static function validarToken($tokenRecebido, $tokenEsperado, $tipo = 'default') {
    $tipo = preg_replace('/[^a-zA-Z0-9_-]/', '', $tipo);
    

    if (self::verificarLink($tipo)) {
        throw new Exception("Link já foi utilizado");
    }

    $codigoDesmascarado = self::decrypt(urldecode($tokenRecebido), CHAVE_SECRETA);
    if (!hash_equals($codigoDesmascarado, $tokenEsperado)) {
        throw new Exception("Token inválido");
    }

    self::marcarComoUsado($tipo);
    
    return true;
}
public static function validarTokenComExpiracao($tokenRecebido, $tokenEsperado, $expiracaoHoras = 24) {
    $codigoDesmascarado = self::decrypt(urldecode($tokenRecebido), CHAVE_SECRETA);
    
    if (!hash_equals($codigoDesmascarado, $tokenEsperado)) {
        throw new Exception("Token inválido");
    }
    
    // Verifica expiração baseada no timestamp
    $partes = explode('_', $codigoDesmascarado);
    if (count($partes) === 2) {
        $timestamp = $partes[1];
        $diferenca = time() - $timestamp;
        if ($diferenca > ($expiracaoHoras * 3600)) {
            throw new Exception("Token expirado");
        }
    }
    
    return true;
}

public static function limparTodosLinks() {
    $tipos = ['representantes', 'gestores', 'default'];
    foreach ($tipos as $tipo) {
        $arquivo = __DIR__ . "/link_info_{$tipo}.json";
        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
    }
    return "Todos os links foram limpos!";
}

    public static function limparLinksUsados($tipo = null) {
        $arquivo = __DIR__ . '/logs/links_usados.log';
        
        // Garante que o diretório existe
        if (!is_dir(dirname($arquivo))) {
            mkdir(dirname($arquivo), 0755, true);
        }
        
        if (!file_exists($arquivo)) {
            return true;
        }
        
        if ($tipo) {
            // Limpa apenas links do tipo específico
            $conteudo = file_get_contents($arquivo);
            if ($conteudo === false) {
                throw new Exception("Não foi possível ler o arquivo: {$arquivo}");
            }
            
            $linhas = explode("\n", $conteudo);
            $novasLinhas = [];
            
            foreach ($linhas as $linha) {
                if (strpos($linha, "|{$tipo}|") === false && !empty(trim($linha))) {
                    $novasLinhas[] = $linha;
                }
            }
            
            file_put_contents($arquivo, implode("\n", $novasLinhas));
        } else {
            // Limpa tudo
            file_put_contents($arquivo, '');
        }
        
        return true;
    }

    public static function verificarLink($tipo = 'default') {
        // Sanitiza o tipo
        $tipo = preg_replace('/[^a-zA-Z0-9_-]/', '', $tipo);
        $arquivo = __DIR__ . "/link_info_{$tipo}.json";
        
        if (!file_exists($arquivo)) {
            return false;
        }
        
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new Exception("Não foi possível ler o arquivo: {$arquivo}");
        }
        
        $dados = json_decode($conteudo, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido no arquivo: {$arquivo}");
        }
        
        return $dados['usado'] ?? false;
    }

    public static function marcarComoUsado($tipo = 'default') {
        // Sanitiza o tipo
        $tipo = preg_replace('/[^a-zA-Z0-9_-]/', '', $tipo);
        $arquivo = __DIR__ . "/link_info_{$tipo}.json";
        
        if (!file_exists($arquivo)) {
            return false;
        }
        
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new Exception("Não foi possível ler o arquivo: {$arquivo}");
        }
        
        $dados = json_decode($conteudo, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON inválido no arquivo: {$arquivo}");
        }
        
        $dados['usado'] = true;
        $dados['data_uso'] = date('Y-m-d H:i:s');
        
        return file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT)) !== false;
    }

    // Funções de criptografia
    public static function encrypt($data, $key) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-128-CBC'));
        $encryptedData = openssl_encrypt($data, 'AES-128-CBC', $key, 0, $iv);
        return base64_encode($encryptedData . '::' . base64_encode($iv));
    }

public static function decrypt($data, $key) {
    $parts = explode('::', base64_decode($data), 2);
    if (count($parts) !== 2) {
        throw new Exception("Dados criptografados inválidos");
    }
    list($encryptedData, $iv) = $parts;
    $iv = base64_decode($iv);
    if ($iv === false) {
        throw new Exception("IV inválido");
    }
    return openssl_decrypt($encryptedData, 'AES-128-CBC', $key, 0, $iv);
}


  public static function construirEmailGestor($gestor) {

    // Formata os valores monetários
    $valorInadimplencia = number_format($gestor['valor_inadimplencia'], 2, ',', '.');
    $dias30 = number_format($gestor['dias_30'], 2, ',', '.');
    $dias60 = number_format($gestor['dias_60'], 2, ',', '.');
    $mais60Dias = number_format($gestor['mais_60_dias'], 2, ',', '.');
    $aVencer = number_format($gestor['a_vencer'], 2, ',', '.');
    $prox30Dias = number_format($gestor['prox_30_dias'], 2, ',', '.');
    $vencidos = number_format($gestor['vencidos'], 2, ',', '.');
    $prazoMedio = number_format($gestor['prazo_medio'] ?? 0, 2, ',', '.');

    
    // Calcula os encargos e juros
    $encargosJuros = $gestor['valor_inadimplencia'] - $gestor['vencidos'];
    $encargosJurosFormatado = number_format($encargosJuros, 2, ',', '.');
    
    $percentualEncargos = $gestor['vencidos'] > 0 ? ($encargosJuros / $gestor['vencidos']) * 100 : 0;
    $percentualEncargosFormatado = number_format($percentualEncargos, 2, ',', '.');
    
    // Formata percentuais
    $percentualGeral = number_format($gestor['percentual_geral'], 2, ',', '.');
    $percentual30 = number_format($gestor['percentual_30'], 2, ',', '.');
    $percentual60 = number_format($gestor['percentual_60'], 2, ',', '.');
    $percentualMais60 = number_format($gestor['percentual_mais_60'], 2, ',', '.');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #0066cc, #004499); color: white; padding: 25px; border-radius: 8px; text-align: center; margin-bottom: 25px; }
            .header h1 { margin: 0; font-size: 28px; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; }
            .table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
            .table th, .table td { padding: 14px; text-align: left; border-bottom: 1px solid #e0e0e0; }
            .table th { background-color: #f8f9fa; font-weight: bold; color: #333; font-size: 13px; text-transform: uppercase; }
            .highlight { background-color: #e3f2fd; font-weight: bold; }
            .danger { background-color: #ffebee; color: #c62828; font-weight: bold; }
            .warning { background-color: #fff8e1; color: #ff8f00; }
            .success { background-color: #e8f5e8; color: #2e7d32; }
            .info { background-color: #e3f2fd; color: #1565c0; }
            .encargos { background-color: #fff3cd; color: #856404; font-weight: bold; }
            .info-box { background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0066cc; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px; }
            .btn-dashboard { 
                display: inline-block; 
                background: linear-gradient(135deg, #0066cc, #004499); 
                color: white; 
                padding: 12px 25px; 
                text-decoration: none; 
                border-radius: 5px; 
                font-weight: bold; 
                margin-top: 15px;
                transition: all 0.3s ease;
            }
            .btn-dashboard:hover { 
                background: linear-gradient(135deg, #004499, #003366); 
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }
            .valor { text-align: right; font-family: monospace; }
            .percentual { text-align: center; font-family: monospace; }
            .total-line { border-top: 2px solid #333; font-weight: bold; }
            .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-success { background: #d4edda; color: #155724; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📊 Relatório de Inadimplência</h1>
                <p>Gestor: ' . htmlspecialchars($gestor['nomegestor']) . ' | Data: ' . date('d/m/Y') . '</p>
            </div>
            
            <div class="info-box">
                <h3 style="margin-top: 0; color: #0066cc;">📈 Visão Geral</h3>
                <p>Este relatório apresenta a situação de inadimplência da sua carteira de clientes.</p>
                <p><strong>Total de Clientes Vencidos:</strong> ' . $gestor['total_clientes_vencidos'] . '</p>
                <p><strong>Títulos Vencidos:</strong> ' . $gestor['total_titulos_vencidos'] . '</p>
                <p><strong>⏱️ Prazo Médio de Venda da Carteira:</strong> ' . $prazoMedio . ' dias</p>
             </div>
            
            <h3>💰 Composição da Inadimplência</h3>
            <table class="table">
                <tr class="info">
                    <td><strong>Valor Principal Vencido</strong></td>
                    <td class="valor"><strong>R$ ' . $vencidos . '</strong></td>
                </tr>
                <tr class="warning">
                    <td>Percentual de Inadimplência sobre Carteira</td>
                    <td class="percentual"><strong>' . $percentualGeral . '%</strong></td>
                </tr>
            </table>

            <h3>📅 Distribuição por Faixa de Atraso</h3>
            <table class="table">
                <tr>
                    <th>Faixa de Atraso</th>
                    <th class="valor">Valor</th>
                    <th class="percentual">Percentual</th>
                    <th>Análise</th>
                </tr>
                <tr>
                    <td>Até 30 dias</td>
                    <td class="valor">R$ ' . $dias30 . '</td>
                    <td class="percentual">' . $percentual30 . '%</td>
                    <td>🟠 Risco moderado</td>
                </tr>
                <tr class="danger">
                    <td>31 a 60 dias</td>
                    <td class="valor">R$ ' . $dias60 . '</td>
                    <td class="percentual">' . $percentual60 . '%</td>
                    <td>🔴 Risco alto - Prioridade máxima</td>
                </tr>
                <tr class="danger">
                    <td>Mais de 60 dias</td>
                    <td class="valor">R$ ' . $mais60Dias . '</td>
                    <td class="percentual">' . $percentualMais60 . '%</td>
                    <td>🔴 Risco Inadimplência - Prioridade máxima</td>
                </tr>
            </table>

            <!-- BOTAO_DASHBOARD -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="http://portal.nutricionalbr.com:8888/#/indicadores/dashboard/NzA=" class="btn-dashboard">
                    📈 Para saber mais detalhes clique aqui
                </a>
            </div>
            
            <div class="footer">
                <p><em>📍 Nutricional - Sistema de Gestão<br>📞 Em caso de dúvidas, entre em contato com o departamento financeiro<br>🕐 Este é um relatório automático. Favor não responder.</em></p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

    public static function criarAvisoAnexos($temExcelRepresentantes = true, $temExcelClientes = false) {
  
    $html = '
    <div style="background-color: #e8f4fd; border: 2px solid #0066cc; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <div style="display: flex; align-items: center; margin-bottom: 15px;">
            <div style="font-size: 24px; margin-right: 15px;">📎</div>
            <h3 style="color: #0066cc; margin: 0;">Arquivos em Anexo</h3>
        </div>
        
        <div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #0066cc;">';
    
    if ($temExcelRepresentantes) {
        $html .= '
            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                <div style="font-size: 20px; margin-right: 10px;">📊</div>
                <div>
                    <strong style="color: #0066cc;">Relatório de Representantes</strong>
                    <p style="margin: 5px 0 0 0; color: #555; font-size: 13px;">
                        Planilha completa com todos os representantes, valores vencidos, distribuição por faixa de atraso e percentuais.
                    </p>
                </div>
            </div>';
    }
    
    if ($temExcelClientes) {
        $html .= '
            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                <div style="font-size: 20px; margin-right: 10px;">👥</div>
                <div>
                    <strong style="color: #0066cc;">Detalhamento por Cliente</strong>
                    <p style="margin: 5px 0 0 0; color: #555; font-size: 13px;">
                        Planilha detalhada com todos os clientes inadimplentes, incluindo dias em atraso, valores, histórico de contato e informações completas.
                    </p>
                </div>
            </div>';
    }
    
    $html .= '
        </div>
        
        <div style="margin-top: 15px; padding: 12px; background: #d1ecf1; border-radius: 5px; border-left: 4px solid #0c5460;">
            <div style="display: flex; align-items: flex-start;">
                <div style="font-size: 18px; margin-right: 10px;">💡</div>
                <div>
                    <strong style="color: #0c5460;">Como utilizar os anexos:</strong>
                    <ul style="margin: 8px 0 0 0; color: #555; font-size: 13px;">
                        <li>Use o <strong>Relatório de Representantes</strong> para análise gerencial da carteira</li>
                        <li>Utilize o <strong>Detalhamento por Cliente</strong> para ações específicas de cobrança</li>
                        <li>Ordene por "Dias em Atraso" para priorizar os casos mais críticos</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>';
    
    return $html;
    }

 public static function gerarExcelRepresentantes($idSupervisor, $dadosRepresentantes) {
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título da planilha
        $sheet->setTitle('Representantes');
        
        // Cabeçalhos (13 colunas: A até M) - ADICIONADO PRAZO_MEDIO
        $headers = [
            'Nome do Representante',
            'Valor Total',
            'Vencidos', 
            'Percentual',
            'Total Titulos',
            'Total Clientes',
            '30 Dias',
            'Perc. 30 Dias', 
            '60 Dias',
            'Perc. 60 Dias',
            'Mais 60 Dias',
            'Perc. Mais 60 Dias',
            'Prazo Médio de Venda',        // NOVO!
            'Pedidos Aguardando'  // NOVO!
        ];
        
        $sheet->fromArray($headers, NULL, 'A1');
        
        // Formatação dos cabeçalhos
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
                'startColor' => ['rgb' => '38574b']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        $sheet->getStyle('A1:N1')->applyFromArray($headerStyle); // A até N
        
        // Preenchimento dos Dados
        $row = 2;
        foreach ($dadosRepresentantes as $representante) {
            $sheet->setCellValue('A' . $row, $representante['Nome do Representante'] ?? 'Não Informado');
            $sheet->setCellValue('B' . $row, $representante['Valor Total'] ?? 0);
            $sheet->setCellValue('C' . $row, $representante['Vencidos'] ?? 0);
            $sheet->setCellValue('D' . $row, $representante['Percentual'] ?? 0);
            $sheet->setCellValue('E' . $row, $representante['Total Titulos'] ?? 0);
            $sheet->setCellValue('F' . $row, $representante['Total Clientes'] ?? 0);
            $sheet->setCellValue('G' . $row, $representante['30 Dias'] ?? 0);
            $sheet->setCellValue('H' . $row, $representante['Perc. 30 Dias'] ?? 0);
            $sheet->setCellValue('I' . $row, $representante['60 Dias'] ?? 0);
            $sheet->setCellValue('J' . $row, $representante['Perc. 60 Dias'] ?? 0);
            $sheet->setCellValue('K' . $row, $representante['Mais 60 Dias'] ?? 0);
            $sheet->setCellValue('L' . $row, $representante['Perc. Mais 60 Dias'] ?? 0);
            $sheet->setCellValue('M' . $row, $representante['Prazo Médio'] ?? 0);      // NOVO!
            $sheet->setCellValue('N' . $row, $representante['Pedidos Aguardando'] ?? 0); // NOVO!
            $row++;
        }
        
        $lastRow = $row - 1;
        
        if ($lastRow > 1) {
            // Formatação de Moeda (B, C, G, I, K)
            $currencyColumns = ['B', 'C', 'G', 'I', 'K'];
            $currencyStyle = [
                'numberFormat' => ['formatCode' => '"R$" #,##0.00']
            ];
            
            foreach ($currencyColumns as $column) {
                $sheet->getStyle($column . '2:' . $column . $lastRow)->applyFromArray($currencyStyle);
            }
            
            // Formatação Percentual (D, H, J, L)
            $percentStyle = [
                'numberFormat' => ['formatCode' => '#,##0.00"%"']
            ];
            
            foreach (['D', 'H', 'J', 'L'] as $column) {
                $sheet->getStyle($column . '2:' . $column . $lastRow)->applyFromArray($percentStyle);
            }
            
            // Formatação Numérica Inteira (E, F, N)
            $numberStyle = [
                'numberFormat' => ['formatCode' => '#,##0']
            ];
            $sheet->getStyle('E2:F' . $lastRow)->applyFromArray($numberStyle);
            $sheet->getStyle('N2:N' . $lastRow)->applyFromArray($numberStyle);
            
            // Formatação Prazo Médio (M) - 1 casa decimal
            $sheet->getStyle('M2:M' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.0');
        }
        
        // Auto dimensionar colunas
        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Congela o topo e ativa filtros
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:N1');
        
        // Salvamento do Arquivo
        $filename = "resumo_representantes_{$idSupervisor}_" . date('Y-m-d_H-i') . ".xlsx";
        $filepath = __DIR__ . "/temp/{$filename}";
        
        if (!is_dir(__DIR__ . '/temp')) {
            mkdir(__DIR__ . '/temp', 0755, true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return $filepath;
        
    } catch (Exception $e) {
        throw new Exception("Erro ao gerar Excel de representantes: " . $e->getMessage());
    }
}
public static function gerarExcelClientesDetalhado($dadosClientes) {
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título da planilha
        $sheet->setTitle('Clientes Detalhados');
        
        // Cabeçalhos - COM PRAZO_MEDIO_CLIENTE
        $headers = [
            'Nome Representante',
            'Nome Fantasia',
            'Documento',
            'Vencimento',
            'Valor Total',
            'Valor Saldo',
            'Data Emissão',
            'Dias em Atraso',
            'Dias do Último Evento',
            'Usuário do Evento',
            'Prazo Médio do Cliente'  // Coluna K
        ];
        
        $sheet->fromArray($headers, NULL, 'A1');
        
        // Formatação dos cabeçalhos
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
                'startColor' => ['rgb' => '38574b']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        
        // Inserção dos Dados
        $row = 2;
        foreach ($dadosClientes as $cliente) {
            $sheet->setCellValue('A' . $row, $cliente['nome_representante'] ?? '');
            $sheet->setCellValue('B' . $row, $cliente['Nome Fantasia'] ?? '');
            $sheet->setCellValue('C' . $row, $cliente['documento'] ?? '');
            $sheet->setCellValue('D' . $row, $cliente['Vencimento'] ?? '');
            $sheet->setCellValue('E' . $row, (float)($cliente['Valor Total'] ?? 0));
            $sheet->setCellValue('F' . $row, (float)($cliente['Valor Saldo'] ?? 0));
            $sheet->setCellValue('G' . $row, $cliente['Data Emissão'] ?? '');
            $sheet->setCellValue('H' . $row, (int)($cliente['Dias em Atraso'] ?? 0));
            $sheet->setCellValue('I' . $row, (int)($cliente['Dias do Último Evento'] ?? 0));
            $sheet->setCellValue('J' . $row, $cliente['Usuário'] ?? '');
            
            // PRAZO MÉDIO DO CLIENTE - GARANTE QUE VEM DA CONSULTA
            $prazoMedioCliente = isset($cliente['Prazo medio cliente']) ? (float)$cliente['Prazo medio cliente'] : 0;
            $sheet->setCellValue('K' . $row, $prazoMedioCliente);
            
            $row++;
        }
        
        $lastRow = $row - 1;
        
        if ($lastRow > 1) {
            // Formatação Moeda (E, F)
            $sheet->getStyle('E2:F' . $lastRow)->getNumberFormat()->setFormatCode('"R$" #,##0.00');
            
            // Formatação Prazo Médio (K) - 1 casa decimal
            $sheet->getStyle('K2:K' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.0');
            
            // Destaque Vermelho para atraso > 60
            $warningStyle = ['font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true]];
            for ($i = 2; $i <= $lastRow; $i++) {
                $diasAtraso = $sheet->getCell('H' . $i)->getValue();
                if ($diasAtraso > 60) {
                    $sheet->getStyle('H' . $i)->applyFromArray($warningStyle);
                }
            }
        }
        
        // Auto dimensionar colunas
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:K1');
        
        // Salva arquivo temporário
        $filename = "detalhado_clientes_" . date('Y-m-d_H-i') . ".xlsx";
        $filepath = __DIR__ . "/temp/{$filename}";
        
        if (!is_dir(__DIR__ . '/temp')) {
            mkdir(__DIR__ . '/temp', 0755, true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return $filepath;
        
    } catch (Exception $e) {
        throw new Exception("Erro ao gerar Excel detalhado de clientes: " . $e->getMessage());
    }
}
/**
 * Constrói o e-mail para o Representante (Mensal) - Layout Profissional
 */
public static function construirEmailRepresentanteMensal($dadosRep, $dadosClientes) {
    
    // Formata os valores
    $vencidos = number_format($dadosRep['vencidos'], 2, ',', '.');
    $dias30 = number_format($dadosRep['dias_30'], 2, ',', '.');
    $dias60 = number_format($dadosRep['dias_60'], 2, ',', '.');
    $mais60Dias = number_format($dadosRep['mais_60_dias'], 2, ',', '.');
    $percentualGeral = number_format($dadosRep['percentual_geral'], 2, ',', '.');
    $percentual30 = number_format($dadosRep['percentual_30'], 2, ',', '.');
    $percentual60 = number_format($dadosRep['percentual_60'], 2, ',', '.');
    $percentualMais60 = number_format($dadosRep['percentual_mais_60'], 2, ',', '.');
    $prazoMedio = number_format($dadosRep['prazo_medio'] ?? 0, 2, ',', '.');
    
    $totalClientes = (int)$dadosRep['total_clientes_vencidos'];
    $totalTitulos = (int)$dadosRep['total_titulos_vencidos'];
    $qtdClientes = count($dadosClientes);
    $percentualRaw = (float)$dadosRep['percentual_geral'];
    
    // Define a cor do card de inadimplência baseado no percentual
    $corInadimplencia = 'azul';
    if ($percentualRaw > 10) {
        $corInadimplencia = 'vermelho';
    } elseif ($percentualRaw > 5) {
        $corInadimplencia = 'laranja';
    }
    
        // Define o nível do alerta - APENAS O PERCENTUAL
    $alerta = '';
    if ($percentualRaw > 5) {
        $alerta = '
            <div style="padding: 0 40px;">
                <div class="alerta alerta-warning">
                    <span class="icon">⚠️</span>
                    <div class="texto">
                        <strong>Inadimplência em nível crítico!</strong> O percentual atual é de <strong>' . $percentualGeral . '%</strong>.
                    </div>
                </div>
            </div>';
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório Mensal - Representante</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
                background: #f0f2f5; 
                padding: 20px;
                color: #333;
                line-height: 1.6;
            }
            .container { 
                max-width: 900px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 16px; 
                box-shadow: 0 8px 40px rgba(0,0,0,0.10);
                overflow: hidden;
            }
            
            /* HEADER */
            .header { 
                background: linear-gradient(135deg, #0d47a1, #1565c0); 
                color: white; 
                padding: 32px 40px;
                position: relative;
            }
            .header::after {
                content: "";
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #42a5f5, #0d47a1, #42a5f5);
            }
            .header h1 { 
                font-size: 26px; 
                font-weight: 700; 
                letter-spacing: -0.5px;
                margin-bottom: 4px;
            }
            .header .subtitulo {
                font-size: 18px;
                font-weight: 500;
                opacity: 0.95;
                margin-top: 2px;
            }
            .header .periodo {
                display: inline-block;
                background: rgba(255,255,255,0.15);
                padding: 6px 20px;
                border-radius: 20px;
                font-size: 13px;
                margin-top: 10px;
                font-weight: 500;
            }
            .header .periodo span {
                font-weight: 700;
            }
            
            /* CARDS */
            .cards {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                padding: 30px 40px 20px 40px;
            }
            .card {
                background: #ffffff;
                border-radius: 12px;
                padding: 20px 18px;
                text-align: center;
                border: 1px solid #e8ecf0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .card .valor {
                font-size: 30px;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: -0.5px;
            }
            .card .valor.azul { color: #1565c0; }
            .card .valor.verde { color: #2e7d32; }
            .card .valor.vermelho { color: #c62828; }
            .card .valor.laranja { color: #e65100; }
            .card .valor.roxo { color: #6a1b9a; }
            
            .card .label {
                font-size: 13px;
                color: #6b7a8a;
                margin-top: 6px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .card .sub-label {
                font-size: 12px;
                color: #95a5a6;
                margin-top: 3px;
                font-weight: 400;
            }
            .card .icone {
                font-size: 22px;
                display: block;
                margin-bottom: 6px;
            }
            
            /* CONTEÚDO */
            .content { 
                padding: 0 40px 30px 40px; 
            }
            
            .section-title {
                font-size: 17px;
                font-weight: 600;
                color: #1a237e;
                margin: 28px 0 16px 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #e8ecf0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .section-title .badge-count {
                background: #e3f2fd;
                color: #1565c0;
                padding: 0 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
            }
            
            /* TABELA */
            .table-wrapper {
                overflow-x: auto;
                border-radius: 10px;
                border: 1px solid #e8ecf0;
                background: #ffffff;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
            }
            table thead {
                background: #f8f9fa;
            }
            table th {
                color: #1a237e;
                font-weight: 600;
                padding: 12px 16px;
                text-align: left;
                border-bottom: 2px solid #e8ecf0;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.4px;
            }
            table td {
                padding: 11px 16px;
                border-bottom: 1px solid #f1f3f5;
                color: #333;
            }
            table tr:last-child td { border-bottom: none; }
            table tbody tr:hover { background: #f8f9fa; }
            
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .font-mono { font-family: "SF Mono", "Courier New", monospace; }
            .font-bold { font-weight: 600; }
            
            /* BADGES */
            .badge {
                display: inline-block;
                padding: 3px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-align: center;
            }
            .badge-success { background: #e8f5e9; color: #2e7d32; }
            .badge-warning { background: #fff3e0; color: #e65100; }
            .badge-danger { background: #ffebee; color: #c62828; }
            .badge-info { background: #e3f2fd; color: #1565c0; }
            
            /* ALERTAS */
            .alerta {
                padding: 16px 20px;
                border-radius: 10px;
                margin: 20px 0;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            .alerta-info {
                background: #e3f2fd;
                border-left: 4px solid #1565c0;
            }
            .alerta-warning {
                background: #fff3e0;
                border-left: 4px solid #e65100;
            }
            .alerta-success {
                background: #e8f5e9;
                border-left: 4px solid #2e7d32;
            }
            .alerta .icon { font-size: 20px; }
            .alerta .texto { font-size: 14px; color: #333; }
            .alerta .texto strong { color: #1a237e; }
            
            /* FOOTER */
            .footer {
                background: #f8f9fa;
                padding: 20px 40px;
                text-align: center;
                border-top: 1px solid #e8ecf0;
                color: #6b7a8a;
                font-size: 12px;
                line-height: 1.8;
            }
            .footer strong { color: #1a237e; }
            
            /* RESPONSIVO */
            @media (max-width: 768px) {
                .cards { 
                    grid-template-columns: repeat(2, 1fr);
                    padding: 20px;
                }
                .header { padding: 24px 20px; }
                .header h1 { font-size: 22px; }
                .content { padding: 0 20px 20px 20px; }
                .footer { padding: 15px 20px; }
                .card .valor { font-size: 24px; }
            }
            @media (max-width: 450px) {
                .cards { grid-template-columns: 1fr; }
                .card { padding: 16px; }
                table { font-size: 12px; }
                table th, table td { padding: 8px 10px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- HEADER -->
            <div class="header">
                <h1>📊 Relatório Mensal de Inadimplência</h1>
                <div class="subtitulo">' . htmlspecialchars($dadosRep['nomerepresentante']) . '</div>
                <div class="periodo">📅 Período: <span>' . date('m/Y', strtotime('-1 month')) . '</span></div>
            </div>
            
            <!-- CARDS -->
            <div class="cards">
                <div class="card">
                    <span class="icone">⏱️</span>
                    <div class="valor azul">' . $prazoMedio . '</div>
                    <div class="label">Prazo Médio de Venda</div>
                    <div class="sub-label">dias</div>
                </div>
                <div class="card">
                    <span class="icone">📊</span>
                    <div class="valor ' . $corInadimplencia . '">' . $percentualGeral . '%</div>
                    <div class="label">Inadimplência</div>
                    <div class="sub-label">sobre a carteira</div>
                </div>
                <div class="card">
                    <span class="icone">👥</span>
                    <div class="valor roxo">' . $totalClientes . '</div>
                    <div class="label">Clientes Vencidos</div>
                    <div class="sub-label">' . $totalTitulos . ' títulos em aberto</div>
                </div>
                <div class="card">
                    <span class="icone">💰</span>
                    <div class="valor verde">R$ ' . $vencidos . '</div>
                    <div class="label">Total Vencido</div>
                    <div class="sub-label">em aberto</div>
                </div>
            </div>
            
            <!-- ALERTA DE ALTO RISCO (quando > 5%) -->
            ' . $alerta . '
            
            <!-- CONTEÚDO -->
            <div class="content">
                <!-- Distribuição -->
                <div class="section-title">
                    📅 Distribuição por Faixa de Atraso
                    <span class="badge-count">Total: R$ ' . $vencidos . '</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Faixa de Atraso</th>
                                <th class="text-right">Valor</th>
                                <th class="text-right">% do Total</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>🟡 Até 30 dias</td>
                                <td class="text-right font-mono">R$ ' . $dias30 . '</td>
                                <td class="text-right font-mono">' . $percentual30 . '%</td>
                                <td class="text-center"><span class="badge badge-success">Regular</span></td>
                            </tr>
                            <tr>
                                <td>🟠 31 a 60 dias</td>
                                <td class="text-right font-mono">R$ ' . $dias60 . '</td>
                                <td class="text-right font-mono">' . $percentual60 . '%</td>
                                <td class="text-center"><span class="badge badge-warning">Atenção</span></td>
                            </tr>
                            <tr style="background: #ffebee;">
                                <td>🔴 Mais de 60 dias</td>
                                <td class="text-right font-mono font-bold">R$ ' . $mais60Dias . '</td>
                                <td class="text-right font-mono font-bold">' . $percentualMais60 . '%</td>
                                <td class="text-center"><span class="badge badge-danger">Crítico</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Clientes Detalhados -->
                <div class="section-title">
                    👥 Clientes com Débitos
                    <span class="badge-count">' . $qtdClientes . ' clientes</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Documento</th>
                                <th>Vencimento</th>
                                <th class="text-right">Valor</th>
                                <th class="text-center">Dias em Atraso</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    // Limita a 25 clientes no e-mail
    $limite = 25;
    $contador = 0;
    $temMais = false;
    foreach ($dadosClientes as $cli) {
        if ($contador++ >= $limite) {
            $temMais = true;
            break;
        }
        $dias = (int)($cli['Dias em Atraso'] ?? 0);
        if ($dias > 60) {
            $badge = 'badge-danger';
        } elseif ($dias > 30) {
            $badge = 'badge-warning';
        } else {
            $badge = 'badge-success';
        }
        $html .= '
            <tr>
                <td>' . htmlspecialchars($cli['Nome Fantasia'] ?? '') . '</td>
                <td>' . htmlspecialchars($cli['documento'] ?? '') . '</td>
                <td>' . htmlspecialchars($cli['Vencimento'] ?? '') . '</td>
                <td class="text-right font-mono">R$ ' . number_format($cli['Valor Saldo'] ?? 0, 2, ',', '.') . '</td>
                <td class="text-center"><span class="badge ' . $badge . '">' . $dias . ' dias</span></td>
            </tr>';
    }
    
    if ($temMais) {
        $restante = count($dadosClientes) - $limite;
        $html .= '
            <tr>
                <td colspan="5" style="text-align:center; color:#95a5a6; padding: 16px; font-style:italic;">
                    📎 ... e mais ' . $restante . ' clientes. Consulte a planilha em anexo para o detalhamento completo.
                </td>
            </tr>';
    }
    
    $html .= '
                        </tbody>
                    </table>
                </div>
                
                <!-- DICA -->
                <div class="alerta alerta-info" style="margin-top: 25px;">
                    <span class="icon">📎</span>
                    <div class="texto">
                        <strong>Planilha em Anexo:</strong> O arquivo Excel contém o detalhamento completo de todos os clientes,
                        incluindo histórico de contato e informações complementares para análise.
                    </div>
                </div>
            </div>
            
            <!-- FOOTER -->
            <div class="footer">
                <p>
                    <strong>Nutricional Distribuidora</strong> • Sistema de Gestão Financeira<br>
                    📞 Em caso de dúvidas, entre em contato com o departamento financeiro<br>
                    ⏱️ Este é um relatório automático mensal. Favor não responder.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
  /**
 * Gera Excel para o Representante (Mensal) com prazo_medio - VERSÃO CORRIGIDA
 */
public static function gerarExcelRepresentanteMensal($idRep, $dadosRep, $dadosClientes) {
    
    // ============================================================
    // VALIDAÇÃO DE ENTRADA
    // ============================================================
    if (empty($idRep) || $idRep <= 0) {
        throw new Exception("ID do representante inválido: " . $idRep);
    }
    
    if (empty($dadosRep) || !is_array($dadosRep)) {
        throw new Exception("Dados do representante inválidos ou vazios");
    }
    
    // ============================================================
    // LOG DE DEBUG
    // ============================================================
    $logDir = __DIR__ . '/erros_log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    file_put_contents(
        $logDir . '/excel_debug.log',
        date('Y-m-d H:i:s') . " - Gerando Excel para ID: $idRep, Clientes: " . count($dadosClientes) . "\n",
        FILE_APPEND
    );
    
    // ============================================================
    // CARREGA O AUTOLOAD DO PHPOFFICE
    // ============================================================
    require_once __DIR__ . '/vendor/autoload.php';
    
    // ============================================================
    // VERIFICA SE O DIRETÓRIO TEMP EXISTE
    // ============================================================
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        if (!@mkdir($tempDir, 0755, true)) {
            throw new Exception("Não foi possível criar o diretório: {$tempDir}");
        }
    }
    
    if (!is_writable($tempDir)) {
        throw new Exception("Diretório temp não é gravável: {$tempDir}");
    }
    
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // ============================================================
        // ABA 1: RESUMO DO REPRESENTANTE
        // ============================================================
        $sheetResumo = $spreadsheet->getActiveSheet();
        $sheetResumo->setTitle('Resumo');
        
        // Cabeçalho
        $sheetResumo->setCellValue('A1', 'RELATÓRIO MENSAL - REPRESENTANTE');
        $sheetResumo->setCellValue('A2', 'Representante:');
        $sheetResumo->setCellValue('B2', $dadosRep['nomerepresentante'] ?? 'N/A');
        $sheetResumo->setCellValue('A3', 'Período:');
        $sheetResumo->setCellValue('B3', date('m/Y', strtotime('-1 month')));
        $sheetResumo->setCellValue('A4', 'Data de Geração:');
        $sheetResumo->setCellValue('B4', date('d/m/Y H:i:s'));
        
        // Dados
        $sheetResumo->setCellValue('A6', 'Métrica');
        $sheetResumo->setCellValue('B6', 'Valor');
        
        $linha = 7;
        $metricas = [
            'Valor Total Vencido' => $dadosRep['vencidos'] ?? 0,
            'Até 30 dias' => $dadosRep['dias_30'] ?? 0,
            '31 a 60 dias' => $dadosRep['dias_60'] ?? 0,
            'Mais de 60 dias' => $dadosRep['mais_60_dias'] ?? 0,
            'A Vencer' => $dadosRep['a_vencer'] ?? 0,
            'Próximos 30 dias' => $dadosRep['prox_30_dias'] ?? 0,
            'Valor Inadimplência Total' => $dadosRep['valor_inadimplencia'] ?? 0,
            'Percentual Geral' => $dadosRep['percentual_geral'] ?? 0,
            'Percentual 30 dias' => $dadosRep['percentual_30'] ?? 0,
            'Percentual 60 dias' => $dadosRep['percentual_60'] ?? 0,
            'Percentual Mais 60 dias' => $dadosRep['percentual_mais_60'] ?? 0,
            'Prazo Médio da Carteira (dias)' => $dadosRep['prazo_medio'] ?? 0,
            'Total Clientes com débitos' => $dadosRep['total_clientes_vencidos'] ?? 0,
            'Total Títulos Vencidos' => $dadosRep['total_titulos_vencidos'] ?? 0
        ];
        
        foreach ($metricas as $nome => $valor) {
            $sheetResumo->setCellValue('A' . $linha, $nome);
            $sheetResumo->setCellValue('B' . $linha, $valor);
            $linha++;
        }
        
        // Formatação
        $sheetResumo->getStyle('A1:B1')->getFont()->setBold(true);
        $sheetResumo->getStyle('A6:B6')->getFont()->setBold(true);
        $sheetResumo->getStyle('B7:B' . ($linha-1))->getNumberFormat()->setFormatCode('#,##0.00');
        
        foreach (range('A', 'B') as $col) {
            $sheetResumo->getColumnDimension($col)->setAutoSize(true);
        }
        
        // ============================================================
        // ABA 2: CLIENTES DETALHADOS (COM PRAZO_MEDIO_CLIENTE)
        // ============================================================
        $sheetClientes = $spreadsheet->createSheet();
        $sheetClientes->setTitle('Clientes Detalhados');
        
        // Cabeçalhos
        $headers = [
            'Nome Representante',
            'Nome Fantasia',
            'Documento',
            'Vencimento',
            'Valor Total',
            'Valor Saldo',
            'Data Emissão',
            'Dias em Atraso',
            'Dias do Último Evento',
            'Usuário do Evento',
            'Prazo Médio do Cliente'
        ];
        
        $sheetClientes->fromArray($headers, NULL, 'A1');
        
        // Estilo do cabeçalho
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 
                'startColor' => ['rgb' => '38574b']
            ]
        ];
        $sheetClientes->getStyle('A1:K1')->applyFromArray($headerStyle);
        
        // Dados
        $row = 2;
        foreach ($dadosClientes as $cliente) {
            $sheetClientes->setCellValue('A' . $row, $cliente['nome_representante'] ?? '');
            $sheetClientes->setCellValue('B' . $row, $cliente['Nome Fantasia'] ?? '');
            $sheetClientes->setCellValue('C' . $row, $cliente['documento'] ?? '');
            $sheetClientes->setCellValue('D' . $row, $cliente['Vencimento'] ?? '');
            $sheetClientes->setCellValue('E' . $row, (float)($cliente['Valor Total'] ?? 0));
            $sheetClientes->setCellValue('F' . $row, (float)($cliente['Valor Saldo'] ?? 0));
            $sheetClientes->setCellValue('G' . $row, $cliente['Data Emissão'] ?? '');
            $sheetClientes->setCellValue('H' . $row, (int)($cliente['Dias em Atraso'] ?? 0));
            $sheetClientes->setCellValue('I' . $row, (int)($cliente['Dias do Último Evento'] ?? 0));
            $sheetClientes->setCellValue('J' . $row, $cliente['Usuário'] ?? '');
            
            // PRAZO MÉDIO DO CLIENTE
            $prazoMedioCliente = 0;
            if (isset($cliente['Prazo medio cliente'])) {
                $prazoMedioCliente = (float) $cliente['Prazo medio cliente'];
            } else {
                foreach ($cliente as $key => $value) {
                    $keyLower = strtolower($key);
                    if (strpos($keyLower, 'prazo') !== false && strpos($keyLower, 'medio') !== false) {
                        $prazoMedioCliente = (float) $value;
                        break;
                    }
                }
            }
            
            $sheetClientes->setCellValue('K' . $row, round($prazoMedioCliente, 1));
            $row++;
        }
        
        $lastRow = $row - 1;
        
        if ($lastRow > 1) {
            // Formatação Moeda (E, F)
            $sheetClientes->getStyle('E2:F' . $lastRow)->getNumberFormat()->setFormatCode('"R$" #,##0.00');
            
            // Formatação Prazo Médio (K) - 1 casa decimal
            $sheetClientes->getStyle('K2:K' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.0');
            
            // Destaque Vermelho para atraso > 60
            $warningStyle = ['font' => ['color' => ['rgb' => 'FF0000'], 'bold' => true]];
            for ($i = 2; $i <= $lastRow; $i++) {
                $diasAtraso = $sheetClientes->getCell('H' . $i)->getValue();
                if ($diasAtraso > 60) {
                    $sheetClientes->getStyle('H' . $i)->applyFromArray($warningStyle);
                }
            }
        }
        
        // Auto dimensionar colunas
        foreach (range('A', 'K') as $col) {
            $sheetClientes->getColumnDimension($col)->setAutoSize(true);
        }
        
        $sheetClientes->freezePane('A2');
        $sheetClientes->setAutoFilter('A1:K1');
        
        // ============================================================
        // SALVAR ARQUIVO
        // ============================================================
        $filename = "relatorio_representante_{$idRep}_" . date('Y-m-d') . ".xlsx";
        $filepath = $tempDir . "/{$filename}";
        
        // VERIFICA SE O ARQUIVO JÁ EXISTE E REMOVE
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        
        // SALVA O ARQUIVO
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // VERIFICA SE O ARQUIVO FOI CRIADO
        if (!file_exists($filepath)) {
            throw new Exception("Falha ao salvar o arquivo: {$filepath}");
        }
        
        // LOG DE SUCESSO
        file_put_contents(
            $logDir . '/excel_debug.log',
            date('Y-m-d H:i:s') . " - ✅ Excel gerado: {$filename} (" . filesize($filepath) . " bytes)\n",
            FILE_APPEND
        );
        
        return $filepath;
        
    } catch (Exception $e) {
        // LOG DO ERRO
        file_put_contents(
            __DIR__ . '/erros_log/excel_errors.log',
            date('Y-m-d H:i:s') . " - ERRO ao gerar Excel para ID $idRep: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        throw new Exception("Erro ao gerar Excel do representante: " . $e->getMessage());
    }
}
    public static function construirCorpoEmail($pedidos, $repre) {
    
    // Verificação básica dos parâmetros
    if (empty($pedidos) || !is_array($pedidos)) {
        return '<div style="font-family: Arial, sans-serif; color: red;">Nenhum pedido encontrado para gerar relatório.</div>';
    }

    $corpo = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style type="text/css">
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
            .pedido-header { margin-top: 25px; color: #444; }
            .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .table th { background-color: #f2f2f2; text-align: left; padding: 10px; border: 1px solid #ddd; }
            .table td { padding: 8px; border: 1px solid #ddd; }
            .text-right { text-align: right; }
            .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; }
            .destaque-negativo { color: #d9534f; }
            .destaque-positivo { color: #5cb85c; }
        </style>
    </head>
    <body>
    <div class="container">';

    $corpo .= "<h1 class='header'>Relatório de Alterações de Pedidos</h1>";
    $corpo .= "<h2>Representante: " . htmlspecialchars($repre) . "</h2>";

    $pedidoAtual = null;
    $totalAlteracoes = 0;
    $clientesAfetados = [];

    foreach ($pedidos as $pedido) {
        // Verificação de estrutura do pedido
        if (!isset($pedido['idpedido']) || !isset($pedido['qt_anterior']) || !isset($pedido['qt_nova'])) {
            continue;
        }

        if ($pedidoAtual !== $pedido['idpedido']) {
            if ($pedidoAtual !== null) {
                $corpo .= '</table>'; // Fechar a tabela anterior
            }
            $pedidoAtual = $pedido['idpedido'];
            
            $dataPedido = !empty($pedido['datapedido']) ? date('d/m/Y', strtotime($pedido['datapedido'])) : 'Data não informada';
            $nomePedidoMercos = !empty($pedido['numeroPedidoMercos']) ? htmlspecialchars($pedido['numeroPedidoMercos']) : 'N/A';

            $corpo .= '<div class="pedido-header">';
            $corpo .= '<h3>Pedido ID: ' . htmlspecialchars($pedidoAtual) . '</h3>';
            $corpo .= '<p><strong>Pedido MERCOS:</strong> ' . $nomePedidoMercos . '</p>';
            $corpo .= '<p><strong>Data do Pedido:</strong> ' . $dataPedido . '</p>';
            $corpo .= '</div>';
            
            $corpo .= '<table class="table">';
            $corpo .= '<tr>
                <th>Cliente</th>
                <th>Produto</th>
                <th class="text-right">Qt. Anterior</th>
                <th class="text-right">Qt. Nova</th>
                <th>Diferença</th>
                <th>Motivo</th>
            </tr>';
        }

        $diferenca = intval($pedido['qt_nova']) - intval($pedido['qt_anterior']);
        $classeDiferenca = ($diferenca < 0) ? 'destaque-negativo' : 'destaque-positivo';
        
        if (!in_array($pedido['cliente'], $clientesAfetados)) {
            $clientesAfetados[] = $pedido['cliente'];
        }
        $totalAlteracoes++;

        $corpo .= "<tr>
            <td>" . htmlspecialchars($pedido['cliente']) . "</td>
            <td>" . htmlspecialchars($pedido['produto']) . "</td>
            <td class='text-right'>" . intval($pedido['qt_anterior']) . "</td>
            <td class='text-right'>" . intval($pedido['qt_nova']) . "</td>
            <td class='text-right {$classeDiferenca}'>" . ($diferenca > 0 ? '+' : '') . $diferenca . "</td>
            <td>" . htmlspecialchars($pedido['motivo']) . "</td>
        </tr>";
    }

    $corpo .= '</table>'; // Fechar a última tabela
    
    // Resumo estatístico
    $corpo .= '<div class="footer">';
    $corpo .= '<h3>Resumo das Alterações</h3>';
    $corpo .= '<p><strong>Total de pedidos com alterações:</strong> ' . count(array_unique(array_column($pedidos, 'idpedido'))) . '</p>';
    $corpo .= '<p><strong>Total de itens alterados:</strong> ' . $totalAlteracoes . '</p>';
    $corpo .= '<p><strong>Clientes afetados:</strong> ' . count($clientesAfetados) . '</p>';
    $corpo .= '<p>Atenciosamente,<br><strong>Equipe Nutricional</strong></p>';
    $corpo .= '</div>';
    
    $corpo .= '</div></body></html>';
    
    return $corpo;
    }

/**
 * Constrói o e-mail consolidado para a gerência
 */
public static function construirEmailConsolidado($resumoConsolidado, $enviados, $falhas, $logErros) {
    
    // Calcula totais com segurança
    $totalVencido = 0;
    $totalClientes = 0;
    $totalTitulos = 0;
    $somaPrazo = 0;
    $qtd = count($resumoConsolidado);
    
    foreach ($resumoConsolidado as $item) {
        $totalVencido += isset($item['vencidos']) ? (float)$item['vencidos'] : 0;
        $totalClientes += isset($item['clientes']) ? (int)$item['clientes'] : 0;
        $totalTitulos += isset($item['titulos']) ? (int)$item['titulos'] : 0;
        $somaPrazo += isset($item['prazo_medio']) ? (float)$item['prazo_medio'] : 0;
    }
    
    $mediaPrazo = $qtd > 0 ? $somaPrazo / $qtd : 0;
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; padding: 20px; background: #f0f2f5; }
            .container { max-width: 1000px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 30px; box-shadow: 0 8px 40px rgba(0,0,0,0.10); }
            .header { background: linear-gradient(135deg, #0d47a1, #1565c0); color: white; padding: 25px 30px; border-radius: 10px; margin-bottom: 25px; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { margin: 5px 0 0 0; opacity: 0.85; }
            .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0; }
            .stat-card { background: #f8f9fa; padding: 18px; border-radius: 10px; text-align: center; border: 1px solid #e8ecf0; }
            .stat-card .number { font-size: 28px; font-weight: 700; color: #0d47a1; }
            .stat-card .label { font-size: 12px; color: #6b7a8a; text-transform: uppercase; margin-top: 4px; }
            .stat-card .sub { font-size: 11px; color: #95a5a6; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
            table th { background: #0d47a1; color: white; padding: 10px 12px; text-align: left; }
            table td { padding: 8px 12px; border-bottom: 1px solid #e8ecf0; }
            table tr:hover { background: #f8f9fa; }
            .badge-success { background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .badge-danger { background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .badge-warning { background: #fff3cd; color: #856404; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .valor { text-align: right; font-family: monospace; }
            .footer { margin-top: 25px; padding-top: 20px; border-top: 1px solid #e8ecf0; text-align: center; color: #6b7a8a; font-size: 12px; }
            .erros { background: #f8d7da; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545; }
            .erros h4 { color: #721c24; margin: 0 0 8px 0; }
            .erros ul { margin: 0; padding-left: 20px; color: #721c24; }
            @media (max-width: 600px) {
                .stats { grid-template-columns: repeat(2, 1fr); }
                .container { padding: 15px; }
            }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <h1>📊 Relatório Consolidado - Representantes</h1>
            <p>Período: ' . date('m/Y', strtotime('-1 month')) . ' | Gerado em: ' . date('d/m/Y H:i') . '</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number">' . $qtd . '</div>
                <div class="label">Total Representantes</div>
                <div class="sub">com inadimplência</div>
            </div>
            <div class="stat-card">
                <div class="number">' . $enviados . '</div>
                <div class="label">✅ Enviados</div>
                <div class="sub">e-mails</div>
            </div>
            <div class="stat-card">
                <div class="number">' . $falhas . '</div>
                <div class="label">❌ Falhas</div>
                <div class="sub">envios</div>
            </div>
            <div class="stat-card">
                <div class="number">R$ ' . number_format($totalVencido, 2, ',', '.') . '</div>
                <div class="label">💰 Total Vencido</div>
                <div class="sub">' . $totalClientes . ' clientes | ' . $totalTitulos . ' títulos</div>
            </div>
        </div>
        
        <h3 style="color: #1a237e; margin: 20px 0 10px 0;">📋 Detalhamento dos Envios</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Representante</th>
                <th>E-mail</th>
                <th class="valor">Total Vencido</th>
                <th class="valor">% Inadimplência</th>
                <th class="valor">Clientes</th>
                <th class="valor">Prazo Médio</th>
            </tr>';
    
    foreach ($resumoConsolidado as $item) {
        $percentual = isset($item['percentual']) ? number_format($item['percentual'], 2, ',', '.') : '0,00';
        $badgeClass = isset($item['percentual']) && $item['percentual'] > 10 ? 'badge-danger' : (isset($item['percentual']) && $item['percentual'] > 5 ? 'badge-warning' : 'badge-success');
        $html .= '
            <tr>
                <td>' . (isset($item['id']) ? $item['id'] : '') . '</td>
                <td>' . htmlspecialchars(isset($item['nome']) ? $item['nome'] : '') . '</td>
                <td>' . htmlspecialchars(isset($item['email']) ? $item['email'] : '') . '</td>
                <td class="valor">R$ ' . number_format(isset($item['vencidos']) ? $item['vencidos'] : 0, 2, ',', '.') . '</td>
                <td class="valor"><span class="' . $badgeClass . '">' . $percentual . '%</span></td>
                <td class="valor">' . (isset($item['clientes']) ? $item['clientes'] : 0) . '</td>
                <td class="valor">' . number_format(isset($item['prazo_medio']) ? $item['prazo_medio'] : 0, 1, ',', '.') . '</td>
            </tr>';
    }
    
    $html .= '
            <tr style="font-weight: bold; background: #f8f9fa; border-top: 2px solid #0d47a1;">
                <td colspan="2">TOTAL GERAL</td>
                <td>' . $qtd . ' representantes</td>
                <td class="valor">R$ ' . number_format($totalVencido, 2, ',', '.') . '</td>
                <td colspan="3" class="valor">Média Prazo: ' . number_format($mediaPrazo, 1, ',', '.') . ' dias</td>
            </tr>
        </table>';
    
    if (!empty($logErros)) {
        $html .= '
        <div class="erros">
            <h4>⚠️ Erros Ocorridos</h4>
            <ul>';
        foreach ($logErros as $erro) {
            $html .= '<li>' . htmlspecialchars($erro) . '</li>';
        }
        $html .= '
            </ul>
        </div>';
    }
    
    $html .= '
        <div class="footer">
            <p>
                <strong>Nutricional Distribuidora</strong> • Sistema de Gestão Financeira<br>
                Este é um relatório automático consolidado. Favor não responder.
            </p>
        </div>
    </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Constrói o e-mail de pedidos aguardando aprovação
 * Agrupa por cliente e por pedido
 */
public static function construirEmailPedidosAguardando($pedidos, $nomeRep) {
    
    // Agrupa por cliente e pedido
    $clientes = [];
    foreach ($pedidos as $pedido) {
        $idCliente = $pedido['idcliente'] ?? 0;
        $idPedido = $pedido['idpedido'];
        
        if (!isset($clientes[$idCliente])) {
            $clientes[$idCliente] = [
                'cliente' => $pedido['cliente'] ?? 'Cliente não informado',
                'endereco' => $pedido['enderecoCliente'] ?? 'Endereço não informado',
                'fone' => $pedido['fone'] ?? 'Não informado',
                'email' => $pedido['email_cliente'] ?? 'Não informado',
                'pedidos' => []
            ];
        }
        
        if (!isset($clientes[$idCliente]['pedidos'][$idPedido])) {
            $clientes[$idCliente]['pedidos'][$idPedido] = [
                'data' => $pedido['data'],
                'condicaopagto' => $pedido['condicaopagto'] ?? 'N/A',
                'metodopagto' => $pedido['metodopagto'] ?? 'N/A',
                'itens' => []
            ];
        }
        
        $clientes[$idCliente]['pedidos'][$idPedido]['itens'][] = $pedido;
    }
    
    $totalClientes = count($clientes);
    $totalPedidos = count(array_unique(array_column($pedidos, 'idpedido')));
    $totalItens = count($pedidos);
    $totalGeral = array_sum(array_column($pedidos, 'valortotalcomdesconto'));
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pedidos Aguardando Aprovação</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
                background: #f0f2f5; 
                padding: 20px;
                color: #333;
                line-height: 1.6;
            }
            .container { 
                max-width: 900px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 16px; 
                box-shadow: 0 8px 40px rgba(0,0,0,0.10);
                overflow: hidden;
            }
            .header { 
                background: linear-gradient(135deg, #e65100, #f57c00); 
                color: white; 
                padding: 25px 30px;
            }
            .header h1 { font-size: 24px; margin: 0; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            
            .stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                padding: 25px 30px;
                background: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
            }
            .stat-card {
                text-align: center;
            }
            .stat-card .number {
                font-size: 28px;
                font-weight: 700;
                color: #e65100;
            }
            .stat-card .label {
                font-size: 12px;
                color: #6b7a8a;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .content { padding: 0 30px 30px 30px; }
            
            .pedido-box {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
                border-left: 4px solid #e65100;
            }
            .pedido-box h3 {
                color: #1a237e;
                margin: 0 0 10px 0;
            }
            .pedido-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 5px 20px;
                font-size: 14px;
                margin-bottom: 15px;
                padding: 10px;
                background: white;
                border-radius: 8px;
            }
            .pedido-info .label { color: #6b7a8a; font-weight: 600; }
            .pedido-info .value { color: #333; }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
                background: white;
                border-radius: 8px;
                overflow: hidden;
            }
            table th {
                background: #1a237e;
                color: white;
                padding: 10px 12px;
                text-align: left;
            }
            table td {
                padding: 8px 12px;
                border-bottom: 1px solid #e9ecef;
            }
            table tr:last-child td { border-bottom: none; }
            .text-right { text-align: right; }
            .font-mono { font-family: monospace; }
            
            .total-pedido {
                text-align: right;
                font-weight: bold;
                font-size: 15px;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 2px solid #e9ecef;
            }
            
            .total-cliente {
                text-align: right;
                font-weight: bold;
                font-size: 16px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 2px solid #e65100;
                color: #e65100;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 15px 30px;
                text-align: center;
                border-top: 1px solid #e9ecef;
                color: #6b7a8a;
                font-size: 12px;
            }
            
            .badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
            }
            .badge-info { background: #d1ecf1; color: #0c5460; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-secondary { background: #e9ecef; color: #6c757d; }
            
            @media (max-width: 768px) {
                .stats { grid-template-columns: repeat(2, 1fr); }
                .pedido-info { grid-template-columns: 1fr; }
                .content { padding: 0 15px 15px 15px; }
                .header { padding: 15px; }
            }
            @media (max-width: 450px) {
                table { font-size: 11px; }
                table th, table td { padding: 5px 8px; }
                .stats { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📋 Pedidos Aguardando Aprovação</h1>
                <p><strong>Representante:</strong> ' . htmlspecialchars($nomeRep) . '</p>
                <p>📅 ' . date('d/m/Y H:i') . '</p>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="number">' . $totalClientes . '</div>
                    <div class="label">Total Clientes</div>
                </div>
                <div class="stat-card">
                    <div class="number">' . $totalPedidos . '</div>
                    <div class="label">Total Pedidos</div>
                </div>
                <div class="stat-card">
                    <div class="number">' . $totalItens . '</div>
                    <div class="label">Total Itens</div>
                </div>
                <div class="stat-card">
                    <div class="number">R$ ' . number_format($totalGeral, 2, ',', '.') . '</div>
                    <div class="label">Valor Total</div>
                </div>
            </div>
            
            <div class="content">';
    
    foreach ($clientes as $idCliente => $cliente) {
        $html .= '
                <div class="pedido-box">
                    <h3>👤 ' . htmlspecialchars($cliente['cliente']) . '</h3>
                    <div class="pedido-info">
                        <div><span class="label">📍 Endereço:</span> <span class="value">' . htmlspecialchars($cliente['endereco']) . '</span></div>
                        <div><span class="label">📞 Telefone:</span> <span class="value">' . htmlspecialchars($cliente['fone']) . '</span></div>
                        <div><span class="label">📧 E-mail:</span> <span class="value">' . htmlspecialchars($cliente['email']) . '</span></div>
                    </div>';
        
        $totalCliente = 0;
        foreach ($cliente['pedidos'] as $idPedido => $pedido) {
            $totalPedido = array_sum(array_column($pedido['itens'], 'valortotalcomdesconto'));
            $totalCliente += $totalPedido;
            
            $html .= '
                    <h4 style="margin: 15px 0 5px 0; color: #e65100;">📦 Pedido #' . $idPedido . '</h4>
                    <div style="font-size: 13px; margin-bottom: 10px;">
                        <span class="badge badge-info">' . date('d/m/Y', strtotime($pedido['data'])) . '</span>
                        <span class="badge badge-warning">' . htmlspecialchars($pedido['condicaopagto']) . '</span>
                        <span class="badge badge-secondary">' . htmlspecialchars($pedido['metodopagto']) . '</span>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="text-right">Qt</th>
                                <th class="text-right">Valor Unit</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($pedido['itens'] as $item) {
                $html .= '
                            <tr>
                                <td>' . htmlspecialchars($item['produto'] ?? 'N/A') . '</td>
                                <td class="text-right">' . number_format($item['qt'] ?? 0, 2, ',', '.') . '</td>
                                <td class="text-right font-mono">R$ ' . number_format($item['valorunitarioimpressao'] ?? 0, 2, ',', '.') . '</td>
                                <td class="text-right font-mono">R$ ' . number_format($item['valortotalcomdesconto'] ?? 0, 2, ',', '.') . '</td>
                            </tr>';
            }
            
            $html .= '
                        </tbody>
                    </table>
                    
                    <div class="total-pedido">
                        TOTAL DO PEDIDO: R$ ' . number_format($totalPedido, 2, ',', '.') . '
                    </div>';
        }
        
        $html .= '
                    <div class="total-cliente">
                        TOTAL CLIENTE: R$ ' . number_format($totalCliente, 2, ',', '.') . '
                    </div>
                </div>';
    }
    
    $html .= '
                <div style="background: #1a237e; color: white; padding: 15px; border-radius: 8px; text-align: center; font-size: 18px; margin: 20px 0;">
                    <strong>💰 TOTAL GERAL: R$ ' . number_format($totalGeral, 2, ',', '.') . '</strong>
                </div>
            </div>
            
            <div class="footer">
                <p>
                    <strong>Nutricional Distribuidora</strong> • Sistema de Gestão de Pedidos<br>
                    📞 Em caso de dúvidas, entre em contato com o departamento comercial<br>
                    ⏱️ Este é um relatório automático diário. Favor não responder.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
/**
 * Constrói o e-mail consolidado de pedidos aguardando aprovação
 */
public static function construirConsolidadoPedidos($resumoConsolidado, $enviados, $falhas, $logErros) {
    
    $totalPedidos = 0;
    $totalItens = 0;
    $totalValor = 0;
    
    foreach ($resumoConsolidado as $item) {
        $totalPedidos += isset($item['total_pedidos']) ? (int)$item['total_pedidos'] : 0;
        $totalItens += isset($item['total_itens']) ? (int)$item['total_itens'] : 0;
        $totalValor += isset($item['valor_total']) ? (float)$item['valor_total'] : 0;
    }
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .container { max-width: 900px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1a237e, #0d47a1); color: white; padding: 20px; border-radius: 8px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #e9ecef; }
            .stat-card .number { font-size: 24px; font-weight: 700; color: #1a237e; }
            .stat-card .label { font-size: 12px; color: #6b7a8a; text-transform: uppercase; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
            table th { background: #1a237e; color: white; padding: 10px; text-align: left; }
            table td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
            table tr:hover { background: #f5f5f5; }
            .text-right { text-align: right; }
            .badge-success { background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .badge-danger { background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; text-align: center; color: #6b7a8a; font-size: 12px; }
            .erros { background: #f8d7da; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545; }
            .erros ul { margin: 0; padding-left: 20px; color: #721c24; }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <h1>📊 Relatório Consolidado - Pedidos Aguardando Aprovação</h1>
            <p>' . date('d/m/Y H:i') . '</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number">' . count($resumoConsolidado) . '</div>
                <div class="label">Representantes</div>
            </div>
            <div class="stat-card">
                <div class="number">' . $enviados . '</div>
                <div class="label">✅ Enviados</div>
            </div>
            <div class="stat-card">
                <div class="number">' . $falhas . '</div>
                <div class="label">❌ Falhas</div>
            </div>
            <div class="stat-card">
                <div class="number">R$ ' . number_format($totalValor, 2, ',', '.') . '</div>
                <div class="label">💰 Total Pedidos</div>
            </div>
        </div>
        
        <h3>📋 Detalhamento</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Representante</th>
                <th>Pedidos</th>
                <th>Itens</th>
                <th class="text-right">Valor Total</th>
            </tr>';
    
    foreach ($resumoConsolidado as $item) {
        $html .= '
            <tr>
                <td>' . (isset($item['id']) ? $item['id'] : '') . '</td>
                <td>' . htmlspecialchars(isset($item['nome']) ? $item['nome'] : '') . '</td>
                <td>' . (isset($item['total_pedidos']) ? $item['total_pedidos'] : 0) . '</td>
                <td>' . (isset($item['total_itens']) ? $item['total_itens'] : 0) . '</td>
                <td class="text-right">R$ ' . number_format(isset($item['valor_total']) ? $item['valor_total'] : 0, 2, ',', '.') . '</td>
            </tr>';
    }
    
    $html .= '
            <tr style="font-weight: bold; background: #f8f9fa; border-top: 2px solid #1a237e;">
                <td colspan="2">TOTAL GERAL</td>
                <td>' . $totalPedidos . '</td>
                <td>' . $totalItens . '</td>
                <td class="text-right">R$ ' . number_format($totalValor, 2, ',', '.') . '</td>
            </tr>
        </table>';
    
    if (!empty($logErros)) {
        $html .= '
        <div class="erros">
            <h4 style="color: #721c24; margin: 0 0 10px 0;">⚠️ Erros Ocorridos</h4>
            <ul>';
        foreach ($logErros as $erro) {
            $html .= '<li>' . htmlspecialchars($erro) . '</li>';
        }
        $html .= '
            </ul>
        </div>';
    }
    
    $html .= '
        <div class="footer">
            <p>Este é um relatório consolidado automático. Favor não responder.</p>
        </div>
    </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Constrói o e-mail de aviso quando não há pedidos aguardando aprovação
 * 
 * @param array $emailsDestino Lista de e-mails para enviar o aviso
 * @return string HTML do e-mail
 */
public static function construirAvisoSemPedidos($emailsDestino) {
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Aviso - Pedidos Aguardando Aprovação</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
                background: #f0f2f5; 
                padding: 20px;
                color: #333;
                line-height: 1.6;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 16px; 
                box-shadow: 0 8px 40px rgba(0,0,0,0.10);
                overflow: hidden;
            }
            .header { 
                background: linear-gradient(135deg, #1a237e, #0d47a1); 
                color: white; 
                padding: 25px 30px;
                text-align: center;
            }
            .header h1 { font-size: 24px; margin: 0; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            
            .content { padding: 30px; text-align: center; }
            
            .icone-aviso {
                font-size: 64px;
                margin-bottom: 20px;
            }
            
            .mensagem {
                font-size: 18px;
                color: #1a237e;
                font-weight: 600;
                margin-bottom: 10px;
            }
            
            .detalhe {
                color: #6b7a8a;
                font-size: 14px;
                margin-bottom: 20px;
            }
            
            .info-box {
                background: #e8f5e9;
                border-left: 4px solid #2e7d32;
                padding: 15px 20px;
                border-radius: 8px;
                text-align: left;
                margin: 15px 0;
            }
            .info-box strong {
                color: #1a237e;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 15px 25px;
                text-align: center;
                border-top: 1px solid #e9ecef;
                color: #6b7a8a;
                font-size: 12px;
            }
            
            .btn {
                display: inline-block;
                padding: 10px 25px;
                background: #1a237e;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 15px;
            }
            .btn:hover {
                background: #0d47a1;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📋 Aviso - Pedidos Aguardando Aprovação</h1>
                <p>' . date('d/m/Y H:i') . '</p>
            </div>
            
            <div class="content">
                <div class="icone-aviso">✅</div>
                <div class="mensagem">Nenhum pedido aguardando aprovação</div>
                <div class="detalhe">Não há pedidos pendentes de aprovação neste momento.</div>
                
                <div class="info-box">
                    <strong>📊 Resumo:</strong><br>
                    • Total de representantes com pedidos: <strong>0</strong><br>
                    • Total de pedidos pendentes: <strong>0</strong><br>
                    • Status: <strong style="color: #2e7d32;">Tudo regular</strong>
                </div>
                
                <p style="color: #6b7a8a; font-size: 14px; margin-top: 10px;">
                    Este é um relatório automático diário. Favor não responder.
                </p>
            </div>
            
            <div class="footer">
                <p>
                    <strong>Nutricional Distribuidora</strong> • Sistema de Gestão de Pedidos<br>
                    📞 Em caso de dúvidas, entre em contato com o departamento comercial
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
/**
 * Constrói o e-mail de pedidos aguardando aprovação - VERSÃO RESUMIDA
 * Apenas o resumo por cliente, sem itens detalhados
 * 
 * @param array $pedidos Lista de pedidos com dados básicos
 * @param string $nomeRep Nome do representante
 * @return string HTML do e-mail
 */
public static function construirEmailPedidosAguardandoResumido($pedidos, $nomeRep) {
    
    // Agrupa por cliente
    $clientes = [];
    $totalPedidos = 0;
    $totalQuantidade = 0;
    $totalGeral = 0;
    
    foreach ($pedidos as $pedido) {
        $idCliente = $pedido['idcliente'] ?? 0;
        $clienteNome = $pedido['cliente'] ?? 'Cliente não informado';
        $qtd = (float)($pedido['total_quantidade'] ?? 0);
        
        if (!isset($clientes[$idCliente])) {
            $clientes[$idCliente] = [
                'cliente' => $clienteNome,
                'pedidos' => [],
                'total_cliente' => 0,
                'quantidade_cliente' => 0
            ];
        }
        
        // Verifica se o pedido já está na lista para não duplicar
        $idPedido = $pedido['idpedido'];
        if (!in_array($idPedido, $clientes[$idCliente]['pedidos'])) {
            $clientes[$idCliente]['pedidos'][] = $idPedido;
            $clientes[$idCliente]['total_cliente'] += (float)($pedido['valortotalitens'] ?? 0);
            $clientes[$idCliente]['quantidade_cliente'] += $qtd;
            $totalPedidos++;
            $totalGeral += (float)($pedido['valortotalitens'] ?? 0);
            $totalQuantidade += $qtd;
        }
    }
    
    $totalClientes = count($clientes);
    
    // Ordena clientes por total (maior primeiro)
    usort($clientes, function($a, $b) {
        return $b['total_cliente'] <=> $a['total_cliente'];
    });
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pedidos Aguardando Aprovação</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
                background: #f0f2f5; 
                padding: 20px;
                color: #333;
                line-height: 1.6;
            }
            .container { 
                max-width: 800px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 16px; 
                box-shadow: 0 8px 40px rgba(0,0,0,0.10);
                overflow: hidden;
            }
            .header { 
                background: linear-gradient(135deg, #e65100, #f57c00); 
                color: white; 
                padding: 25px 30px;
            }
            .header h1 { font-size: 24px; margin: 0; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            
            .stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                padding: 20px 25px;
                background: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
            }
            .stat-card {
                text-align: center;
            }
            .stat-card .number {
                font-size: 24px;
                font-weight: 700;
                color: #e65100;
            }
            .stat-card .label {
                font-size: 11px;
                color: #6b7a8a;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .content { padding: 0 25px 25px 25px; }
            
            .section-title {
                font-size: 16px;
                font-weight: 600;
                color: #1a237e;
                margin: 20px 0 12px 0;
                padding-bottom: 8px;
                border-bottom: 2px solid #e8ecf0;
            }
            
            .cliente-card {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 12px 18px;
                margin: 8px 0;
                border-left: 4px solid #e65100;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: background 0.2s ease;
            }
            .cliente-card:hover {
                background: #e8ecf0;
            }
            .cliente-card .cliente-nome {
                font-weight: 600;
                color: #1a237e;
                font-size: 14px;
            }
            .cliente-card .cliente-info {
                font-size: 12px;
                color: #6b7a8a;
            }
            .cliente-card .cliente-total {
                font-weight: 700;
                color: #e65100;
                font-size: 15px;
                white-space: nowrap;
            }
            .cliente-card .badge-pedidos {
                background: #1a237e;
                color: white;
                padding: 2px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                margin-right: 10px;
            }
            .cliente-card .badge-itens {
                background: #6c757d;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                margin-right: 10px;
            }
            
            .total-geral {
                background: #1a237e;
                color: white;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                font-size: 18px;
                margin-top: 20px;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 15px 25px;
                text-align: center;
                border-top: 1px solid #e9ecef;
                color: #6b7a8a;
                font-size: 12px;
            }
            
            @media (max-width: 600px) {
                .stats { grid-template-columns: repeat(2, 1fr); }
                .cliente-card { flex-wrap: wrap; }
                .cliente-card .cliente-total { margin-top: 5px; width: 100%; text-align: right; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📋 Pedidos Aguardando Aprovação</h1>
                <p><strong>Representante:</strong> ' . htmlspecialchars($nomeRep) . '</p>
                <p>📅 ' . date('d/m/Y H:i') . '</p>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="number">' . $totalClientes . '</div>
                    <div class="label">Total Clientes</div>
                </div>
                <div class="stat-card">
                    <div class="number">' . $totalPedidos . '</div>
                    <div class="label">Total Pedidos</div>
                </div>
                <div class="stat-card">
                    <div class="number">' . number_format($totalQuantidade, 0, ',', '.') . '</div>
                    <div class="label">Total Itens (unidades)</div>
                </div>
                <div class="stat-card">
                    <div class="number">R$ ' . number_format($totalGeral, 2, ',', '.') . '</div>
                    <div class="label">Valor Total</div>
                </div>
            </div>
            
            <div class="content">
                <div class="section-title">📋 LISTA DE CLIENTES</div>';
    
    foreach ($clientes as $cliente) {
        $qtdPedidos = count($cliente['pedidos']);
        $qtdItens = (int)$cliente['quantidade_cliente'];
        $html .= '
                <div class="cliente-card">
                    <div>
                        <span class="cliente-nome">👤 ' . htmlspecialchars($cliente['cliente']) . '</span>
                        <span class="badge-pedidos">' . $qtdPedidos . ' pedido' . ($qtdPedidos > 1 ? 's' : '') . '</span>
                        <span class="badge-itens">' . number_format($qtdItens, 0, ',', '.') . ' itens</span>
                    </div>
                    <div class="cliente-total">R$ ' . number_format($cliente['total_cliente'], 2, ',', '.') . '</div>
                </div>';
    }
    
    $html .= '
                <div class="total-geral">
                    💰 TOTAL GERAL: R$ ' . number_format($totalGeral, 2, ',', '.') . ' (' . number_format($totalQuantidade, 0, ',', '.') . ' itens)
                </div>
            </div>
            
            <div class="footer">
                <p>
                    <strong>Nutricional Distribuidora</strong> • Sistema de Gestão de Pedidos<br>
                    📞 Em caso de dúvidas, entre em contato com o departamento comercial<br>
                    ⏱️ Este é um relatório automático diário. Favor não responder.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Constrói o e-mail consolidado de pedidos aguardando aprovação - VERSÃO RESUMIDA
 * 
 * @param array $resumoConsolidado Lista com resumo de cada representante
 * @param int $enviados Quantos e-mails foram enviados
 * @param int $falhas Quantas falhas ocorreram
 * @param array $logErros Lista de erros
 * @return string HTML do e-mail
 */
public static function construirConsolidadoPedidosResumido($resumoConsolidado, $enviados, $falhas, $logErros) {
    
    $totalClientes = 0;
    $totalPedidos = 0;
    $totalQuantidade = 0;
    $totalValor = 0;
    
    foreach ($resumoConsolidado as $item) {
        $totalClientes += isset($item['total_clientes']) ? (int)$item['total_clientes'] : 0;
        $totalPedidos += isset($item['total_pedidos']) ? (int)$item['total_pedidos'] : 0;
        $totalQuantidade += isset($item['total_quantidade']) ? (float)$item['total_quantidade'] : 0;
        $totalValor += isset($item['valor_total']) ? (float)$item['valor_total'] : 0;
    }
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .container { max-width: 900px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1a237e, #0d47a1); color: white; padding: 20px; border-radius: 8px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #e9ecef; }
            .stat-card .number { font-size: 24px; font-weight: 700; color: #1a237e; }
            .stat-card .label { font-size: 12px; color: #6b7a8a; text-transform: uppercase; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
            table th { background: #1a237e; color: white; padding: 10px; text-align: left; }
            table td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
            table tr:hover { background: #f5f5f5; }
            .text-right { text-align: right; }
            .badge-success { background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .badge-danger { background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 12px; font-size: 11px; display: inline-block; }
            .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; text-align: center; color: #6b7a8a; font-size: 12px; }
            .erros { background: #f8d7da; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #dc3545; }
            .erros ul { margin: 0; padding-left: 20px; color: #721c24; }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <h1>📊 Relatório Consolidado - Pedidos Aguardando Aprovação</h1>
            <p>' . date('d/m/Y H:i') . '</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number">' . count($resumoConsolidado) . '</div>
                <div class="label">Representantes</div>
            </div>
            <div class="stat-card">
                <div class="number">' . $enviados . '</div>
                <div class="label">✅ Enviados</div>
            </div>
            <div class="stat-card">
                <div class="number">' . $falhas . '</div>
                <div class="label">❌ Falhas</div>
            </div>
            <div class="stat-card">
                <div class="number">R$ ' . number_format($totalValor, 2, ',', '.') . '</div>
                <div class="label">💰 Total Pedidos</div>
            </div>
        </div>
        
        <h3>📋 Detalhamento</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Representante</th>
                <th>Clientes</th>
                <th>Pedidos</th>
                <th>Itens</th>
                <th class="text-right">Valor Total</th>
            </tr>';
    
    foreach ($resumoConsolidado as $item) {
        $html .= '
            <tr>
                <td>' . (isset($item['id']) ? $item['id'] : '') . '</td>
                <td>' . htmlspecialchars(isset($item['nome']) ? $item['nome'] : '') . '</td>
                <td>' . (isset($item['total_clientes']) ? $item['total_clientes'] : 0) . '</td>
                <td>' . (isset($item['total_pedidos']) ? $item['total_pedidos'] : 0) . '</td>
                <td>' . (isset($item['total_quantidade']) ? number_format($item['total_quantidade'], 0, ',', '.') : 0) . '</td>
                <td class="text-right">R$ ' . number_format(isset($item['valor_total']) ? $item['valor_total'] : 0, 2, ',', '.') . '</td>
            </tr>';
    }
    
    $html .= '
            <tr style="font-weight: bold; background: #f8f9fa; border-top: 2px solid #1a237e;">
                <td colspan="2">TOTAL GERAL</td>
                <td>' . $totalClientes . '</td>
                <td>' . $totalPedidos . '</td>
                <td>' . number_format($totalQuantidade, 0, ',', '.') . '</td>
                <td class="text-right">R$ ' . number_format($totalValor, 2, ',', '.') . '</td>
            </tr>
        </table>';
    
    if (!empty($logErros)) {
        $html .= '
        <div class="erros">
            <h4 style="color: #721c24; margin: 0 0 10px 0;">⚠️ Erros Ocorridos</h4>
            <ul>';
        foreach ($logErros as $erro) {
            $html .= '<li>' . htmlspecialchars($erro) . '</li>';
        }
        $html .= '
            </ul>
        </div>';
    }
    
    $html .= '
        <div class="footer">
            <p>Este é um relatório consolidado automático. Favor não responder.</p>
        </div>
    </div>
    </body>
    </html>';
    
    return $html;
}

    public static function enviarEmail($mail, $para, $nome, $assunto, $corpo, $cc = [], $bcc = []) {

    try {
        // Validação básica dos parâmetros
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Endereço de e-mail inválido: $para");
        }

        $mail->clearAddresses();
        $mail->clearCCs();
        $mail->clearBCCs();
        $mail->clearReplyTos();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();

        // Configurações principais
        $mail->addAddress($para, $nome);
        $mail->Subject = $assunto;
        $mail->Body = $corpo;
        
        // Adiciona cópias (CC) se fornecidas
        foreach ($cc as $emailCc) {
            if (filter_var($emailCc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($emailCc);
            }
        }
        
        // Adiciona cópias ocultas (BCC) se fornecidas
        foreach ($bcc as $emailBcc) {
            if (filter_var($emailBcc, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($emailBcc);
            }
        }

        // Configuração adicional para melhor entrega
        $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
        $mail->addCustomHeader('X-Priority', '1'); // Prioridade alta
        $mail->addCustomHeader('Importance', 'High');
        
        // Tentativa de envio com timeout
        $mail->Timeout = 30;
        $resultado = $mail->send();
        
        if (!$resultado) {
            throw new Exception($mail->ErrorInfo);
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        // Log detalhado do erro
        $mensagemErro = sprintf(
            "[%s] Erro ao enviar e-mail para %s (%s): %s\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $para,
            $nome,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        
        error_log($mensagemErro);
        
        // Log em arquivo adicional se necessário
        file_put_contents(
            __DIR__ . '/email_errors.log',
            $mensagemErro . "\n\n",
            FILE_APPEND
        );
        
        return false;
    }
    }

    public static function removeAcentos($string, $slug = false) {
        
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y'
        ];

        $string = strtr($string, $acentos);
        $string = strtolower($string);

        if ($slug) {
            $string = preg_replace('/[^a-z0-9]/i', '-', $string);
            $string = preg_replace('/-+/', '-', $string); // Remove duplicatas
            return trim($string, '-');
        }
        
        return $string;
    }

    public static function geranomeimagem($nome, $altera = false) {

        $extensao = pathinfo($nome, PATHINFO_EXTENSION);
        $baseNome = pathinfo($nome, PATHINFO_FILENAME);

        if ($altera) {
            return date('YmdHis') . '.' . $extensao;
        }

        $slug = self::removeAcentos($baseNome . '-' . mt_rand(0, 9999999999), true);
        return substr($slug, 0, 90) . '.' . $extensao;
    }

    public static function informacoesData($strData) {
   
        $date = new DateTime($strData);
        return [
            'dia' => $date->format('d'),
            'mes' => $date->format('M'),
            'ano' => $date->format('Y'),
            'diaSemana' => $date->format('l'),
            'hora' => $date->format('H:i')
        ];
    }

    public static function formatarData($strData) {
       $date = DateTime::createFromFormat('Y-m-d', $strData);
        return $date ? $date->format('d/m/Y') : null;
    }

    public static function formatarHora($strData) {
     return date("H:i", strtotime($strData));
    }

    public static function getDiasUteis($dtInicio, $dtFim, $fer = []) {
     
        $tsInicio = strtotime($dtInicio);
        $tsFim = strtotime($dtFim);
        $quantidadeDias = 0;

        while ($tsInicio <= $tsFim) {
            if (date('N', $tsInicio) < 6 && !in_array(date('Y-m-d', $tsInicio), $fer)) {
                $quantidadeDias++;
            }
            $tsInicio += 86400; // Incrementa um dia
        }

        return $quantidadeDias;
    }

    public static function diasDatas($data_inicial, $data_final) {
        // Melhoria: usar DateTime para cálculo mais preciso
        $date1 = new DateTime($data_inicial);
        $date2 = new DateTime($data_final);
        return $date1->diff($date2)->days;
    }

    public static function dias_feriados($ano = null) {
        
        if (empty($ano)) {
            $ano = intval(date('Y'));
        }

        $pascoa = easter_date($ano);
        $dia_pascoa = date('j', $pascoa);
        $mes_pascoa = date('n', $pascoa);
        $ano_pascoa = date('Y', $pascoa);

        $feriadosNacionais = [
            mktime(0, 0, 0, 1, 1, $ano),    // Confraternização Universal
            mktime(0, 0, 0, 4, 21, $ano),   // Tiradentes
            mktime(0, 0, 0, 5, 1, $ano),    // Dia do Trabalhador
            mktime(0, 0, 0, 9, 7, $ano),    // Dia da Independência
            mktime(0, 0, 0, 10, 12, $ano),  // N. S. Aparecida
            mktime(0, 0, 0, 11, 2, $ano),   // Todos os Santos
            mktime(0, 0, 0, 11, 15, $ano),  // Proclamação da República
            mktime(0, 0, 0, 12, 25, $ano),  // Natal
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 47, $ano_pascoa), // 3º feira de Carnaval
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 2, $ano_pascoa), // 6ª feira Santa
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa, $ano_pascoa), // Páscoa
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa + 60, $ano_pascoa) // Corpus Christi
        ];

        sort($feriadosNacionais);
        return $feriadosNacionais;
    }

    public static function formatar_cpf_cnpj($value) {
           $cnpj_cpf = preg_replace("/\D/", '', $value);
        
        if (strlen($cnpj_cpf) === 11) {
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "$1.$2.$3-$4", $cnpj_cpf);
        }

        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $cnpj_cpf);
    }
}

// Função para obter dados do cliente
function obterDadosCliente($idcliforemp) {
    global $pdo;
    
    // Prevenção contra SQL Injection
    $idcliforemp = (int)$idcliforemp;
    
    $query = "SELECT DISTINCT 
                cliforemp.fantasia AS cliente,
                cliforemp.idcliforemp AS codigo_cliente,
                cliforemp.tipopessoa AS tipopessoa,
                CASE 
                    WHEN cliforemp.tipopessoa = 0 THEN cliforemp.cpf 
                    ELSE cliforemp.cnpj 
                END AS cpfcnpj,
                cliforemp.ie AS ie,
                cliforemp.endereco AS endereco,
                cliforemp.numero AS numero,
                cliforemp.bairro AS bairro,
                cliforemp.idcidade AS idcidade,
                cliforemp.uf AS uf,
                cliforemp.cep AS cep
              FROM cliforemp
              WHERE idcliforemp = ?";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$idcliforemp]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


?>