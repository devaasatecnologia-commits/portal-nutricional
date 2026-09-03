<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Exception;

class MarketingAdminController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/marketing-admin/config
     * Retorna configurações e opções disponíveis
     */
    public function getConfig(Request $request, Response $response): Response
    {
        try {
            // Tipos de cadastro disponíveis
            $tiposCadastro = [
                [
                    'id' => 'lead',
                    'nome' => 'Novo Lead',
                    'icone' => 'fa-user-plus',
                    'cor' => 'blue',
                    'descricao' => 'Registrar um novo lead/oportunidade',
                    'formulario' => $this->getFormLead()
                ],
                [
                    'id' => 'cliente',
                    'nome' => 'Novo Cliente',
                    'icone' => 'fa-building',
                    'cor' => 'green',
                    'descricao' => 'Cadastrar cliente no CRM',
                    'formulario' => $this->getFormCliente()
                ],
                [
                    'id' => 'meta',
                    'nome' => 'Nova Meta',
                    'icone' => 'fa-bullseye',
                    'cor' => 'purple',
                    'descricao' => 'Criar meta/objetivo',
                    'formulario' => $this->getFormMeta()
                ],
                [
                    'id' => 'alimentacao',
                    'nome' => 'Alimentação Diária',
                    'icone' => 'fa-calendar-day',
                    'cor' => 'amber',
                    'descricao' => 'Registrar dados do dia',
                    'formulario' => $this->getFormAlimentacao()
                ]
            ];
            
            // Lista de metas ativas
            $stmtMetas = $this->pdo->query("SELECT id, titulo, meta_leads, meta_faturamento FROM mkt_metas WHERE status = 'Ativa' ORDER BY data_fim ASC");
            $metasAtivas = $stmtMetas->fetchAll(PDO::FETCH_ASSOC);
            
            // Lista de origens
            $origens = ['Site', 'WhatsApp', 'Instagram', 'Facebook', 'Indicação', 'Telefone', 'Feira', 'E-mail', 'Outros'];
            
            // Lista de status
            $statusLead = ['Novo', 'Qualificado', 'Proposta', 'Followup', 'Fechado', 'Perdido'];
            $statusCliente = ['Novo', 'Qualificado', 'Proposta', 'Fechado', 'Perdido'];
            $termometro = ['Frio', 'Morno', 'Quente'];
            
            return $this->json($response, [
                'success' => true,
                'tipos_cadastro' => $tiposCadastro,
                'metas_ativas' => $metasAtivas,
                'origens' => $origens,
                'status_lead' => $statusLead,
                'status_cliente' => $statusCliente,
                'termometro' => $termometro
            ]);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /v1/marketing-admin/salvar
     */
    public function salvar(Request $request, Response $response): Response
    {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $tipo = $input['tipo'] ?? '';
        $dados = $input['dados'] ?? [];
        
        try {
            $resultado = ['success' => false, 'message' => '', 'id' => null];
            
            switch ($tipo) {
                case 'lead':
                    $resultado = $this->salvarLead($dados);
                    break;
                case 'cliente':
                    $resultado = $this->salvarCliente($dados);
                    break;
                case 'meta':
                    $resultado = $this->salvarMeta($dados);
                    break;
                case 'alimentacao':
                    $resultado = $this->salvarAlimentacao($dados);
                    break;
                default:
                    throw new Exception('Tipo de cadastro inválido');
            }
            
            return $this->json($response, $resultado);
        } catch (Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * GET /v1/marketing-admin/dashboard
     */
    public function getDashboard(Request $request, Response $response): Response
    {
        try {
            // Totais
            $stmtTotal = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM mkt_leads_controle) as total_leads,
                    (SELECT COUNT(*) FROM mkt_clientes) as total_clientes,
                    (SELECT COUNT(*) FROM mkt_metas WHERE status = 'Ativa') as metas_ativas,
                    (SELECT COUNT(*) FROM mkt_metas) as total_metas
            ");
            $totais = $stmtTotal->fetch(PDO::FETCH_ASSOC);
            
            // Últimos registros
            $stmtUltimos = $this->pdo->query("
                SELECT 'lead' as tipo, id, empresa as nome, data_registro as data, status 
                FROM mkt_leads_controle 
                ORDER BY data_registro DESC LIMIT 5
                UNION ALL
                SELECT 'cliente' as tipo, id, nome, data_cadastro as data, status 
                FROM mkt_clientes 
                ORDER BY data_cadastro DESC LIMIT 5
                UNION ALL
                SELECT 'meta' as tipo, id, titulo as nome, data_criacao as data, status 
                FROM mkt_metas 
                ORDER BY data_criacao DESC LIMIT 5
                ORDER BY data DESC LIMIT 10
            ");
            $ultimosRegistros = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);
            
            // KPIs
            $stmtKPI = $this->pdo->query("
                SELECT 
                    COALESCE((SELECT SUM(valor_fechamento) FROM mkt_leads_controle WHERE status = 'Fechado'), 0)::float as faturamento_leads,
                    COALESCE((SELECT SUM(valor_negocio) FROM mkt_clientes WHERE status = 'Fechado'), 0)::float as faturamento_clientes,
                    COALESCE((SELECT SUM(meta_faturamento) FROM mkt_metas), 0)::float as meta_faturamento_total
            ");
            $kpis = $stmtKPI->fetch(PDO::FETCH_ASSOC);
            
            return $this->json($response, [
                'success' => true,
                'totais' => $totais,
                'ultimos_registros' => $ultimosRegistros,
                'kpis' => $kpis
            ]);
        } catch (Exception $e) {
            // Fallback com dados mockados
            return $this->json($response, [
                'success' => true,
                'totais' => ['total_leads' => 0, 'total_clientes' => 0, 'metas_ativas' => 0, 'total_metas' => 0],
                'ultimos_registros' => [],
                'kpis' => ['faturamento_leads' => 0, 'faturamento_clientes' => 0, 'meta_faturamento_total' => 0]
            ]);
        }
    }
    
    // ======================================================================
    // FORMULÁRIOS
    // ======================================================================
    
    private function getFormLead(): array
    {
        return [
            'fields' => [
                ['name' => 'empresa', 'label' => 'Empresa/Nome', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome da empresa ou contato'],
                ['name' => 'telefone', 'label' => 'Telefone', 'type' => 'tel', 'required' => false, 'placeholder' => '(00) 00000-0000'],
                ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => false, 'placeholder' => 'email@exemplo.com'],
                ['name' => 'origem', 'label' => 'Origem', 'type' => 'select', 'required' => true, 'options' => 'origens'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => 'status_lead'],
                ['name' => 'termometro', 'label' => 'Termômetro', 'type' => 'select', 'required' => true, 'options' => 'termometro'],
                ['name' => 'valor', 'label' => 'Valor (R$)', 'type' => 'number', 'required' => false, 'step' => '0.01'],
                ['name' => 'id_meta', 'label' => 'Meta Vinculada', 'type' => 'select', 'required' => false, 'options' => 'metas'],
                ['name' => 'observacoes', 'label' => 'Observações', 'type' => 'textarea', 'required' => false, 'rows' => 3]
            ]
        ];
    }
    
    private function getFormCliente(): array
    {
        return [
            'fields' => [
                ['name' => 'nome', 'label' => 'Nome do Contato', 'type' => 'text', 'required' => true, 'placeholder' => 'Nome completo'],
                ['name' => 'empresa', 'label' => 'Empresa', 'type' => 'text', 'required' => false, 'placeholder' => 'Nome da empresa'],
                ['name' => 'telefone', 'label' => 'Telefone', 'type' => 'tel', 'required' => true, 'placeholder' => '(00) 00000-0000'],
                ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => false, 'placeholder' => 'email@exemplo.com'],
                ['name' => 'cidade', 'label' => 'Cidade', 'type' => 'text', 'required' => false, 'placeholder' => 'Cidade'],
                ['name' => 'uf', 'label' => 'UF', 'type' => 'text', 'required' => false, 'placeholder' => 'UF', 'maxlength' => 2],
                ['name' => 'origem', 'label' => 'Origem', 'type' => 'select', 'required' => true, 'options' => 'origens'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => 'status_cliente'],
                ['name' => 'termometro', 'label' => 'Termômetro', 'type' => 'select', 'required' => true, 'options' => 'termometro'],
                ['name' => 'valor_negocio', 'label' => 'Valor do Negócio (R$)', 'type' => 'number', 'required' => false, 'step' => '0.01'],
                ['name' => 'id_meta', 'label' => 'Meta Vinculada', 'type' => 'select', 'required' => false, 'options' => 'metas'],
                ['name' => 'observacoes', 'label' => 'Observações', 'type' => 'textarea', 'required' => false, 'rows' => 3]
            ]
        ];
    }
    
    private function getFormMeta(): array
    {
        return [
            'fields' => [
                ['name' => 'titulo', 'label' => 'Título da Meta', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Black Friday 2024'],
                ['name' => 'objetivo', 'label' => 'Descrição/Objetivo', 'type' => 'textarea', 'required' => false, 'rows' => 2, 'placeholder' => 'Descreva o objetivo...'],
                ['name' => 'meta_leads', 'label' => 'Meta de Leads', 'type' => 'number', 'required' => true, 'min' => 0],
                ['name' => 'meta_faturamento', 'label' => 'Meta de Faturamento (R$)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01'],
                ['name' => 'data_inicio', 'label' => 'Data Início', 'type' => 'date', 'required' => true],
                ['name' => 'data_fim', 'label' => 'Data Fim', 'type' => 'date', 'required' => true],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => 'status_meta']
            ]
        ];
    }
    
    private function getFormAlimentacao(): array
    {
        return [
            'fields' => [
                ['name' => 'data_registro', 'label' => 'Data', 'type' => 'date', 'required' => true],
                ['name' => 'leads_recebidos', 'label' => 'Leads Recebidos', 'type' => 'number', 'required' => true, 'min' => 0],
                ['name' => 'vendas_fechadas', 'label' => 'Vendas Fechadas', 'type' => 'number', 'required' => true, 'min' => 0],
                ['name' => 'valor_faturado', 'label' => 'Valor Faturado (R$)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01'],
                ['name' => 'investimento_dia', 'label' => 'Investimento do Dia (R$)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01'],
                ['name' => 'id_meta', 'label' => 'Meta Vinculada', 'type' => 'select', 'required' => false, 'options' => 'metas']
            ]
        ];
    }
    
    // ======================================================================
    // SALVAR DADOS
    // ======================================================================
    
    private function salvarLead($dados): array
    {
        $sql = "INSERT INTO mkt_leads_controle (
            data_registro, empresa, telefone, email, origem, status, 
            termometro, valor_fechamento, id_meta, gestor, qualificado
        ) VALUES (
            COALESCE(:data, CURRENT_DATE), :empresa, :telefone, :email, :origem, :status,
            :termometro, :valor, :id_meta, :gestor, 'true'
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'data' => $dados['data_registro'] ?? date('Y-m-d'),
            'empresa' => $dados['empresa'] ?? '',
            'telefone' => $dados['telefone'] ?? '',
            'email' => $dados['email'] ?? '',
            'origem' => $dados['origem'] ?? 'Site',
            'status' => $dados['status'] ?? 'Novo',
            'termometro' => $dados['termometro'] ?? 'Frio',
            'valor' => (float)($dados['valor'] ?? 0),
            'id_meta' => !empty($dados['id_meta']) ? (int)$dados['id_meta'] : null,
            'gestor' => $dados['gestor'] ?? 'Sistema'
        ]);
        
        return ['success' => true, 'message' => 'Lead salvo com sucesso!', 'id' => $this->pdo->lastInsertId()];
    }
    
    private function salvarCliente($dados): array
    {
        $sql = "INSERT INTO mkt_clientes (
            nome, empresa, telefone, email, cidade, uf, origem, status,
            termometro, valor_negocio, id_meta, observacoes, data_cadastro, usuario_criacao
        ) VALUES (
            :nome, :empresa, :telefone, :email, :cidade, :uf, :origem, :status,
            :termometro, :valor, :id_meta, :obs, :data, :usuario
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nome' => $dados['nome'] ?? '',
            'empresa' => $dados['empresa'] ?? '',
            'telefone' => $dados['telefone'] ?? '',
            'email' => $dados['email'] ?? '',
            'cidade' => $dados['cidade'] ?? '',
            'uf' => $dados['uf'] ?? '',
            'origem' => $dados['origem'] ?? 'Site',
            'status' => $dados['status'] ?? 'Novo',
            'termometro' => $dados['termometro'] ?? 'Frio',
            'valor' => (float)($dados['valor_negocio'] ?? 0),
            'id_meta' => !empty($dados['id_meta']) ? (int)$dados['id_meta'] : null,
            'obs' => $dados['observacoes'] ?? '',
            'data' => $dados['data_cadastro'] ?? date('Y-m-d'),
            'usuario' => $dados['usuario'] ?? 'Sistema'
        ]);
        
        return ['success' => true, 'message' => 'Cliente salvo com sucesso!', 'id' => $this->pdo->lastInsertId()];
    }
    
    private function salvarMeta($dados): array
    {
        $sql = "INSERT INTO mkt_metas (
            titulo, objetivo, meta_leads, meta_faturamento, data_inicio, data_fim, status
        ) VALUES (
            :titulo, :objetivo, :leads, :faturamento, :inicio, :fim, :status
        )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'titulo' => $dados['titulo'] ?? '',
            'objetivo' => $dados['objetivo'] ?? '',
            'leads' => (int)($dados['meta_leads'] ?? 0),
            'faturamento' => (float)($dados['meta_faturamento'] ?? 0),
            'inicio' => $dados['data_inicio'] ?? date('Y-m-d'),
            'fim' => $dados['data_fim'] ?? date('Y-m-d', strtotime('+30 days')),
            'status' => $dados['status'] ?? 'Ativa'
        ]);
        
        return ['success' => true, 'message' => 'Meta criada com sucesso!', 'id' => $this->pdo->lastInsertId()];
    }
    
    private function salvarAlimentacao($dados): array
    {
        $sql = "INSERT INTO mkt_alimentacao_diaria (
            data_registro, leads_recebidos, vendas_fechadas, valor_faturado, investimento_dia, id_meta
        ) VALUES (
            :data, :leads, :vendas, :faturado, :investimento, :id_meta
        ) ON CONFLICT (data_registro, id_meta) DO UPDATE SET
            leads_recebidos = mkt_alimentacao_diaria.leads_recebidos + EXCLUDED.leads_recebidos,
            vendas_fechadas = mkt_alimentacao_diaria.vendas_fechadas + EXCLUDED.vendas_fechadas,
            valor_faturado = mkt_alimentacao_diaria.valor_faturado + EXCLUDED.valor_faturado,
            investimento_dia = mkt_alimentacao_diaria.investimento_dia + EXCLUDED.investimento_dia";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'data' => $dados['data_registro'] ?? date('Y-m-d'),
            'leads' => (int)($dados['leads_recebidos'] ?? 0),
            'vendas' => (int)($dados['vendas_fechadas'] ?? 0),
            'faturado' => (float)($dados['valor_faturado'] ?? 0),
            'investimento' => (float)($dados['investimento_dia'] ?? 0),
            'id_meta' => !empty($dados['id_meta']) ? (int)$dados['id_meta'] : null
        ]);
        
        return ['success' => true, 'message' => 'Dados registrados com sucesso!'];
    }
    
    private function json($response, $data, $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}