<?php
namespace Nutricional\Services;

use PDO;

/**
 * Serviço de Inteligência Artificial para Triagem e Diagnóstico de Chamados.
 * Analisa o relato bruto do usuário, padroniza títulos, detecta módulos, classifica criticidade
 * e gera um Dossiê Técnico para acelerar a resolução pelo desenvolvedor.
 */
class ChamadosIaService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Catálogo resumido de módulos e seus contextos para inferência
     */
    private function catalogoModulos(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT slug, nome, setor FROM sistema_modulos WHERE ativo = true ORDER BY nome");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Realiza a triagem inteligente do chamado
     */
    public function triar(string $relato, ?string $moduloInformado = null, int $filial = 1): array
    {
        $relatoLimpo = trim($relato);
        if (mb_strlen($relatoLimpo) < 5) {
            return [
                'titulo' => 'Chamado sem descrição suficiente',
                'modulo_slug' => $moduloInformado ?: 'geral',
                'modulo_nome' => 'Geral',
                'categoria' => 'duvida',
                'criticidade' => 'baixa',
                'diagnostico_ia' => 'Relato muito curto para gerar diagnóstico técnico prévio.',
                'relato_formatado' => $relatoLimpo
            ];
        }

        // Tenta Gemini API se disponível
        $geminiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
        if (!empty($geminiKey)) {
            $analiseRemota = $this->chamarGemini($relatoLimpo, $geminiKey, $moduloInformado);
            if (!empty($analiseRemota['sucesso'])) {
                return $analiseRemota['dados'];
            }
        }

        // Motor Heurístico Especialista (Local / Offline)
        return $this->analiseEspecialistaLocal($relatoLimpo, $moduloInformado, $filial);
    }

    /**
     * Motor de análise especialista com base no conhecimento do ecossistema Nutricional
     */
    private function analiseEspecialistaLocal(string $relato, ?string $moduloInformado, int $filial): array
    {
        $texto = mb_strtolower($relato, 'UTF-8');
        $modulos = $this->catalogoModulos();

        // 1. Detectar Módulo
        $slugDetectado = $moduloInformado;
        $nomeModulo = 'Portal Geral';

        $mapaTermos = [
            'verbas-flex' => ['flex', 'bonifica', 'saldo flex', 'gestor', 'empréstimo', 'aporte', 'mercos', 'saldo', 'verba'],
            'transferencia-flex' => ['transferência de flex', 'transferir flex', 'ceder flex', 'transferencia flex'],
            'movimentacao-flex' => ['movimentação de flex', 'movimentar saldo', 'débito manual flex'],
            'financeiro' => ['rate limit', 'muitas requisições', '429', 'iap', 'iag', 'inadimplência', 'recuperação', 'metas financeiras', 'duplicatas', 'vencidos'],
            'analise_carteira' => ['análise de carteira', 'carteira', 'títulos vencidos', 'cobrança'],
            'separacao' => ['separação', 'separador', 'bipe', 'wms', 'conferir item', 'estorno item'],
            'carregamento' => ['carregamento', 'doca', 'conferência de carga', 'caminhão'],
            'frota-embarques' => ['embarque', 'romaneio', 'motorista', 'rota'],
            'retorno-carga' => ['retorno de carga', 'devolução', 'sobra', 'prestação de contas'],
            'xml' => ['xml', 'nota fiscal', 'nfe fornecedor', 'conferência fiscal'],
            'marketing-clientes' => ['crm', 'cliente', 'prospecção', 'lead'],
            'cubo_vendas' => ['cubo', 'olap', 'vendas período', 'ranking vendas'],
            'dashboard_vendas' => ['dashboard vendas', 'faturamento vendas', 'ticket médio'],
            'estoque-previsao' => ['previsão de chegada', 'estoque futuro', 'compra sugerida']
        ];

        if (empty($slugDetectado)) {
            foreach ($mapaTermos as $slug => $palavras) {
                foreach ($palavras as $p) {
                    if (str_contains($texto, $p)) {
                        $slugDetectado = $slug;
                        break 2;
                    }
                }
            }
        }

        if (empty($slugDetectado)) {
            $slugDetectado = 'geral';
        }

        foreach ($modulos as $m) {
            if ($m['slug'] === $slugDetectado) {
                $nomeModulo = $m['nome'];
                break;
            }
        }

        // 2. Detectar Criticidade
        $criticidade = 'media';
        if (preg_match('/(travou|parou|bloqueado|não fatura|impossível|erro 500|429|urgente|paralisado|queda)/i', $texto)) {
            $criticidade = 'critica';
        } elseif (preg_match('/(divergência|valor errado|saldo incorreto|não atualizou|duplicou|falhou)/i', $texto)) {
            $criticidade = 'alta';
        } elseif (preg_match('/(layout|cor|texto|botão|melhoria|sugestão|ajuste|posição)/i', $texto)) {
            $criticidade = 'baixa';
        }

        // 3. Detectar Categoria
        $categoria = 'bug';
        if (preg_match('/(como faço|como funciona|dúvida|onde fica|manual|ajuda)/i', $texto)) {
            $categoria = 'duvida';
        } elseif (preg_match('/(seria bom|sugestão|criar tela|adicionar botão|melhorar)/i', $texto)) {
            $categoria = 'melhoria';
        } elseif (preg_match('/(novo módulo|novo processo|novo relatório)/i', $texto)) {
            $categoria = 'novo_modulo';
        }

        // 4. Gerar Título Formatado
        $primeiraLinha = trim(explode("\n", $relato)[0]);
        if (mb_strlen($primeiraLinha) > 70) {
            $primeiraLinha = mb_substr($primeiraLinha, 0, 67) . '...';
        }
        $tituloFormatado = sprintf('[%s] %s', $nomeModulo, ucfirst($primeiraLinha));

        // 5. Gerar Dossiê Técnico para Desenvolvedores
        $dossie = $this->gerarDossieTecnico($slugDetectado, $texto, $filial);

        return [
            'titulo' => $tituloFormatado,
            'modulo_slug' => $slugDetectado,
            'modulo_nome' => $nomeModulo,
            'categoria' => $categoria,
            'criticidade' => $criticidade,
            'diagnostico_ia' => $dossie,
            'relato_formatado' => $relato
        ];
    }

    /**
     * Dossiê Técnico estruturado com tabelas, arquivos e causas prováveis
     */
    private function gerarDossieTecnico(string $slug, string $texto, int $filial): string
    {
        $dossie = "[IA] DIAGNOSTICO PRELIMINAR DE ENGENHARIA (TRIAGEM INTELIGENTE)\n";
        $dossie .= "--------------------------------------------------------\n";

        switch ($slug) {
            case 'verbas-flex':
                $dossie .= "• Módulo: Gestão de Verbas Flex (v3.5)\n";
                $dossie .= "• Arquivos Envolvidos:\n";
                $dossie .= "  - Backend: v1/src/Controllers/VerbasFlexController.php\n";
                $dossie .= "  - Frontend: portal/modules/verbas-flex.php & portal/assets/js/verbas-flex.js\n";
                $dossie .= "  - Crons: v1/src/Controllers/CronRotinasController.php\n";
                $dossie .= "• Tabelas ERP Prováveis:\n";
                $dossie .= "  - log_flex_bonificacao (verificar b.valor vs b.valor_tirado)\n";
                $dossie .= "  - log_debito_flex_gestor & flex_minimo_config\n";
                $dossie .= "  - View: verificabonificacao\n";
                $dossie .= "• Sugestão Técnica:\n";
                if (str_contains($texto, 'zero') || str_contains($texto, '0,00')) {
                    $dossie .= "  -> Se o valor apareceu zerado, checar se a bonificação foi débito direto (saldo próprio do rep). Na versão 3.5 agora b.valor traz o montante real com badge verde.";
                } else {
                    $dossie .= "  -> Consultar saldo ao vivo na API Mercos (MercosFlexService) e validar se o rep possui conta ativa.";
                }
                break;

            case 'financeiro':
                $dossie .= "• Módulo: Controladoria Financeira & Metas (v3.3)\n";
                $dossie .= "• Arquivos Envolvidos:\n";
                $dossie .= "  - Backend: v1/src/Controllers/FinanceiroController.php\n";
                $dossie .= "  - Middleware: v1/src/Middleware/RateLimitMiddleware.php\n";
                $dossie .= "  - Frontend: portal/modules/financeiro.php & portal/assets/js/financeiro.js\n";
                $dossie .= "• Tabelas ERP Prováveis:\n";
                $dossie .= "  - rate_limit (Postgres)\n";
                $dossie .= "  - metas_financeiro & usuario\n";
                $dossie .= "• Sugestão Técnica:\n";
                if (str_contains($texto, '429') || str_contains($texto, 'muitas requisições')) {
                    $dossie .= "  -> Chave rate_limit isolada por rota. Verificar teto da rota /v1/financeiro/dashboard (limite 200 req/min).";
                } else {
                    $dossie .= "  -> Validar se as metas ativas no mês possuem data_inicio e data_fim compatíveis.";
                }
                break;

            case 'separacao':
            case 'carregamento':
                $dossie .= "• Módulo: Logística WMS\n";
                $dossie .= "• Arquivos Envolvidos:\n";
                $dossie .= "  - Backend: v1/src/Controllers/SeparacaoController.php ou CarregamentoController.php\n";
                $dossie .= "  - Frontend: portal/modules/separacao.php\n";
                $dossie .= "• Tabelas ERP Prováveis:\n";
                $dossie .= "  - embarque_item, pedido, pedido_item\n";
                $dossie .= "• Sugestão Técnica: Conferir status do embarque e advisory locks de concorrência.";
                break;

            default:
                $dossie .= "• Módulo: " . strtoupper($slug) . "\n";
                $dossie .= "• Análise Heurística: Identificar rota no v1/routes/api.php e controller associado.\n";
                $dossie .= "• Sugestão Técnica: Verificar logs de erro em v1/logs/ e permissões do usuário em usuario.dash_filiais.";
                break;
        }

        return $dossie;
    }

    /**
     * Chamada opcional à API Gemini se a chave estiver configurada
     */
    private function chamarGemini(string $relato, string $apiKey, ?string $moduloInformado): array
    {
        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
            $prompt = "Você é um especialista sênior em suporte técnico do ERP Nutricional. "
                . "Analise o relato do usuário e responda APENAS em JSON no formato: "
                . '{"titulo":"...", "modulo_slug":"...", "modulo_nome":"...", "categoria":"bug|melhoria|duvida|novo_modulo", "criticidade":"critica|alta|media|baixa", "diagnostico_ia":"..."}'
                . "\nRelato do usuário: " . $relato;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode([
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ])
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($resp, true);
            $textoGerado = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (preg_match('/\{.*\}/s', $textoGerado, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed) && isset($parsed['titulo'])) {
                    $parsed['relato_formatado'] = $relato;
                    return ['sucesso' => true, 'dados' => $parsed];
                }
            }
        } catch (\Throwable $e) {
            error_log('[IA Chamados] Falha Gemini API: ' . $e->getMessage());
        }
        return ['sucesso' => false];
    }
}
