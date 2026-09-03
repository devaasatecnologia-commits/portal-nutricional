<?php
namespace Nutricional\Services;

use PDO;

class MarketingService
{
    private $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
   /**
 * Calcula o progresso de uma meta de forma consistente
 */
public function calcularProgressoMeta(int $idMeta, ?float $valorAtual = null): array
{
    try {
        // Buscar meta
        $stmt = $this->pdo->prepare("
            SELECT mi.*, tm.nome as tipo_nome
            FROM mkt_metas_instancias mi
            LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
            WHERE mi.id = :id
        ");
        $stmt->execute(['id' => $idMeta]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meta) {
            return ['success' => false, 'error' => 'Meta não encontrada'];
        }
        
        // Buscar registros de alimentação
        $stmtReg = $this->pdo->prepare("
            SELECT 
                SUM(COALESCE((valores->>'valor_alcancado')::numeric, 0)) as total_alcancado,
                COUNT(*) as total_registros,
                MAX(data_registro) as ultimo_registro
            FROM mkt_alimentacao_registros 
            WHERE id_meta_instancia = :id_meta
        ");
        $stmtReg->execute(['id_meta' => $idMeta]);
        $registros = $stmtReg->fetch(PDO::FETCH_ASSOC);
        
        // Valores da meta
        $valores = json_decode($meta['valores'] ?? '{}', true) ?: [];
        $taxaInicial = (float)($valores['taxa_inicial'] ?? $valores['meta_leads'] ?? $meta['meta_leads'] ?? 0);
        $metaFinal = (float)($valores['meta_final'] ?? $valores['meta_faturamento'] ?? $meta['meta_faturamento'] ?? 0);
        $valorAcumulado = (float)($registros['total_alcancado'] ?? $taxaInicial);
        
        // ⭐ CORREÇÃO: Calcular progresso corretamente
        $progresso = 0;
        
        // Se a meta final é maior que a taxa inicial (meta de crescimento)
        if ($metaFinal > $taxaInicial) {
            $necessario = $metaFinal - $taxaInicial;
            if ($necessario > 0) {
                $conquistado = $valorAcumulado - $taxaInicial;
                // ⭐ Garantir que o progresso nunca seja negativo
                $progresso = max(0, min(100, round(($conquistado / $necessario) * 100, 1)));
            }
        } 
        // Se a meta final é menor que a taxa inicial (meta de redução)
        else if ($metaFinal < $taxaInicial) {
            $necessario = $taxaInicial - $metaFinal;
            if ($necessario > 0) {
                $reduzido = $taxaInicial - $valorAcumulado;
                // ⭐ Progresso de redução: quanto mais próximo da meta final, maior o progresso
                $progresso = max(0, min(100, round(($reduzido / $necessario) * 100, 1)));
            }
        }
        // Se a meta final é igual à taxa inicial
        else {
            // Se já atingiu ou ultrapassou a meta
            $progresso = $valorAcumulado >= $metaFinal ? 100 : 0;
        }
        
        return [
            'success' => true,
            'id' => $idMeta,
            'titulo' => $meta['titulo'],
            'tipo_nome' => $meta['tipo_nome'] ?? 'Meta Padrão',
            'taxa_inicial' => $taxaInicial,
            'meta_final' => $metaFinal,
            'valor_atual' => $valorAcumulado,
            'progresso' => $progresso,
            'total_registros' => (int)($registros['total_registros'] ?? 0),
            'ultimo_registro' => $registros['ultimo_registro'] ?? null,
            'status' => $meta['status'],
            'data_inicio' => $meta['data_inicio'],
            'data_fim' => $meta['data_fim']
        ];
    } catch (\Exception $e) {
        error_log('Erro em calcularProgressoMeta: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
    
    /**
     * Obtém todos os campos editáveis de uma meta para alimentação
     */
    public function getCamposEditaveisMeta(int $idMeta): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mi.id_tipo_meta, tc.*
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_campos tc ON tc.id_tipo_meta = mi.id_tipo_meta
                WHERE mi.id = :id_meta AND (tc.editavel = true OR tc.editavel IS NULL)
                ORDER BY tc.ordem ASC
            ");
            $stmt->execute(['id_meta' => $idMeta]);
            $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Se não tem campos configurados, usar padrão
            if (empty($campos)) {
                $campos = [
                    [
                        'nome_campo' => 'valor_alcancado',
                        'rotulo' => 'Valor Alcançado',
                        'tipo_campo' => 'number',
                        'unidade' => 'R$',
                        'obrigatorio' => true,
                        'editavel' => true
                    ]
                ];
            }
            
            return $campos;
        } catch (\Exception $e) {
            error_log('Erro em getCamposEditaveisMeta: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registra alimentação de meta
     */
    public function registrarAlimentacao(int $idMeta, string $dataRegistro, array $valores, int $usuarioId): array
    {
        try {
            // Verificar se já existe registro
            $stmtCheck = $this->pdo->prepare("
                SELECT id FROM mkt_alimentacao_registros 
                WHERE id_meta_instancia = :id_meta AND data_registro = :data
            ");
            $stmtCheck->execute(['id_meta' => $idMeta, 'data' => $dataRegistro]);
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existente) {
                // Atualizar
                $stmt = $this->pdo->prepare("
                    UPDATE mkt_alimentacao_registros 
                    SET valores = :valores::jsonb, 
                        usuario_id = :usuario_id, 
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    'id' => $existente['id'],
                    'valores' => json_encode($valores),
                    'usuario_id' => $usuarioId
                ]);
            } else {
                // Criar novo
                $stmt = $this->pdo->prepare("
                    INSERT INTO mkt_alimentacao_registros (id_meta_instancia, data_registro, valores, usuario_id, created_at) 
                    VALUES (:id_meta, :data_registro, :valores::jsonb, :usuario_id, NOW())
                ");
                $stmt->execute([
                    'id_meta' => $idMeta,
                    'data_registro' => $dataRegistro,
                    'valores' => json_encode($valores),
                    'usuario_id' => $usuarioId
                ]);
            }
            
            // Registrar log
            $this->registrarLogAlimentacao($idMeta, $usuarioId, $valores, $dataRegistro);
            
            // Atualizar alimentação diária
            $this->atualizarAlimentacaoDiaria($idMeta, $dataRegistro, $valores);
            
            return ['success' => true, 'message' => 'Dados registrados com sucesso!'];
        } catch (\Exception $e) {
            error_log('Erro em registrarAlimentacao: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Registra log de alimentação
     */
    private function registrarLogAlimentacao(int $idMeta, int $usuarioId, array $valores, string $dataRegistro): void
    {
        try {
            // Verificar se tabela de logs existe
            $stmt = $this->pdo->query("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = 'mkt_alimentacao_logs'
                )
            ");
            $existe = $stmt->fetchColumn();
            
            if (!$existe) {
                $this->pdo->exec("
                    CREATE TABLE mkt_alimentacao_logs (
                        id SERIAL PRIMARY KEY,
                        id_meta_instancia INTEGER,
                        usuario_id INTEGER,
                        valores JSONB,
                        data_registro DATE,
                        created_at TIMESTAMP DEFAULT NOW()
                    )
                ");
            }
            
            $stmtLog = $this->pdo->prepare("
                INSERT INTO mkt_alimentacao_logs (id_meta_instancia, usuario_id, valores, data_registro)
                VALUES (:id_meta, :usuario_id, :valores::jsonb, :data_registro)
            ");
            $stmtLog->execute([
                'id_meta' => $idMeta,
                'usuario_id' => $usuarioId,
                'valores' => json_encode($valores),
                'data_registro' => $dataRegistro
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao registrar log: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualiza a tabela de alimentação diária
     */
    private function atualizarAlimentacaoDiaria(int $idMeta, string $dataRegistro, array $valores): void
    {
        try {
            $leads = $valores['meta_leads'] ?? $valores['leads'] ?? $valores['leads_recebidos'] ?? 0;
            $vendas = $valores['vendas'] ?? $valores['vendas_fechadas'] ?? 0;
            $faturamento = $valores['meta_faturamento'] ?? $valores['faturamento'] ?? $valores['valor_faturado'] ?? 0;
            $investimento = $valores['investimento'] ?? $valores['investimento_dia'] ?? 0;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO mkt_alimentacao_diaria (data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, id_meta, idusuario_mkt)
                VALUES (:data, :leads, :vendas, :faturamento, :investimento, :id_meta, 0)
                ON CONFLICT (data_registro, id_meta) DO UPDATE SET 
                leads_recebidos = mkt_alimentacao_diaria.leads_recebidos + EXCLUDED.leads_recebidos,
                vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + EXCLUDED.vendas_fechadas,
                valor_faturado = mkt_alimentacao_diaria.valor_faturado + EXCLUDED.valor_faturado,
                investimento_dia = mkt_alimentacao_diaria.investimento_dia + EXCLUDED.investimento_dia
            ");
            
            $stmt->execute([
                'data' => $dataRegistro,
                'leads' => (int)$leads,
                'vendas' => (int)$vendas,
                'faturamento' => (float)$faturamento,
                'investimento' => (float)$investimento,
                'id_meta' => $idMeta
            ]);
        } catch (\Exception $e) {
            error_log('Erro ao atualizar alimentacao_diaria: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtém todas as metas com progresso
     */
    public function getMetasComProgresso(string $status = 'ativa'): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mi.*, tm.nome as tipo_nome, tm.icone, tm.cor
                FROM mkt_metas_instancias mi
                LEFT JOIN mkt_tipos_meta tm ON tm.id = mi.id_tipo_meta
                WHERE mi.status = :status OR :status = 'todas'
                ORDER BY mi.data_fim ASC NULLS LAST
            ");
            $stmt->execute(['status' => $status]);
            $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular progresso para cada meta
            foreach ($metas as &$meta) {
                $progresso = $this->calcularProgressoMeta($meta['id']);
                if ($progresso['success']) {
                    $meta['progresso'] = $progresso['progresso'];
                    $meta['valor_acumulado'] = $progresso['valor_acumulado'];
                    $meta['total_registros'] = $progresso['total_registros'];
                } else {
                    $meta['progresso'] = 0;
                    $meta['valor_acumulado'] = 0;
                }
            }
            
            return $metas;
        } catch (\Exception $e) {
            error_log('Erro em getMetasComProgresso: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Dashboard do Marketing
     */
    public function getDashboardData(int $usuarioId): array
    {
        try {
            // KPIs principais
            $stmtKPI = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_leads,
                    COUNT(*) FILTER (WHERE status = 'Fechado') as vendas,
                    COALESCE(SUM(valor_fechamento), 0) as faturamento,
                    COALESCE(SUM(investimento_dia), 0) as total_investido
                FROM mkt_leads_controle
                WHERE data_registro >= date_trunc('month', CURRENT_DATE)
            ");
            $kpis = $stmtKPI->fetch(PDO::FETCH_ASSOC);
            
            $totalLeads = (int)$kpis['total_leads'];
            $totalInvestido = (float)$kpis['total_investido'];
            
            // Metas ativas
            $metasAtivas = $this->getMetasComProgresso('ativa');
            $totalMetas = count($metasAtivas);
            $mediaProgresso = 0;
            foreach ($metasAtivas as $meta) {
                $mediaProgresso += $meta['progresso'] ?? 0;
            }
            $mediaProgresso = $totalMetas > 0 ? round($mediaProgresso / $totalMetas, 1) : 0;
            
            return [
                'success' => true,
                'kpis' => [
                    'total_leads' => $totalLeads,
                    'vendas' => (int)$kpis['vendas'],
                    'faturamento' => (float)$kpis['faturamento'],
                    'cpl' => $totalLeads > 0 ? round($totalInvestido / $totalLeads, 2) : 0,
                    'roas' => $totalInvestido > 0 ? round((float)$kpis['faturamento'] / $totalInvestido, 2) : 0,
                    'taxa_conversao' => $totalLeads > 0 ? round(((int)$kpis['vendas'] / $totalLeads) * 100, 1) : 0
                ],
                'metas' => [
                    'total_ativas' => $totalMetas,
                    'media_progresso' => $mediaProgresso
                ]
            ];
        } catch (\Exception $e) {
            error_log('Erro em getDashboardData: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}