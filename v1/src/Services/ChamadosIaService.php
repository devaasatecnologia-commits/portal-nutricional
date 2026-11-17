<?php
namespace Nutricional\Services;

use PDO;

/**
 * Serviço de Inteligência Artificial para Triagem, Correção Ortográfica e Diagnóstico Técnico de Chamados.
 *
 * Integra o Token de Sabedoria do Ecossistema Nutricional (ERP EMA, PostgreSQL Legado, Mercos, Cobli, Logisoft e Auth/2FA)
 * com consulta em tempo real ao banco de dados para alinhar módulo, categoria, criticidade e gerar prévia exata da solução.
 */
class ChamadosIaService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Catálogo ativo de módulos do Portal em sistema_modulos.
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
     * Carrega um resumo objetivo do Token de Sabedoria em docs/tokens/KNOWLEDGE-BASE-ECOSSISTEMA-NUTRICIONAL.md.
     */
    private function carregarKnowledgeBase(): string
    {
        $path = dirname(__DIR__, 3) . '/docs/tokens/KNOWLEDGE-BASE-ECOSSISTEMA-NUTRICIONAL.md';
        if (!is_file($path)) {
            return '';
        }
        $conteudo = (string)file_get_contents($path);
        return mb_substr(trim($conteudo), 0, 5500, 'UTF-8');
    }

    /**
     * Realiza a triagem inteligente do chamado, respeitando escolhas explícitas do usuário quando sinalizadas.
     */
    public function triar(
        string $relato,
        ?string $moduloInformado = null,
        int $filial = 1,
        ?string $tipoSistemaInformado = null,
        array $opcoes = []
    ): array {
        $relatoCorrigido = $this->corrigirOrtografia(trim($relato));
        $moduloInformado = $moduloInformado !== null ? trim($moduloInformado) : null;
        if ($moduloInformado === '') {
            $moduloInformado = null;
        }

        if (mb_strlen($relatoCorrigido) < 4) {
            return [
                'titulo' => 'Chamado sem descrição suficiente',
                'tipo_sistema' => $tipoSistemaInformado ?: 'PORTAL',
                'subtipo_item' => 'Geral',
                'modulo_slug' => $moduloInformado ?: 'geral',
                'modulo_nome' => 'Portal Geral',
                'categoria' => !empty($opcoes['categoria_informada']) ? (string)$opcoes['categoria_informada'] : 'duvida',
                'criticidade' => !empty($opcoes['criticidade_informada']) ? (string)$opcoes['criticidade_informada'] : 'baixa',
                'diagnostico_ia' => 'Relato muito curto para gerar diagnóstico técnico prévio.',
                'relato_formatado' => $relatoCorrigido,
            ];
        }

        $analiseLocal = $this->analiseEspecialistaLocal($relatoCorrigido, $moduloInformado, $filial, $tipoSistemaInformado, $opcoes);

        // Tenta Gemini API se configurada, mantendo o alinhamento de sabedoria do banco e travas de campos manuais
        $geminiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
        if (!empty($geminiKey)) {
            $analiseRemota = $this->chamarGemini($relatoCorrigido, $geminiKey, $moduloInformado, $tipoSistemaInformado, $opcoes, $analiseLocal);
            if (!empty($analiseRemota['sucesso'])) {
                return $analiseRemota['dados'];
            }
        }

        return $analiseLocal;
    }

    /**
     * Corrige erros ortográficos comuns e padroniza termos técnicos do ecossistema mantendo quebras de linha.
     */
    public function corrigirOrtografia(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        // Protege blocos que não devem sofrer substituição ortográfica (URLs, paths com barra, identificadores snake_case/camelCase)
        $linhas = preg_split("/(\r\n|\n|\r)/", $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($linhas)) {
            return $texto;
        }

        $substituicoes = [
            // Termos técnicos compostos primeiro
            '/\bre-?\s*captcha\b/ui' => 'reCAPTCHA',
            '/\bcloud[\s\-]*f(?:ire|lare)(?:\s+turnstile)?\b/ui' => 'Cloudflare Turnstile',
            '/\btop\s+das\s+informa(?:c|ç)(?:o|õ)es\b/ui' => 'topo das informações',
            '/\bboletos\s+em\s+abertos\b/ui' => 'boletos em aberto',
            '/\b2\s*etapas\b/ui' => '2 etapas',
            '/\berp\s+ema\b/ui' => 'ERP EMA',
            '/\be-?mail\b/ui' => 'e-mail',
            // Erros ortográficos e falta de acentuação frequentes
            '/\bvisiali[zs]ar\b/ui' => 'visualizar',
            '/\bvisuali[zs]ar\b/ui' => 'visualizar',
            '/\badcionar\b/ui' => 'adicionar',
            '/\bmecher\b/ui' => 'mexer',
            '/\bdivirg[eê]ncia\b/ui' => 'divergência',
            '/\bdivirg[eê]ncias\b/ui' => 'divergências',
            '/\bconferecia\b/ui' => 'conferência',
            '/\bconferencia\b/ui' => 'conferência',
            '/\bdesenbarque\b/ui' => 'desembarque',
            '/\breemprimir\b/ui' => 'reimprimir',
            '/\breempressao\b/ui' => 'reimpressão',
            '/\btbm\b/ui' => 'também',
            '/\btambem\b/ui' => 'também',
            '/\bvc\b/ui' => 'você',
            '/\bvoce\b/ui' => 'você',
            '/\bnao\b/ui' => 'não',
            '/\bapos\b/ui' => 'após',
            '/\bja\b/ui' => 'já',
            '/\bate\b/ui' => 'até',
            '/\bso\b/ui' => 'só',
            '/\ba disposi[cç][aã]o\b/ui' => 'à disposição',
            '/\bento\b/ui' => 'então',
            '/\bentaum\b/ui' => 'então',
            '/\bsabendo\b/ui' => 'sabendo',
            '/\bobservacao\b/ui' => 'observação',
            '/\bobs\s*:/ui' => 'Obs.:',
            '/\busuario\b/ui' => 'usuário',
            '/\busuarios\b/ui' => 'usuários',
            '/\brelatorio\b/ui' => 'relatório',
            '/\brelatorios\b/ui' => 'relatórios',
            '/\bdiagnostico\b/ui' => 'diagnóstico',
            '/\bdosie\b/ui' => 'dossiê',
            '/\bdossie\b/ui' => 'dossiê',
            '/\bprevia\b/ui' => 'prévia',
            '/\bsolucao\b/ui' => 'solução',
            '/\bsolucoes\b/ui' => 'soluções',
            '/\binformacao\b/ui' => 'informação',
            '/\binformacoes\b/ui' => 'informações',
            '/\bcodigo\b/ui' => 'código',
            '/\bcodigos\b/ui' => 'códigos',
            '/\bmodulo\b/ui' => 'módulo',
            '/\bmodulos\b/ui' => 'módulos',
            '/\bintegracao\b/ui' => 'integração',
            '/\bmovimentacao\b/ui' => 'movimentação',
            '/\bmovimentacoes\b/ui' => 'movimentações',
            '/\bavaliacao\b/ui' => 'avaliação',
            '/\bsituacao\b/ui' => 'situação',
            '/\brequisicao\b/ui' => 'requisição',
            '/\brequisicoes\b/ui' => 'requisições',
            '/\balteracao\b/ui' => 'alteração',
            '/\balteracoes\b/ui' => 'alterações',
            '/\bcorrecao\b/ui' => 'correção',
            '/\bmanutencao\b/ui' => 'manutenção',
            '/\bpermissao\b/ui' => 'permissão',
            '/\bpermissoes\b/ui' => 'permissões',
            '/\bautenticacao\b/ui' => 'autenticação',
            '/\brecuperacao\b/ui' => 'recuperação',
            '/\bredefinicao\b/ui' => 'redefinição',
            '/\bexibicao\b/ui' => 'exibição',
            '/\bvisualizacao\b/ui' => 'visualização',
            '/\boperacao\b/ui' => 'operação',
            '/\bversao\b/ui' => 'versão',
            '/\bseguranca\b/ui' => 'segurança',
            '/\bprazo\b/ui' => 'prazo',
            '/\binclusao\b/ui' => 'inclusão',
            '/\bexclusao\b/ui' => 'exclusão',
            '/\bconclusao\b/ui' => 'conclusão',
            '/\bduvida\b/ui' => 'dúvida',
            '/\bduvidas\b/ui' => 'dúvidas',
            '/\bdisposicao\b/ui' => 'disposição',
            '/\bcabecalho\b/ui' => 'cabeçalho',
            '/\bautomatico\b/ui' => 'automático',
            '/\bautomatica\b/ui' => 'automática',
            '/\bortografico\b/ui' => 'ortográfico',
            '/\bortograficos\b/ui' => 'ortográficos',
            '/\bortografia\b/ui' => 'ortografia',
            '/\bteorico\b/ui' => 'teórico',
            '/\btecnico\b/ui' => 'técnico',
            '/\btecnica\b/ui' => 'técnica',
            '/\btecnicos\b/ui' => 'técnicos',
            '/\bproprio\b/ui' => 'próprio',
            '/\bpropria\b/ui' => 'própria',
            '/\bnumero\b/ui' => 'número',
            '/\bhistorico\b/ui' => 'histórico',
        ];

        $resultado = '';
        foreach ($linhas as $parte) {
            if ($parte === "\r\n" || $parte === "\n" || $parte === "\r") {
                $resultado .= $parte;
                continue;
            }

            $limpo = $parte;
            foreach ($substituicoes as $regex => $repl) {
                $limpo = (string)preg_replace_callback($regex, function (array $m) use ($repl): string {
                    $orig = $m[0];
                    // Preserva caixa se a palavra original estava toda em MAIÚSCULAS
                    if (mb_strtoupper($orig, 'UTF-8') === $orig && mb_strlen($orig, 'UTF-8') > 2) {
                        return mb_strtoupper($repl, 'UTF-8');
                    }
                    // Preserva inicial maiúscula se não for termo de marca própria já capitalizado
                    $firstChar = mb_substr($orig, 0, 1, 'UTF-8');
                    $replFirst = mb_substr($repl, 0, 1, 'UTF-8');
                    if ($firstChar === mb_strtoupper($firstChar, 'UTF-8')
                        && $firstChar !== mb_strtolower($firstChar, 'UTF-8')
                        && $replFirst === mb_strtolower($replFirst, 'UTF-8')) {
                        return mb_strtoupper($replFirst, 'UTF-8') . mb_substr($repl, 1, null, 'UTF-8');
                    }
                    return $repl;
                }, $limpo);
            }

            $limpo = (string)preg_replace('/[ \t]{2,}/', ' ', $limpo);
            $resultado .= trim($limpo);
        }

        // Garante primeira letra maiúscula no início do texto se começar com letra minúscula
        if ($resultado !== '') {
            $first = mb_substr($resultado, 0, 1, 'UTF-8');
            $resultado = mb_strtoupper($first, 'UTF-8') . mb_substr($resultado, 1, null, 'UTF-8');
        }

        return $resultado;
    }

    /**
     * Motor heurístico especialista enriquecido com o Token de Sabedoria e consultas reais ao PostgreSQL.
     */
    private function analiseEspecialistaLocal(
        string $relato,
        ?string $moduloInformado,
        int $filial,
        ?string $tipoSistemaInformado,
        array $opcoes = []
    ): array {
        $texto = mb_strtolower($relato, 'UTF-8');
        $modulos = $this->catalogoModulos();

        $respeitarModulo = !empty($opcoes['respeitar_modulo']);
        $respeitarCategoria = !empty($opcoes['respeitar_categoria']);
        $respeitarCriticidade = !empty($opcoes['respeitar_criticidade']);
        $respeitarSistema = !empty($opcoes['respeitar_sistema']);

        // 1. Detectar Tipo de Sistema & Subtipo (evitando falsos positivos quando é funcionalidade do Portal citando "ERP e Banco")
        $tipoSistema = ($tipoSistemaInformado && $tipoSistemaInformado !== 'AUTO') ? strtoupper($tipoSistemaInformado) : '';
        $subtipoItem = !empty($opcoes['subtipo_informado']) ? trim((string)$opcoes['subtipo_informado']) : 'Geral';

        $ehArquiteturaPortalB2B = $this->ehCenarioPortalClienteHierarquia($texto);

        if ($tipoSistema === '' || (!$respeitarSistema && $tipoSistema === 'PORTAL')) {
            if ($ehArquiteturaPortalB2B) {
                $tipoSistema = 'PORTAL';
                $subtipoItem = 'Portal B2B / Hierarquia & Auth 2FA';
            } elseif (preg_match('/\b(impressora|toner|papel|imprimir|impressão|telefone|ramal|ligação|mouse|teclado|monitor|computador|nobreak|leitor|coletor|cabo)\b/ui', $texto)
                && !preg_match('/\b(pdf|a4|tela|modal|botão|portal|relatório)\b/ui', $texto)) {
                $tipoSistema = 'EQUIPAMENTO';
                if (preg_match('/(impressora|toner)/ui', $texto)) $subtipoItem = 'Impressora';
                elseif (preg_match('/(telefone|ramal|ligação)/ui', $texto)) $subtipoItem = 'Telefonia / Ramal';
                elseif (preg_match('/(computador|pc|desktop|notebook)/ui', $texto)) $subtipoItem = 'Computador';
                elseif (preg_match('/(leitor|coletor)/ui', $texto)) $subtipoItem = 'Coletor / Leitor';
                else $subtipoItem = 'Periféricos';
            } elseif (preg_match('/\b(logisoft|ct-?e|mdf-?e|ciot|conhecimento de transporte)\b/ui', $texto)) {
                $tipoSistema = 'LOGISOFT';
                $subtipoItem = 'Emissão Fiscal (CT-e / MDF-e / CIOT)';
            } elseif (preg_match('/\b(cobli|rastreador|telemetria|gps|ignição)\b/ui', $texto)) {
                $tipoSistema = 'COBLI';
                $subtipoItem = 'Telemetria & Rastreamento';
            } elseif (preg_match('/\b(tela do ema|erp ema desktop|executável do ema|sistema ema desktop|faturamento no ema|travou o ema)\b/ui', $texto)) {
                $tipoSistema = 'ERP_EMA';
                $subtipoItem = 'ERP EMA Desktop';
            } elseif (preg_match('/\b(app mercos|força de vendas mercos|integrador mercos|ema-mercos-integration)\b/ui', $texto) && !str_contains($texto, 'portal')) {
                $tipoSistema = 'MERCOS';
                $subtipoItem = 'Integração Mercos';
            } elseif (preg_match('/\b(roundcube|webmail|outlook|caixa postal|não recebo e-mail)\b/ui', $texto)) {
                $tipoSistema = 'EMAIL';
                $subtipoItem = 'E-mail Corporativo';
            } elseif (preg_match('/\b(planilha|excel|fórmula|macro)\b/ui', $texto)) {
                $tipoSistema = 'PLANILHAS';
                $subtipoItem = 'Planilhas Excel';
            } elseif (preg_match('/\b(servidor|vpn|internet|queda de link|roteador|switch|wi-fi|wifi)\b/ui', $texto)) {
                $tipoSistema = 'SERVIDOR';
                $subtipoItem = 'Rede & Servidores';
            } else {
                $tipoSistema = $tipoSistema ?: 'PORTAL';
                if ($subtipoItem === 'Geral' && $tipoSistema === 'PORTAL') {
                    $subtipoItem = 'Módulos Web';
                }
            }
        }

        // 2. Detectar Módulo por Pontuação Ponderada (sem falso positivo em verbas-flex)
        $deteccaoModulo = $this->detectarModuloPonderado($texto, $tipoSistema, $moduloInformado, $modulos, $respeitarModulo, $ehArquiteturaPortalB2B);
        $slugDetectado = $deteccaoModulo['slug'];
        $nomeModulo = $deteccaoModulo['nome'];
        $cenarioEspecial = $deteccaoModulo['cenario'];

        // 3. Detectar Categoria (respeitando escolha explícita do usuário quando aplicável)
        $categoriaInformada = !empty($opcoes['categoria_informada']) ? strtolower(trim((string)$opcoes['categoria_informada'])) : '';
        if ($respeitarCategoria && $categoriaInformada !== '') {
            $categoria = $categoriaInformada;
        } elseif ($categoriaInformada !== '' && $categoriaInformada !== 'bug') {
            // Se usuário já trocou explicitamente para melhoria/duvida/permissao/equipamento, sempre preserva
            $categoria = $categoriaInformada;
        } else {
            $categoria = $this->detectarCategoriaPonderada($texto, $tipoSistema, $ehArquiteturaPortalB2B);
        }

        // 4. Detectar Criticidade (respeitando escolha explícita quando aplicável)
        $critInformada = !empty($opcoes['criticidade_informada']) ? strtolower(trim((string)$opcoes['criticidade_informada'])) : '';
        if ($respeitarCriticidade && in_array($critInformada, ['critica', 'alta', 'media', 'baixa'], true)) {
            $criticidade = $critInformada;
        } elseif (in_array($critInformada, ['critica', 'alta', 'baixa'], true)) {
            $criticidade = $critInformada;
        } else {
            $criticidade = $this->detectarCriticidade($texto);
        }

        // 5. Título Formatado (usa título informado corrigido se o usuário editou manualmente, ou gera limpo com ortografia revisada)
        $tituloInformado = !empty($opcoes['titulo_informado']) ? $this->corrigirOrtografia(trim((string)$opcoes['titulo_informado'])) : '';
        if (!empty($opcoes['respeitar_titulo']) && $tituloInformado !== '') {
            $tituloFormatado = $tituloInformado;
        } else {
            $primeiraLinha = trim(preg_split("/\r\n|\n|\r/", $relato)[0] ?? $relato);
            if (mb_strlen($primeiraLinha, 'UTF-8') > 78) {
                $primeiraLinha = mb_substr($primeiraLinha, 0, 75, 'UTF-8') . '...';
            }
            $prefixo = $tipoSistema !== 'PORTAL' ? $this->nomeFormatadoSistema($tipoSistema) : $nomeModulo;
            $tituloFormatado = sprintf('[%s] %s', $prefixo, $primeiraLinha);
        }

        // 6. Consulta em Tempo Real ao Banco de Dados + Token de Sabedoria
        $sabedoriaBanco = $this->consultarSabedoriaBanco($relato, $slugDetectado, $nomeModulo);

        // 7. Geração do Dossiê Técnico Real com Prévia Exata da Solução
        $dossie = $this->gerarDossieTecnicoCompleto(
            $tipoSistema,
            $subtipoItem,
            $slugDetectado,
            $nomeModulo,
            $categoria,
            $criticidade,
            $texto,
            $relato,
            $filial,
            $cenarioEspecial,
            $sabedoriaBanco
        );

        $prazoDias = match ($criticidade) {
            'critica' => 1,
            'alta'    => 3,
            'baixa'   => 7,
            default   => 5,
        };
        $prazoEntrega = date('Y-m-d', strtotime("+{$prazoDias} days"));

        return [
            'titulo' => $tituloFormatado,
            'tipo_sistema' => $tipoSistema,
            'subtipo_item' => $subtipoItem,
            'modulo_slug' => $slugDetectado,
            'modulo_nome' => $nomeModulo,
            'categoria' => $categoria,
            'criticidade' => $criticidade,
            'prazo_dias' => $prazoDias,
            'prazo_entrega' => $prazoEntrega,
            'diagnostico_ia' => $dossie,
            'relato_formatado' => $relato,
            'chamados_similares' => $sabedoriaBanco['chamados_similares'] ?? [],
        ];
    }

    private function ehCenarioPortalClienteHierarquia(string $texto): bool
    {
        $temHierarquia = preg_match('/\b(hierarquia|gestor.*representante|representante.*cliente|clientes liberados|próprio cliente)\b/ui', $texto);
        $temDocsCliente = preg_match('/\b(boletos?|títulos?|notas?\s+fisc|pedidos?)\b/ui', $texto);
        $temAuth2FA = preg_match('/\b(2\s*etapas|2fa|two.?factor|recaptcha|cloudflare|turnstile|esqueceu\s+a\s+senha|redefiniç|solicitar\s+cadastro|login|cnpj)\b/ui', $texto);
        return ($temHierarquia && ($temDocsCliente || $temAuth2FA)) || ($temDocsCliente && $temAuth2FA);
    }

    /**
     * Resolve módulo por pontuação ponderada e garante nome amigável (nunca slug cru em modulo_nome).
     */
    private function detectarModuloPonderado(
        string $texto,
        string $tipoSistema,
        ?string $moduloInformado,
        array $modulos,
        bool $respeitarModulo,
        bool $ehArquiteturaPortalB2B
    ): array {
        $mapaNomes = [];
        foreach ($modulos as $m) {
            $mapaNomes[$m['slug']] = $m['nome'];
        }

        // Cenário explícito escolhido pelo usuário e validado
        if ($respeitarModulo && $moduloInformado !== null) {
            if ($moduloInformado === 'geral' && $ehArquiteturaPortalB2B) {
                return [
                    'slug' => 'geral',
                    'nome' => 'Portal do Cliente & Hierarquia Comercial (ERP / Auth 2FA)',
                    'cenario' => 'portal_cliente_hierarquia',
                ];
            }
            $nome = $mapaNomes[$moduloInformado] ?? ($moduloInformado === 'geral' ? 'Portal Geral' : $this->formatarNomeModuloFallback($moduloInformado));
            return [
                'slug' => $moduloInformado,
                'nome' => $nome,
                'cenario' => $ehArquiteturaPortalB2B ? 'portal_cliente_hierarquia' : $moduloInformado,
            ];
        }

        if ($tipoSistema !== 'PORTAL') {
            $slugSis = mb_strtolower($tipoSistema, 'UTF-8');
            return [
                'slug' => $slugSis,
                'nome' => $this->nomeFormatadoSistema($tipoSistema),
                'cenario' => $slugSis,
            ];
        }

        if ($ehArquiteturaPortalB2B) {
            return [
                'slug' => $moduloInformado ?: 'geral',
                'nome' => $moduloInformado && isset($mapaNomes[$moduloInformado])
                    ? $mapaNomes[$moduloInformado]
                    : 'Portal do Cliente & Hierarquia Comercial (ERP / Auth 2FA)',
                'cenario' => 'portal_cliente_hierarquia',
            ];
        }

        // Regras ponderadas por especificidade (evita que palavras genéricas como "gestor", "mercos" ou "saldo" caiam em verbas-flex)
        $regrasPonderadas = [
            'verbas-flex' => [
                'verba flex' => 14, 'verbas flex' => 14, 'saldo flex' => 12, 'bonificação flex' => 12,
                'desconto flex' => 10, 'flex mínimo' => 10, 'empréstimo flex' => 10, 'aporte flex' => 10,
                'verificabonificacao' => 12, 'log_flex_bonificacao' => 12,
            ],
            'movimentacao-flex' => [
                'movimentação de flex' => 15, 'movimentacao-flex' => 15, 'extrato flex' => 12,
                'lançamento flex' => 10, 'débito manual flex' => 12,
            ],
            'transferencia-flex' => [
                'transferência de flex' => 15, 'transferencia-flex' => 15, 'transferir flex' => 14, 'ceder flex' => 14,
            ],
            'desembarque' => [
                'desembarque' => 14, 'bipar oc' => 12, 'bipagem no coletor' => 10, 'aps_oc_conferencia' => 14,
                'ordem de compra no coletor' => 10, 'endereçamento de recebimento' => 10,
            ],
            'xml' => [
                'conferência xml' => 15, 'importação xml' => 14, 'nfe fornecedor' => 12, 'xml da nota' => 12,
                'chave de acesso' => 8, 'divergência entre oc e xml' => 14, 'ordem de compra vs xml' => 14,
            ],
            'avaliacao-chapd' => [
                'chapd' => 15, 'c.h.a.p.d' => 15, 'avaliação de desempenho' => 13, 'ficha chapd' => 14,
            ],
            'plano-acao-chapd' => [
                'pdi' => 14, 'plano de ação' => 14, '5w2h' => 15,
            ],
            'chamados' => [
                'central de chamados' => 15, 'abrir chamado' => 14, 'abertura de chamado' => 14, 'triagem ia' => 13,
                'dossiê técnico' => 10, 'kanban de chamados' => 14, 'chamado' => 7,
            ],
            'financeiro' => [
                'boleto' => 9, 'duplicata' => 10, 'contas a receber' => 11, 'metas financeiras' => 12,
                'inadimplência' => 11, 'rate limit' => 10, '429' => 10, 'iag' => 10, 'iap' => 10,
            ],
            'analise_carteira' => [
                'análise de carteira' => 15, 'títulos vencidos' => 12, 'cobrança de títulos' => 11,
            ],
            'limite-credito' => [
                'limite de crédito' => 15, 'análise de crédito' => 13, 'serasa' => 10,
            ],
            'separacao' => [
                'separação' => 12, 'separador' => 12, 'wms' => 9, 'estorno de separação' => 14,
            ],
            'carregamento' => [
                'carregamento' => 13, 'doca' => 10, 'conferência de carga' => 13,
            ],
            'frota-embarques' => [
                'embarque' => 10, 'romaneio' => 11, 'motorista' => 8, 'rota de entrega' => 11,
            ],
            'acerto-embarque' => [
                'acerto de embarque' => 15, 'prestação de contas motorista' => 13,
            ],
            'retorno-carga' => [
                'retorno de carga' => 15, 'retorno de item' => 14, 'redirecionamento de produtos' => 14, 'custo gerencial' => 12,
            ],
            'estoque-previsao' => [
                'estoque com previsão' => 15, 'previsão de chegada' => 13, 'estoque futuro' => 12, 'visualização de estoque' => 12, 'saldo de estoque' => 10,
            ],
            'transferencia-matriz-filial' => [
                'matriz para filial' => 15, 'transferência de estoque' => 11, 'guia st' => 12, 'nf_transf_auto' => 14,
            ],
            'transferencia-filial-matriz' => [
                'filial para matriz' => 15,
            ],
            'marketing-clientes' => [
                'crm clientes' => 15, 'carteira crm' => 13, 'agendamento de visita' => 12, 'prospecção' => 10,
            ],
            'marketing-relatorios' => [
                'relatórios mkt' => 15, 'relatórios do marketing' => 15, 'marketing' => 7,
            ],
            'cubo_vendas' => [
                'cubo de vendas' => 15, 'cubo vendas' => 15, 'olap' => 12,
            ],
            'dashboard_vendas' => [
                'dashboard vendas' => 15, 'ticket médio' => 10,
            ],
            'recompra_clientes' => [
                'recompra' => 13, 'clientes sem compras' => 14,
            ],
            'pedido-amostra' => [
                'material de apoio' => 14, 'amostras' => 12, 'pedido de amostra' => 14,
            ],
            'migracao-carteira' => [
                'migração de carteira' => 15,
            ],
            'fluxo-ecomerce' => [
                'e-commerce' => 12, 'ecommerce' => 12, 'fluxo ecommerce' => 14,
            ],
            'admin' => [
                'permissões do usuário' => 11, 'cadastro de usuário' => 10, 'cofre de segredos' => 13,
            ],
        ];

        $pontuacoes = [];
        foreach ($regrasPonderadas as $slug => $termos) {
            $pts = 0;
            foreach ($termos as $termo => $peso) {
                if (str_contains($texto, $termo)) {
                    $pts += $peso;
                }
            }
            if ($pts > 0) {
                $pontuacoes[$slug] = $pts;
            }
        }

        // Pontua também coincidência direta com o nome oficial de qualquer módulo ativo
        foreach ($modulos as $m) {
            $slug = (string)$m['slug'];
            $nomeLower = mb_strtolower((string)$m['nome'], 'UTF-8');
            if ($nomeLower !== '' && str_contains($texto, $nomeLower)) {
                $pontuacoes[$slug] = ($pontuacoes[$slug] ?? 0) + 12;
            }
        }

        if ($moduloInformado !== null && $moduloInformado !== 'geral') {
            $pontuacoes[$moduloInformado] = ($pontuacoes[$moduloInformado] ?? 0) + 15;
        }

        arsort($pontuacoes);
        $melhorSlug = array_key_first($pontuacoes);
        $melhorPontos = $melhorSlug !== null ? $pontuacoes[$melhorSlug] : 0;

        if ($melhorSlug !== null && $melhorPontos >= 8) {
            return [
                'slug' => $melhorSlug,
                'nome' => $mapaNomes[$melhorSlug] ?? $this->formatarNomeModuloFallback($melhorSlug),
                'cenario' => $melhorSlug,
            ];
        }

        $slugFinal = $moduloInformado ?: 'geral';
        $nomeFinal = $mapaNomes[$slugFinal] ?? ($slugFinal === 'geral' ? 'Portal Geral / Arquitetura' : $this->formatarNomeModuloFallback($slugFinal));

        // Detecta cenários genéricos úteis mesmo em Portal Geral (ex: fixar cabeçalho de relatório, automação de relatórios)
        $cenario = $slugFinal;
        if (preg_match('/\b(fixar|congelar|sticky|cabeçalho|topo da tabela|topo do relatório)\b/ui', $texto)) {
            $cenario = 'ui_sticky_relatorio';
        } elseif (preg_match('/\b(relatório.*automátic|gerar automátic|enviar para e-?mail|whatsapp|teams)\b/ui', $texto)) {
            $cenario = 'automacao_relatorios';
        }

        return [
            'slug' => $slugFinal,
            'nome' => $nomeFinal,
            'cenario' => $cenario,
        ];
    }

    private function formatarNomeModuloFallback(string $slug): string
    {
        $limpo = str_replace(['-', '_'], ' ', trim($slug));
        return mb_convert_case($limpo, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Classifica categoria sem assumir 'bug' como padrão cego quando se trata de melhoria/novo recurso/permissão.
     */
    private function detectarCategoriaPonderada(string $texto, string $tipoSistema, bool $ehArquiteturaPortalB2B): string
    {
        if ($tipoSistema === 'EQUIPAMENTO') {
            return 'equipamento';
        }

        $scores = [
            'bug' => 0,
            'melhoria' => 0,
            'permissao' => 0,
            'duvida' => 0,
        ];

        $padroesBug = [
            '/\b(erro|erros|falha|falhou|bug|travou|parou|quebrou|não abre|não carrega|não salva|não envia|não aparece|não funciona|sqlstate|exception|500|404|409|429|duplicou|divergência|em branco|fechando sozinho|ao clicar fora)\b/ui' => 10,
            '/\b(incorreto|errado|zerado|sumiu|travando)\b/ui' => 6,
        ];
        $padroesMelhoria = [
            '/\b(fazer com que|implementar|criar tela|novo módulo|nova tela|novo portal|ter como ver|ter a opção|opção de solicitar|gostaria de|melhorar|melhoria|ajuste|ajustar|fixar o topo|fixar cabeçalho|gerar automático|relatório automático|permitir que|adicionar|incluir|integrar|layout|com base no erp)\b/ui' => 10,
            '/\b(seria bom|sugestão|padronizar|exportar|reprocessar)\b/ui' => 7,
        ];
        $padroesPermissao = [
            '/\b(liberar acesso|permissão|sem acesso|desbloquear usuário|acesso negado|403|solicitar cadastro|perfil de acesso)\b/ui' => 9,
            '/\b(hierarquia|2 etapas|2fa|cloudflare|recaptcha|esqueceu a senha)\b/ui' => 5,
        ];
        $padroesDuvida = [
            '/\b(como faço|como funciona|onde fica|qual o procedimento|manual|treinamento|dúvida)\b/ui' => 12,
        ];

        foreach ($padroesBug as $rx => $peso) {
            if (preg_match_all($rx, $texto, $m)) {
                $scores['bug'] += count($m[0]) * $peso;
            }
        }
        foreach ($padroesMelhoria as $rx => $peso) {
            if (preg_match_all($rx, $texto, $m)) {
                $scores['melhoria'] += count($m[0]) * $peso;
            }
        }
        foreach ($padroesPermissao as $rx => $peso) {
            if (preg_match_all($rx, $texto, $m)) {
                $scores['permissao'] += count($m[0]) * $peso;
            }
        }
        foreach ($padroesDuvida as $rx => $peso) {
            if (preg_match_all($rx, $texto, $m)) {
                $scores['duvida'] += count($m[0]) * $peso;
            }
        }

        if ($ehArquiteturaPortalB2B && $scores['bug'] === 0) {
            $scores['melhoria'] += 8;
        }

        arsort($scores);
        $top = array_key_first($scores);
        if (($scores[$top] ?? 0) === 0) {
            return 'melhoria';
        }

        return (string)$top;
    }

    private function detectarCriticidade(string $texto): string
    {
        if (preg_match('/\b(travou|parou|bloqueado|não fatura|impossível|erro 500|429|urgente|paralisado|queda|queimou|sem internet|sem sinal|parado)\b/ui', $texto)) {
            return 'critica';
        }
        if (preg_match('/\b(divergência|valor errado|saldo incorreto|não atualizou|duplicou|falhou|não imprime|falhando|sem ramal|2 etapas|2fa|segurança|boletos|notas|hierarquia)\b/ui', $texto)) {
            return 'alta';
        }
        if (preg_match('/\b(layout|cor|texto|fixar|ajuste visual|espaçamento|icone|ícone|alinhamento)\b/ui', $texto)) {
            return 'baixa';
        }
        return 'media';
    }

    /**
     * Consulta em tempo real banco de dados: manual do módulo, chamados similares e entidades citadas no texto.
     */
    private function consultarSabedoriaBanco(string $relato, string $slug, string $nomeModulo): array
    {
        $res = [
            'manual' => null,
            'chamados_similares' => [],
            'registros_citados' => [],
        ];

        try {
            // 1. Manual técnico cadastrado em sistema_manuais
            if ($slug !== '' && $slug !== 'geral') {
                $stMan = $this->pdo->prepare("
                    SELECT modulo_slug, titulo, versao, objetivo_geral
                    FROM sistema_manuais
                    WHERE modulo_slug = :slug
                    LIMIT 1
                ");
                $stMan->execute(['slug' => $slug]);
                $man = $stMan->fetch(PDO::FETCH_ASSOC);
                if ($man) {
                    $res['manual'] = $man;
                }
            }

            // 2. Até 3 chamados similares já existentes no banco para alinhamento histórico
            $termos = [];
            if (preg_match_all('/\b[a-zA-Záàâãéèêíïóôõöúçñ]{5,}\b/u', mb_strtolower($relato, 'UTF-8'), $matches)) {
                $stopWords = ['sobre', 'com base', 'fazer', 'exemplo', 'apenas', 'como', 'para', 'pelo', 'pela', 'seus', 'suas', 'entre', 'outros', 'além', 'também', 'nesse', 'nesta', 'estao', 'estão', 'agora', 'quero', 'gostaria', 'sistema', 'modulo', 'módulo', 'dados', 'banco', 'todos', 'todas', 'gerar', 'meios', 'melhor', 'falamos', 'conforme', 'disposição', 'dúvidas', 'enviar', 'outro', 'outra'];
                foreach ($matches[0] as $palavra) {
                    if (!in_array($palavra, $stopWords, true) && !in_array($palavra, $termos, true)) {
                        $termos[] = $palavra;
                    }
                    if (count($termos) >= 5) {
                        break;
                    }
                }
            }

            $conds = [];
            $params = [];
            $orderSlugCase = '';
            if ($slug !== '' && $slug !== 'geral') {
                $conds[] = "modulo_slug = :slug";
                $params['slug'] = $slug;
                $orderSlugCase = "CASE WHEN modulo_slug = :slug_ord THEN 0 ELSE 1 END, ";
                $params['slug_ord'] = $slug;
            }
            foreach ($termos as $idx => $t) {
                $k = "kw_{$idx}";
                $conds[] = "(titulo ILIKE :{$k} OR motivo ILIKE :{$k}_m)";
                $params[$k] = '%' . $t . '%';
                $params["{$k}_m"] = '%' . $t . '%';
            }

            if (!empty($conds)) {
                $sqlSim = "
                    SELECT idchamado, titulo, status, categoria, modulo_nome, COALESCE(versao_entrega, '') AS versao_entrega
                    FROM chamado
                    WHERE " . implode(' OR ', $conds) . "
                    ORDER BY {$orderSlugCase}CASE WHEN status = 3 THEN 0 ELSE 1 END, idchamado DESC
                    LIMIT 3
                ";
                $stSim = $this->pdo->prepare($sqlSim);
                $stSim->execute($params);
                $res['chamados_similares'] = $stSim->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            // 3. Inspeção rápida de IDs citados no relato (OC, Pedido, Cliente, Chamado)
            if (preg_match('/\b(?:oc|ordem(?:\s+de\s+compra)?)\s*#?\s*(\d{3,6})\b/ui', $relato, $mOc)) {
                $idoc = (int)$mOc[1];
                $stOc = $this->pdo->prepare("SELECT oc.idoc, oc.status, oc.valortotal, f.razao FROM oc LEFT JOIN cliforemp f ON f.idcliforemp = oc.idcliforemp WHERE oc.idoc = :id");
                $stOc->execute(['id' => $idoc]);
                if ($rowOc = $stOc->fetch(PDO::FETCH_ASSOC)) {
                    $res['registros_citados'][] = "OC #{$rowOc['idoc']}: {$rowOc['razao']} | Valor R$ {$rowOc['valortotal']} | Status ERP {$rowOc['status']}";
                }
            }
            if (preg_match('/\bpedido\s*#?\s*(\d{4,8})\b/ui', $relato, $mPed)) {
                $idped = (int)$mPed[1];
                $stPed = $this->pdo->prepare("SELECT p.idpedido, p.status, p.valortotal, c.razao FROM pedido p LEFT JOIN cliforemp c ON c.idcliforemp = p.idcliforemp WHERE p.idpedido = :id");
                $stPed->execute(['id' => $idped]);
                if ($rowPed = $stPed->fetch(PDO::FETCH_ASSOC)) {
                    $res['registros_citados'][] = "Pedido #{$rowPed['idpedido']}: {$rowPed['razao']} | Total R$ {$rowPed['valortotal']} | Status {$rowPed['status']}";
                }
            }
            if (preg_match('/\bcliente\s*#?\s*(\d{2,7})\b/ui', $relato, $mCli)) {
                $idcli = (int)$mCli[1];
                $stCli = $this->pdo->prepare("SELECT idcliforemp, razao, cnpj FROM cliforemp WHERE idcliforemp = :id");
                $stCli->execute(['id' => $idcli]);
                if ($rowCli = $stCli->fetch(PDO::FETCH_ASSOC)) {
                    $res['registros_citados'][] = "Cliente #{$rowCli['idcliforemp']}: {$rowCli['razao']} (Doc: {$rowCli['cnpj']})";
                }
            }
        } catch (\Throwable $e) {
            // Consulta auxiliar não bloqueia a triagem
        }

        return $res;
    }

    private function nomeFormatadoSistema(string $sis): string
    {
        $map = [
            'PORTAL' => 'Portal Nutricional',
            'ERP_EMA' => 'ERP EMA',
            'MERCOS' => 'Mercos',
            'COBLI' => 'Cobli Rastreamento',
            'LOGISOFT' => 'Logisoft CT-e/MDF-e',
            'EMAIL' => 'E-mail Corporativo',
            'SITE' => 'Site & Portal B2B',
            'EQUIPAMENTO' => 'Equipamentos & Hardware',
            'PLANILHAS' => 'Planilhas Excel',
            'SERVIDOR' => 'Rede & Servidores',
            'OUTROS' => 'Outros',
        ];
        return $map[$sis] ?? $sis;
    }

    /**
     * Monta o Dossiê Técnico Sênior com Escopo Revisado, Ecossistema Impactado, Prévia Exata da Solução e Contexto de Banco.
     */
    private function gerarDossieTecnicoCompleto(
        string $sistema,
        string $subtipo,
        string $slug,
        string $nomeModulo,
        string $categoria,
        string $criticidade,
        string $textoLower,
        string $relatoRevisado,
        int $filial,
        string $cenarioEspecial,
        array $sabedoriaBanco
    ): string {
        $filialLabel = $filial === 6 ? '6 - Paraná PR' : ($filial === 1 ? '1 - Matriz SC' : "Filial {$filial}");
        $labelsCat = [
            'bug' => 'Bug / Erro',
            'melhoria' => 'Melhoria / Novo Recurso',
            'permissao' => 'Permissão / Hierarquia & Acesso',
            'duvida' => 'Dúvida / Orientação',
            'equipamento' => 'Equipamento / Infraestrutura',
        ];
        $catLabel = $labelsCat[$categoria] ?? strtoupper($categoria);

        $dossie  = "[IA] DOSSIÊ TÉCNICO DE ENGENHARIA & PRÉVIA DA SOLUÇÃO\n";
        $dossie .= "====================================================================\n";
        $dossie .= "1. TRADUÇÃO TÉCNICA & CLASSIFICAÇÃO (ORTOGRAFIA REVISADA)\n";
        $dossie .= "--------------------------------------------------------------------\n";
        $dossie .= "• Sistema / Origem: " . $this->nomeFormatadoSistema($sistema) . " ({$subtipo})\n";
        $dossie .= "• Módulo / Domínio: {$nomeModulo} [slug: {$slug}]\n";
        $prazoDias = match ($criticidade) {
            'critica' => 1,
            'alta'    => 3,
            'baixa'   => 7,
            default   => 5,
        };
        $prazoDataBr = date('d/m/Y', strtotime("+{$prazoDias} days"));
        $dossie .= "• Categoria / Criticidade: {$catLabel} | Criticidade: " . strtoupper($criticidade) . " | Prazo SLA: {$prazoDias} dia(s) (até {$prazoDataBr})\n";
        $dossie .= "• Filial Base: {$filialLabel}\n";
        $dossie .= "• Escopo Revisado: " . mb_substr($relatoRevisado, 0, 260, 'UTF-8') . (mb_strlen($relatoRevisado, 'UTF-8') > 260 ? '...' : '') . "\n\n";

        $dossie .= "2. ECOSSISTEMA, ARQUIVOS & TABELAS ENVOLVIDAS\n";
        $dossie .= "--------------------------------------------------------------------\n";
        $dossie .= $this->blocoArquiteturaEcossistema($sistema, $subtipo, $slug, $cenarioEspecial, $textoLower);

        $dossie .= "\n3. PRÉVIA DA SOLUÇÃO TÉCNICA (ROTEIRO RECOMENDADO DE IMPLEMENTAÇÃO)\n";
        $dossie .= "--------------------------------------------------------------------\n";
        $dossie .= $this->blocoPreviaSolucao($sistema, $subtipo, $slug, $cenarioEspecial, $textoLower);

        $dossie .= "\n4. ALINHAMENTO COM O BANCO & HISTÓRICO RECENTE\n";
        $dossie .= "--------------------------------------------------------------------\n";
        if (!empty($sabedoriaBanco['manual'])) {
            $m = $sabedoriaBanco['manual'];
            $dossie .= "• Manual Oficial: {$m['titulo']} (v{$m['versao']}) — {$m['objetivo_geral']}\n";
        } else {
            $dossie .= "• Manual: sem manual específico vinculado ao slug '{$slug}' (criar/atualizar em sistema_manuais na entrega).\n";
        }

        if (!empty($sabedoriaBanco['registros_citados'])) {
            foreach ($sabedoriaBanco['registros_citados'] as $reg) {
                $dossie .= "• Registro Citado Verificado: {$reg}\n";
            }
        }

        $mapaSt = [0 => 'Aberto', 1 => 'Em Análise', 2 => 'Em Correção', 3 => 'Concluído', 4 => 'Cancelado'];
        if (!empty($sabedoriaBanco['chamados_similares'])) {
            $dossie .= "• Chamados Correlatos no Banco:\n";
            foreach ($sabedoriaBanco['chamados_similares'] as $sim) {
                $stTxt = $mapaSt[(int)$sim['status']] ?? 'Aberto';
                $verTxt = !empty($sim['versao_entrega']) ? " ({$sim['versao_entrega']})" : '';
                $dossie .= "  - #{$sim['idchamado']} [{$stTxt}{$verTxt}] {$sim['titulo']}\n";
            }
        } else {
            $dossie .= "• Histórico: sem chamados correlatos anteriores com o mesmo escopo.\n";
        }

        return $dossie;
    }

    private function blocoArquiteturaEcossistema(string $sistema, string $subtipo, string $slug, string $cenario, string $texto): string
    {
        if ($cenario === 'portal_cliente_hierarquia') {
            return "• Backend Auth & Segurança: v1/src/Controllers/AuthController.php, Meu2faController.php, v1/src/Services/Totp.php, SecretVault.php\n"
                 . "• Backend ERP / Cliente / Pedidos / Financeiro: v1/src/Controllers/ClientePedidosController.php, FinanceiroController.php, AnaliseCarteiraController.php\n"
                 . "• Rotas & Frontend: v1/routes/api.php | Novo módulo ou portal B2B em portal/modules/\n"
                 . "• Tabelas PostgreSQL (ERP EMA):\n"
                 . "  - Cadastro & Hierarquia: cliforemp (idcliforemp, cnpj, cpf, email, razao, idvendedor, inativo), usuario, usuario_site, usuarios_mercos\n"
                 . "  - Títulos & Boletos em Aberto: receber (idreceber, idcliforemp, idvendrepre, valorsaldo, vencimento, status, nossonumero, excluido) + receber_boleto (idboleto, codigobarra, shipayqrcode)\n"
                 . "  - Notas Fiscais & Pedidos: nfs + nfs_item (notas emitidas) e pedido + pedido_item (status e acompanhamento)\n"
                 . "  - Segurança 2FA & Cadastro: usuario_seguranca, api_tokens, envio SMTP via PHPMailer + SecretVault::get('MAIL_PASS')\n";
        }

        if ($sistema === 'EQUIPAMENTO') {
            return "• Escopo Físico / Infraestrutura: {$subtipo}\n"
                 . "• Pontos de Checagem: rede local, spooler/serviço VoIP/coletor, patrimônio e ponto de rede da filial.\n";
        }

        if ($sistema === 'COBLI') {
            return "• Backend Frota & Cobli: v1/src/Controllers/Frota/CobliController.php, RastreamentoController.php, v1/src/Services/Frota/CobliService.php\n"
                 . "• Tabelas PostgreSQL: frota_cobli_dispositivo, frota_cobli_posicao, frota_cobli_evento_risco, frota_cobli_log_api, frota_veiculo, frota_motorista\n";
        }

        if ($sistema === 'LOGISOFT') {
            return "• Integração Fiscal Logisoft: emissão/transmissão CT-e, MDF-e e CIOT vinculados a frota_embarque / frota_entrega\n"
                 . "• Pontos Fiscais: Certificado Digital A1 da filial, Web Services SEFAZ e chaves NF-e referenciadas em crm_processo_xml / nfs\n";
        }

        if ($sistema === 'ERP_EMA' || $sistema === 'MERCOS') {
            return "• Integrador Mercos / ERP: ema-mercos-integration.exe (porta 8081), Firebird DE_PARA_MERCOS.FDB (porta 3050), v1/src/Services/MercosFlexService.php\n"
                 . "• Tabelas ERP Legadas: cliforemp, pedido, pedido_item, palmtop_pedido, palmtop_pedido_item, estoque_filial, estoque_filial_deposito (iddeposito = 1)\n";
        }

        switch ($slug) {
            case 'verbas-flex':
            case 'movimentacao-flex':
            case 'transferencia-flex':
                return "• Backend: VerbasFlexController.php, FlexMovimentacaoController.php, FlexTransferenciaController.php, MercosFlexService.php (cache 20s / anti-burst 3s)\n"
                     . "• Frontend: portal/modules/{$slug}.php & portal/assets/js/{$slug}.js\n"
                     . "• Tabelas: saldo_flex, log_flex_bonificacao, log_debito_flex_gestor, flex_minimo_config, usuarios_mercos, view verificabonificacao\n";

            case 'desembarque':
            case 'xml':
                return "• Backend: DesembarqueController.php & XmlController.php (trava obrigatória conferido_portal = 'S' antes do desembarque)\n"
                     . "• Frontend: portal/modules/desembarque.php & portal/modules/conferencia_xml.php (+ desembarque.js / xml.js)\n"
                     . "• Tabelas: crm_processo_xml, oc, oc_item, oc_item_lote, aps_oc_conferencia, aps_oc_conferencia_item, aps_oc_conferencia_item_lote, lote_endereco, oc_operacao\n";

            case 'financeiro':
            case 'analise_carteira':
            case 'limite-credito':
                return "• Backend: FinanceiroController.php, AnaliseCarteiraController.php, FinanceiroLimiteCreditoController.php, RateLimitMiddleware.php\n"
                     . "• Tabelas: receber, receber_boleto, receber_baixa, analytics_receber, metas_financeiro, cliforemp, rate_limit\n";

            case 'separacao':
            case 'carregamento':
            case 'frota-embarques':
            case 'acerto-embarque':
            case 'retorno-carga':
                return "• Backend: SeparacaoController.php, CarregamentoController.php, RetornoController.php, Frota/EmbarqueController.php, Frota/AcertoEmbarqueController.php\n"
                     . "• Tabelas: pedido, pedido_item (valorcustogerencial), embarque_separacao*, embarque_pedido*, frota_embarque, frota_acerto_*\n";

            case 'estoque-previsao':
                return "• Backend & Frontend: v1/src/Controllers/EstoquePrevisaoController.php | portal/modules/estoque-previsao.php & portal/assets/js/estoque-previsao.js\n"
                     . "• Tabelas: estoque_filial (saldoatual, custogerencial), estoque_filial_reserva, oc + oc_item, nf_transf_auto (status = 2)\n";

            case 'chamados':
                return "• Backend: ChamadosController.php & ChamadosIaService.php\n"
                     . "• Frontend: portal/modules/chamados.php & portal/assets/js/chamados.js\n"
                     . "• Tabelas: chamado, chamado_operacao, sistema_modulos, sistema_manuais, notificacoes\n";

            case 'marketing-relatorios':
                return "• Backend: MarketingController.php, CronRotinasController.php, CronController.php, WhatsAppService.php\n"
                     . "• Frontend: portal/modules/marketing/relatorios.php (ou módulo correlato)\n"
                     . "• Tabelas: cron_jobs, mkt_*, usuario (dash_filiais)\n";

            default:
                return "• API & Frontend: v1/routes/api.php + controller/módulo correspondente a '{$slug}' em v1/src/Controllers/ e portal/modules/\n"
                     . "• Base de Apoio: docs/tokens/KNOWLEDGE-BASE-ECOSSISTEMA-NUTRICIONAL.md e logs em v1/logs/\n";
        }
    }

    private function blocoPreviaSolucao(string $sistema, string $subtipo, string $slug, string $cenario, string $texto): string
    {
        if ($cenario === 'portal_cliente_hierarquia') {
            return "  1. Autenticação B2B Unificada (E-mail ou CNPJ) + Proteção Cloudflare Turnstile:\n"
                 . "     - Endpoint de login aceita CPF/CNPJ (somente dígitos) ou e-mail contra cliforemp + usuario_site.\n"
                 . "     - Validar obrigatoriamente o token 'cf-turnstile-response' no backend via siteverify da Cloudflare (chave no SecretVault).\n"
                 . "  2. Autenticação em 2 Etapas (Código por E-mail) + Solicitação de Cadastro / Recuperação de Senha:\n"
                 . "     - Gerar código OTP de 6 dígitos com validade (10 min) e limite de tentativas, enviando via PHPMailer ao e-mail cadastrado.\n"
                 . "     - Adicionar rotas públicas (protegidas por Turnstile) para 'Solicitar Cadastro' (pré-vínculo por CNPJ) e 'Esqueceu a Senha' (link/token temporário).\n"
                 . "  3. Middleware / Filtro Hierárquico no Banco (RBAC no Backend):\n"
                 . "     - Perfil GESTOR: escolhe o Representante ou visualiza a carteira liberada em cliforemp.\n"
                 . "     - Perfil REPRESENTANTE: query força `cliforemp.idvendedor = :idRepLogado` exibindo apenas seus próprios clientes.\n"
                 . "     - Perfil CLIENTE: query trava `idcliforemp = :idCliLogado` em todas as consultas.\n"
                 . "  4. Consultas Seguras de Boletos, Notas e Pedidos:\n"
                 . "     - Boletos em aberto: SELECT em `receber` + `receber_boleto` WHERE `idcliforemp = :id AND status = 1 AND valorsaldo > 0 AND excluido = 'N'`.\n"
                 . "     - Notas Fiscais: SELECT em `nfs` (e `nfs_item`) emitidas para o cliente.\n"
                 . "     - Pedidos: SELECT em `pedido` + `pedido_item` com status, rastreio e previsão.\n";
        }

        if ($cenario === 'ui_sticky_relatorio' || preg_match('/\b(fixar|congelar|sticky|fixar o topo|topo do relatório)\b/ui', $texto)) {
            return "  1. No HTML/PHP da tabela/relatório de estoque (`estoque-previsao.php` / módulo alvo):\n"
                 . "     - Aplicar `position: sticky; top: 0; z-index: 20;` no `<thead>` e no bloco de cabeçalho/KPIs superiores.\n"
                 . "  2. Garantir que o container pai direto da tabela use altura controlada (`max-h-[70vh] overflow-auto`) sem `overflow-hidden` nos ancestrais (que anula CSS sticky).\n"
                 . "  3. Validar no Desktop e Mobile para evitar sobreposição com o menu lateral ou barra móvel.\n";
        }

        if ($cenario === 'automacao_relatorios' || (preg_match('/\b(automátic|agendad|rotina cron)\b/ui', $texto) && preg_match('/\b(relatório|e-?mail|whatsapp|teams|pdf)\b/ui', $texto))) {
            return "  1. Criar job agendado no motor de crons (`CronController.php` / `CronRotinasController.php` + tabela `cron_jobs`) consolidando todos os indicadores/relatórios de Marketing.\n"
                 . "  2. Integrar disparadores unificados em PDF/HTML/CSV: SMTP corporativo (`PHPMailer` + `SecretVault`), WhatsApp (`WhatsAppService.php`) e Webhook do Microsoft Teams.\n"
                 . "  3. Gravar log de execução e destinatários para auditoria e permitir disparo manual sob demanda no painel de Marketing.\n";
        }

        if ($cenario === 'estoque-previsao' || str_contains($texto, 'estoque')) {
            return "  1. Validar cálculo em `EstoquePrevisaoController.php`: `saldoatual` (`estoque_filial`), (-) reserva (`estoque_filial_reserva`), (+) compra (`oc` + `oc_item`), (+) transferência em trânsito (`nf_transf_auto.status = 2`).\n"
                 . "  2. Se a demanda for visualização fixa de cabeçalho/resumo, aplicar container com `overflow-y: auto` e `thead` com `sticky top-0 bg-white z-20`.\n";
        }

        if ($cenario === 'desembarque' || $cenario === 'xml') {
            return "  1. Conferir se a OC já concluiu a Conferência XML (`aps_oc_conferencia.conferido_portal = 'S'` ou operação `PORTAL` em `oc_operacao`).\n"
                 . "  2. Em divergência de lote após reconferência XML, verificar sincronização entre `oc_item`, `oc_item_lote` e `aps_oc_conferencia_item_lote`.\n"
                 . "  3. Se o coletor reportar item não encontrado, validar normalização de `QUERY_STRING` no gateway `index.php` e busca por ID/Referência/EAN em `DesembarqueController::buscarItem`.\n";
        }

        if ($sistema === 'EQUIPAMENTO') {
            if ($subtipo === 'Impressora') {
                return "  1. Validar conectividade IP/USB e fila de Spooler do Windows (`services.msc`).\n"
                     . "  2. Reinstalar driver/porta TCP-IP e testar página de teste com o usuário da estação.\n";
            }
            if ($subtipo === 'Telefonia / Ramal') {
                return "  1. Verificar status SIP/registro do ramal no PABX, cabo RJ45 e ping no IP do telefone.\n";
            }
            return "  1. Identificar patrimônio/estação da filial, testar energia/rede e registrar diagnóstico físico.\n";
        }

        if ($sistema === 'COBLI') {
            return "  1. Checar log de sincronização em `frota_cobli_log_api` e chave `COBLI_API_KEY` no `.env.api`.\n"
                 . "  2. Validar vínculo placa x `frota_cobli_dispositivo` e reprocessar posições em `CobliController.php`.\n";
        }

        if ($sistema === 'LOGISOFT') {
            return "  1. Validar serviço SEFAZ autorizadora, validade do Certificado A1 e retorno XML no Logisoft.\n"
                 . "  2. Conferir chaves de NF-e referenciadas em `nfs`/`crm_processo_xml` antes de retransmitir CT-e/MDF-e.\n";
        }

        return "  1. Inspecionar a rota correspondente em `v1/routes/api.php`, validar payload/permissões no controller do módulo '{$slug}' e revisar consultas no PostgreSQL.\n"
             . "  2. Reproduzir o cenário descrito, aplicar a correção/melhoria no backend + frontend Alpine.js, rodar `php -l` / `node --check` e registrar a entrega na timeline do chamado.\n";
    }

    /**
     * Chamada opcional à API Gemini alimentada com o Token de Sabedoria do Ecossistema e travas do formulário.
     */
    private function chamarGemini(
        string $relato,
        string $apiKey,
        ?string $moduloInformado,
        ?string $tipoSistemaInformado,
        array $opcoes,
        array $fallbackLocal
    ): array {
        try {
            $kb = $this->carregarKnowledgeBase();
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
            $prompt = "Você é Arquiteto de Software Sênior da Nutricional Distribuidora.\n"
                . "Base de Conhecimento Oficial do Ecossistema (use exatamente estes nomes de arquivos e tabelas):\n{$kb}\n\n"
                . "Instruções:\n"
                . "1. Corrija eventuais erros ortográficos no relato e devolva em 'relato_formatado'.\n"
                . "2. Classifique corretamente 'categoria' (bug|melhoria|permissao|duvida|equipamento) — NÃO classifique solicitação de nova funcionalidade/hierarquia/melhoria como bug.\n"
                . "3. Não classifique em 'verbas-flex' a menos que trate explicitamente de verbas/saldo flex.\n"
                . "4. Escreva 'diagnostico_ia' em português correto, técnico e prático contendo uma PRÉVIA REAL DA SOLUÇÃO (controllers, tabelas e passos exatos).\n"
                . "Responda APENAS em JSON válido: "
                . '{"titulo":"...", "modulo_slug":"...", "modulo_nome":"...", "categoria":"...", "criticidade":"critica|alta|media|baixa", "relato_formatado":"...", "diagnostico_ia":"..."}'
                . "\nContexto informado pelo usuário: sistema=" . ($tipoSistemaInformado ?: 'PORTAL')
                . ", modulo=" . ($moduloInformado ?: 'auto')
                . ", categoria=" . ($opcoes['categoria_informada'] ?? 'auto')
                . "\nRelato do usuário:\n" . $relato;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode([
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ])
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);

            $data = json_decode((string)$resp, true);
            $textoGerado = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (preg_match('/\{.*\}/s', $textoGerado, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed) && isset($parsed['titulo'])) {
                    // Respeita travas manuais do usuário
                    if (!empty($opcoes['respeitar_categoria']) && !empty($opcoes['categoria_informada'])) {
                        $parsed['categoria'] = $opcoes['categoria_informada'];
                    }
                    if (!empty($opcoes['respeitar_criticidade']) && !empty($opcoes['criticidade_informada'])) {
                        $parsed['criticidade'] = $opcoes['criticidade_informada'];
                    }
                    if (!empty($opcoes['respeitar_modulo']) && !empty($moduloInformado)) {
                        $parsed['modulo_slug'] = $fallbackLocal['modulo_slug'];
                        $parsed['modulo_nome'] = $fallbackLocal['modulo_nome'];
                    }
                    $parsed['tipo_sistema'] = $fallbackLocal['tipo_sistema'];
                    $parsed['subtipo_item'] = $fallbackLocal['subtipo_item'];
                    $parsed['relato_formatado'] = $this->corrigirOrtografia((string)($parsed['relato_formatado'] ?? $relato));
                    $parsed['chamados_similares'] = $fallbackLocal['chamados_similares'] ?? [];
                    return ['sucesso' => true, 'dados' => $parsed];
                }
            }
        } catch (\Throwable $e) {
            error_log('[IA Chamados] Falha Gemini API: ' . $e->getMessage());
        }
        return ['sucesso' => false];
    }
}
