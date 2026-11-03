<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Throwable;

class AvaliacaoChapdController
{
    /**
     * Obtém conexão PDO
     */
    private function getDb(): PDO
    {
        return getPDO();
    }

    /**
     * Retorna dados do usuário autenticado na sessão
     */
    private function getUsuarioSessao(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $usuario = $_SESSION['usuario'] ?? $_SESSION['user'] ?? null;
        $nome = 'Avaliador';
        $login = 'sistema';

        if (is_array($usuario)) {
            $nome = $usuario['nome'] ?? $usuario['name'] ?? $usuario['login'] ?? 'Avaliador';
            $login = $usuario['login'] ?? $usuario['username'] ?? 'sistema';
        } elseif (is_string($usuario) && !empty($usuario)) {
            $nome = $usuario;
            $login = $usuario;
        }

        return [
            'login' => $login,
            'nome' => $nome
        ];
    }

    /**
     * Resposta JSON padronizada
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }

    /**
     * GET /v1/avaliacao-chapd/contexto
     * Retorna colaboradores ativos do ERP, perguntas ativas, escala de notas e letras conceituais
     */
    public function contexto(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $user = $this->getUsuarioSessao();

            // 1. Colaboradores Ativos no ERP (cliforemp + empregado) com JOIN da área separada de perfil/fotos e Usuário Portal
            $sqlColab = "
                SELECT 
                    c.idcliforemp, 
                    COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) AS nome, 
                    TRIM(c.razao) AS nome_erp,
                    TRIM(COALESCE(c.fantasia, c.razao)) AS nome_fantasia, 
                    COALESCE(cp.idfilial_custom, c.idfilial) AS idfilial,
                    c.idfilial AS idfilial_erp,
                    COALESCE(NULLIF(TRIM(cp.cargo_custom), ''), TRIM(e.cargo), 'Não informado') AS cargo,
                    TRIM(COALESCE(e.cargo, 'Não informado')) AS cargo_erp,
                    COALESCE(NULLIF(TRIM(cp.setor_custom), ''), '') AS setor_custom,
                    COALESCE(NULLIF(TRIM(cp.celula_custom), ''), '') AS celula_custom,
                    COALESCE(TO_CHAR(cp.data_admissao_custom, 'YYYY-MM-DD'), TO_CHAR(e.data_admissao, 'YYYY-MM-DD')) AS data_admissao,
                    TO_CHAR(e.data_admissao, 'YYYY-MM-DD') AS data_admissao_erp,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url,
                    u.idusuario,
                    u.username AS usuario_portal,
                    cp.observacoes AS perfil_observacoes,
                    (cp.idcliforemp IS NOT NULL AND (cp.nome_custom IS NOT NULL OR cp.cargo_custom IS NOT NULL OR cp.setor_custom IS NOT NULL OR cp.celula_custom IS NOT NULL OR cp.idfilial_custom IS NOT NULL)) AS perfil_editado
                FROM cliforemp c
                JOIN empregado e ON e.idcliforemp = c.idcliforemp 
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = c.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = c.idcliforemp
                WHERE c.inativo = 'N' 
                ORDER BY COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) ASC
            ";
            $colaboradores = $db->query($sqlColab)->fetchAll(PDO::FETCH_ASSOC);

            // 2. Perguntas Ativas
            $sqlPerg = "
                SELECT id, codigo, pilar, pergunta, descricao_auxiliar, peso, ordem 
                FROM avaliacao_chapd_perguntas 
                WHERE ativo = TRUE 
                ORDER BY ordem ASC, id ASC
            ";
            $perguntas = $db->query($sqlPerg)->fetchAll(PDO::FETCH_ASSOC);

            // 3. Escala padrão de pontuação (conforme planilha)
            $escalaPontos = [
                ['valor' => 10, 'label' => 'Sempre', 'cor' => 'emerald', 'descricao' => 'Desempenho exemplar e constante'],
                ['valor' => 8,  'label' => 'Frequentemente', 'cor' => 'blue', 'descricao' => 'Atende na grande maioria dos casos'],
                ['valor' => 6,  'label' => 'Às Vezes', 'cor' => 'amber', 'descricao' => 'Comportamento irregular ou pontual'],
                ['valor' => 4,  'label' => 'Raramente ou Quase Nunca', 'cor' => 'orange', 'descricao' => 'Dificuldade evidente na prática'],
                ['valor' => 2,  'label' => 'Nunca', 'cor' => 'rose', 'descricao' => 'Inexistente ou prejudicial']
            ];

            // 4. Letras Conceituais (conforme planilha oficial)
            $conceitosLetras = [
                [
                    'grupo' => 'Características construtivas (Diferencial)',
                    'tipo' => 'diferencial',
                    'itens' => [
                        [
                            'letra' => 'A',
                            'titulo' => 'Altamente Engajado',
                            'descricao' => 'Altamente engajado e competente nas metas e tarefas.'
                        ],
                        [
                            'letra' => 'B',
                            'titulo' => 'Excelente Desempenho',
                            'descricao' => 'Excelente desempenho, busca aperfeiçoamento e assume responsabilidades.'
                        ]
                    ]
                ],
                [
                    'grupo' => 'Características com potencial de desenvolvimento',
                    'tipo' => 'potencial',
                    'itens' => [
                        [
                            'letra' => 'C',
                            'titulo' => 'Médio Potencial',
                            'descricao' => 'Médio potencial, falta desenvolver proatividade mas mostra-se disposto a crescimento pessoal e profissional.'
                        ],
                        [
                            'letra' => 'D',
                            'titulo' => 'Executor de Rotina',
                            'descricao' => 'Limita-se às tarefas de rotinas, mas busca atender às expectativas em novos desafios.'
                        ]
                    ]
                ],
                [
                    'grupo' => 'Características que não agregam valor e não contribuem',
                    'tipo' => 'critico',
                    'itens' => [
                        [
                            'letra' => 'E',
                            'titulo' => 'Baixo Desempenho Decisório',
                            'descricao' => 'Baixo desempenho, tomadas de decisões e aperfeiçoamento não fazem parte do perfil.'
                        ],
                        [
                            'letra' => 'F',
                            'titulo' => 'Falta Dinamismo',
                            'descricao' => 'Baixo desempenho, falta dinamismo e proatividade.'
                        ]
                    ]
                ]
            ];

            // 5. Meses do Ano
            $meses = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
            ];

            return $this->json($response, [
                'sucesso' => true,
                'usuario' => $user,
                'ano_atual' => (int)date('Y'),
                'mes_atual' => (int)date('n'),
                'meses' => $meses,
                'colaboradores' => $colaboradores,
                'perguntas' => $perguntas,
                'escala_pontos' => $escalaPontos,
                'conceitos_letras' => $conceitosLetras,
                'conjuntos_letras' => [
                    'AB' => [
                        'codigo' => 'AB',
                        'nome' => 'Conjunto A & B (Soma Máxima)',
                        'rotulo' => 'A & B',
                        'faixa' => 'Soma Máxima (115 a 140 pts)',
                        'min' => 115,
                        'max' => 140,
                        'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                        'cor' => 'emerald',
                        'descricao' => 'Diferencial Construtivo: Alta performance, engajamento com metas, responsabilidade e busca por aperfeiçoamento contínuo.',
                        'letras' => ['A', 'B']
                    ],
                    'CD' => [
                        'codigo' => 'CD',
                        'nome' => 'Conjunto C & D (Meio dos Pontos)',
                        'rotulo' => 'C & D',
                        'faixa' => 'Meio dos Pontos (85 a 114.9 pts)',
                        'min' => 85,
                        'max' => 114.99,
                        'badge' => 'bg-blue-100 text-blue-800 border-blue-300',
                        'cor' => 'blue',
                        'descricao' => 'Potencial de Desenvolvimento: Executor sólido de rotinas, boa capacidade de entrega com espaço para desenvolver proatividade.',
                        'letras' => ['C', 'D']
                    ],
                    'EF' => [
                        'codigo' => 'EF',
                        'nome' => 'Conjunto E & F (Últimos Abaixo)',
                        'rotulo' => 'E & F',
                        'faixa' => 'Abaixo de 85 pts (Atenção Crítica)',
                        'min' => 0,
                        'max' => 84.99,
                        'badge' => 'bg-rose-100 text-rose-800 border-rose-300',
                        'cor' => 'rose',
                        'descricao' => 'Pontos de Atenção / PDI: Desempenho insuficiente, falta de dinamismo ou decisões, exigindo intervenção com plano de ação corretivo.',
                        'letras' => ['E', 'F']
                    ]
                ],
                'faixas_classificacao' => [
                    ['min' => 115, 'max' => 140,    'conjunto' => 'AB', 'nome' => 'Excelente (Conjunto A & B)', 'classe' => 'bg-emerald-500 text-white'],
                    ['min' => 85,  'max' => 114.99, 'conjunto' => 'CD', 'nome' => 'Médio Potencial (Conjunto C & D)', 'classe' => 'bg-blue-500 text-white'],
                    ['min' => 0,   'max' => 84.99,  'conjunto' => 'EF', 'nome' => 'Baixo Potencial / Atenção PDI (Conjunto E & F)', 'classe' => 'bg-rose-500 text-white']
                ]
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar contexto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/dashboard
     * People Analytics e KPIs de desempenho
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $request->getQueryParams();

            $ano = !empty($params['ano']) ? (int)$params['ano'] : (int)date('Y');
            $mes = !empty($params['mes']) ? (int)$params['mes'] : null;
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            $where = ['a.ano = :ano'];
            $binds = [':ano' => $ano];

            if ($mes) {
                $where[] = 'a.mes = :mes';
                $binds[':mes'] = $mes;
            }
            if ($filial) {
                $where[] = 'a.idfilial = :filial';
                $binds[':filial'] = $filial;
            }

            $whereStr = implode(' AND ', $where);

            // 1. Métricas Gerais (Total, Média, Máxima, Mínima)
            $sqlMetricas = "
                SELECT 
                    COUNT(*) AS total_avaliacoes,
                    COALESCE(ROUND(AVG(a.pontuacao_total), 1), 0) AS media_pontos,
                    COALESCE(ROUND(AVG(a.percentual_atingido), 1), 0) AS media_percentual,
                    COALESCE(ROUND(AVG(a.pontos_conhecimento), 1), 0) AS media_c,
                    COALESCE(ROUND(AVG(a.pontos_habilidade), 1), 0) AS media_h,
                    COALESCE(ROUND(AVG(a.pontos_atitude), 1), 0) AS media_a,
                    COALESCE(ROUND(AVG(a.pontos_postura), 1), 0) AS media_p,
                    COALESCE(ROUND(AVG(a.pontos_disciplina), 1), 0) AS media_d
                FROM avaliacao_chapd a
                WHERE {$whereStr}
            ";
            $stMetricas = $db->prepare($sqlMetricas);
            $stMetricas->execute($binds);
            $metricas = $stMetricas->fetch(PDO::FETCH_ASSOC);

            // 2. Distribuição por Faixa de Pontuação
            $sqlFaixas = "
                SELECT 
                    CASE 
                        WHEN a.pontuacao_total >= 125 THEN 'Excelente (> 125)'
                        WHEN a.pontuacao_total >= 100 THEN 'Aceitável e com potencial (100 a 125)'
                        WHEN a.pontuacao_total >= 85  THEN 'Médio potencial (85 a 99)'
                        ELSE 'Baixo potencial (< 85)'
                    END AS faixa,
                    COUNT(*) AS quantidade
                FROM avaliacao_chapd a
                WHERE {$whereStr}
                GROUP BY 1
                ORDER BY MIN(a.pontuacao_total) DESC
            ";
            $stFaixas = $db->prepare($sqlFaixas);
            $stFaixas->execute($binds);
            $faixas = $stFaixas->fetchAll(PDO::FETCH_ASSOC);

            // 3. Distribuição por Letra (A, B, C, D, E, F)
            $sqlLetras = "
                SELECT 
                    COALESCE(a.conceito_letra, 'N/D') AS letra,
                    COUNT(*) AS quantidade
                FROM avaliacao_chapd a
                WHERE {$whereStr}
                GROUP BY a.conceito_letra
                ORDER BY a.conceito_letra ASC
            ";
            $stLetras = $db->prepare($sqlLetras);
            $stLetras->execute($binds);
            $distrLetras = $stLetras->fetchAll(PDO::FETCH_ASSOC);

            // 4. Top Colaboradores (Destaques)
            $sqlTop = "
                SELECT 
                    a.id, a.idcliforemp, a.nome_colaborador, a.cargo, a.pontuacao_total, 
                    a.conceito_letra, a.classificacao_pontos, a.mes, a.ano
                FROM avaliacao_chapd a
                WHERE {$whereStr}
                ORDER BY a.pontuacao_total DESC, a.data_avaliacao DESC
                LIMIT 5
            ";
            $stTop = $db->prepare($sqlTop);
            $stTop->execute($binds);
            $topColaboradores = $stTop->fetchAll(PDO::FETCH_ASSOC);

            // 5. Colaboradores que Requerem PDI / Baixo Potencial (< 85)
            $sqlAtencao = "
                SELECT 
                    a.id, a.idcliforemp, a.nome_colaborador, a.cargo, a.pontuacao_total, 
                    a.conceito_letra, a.classificacao_pontos, a.pontos_desenvolver, a.plano_acao, a.mes, a.ano
                FROM avaliacao_chapd a
                WHERE {$whereStr} AND a.pontuacao_total < 85
                ORDER BY a.pontuacao_total ASC
                LIMIT 5
            ";
            $stAtencao = $db->prepare($sqlAtencao);
            $stAtencao->execute($binds);
            $atencaoColaboradores = $stAtencao->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'sucesso' => true,
                'metricas' => $metricas,
                'distribuicao_faixas' => $faixas,
                'distribuicao_letras' => $distrLetras,
                'top_colaboradores' => $topColaboradores,
                'atencao_colaboradores' => $atencaoColaboradores
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/matriz-anual
     * Visão idêntica à planilha: cada colaborador com colunas Jan..Dez
     */
    public function matrizAnual(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $request->getQueryParams();
            $ano = !empty($params['ano']) ? (int)$params['ano'] : (int)date('Y');
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            // Busca todos os colaboradores ativos com JOIN de perfil personalizado
            $sqlColab = "
                SELECT 
                    c.idcliforemp, 
                    COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) AS nome, 
                    COALESCE(cp.idfilial_custom, c.idfilial) AS idfilial, 
                    COALESCE(NULLIF(TRIM(cp.cargo_custom), ''), TRIM(e.cargo), 'Não informado') AS cargo,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url,
                    u.username AS usuario_portal
                FROM cliforemp c
                JOIN empregado e ON e.idcliforemp = c.idcliforemp 
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = c.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = c.idcliforemp
                WHERE c.inativo = 'N' " . ($filial ? "AND COALESCE(cp.idfilial_custom, c.idfilial) = :filial " : "") . "
                ORDER BY COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) ASC
            ";
            $stColab = $db->prepare($sqlColab);
            if ($filial) {
                $stColab->bindValue(':filial', $filial, PDO::PARAM_INT);
            }
            $stColab->execute();
            $colaboradores = $stColab->fetchAll(PDO::FETCH_ASSOC);

            // Busca todas as avaliações do ano
            $sqlAval = "
                SELECT id, idcliforemp, mes, pontuacao_total, conceito_letra, classificacao_pontos, foto_colaborador
                FROM avaliacao_chapd
                WHERE ano = :ano
            ";
            $stAval = $db->prepare($sqlAval);
            $stAval->execute([':ano' => $ano]);
            $avaliacoes = $stAval->fetchAll(PDO::FETCH_ASSOC);

            // Mapeia por [idcliforemp][mes]
            $mapa = [];
            foreach ($avaliacoes as $av) {
                $pts = (float)$av['pontuacao_total'];
                $conjunto = $pts >= 115 ? 'AB' : ($pts >= 85 ? 'CD' : 'EF');
                $mapa[$av['idcliforemp']][(int)$av['mes']] = [
                    'id' => (int)$av['id'],
                    'pontos' => $pts,
                    'letra' => $av['conceito_letra'],
                    'conjunto' => $conjunto,
                    'classificacao' => $av['classificacao_pontos'],
                    'foto_colaborador' => $av['foto_colaborador'] ?? null
                ];
            }

            // Monta matriz final
            $matriz = [];
            foreach ($colaboradores as $c) {
                $id = (int)$c['idcliforemp'];
                $mesesData = [];
                $soma = 0;
                $qtd = 0;

                for ($m = 1; $m <= 12; $m++) {
                    $item = $mapa[$id][$m] ?? null;
                    $mesesData[$m] = $item;
                    if ($item) {
                        $soma += $item['pontos'];
                        $qtd++;
                    }
                }

                $media = $qtd > 0 ? round($soma / $qtd, 1) : null;
                $conjuntoMedia = $media !== null ? ($media >= 115 ? 'AB' : ($media >= 85 ? 'CD' : 'EF')) : null;

                $matriz[] = [
                    'idcliforemp' => $id,
                    'nome' => $c['nome'],
                    'cargo' => $c['cargo'],
                    'filial' => (int)$c['idfilial'],
                    'foto_url' => $c['foto_url'] ?? null,
                    'meses' => $mesesData,
                    'total_avaliacoes' => $qtd,
                    'media_anual' => $media,
                    'conjunto_media' => $conjuntoMedia
                ];
            }

            return $this->json($response, [
                'sucesso' => true,
                'ano' => $ano,
                'matriz' => $matriz
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar matriz anual: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/historico
     * Lista filtrada de avaliações realizadas
     */
    public function historico(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $request->getQueryParams();

            $ano = !empty($params['ano']) ? (int)$params['ano'] : null;
            $mes = !empty($params['mes']) ? (int)$params['mes'] : null;
            $idcliforemp = !empty($params['idcliforemp']) ? (int)$params['idcliforemp'] : null;
            $letra = !empty($params['letra']) ? trim($params['letra']) : null;
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            $where = ['1=1'];
            $binds = [];

            if ($ano) {
                $where[] = 'a.ano = :ano';
                $binds[':ano'] = $ano;
            }
            if ($mes) {
                $where[] = 'a.mes = :mes';
                $binds[':mes'] = $mes;
            }
            if ($idcliforemp) {
                $where[] = 'a.idcliforemp = :idcliforemp';
                $binds[':idcliforemp'] = $idcliforemp;
            }
            if ($letra) {
                $where[] = 'a.conceito_letra = :letra';
                $binds[':letra'] = $letra;
            }
            if ($filial) {
                $where[] = 'a.idfilial = :filial';
                $binds[':filial'] = $filial;
            }

            $sql = "
                SELECT 
                    a.id, a.idcliforemp, a.nome_colaborador, a.cargo, a.idfilial, a.setor, a.celula_trabalho,
                    a.ano, a.mes, TO_CHAR(a.data_avaliacao, 'DD/MM/YYYY') AS data_formatada,
                    a.avaliador_nome, a.aprovador_nome,
                    a.pontuacao_total, a.pontuacao_maxima, a.percentual_atingido,
                    a.classificacao_pontos, a.conceito_letra, a.conceito_descricao,
                    a.pontos_conhecimento, a.pontos_habilidade, a.pontos_atitude, a.pontos_postura, a.pontos_disciplina,
                    a.status,
                    COALESCE(a.foto_colaborador, cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url
                FROM avaliacao_chapd a
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = a.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = a.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = a.idcliforemp
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.ano DESC, a.mes DESC, a.id DESC
                LIMIT 150
            ";

            $st = $db->prepare($sql);
            $st->execute($binds);
            $lista = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lista as &$row) {
                $pts = (float)$row['pontuacao_total'];
                $row['conjunto'] = $pts >= 115 ? 'AB' : ($pts >= 85 ? 'CD' : 'EF');
            }
            unset($row);

            return $this->json($response, [
                'sucesso' => true,
                'total' => count($lista),
                'avaliacoes' => $lista
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/{id}
     * Detalhes de uma avaliação individual com todas as perguntas e respostas
     */
    public function detalhes(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDb();
            $id = (int)$args['id'];

            $stAval = $db->prepare("SELECT * FROM avaliacao_chapd WHERE id = ?");
            $stAval->execute([$id]);
            $avaliacao = $stAval->fetch(PDO::FETCH_ASSOC);

            if (!$avaliacao) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Avaliação não encontrada.'], 404);
            }

            if (empty($avaliacao['foto_colaborador'])) {
                $stFoto = $db->prepare("
                    SELECT COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil)
                    FROM cliforemp c
                    LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                    LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = c.idcliforemp
                    LEFT JOIN usuario u ON u.idcliforemp = c.idcliforemp
                    WHERE c.idcliforemp = ?
                ");
                $stFoto->execute([$avaliacao['idcliforemp']]);
                $avaliacao['foto_colaborador'] = $stFoto->fetchColumn() ?: null;
            }
            $pts = (float)$avaliacao['pontuacao_total'];
            $avaliacao['conjunto'] = $pts >= 115 ? 'AB' : ($pts >= 85 ? 'CD' : 'EF');

            $sqlResp = "
                SELECT 
                    r.id, r.idpergunta, r.pilar, r.pontuacao, r.texto_opcao, r.observacao_item,
                    p.codigo, p.pergunta, p.descricao_auxiliar, p.ordem
                FROM avaliacao_chapd_respostas r
                JOIN avaliacao_chapd_perguntas p ON p.id = r.idpergunta
                WHERE r.idavaliacao = ?
                ORDER BY p.ordem ASC
            ";
            $stResp = $db->prepare($sqlResp);
            $stResp->execute([$id]);
            $respostas = $stResp->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'sucesso' => true,
                'avaliacao' => $avaliacao,
                'respostas' => $respostas
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao buscar detalhes da avaliação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/salvar
     * Salva ou atualiza a avaliação com cálculo automático de scores e pilares
     */
    public function salvar(Request $request, Response $response): Response
    {
        $db = $this->getDb();
        try {
            $user = $this->getUsuarioSessao();
            $body = $request->getParsedBody() ?? [];

            $idcliforemp = (int)($body['idcliforemp'] ?? 0);
            $ano = (int)($body['ano'] ?? date('Y'));
            $mes = (int)($body['mes'] ?? date('n'));
            $respostas = $body['respostas'] ?? []; // Array [{idpergunta: X, pontuacao: Y, observacao: Z}]
            $conceitoLetra = trim($body['conceito_letra'] ?? '');
            $setor = trim($body['setor'] ?? '');
            $celula = trim($body['celula_trabalho'] ?? '');
            $avaliadorNome = trim($body['avaliador_nome'] ?? $user['nome']);
            $aprovadorNome = trim($body['aprovador_nome'] ?? 'Robson Becker');
            $pontosFortes = trim($body['pontos_fortes'] ?? '');
            $pontosDesenvolver = trim($body['pontos_desenvolver'] ?? '');
            $planoAcao = trim($body['plano_acao'] ?? '');
            $observacoes = trim($body['observacoes_gerais'] ?? '');

            if ($idcliforemp <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Selecione o colaborador a ser avaliado.'], 400);
            }

            if (empty($respostas) || !is_array($respostas)) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Nenhuma resposta foi enviada.'], 400);
            }

            // Busca dados do colaborador no ERP
            $stColab = $db->prepare("
                SELECT c.razao, c.idfilial, e.cargo 
                FROM cliforemp c 
                JOIN empregado e ON e.idcliforemp = c.idcliforemp 
                WHERE c.idcliforemp = ?
            ");
            $stColab->execute([$idcliforemp]);
            $colabERP = $stColab->fetch(PDO::FETCH_ASSOC);

            if (!$colabERP) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Colaborador não encontrado no ERP.'], 404);
            }

            $nomeColaborador = trim($colabERP['razao']);
            $cargo = trim($colabERP['cargo'] ?? '');
            $idfilial = (int)$colabERP['idfilial'];

            // Busca perguntas para validar pilares e pesos
            $perguntasDb = $db->query("SELECT id, pilar, peso FROM avaliacao_chapd_perguntas WHERE ativo = TRUE")->fetchAll(PDO::FETCH_ASSOC);
            $pergMap = [];
            $maxPossivel = 0;
            foreach ($perguntasDb as $p) {
                $pergMap[(int)$p['id']] = $p;
                $maxPossivel += (10 * (float)$p['peso']); // Se peso 1, cada pergunta vale até 10
            }

            // Calcula pontuações totais e por pilar C.H.A.P.D
            $totalPontos = 0;
            $pilaresPontos = [
                'C' => 0,
                'H' => 0,
                'A' => 0,
                'P' => 0,
                'D' => 0
            ];

            $respostasProcessadas = [];

            foreach ($respostas as $r) {
                $idPerg = (int)($r['idpergunta'] ?? 0);
                $nota = (float)($r['pontuacao'] ?? 0);
                $obsItem = trim($r['observacao_item'] ?? '');

                if (!isset($pergMap[$idPerg])) {
                    continue;
                }

                $pilar = strtoupper(trim($pergMap[$idPerg]['pilar'] ?? 'C'));
                $totalPontos += $nota;

                if (isset($pilaresPontos[$pilar])) {
                    $pilaresPontos[$pilar] += $nota;
                }

                $textoOpcao = 'Nota ' . $nota;
                if ($nota == 10) $textoOpcao = 'Sempre';
                elseif ($nota == 8) $textoOpcao = 'Frequentemente';
                elseif ($nota == 6) $textoOpcao = 'Às Vezes';
                elseif ($nota == 4) $textoOpcao = 'Raramente ou Quase Nunca';
                elseif ($nota == 2) $textoOpcao = 'Nunca';

                $respostasProcessadas[] = [
                    'idpergunta' => $idPerg,
                    'pilar' => $pilar,
                    'pontuacao' => $nota,
                    'texto_opcao' => $textoOpcao,
                    'observacao_item' => $obsItem
                ];
            }

            $percentual = $maxPossivel > 0 ? round(($totalPontos / $maxPossivel) * 100, 2) : 0;

            // Classificação por Conjuntos de Letras em Duplas (A&B Máxima, C&D Meio, E&F Abaixo)
            $classificacaoPontos = 'Baixo Potencial / Atenção PDI (Conjunto E & F)';
            $conjuntoSugerido = 'EF';
            if ($totalPontos >= 115) {
                $classificacaoPontos = 'Excelente (Conjunto A & B)';
                $conjuntoSugerido = 'AB';
            } elseif ($totalPontos >= 85) {
                $classificacaoPontos = 'Médio Potencial (Conjunto C & D)';
                $conjuntoSugerido = 'CD';
            }

            // Descrição da Letra / Conjunto Conceito
            $conceitoDescricao = '';
            $descricoesLetras = [
                'A' => 'Altamente engajado e competente nas metas e tarefas (Conjunto A & B).',
                'B' => 'Excelente desempenho, busca aperfeiçoamento e assume responsabilidades (Conjunto A & B).',
                'A-B' => 'Conjunto A & B: Alto engajamento, metas superadas e busca por excelência.',
                'A/B' => 'Conjunto A & B: Alto engajamento, metas superadas e busca por excelência.',
                'C' => 'Médio potencial, falta desenvolver proatividade mas mostra-se disposto a crescimento (Conjunto C & D).',
                'D' => 'Limita-se às tarefas de rotinas, mas busca atender às expectativas em novos desafios (Conjunto C & D).',
                'C-D' => 'Conjunto C & D: Executor sólido de rotinas, com potencial de desenvolvimento.',
                'C/D' => 'Conjunto C & D: Executor sólido de rotinas, com potencial de desenvolvimento.',
                'E' => 'Baixo desempenho, tomadas de decisões e aperfeiçoamento não fazem parte do perfil (Conjunto E & F).',
                'F' => 'Baixo desempenho, falta dinamismo e proatividade (Conjunto E & F).',
                'E-F' => 'Conjunto E & F: Baixo desempenho e dinamismo, requer plano de desenvolvimento (PDI).',
                'E/F' => 'Conjunto E & F: Baixo desempenho e dinamismo, requer plano de desenvolvimento (PDI).'
            ];

            if (empty($conceitoLetra)) {
                $conceitoLetra = $conjuntoSugerido === 'AB' ? 'A' : ($conjuntoSugerido === 'CD' ? 'C' : 'E');
            }
            $conceitoLetra = strtoupper($conceitoLetra);
            $conceitoDescricao = $descricoesLetras[$conceitoLetra] ?? 'Avaliação de Desempenho C.H.A.P.D';

            // Foto do Colaborador: se enviada usa ela, senão busca da tabela de fotos padrão / perfil / usuario
            $fotoColaborador = trim($body['foto_colaborador'] ?? '');
            if (empty($fotoColaborador)) {
                $stFoto = $db->prepare("
                    SELECT COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil)
                    FROM cliforemp c
                    LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                    LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = c.idcliforemp
                    LEFT JOIN usuario u ON u.idcliforemp = c.idcliforemp
                    WHERE c.idcliforemp = ?
                ");
                $stFoto->execute([$idcliforemp]);
                $fotoColaborador = $stFoto->fetchColumn() ?: null;
            }

            $db->beginTransaction();

            // Verifica se já existe avaliação para esse colaborador no mesmo mês/ano
            $stExist = $db->prepare("SELECT id FROM avaliacao_chapd WHERE idcliforemp = ? AND ano = ? AND mes = ?");
            $stExist->execute([$idcliforemp, $ano, $mes]);
            $idAvaliacaoExistente = $stExist->fetchColumn();

            if ($idAvaliacaoExistente) {
                // Atualização
                $idAvaliacao = (int)$idAvaliacaoExistente;
                $sqlUpd = "
                    UPDATE avaliacao_chapd SET
                        nome_colaborador = :nome_colaborador,
                        cargo = :cargo,
                        idfilial = :idfilial,
                        setor = :setor,
                        celula_trabalho = :celula_trabalho,
                        avaliador_nome = :avaliador_nome,
                        avaliador_usuario = :avaliador_usuario,
                        aprovador_nome = :aprovador_nome,
                        pontuacao_total = :pontuacao_total,
                        pontuacao_maxima = :pontuacao_maxima,
                        percentual_atingido = :percentual_atingido,
                        classificacao_pontos = :classificacao_pontos,
                        conceito_letra = :conceito_letra,
                        conceito_descricao = :conceito_descricao,
                        foto_colaborador = COALESCE(:foto_colaborador, foto_colaborador),
                        pontos_conhecimento = :pontos_conhecimento,
                        pontos_habilidade = :pontos_habilidade,
                        pontos_atitude = :pontos_atitude,
                        pontos_postura = :pontos_postura,
                        pontos_disciplina = :pontos_disciplina,
                        pontos_fortes = :pontos_fortes,
                        pontos_desenvolver = :pontos_desenvolver,
                        plano_acao = :plano_acao,
                        observacoes_gerais = :observacoes_gerais,
                        status = 'concluida',
                        atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = :id
                ";
                $stUpd = $db->prepare($sqlUpd);
                $stUpd->execute([
                    ':nome_colaborador' => $nomeColaborador,
                    ':cargo' => $cargo,
                    ':idfilial' => $idfilial,
                    ':setor' => $setor,
                    ':celula_trabalho' => $celula,
                    ':avaliador_nome' => $avaliadorNome,
                    ':avaliador_usuario' => $user['login'],
                    ':aprovador_nome' => $aprovadorNome,
                    ':pontuacao_total' => $totalPontos,
                    ':pontuacao_maxima' => $maxPossivel,
                    ':percentual_atingido' => $percentual,
                    ':classificacao_pontos' => $classificacaoPontos,
                    ':conceito_letra' => $conceitoLetra,
                    ':conceito_descricao' => $conceitoDescricao,
                    ':foto_colaborador' => $fotoColaborador,
                    ':pontos_conhecimento' => $pilaresPontos['C'],
                    ':pontos_habilidade' => $pilaresPontos['H'],
                    ':pontos_atitude' => $pilaresPontos['A'],
                    ':pontos_postura' => $pilaresPontos['P'],
                    ':pontos_disciplina' => $pilaresPontos['D'],
                    ':pontos_fortes' => $pontosFortes,
                    ':pontos_desenvolver' => $pontosDesenvolver,
                    ':plano_acao' => $planoAcao,
                    ':observacoes_gerais' => $observacoes,
                    ':id' => $idAvaliacao
                ]);

                // Remove respostas antigas para reinserir
                $db->prepare("DELETE FROM avaliacao_chapd_respostas WHERE idavaliacao = ?")->execute([$idAvaliacao]);

            } else {
                // Inserção nova
                $sqlIns = "
                    INSERT INTO avaliacao_chapd (
                        idcliforemp, nome_colaborador, cargo, idfilial, setor, celula_trabalho,
                        ano, mes, avaliador_nome, avaliador_usuario, aprovador_nome,
                        pontuacao_total, pontuacao_maxima, percentual_atingido,
                        classificacao_pontos, conceito_letra, conceito_descricao, foto_colaborador,
                        pontos_conhecimento, pontos_habilidade, pontos_atitude, pontos_postura, pontos_disciplina,
                        pontos_fortes, pontos_desenvolver, plano_acao, observacoes_gerais, status
                    ) VALUES (
                        :idcliforemp, :nome_colaborador, :cargo, :idfilial, :setor, :celula_trabalho,
                        :ano, :mes, :avaliador_nome, :avaliador_usuario, :aprovador_nome,
                        :pontuacao_total, :pontuacao_maxima, :percentual_atingido,
                        :classificacao_pontos, :conceito_letra, :conceito_descricao, :foto_colaborador,
                        :pontos_conhecimento, :pontos_habilidade, :pontos_atitude, :pontos_postura, :pontos_disciplina,
                        :pontos_fortes, :pontos_desenvolver, :plano_acao, :observacoes_gerais, 'concluida'
                    ) RETURNING id
                ";
                $stIns = $db->prepare($sqlIns);
                $stIns->execute([
                    ':idcliforemp' => $idcliforemp,
                    ':nome_colaborador' => $nomeColaborador,
                    ':cargo' => $cargo,
                    ':idfilial' => $idfilial,
                    ':setor' => $setor,
                    ':celula_trabalho' => $celula,
                    ':ano' => $ano,
                    ':mes' => $mes,
                    ':avaliador_nome' => $avaliadorNome,
                    ':avaliador_usuario' => $user['login'],
                    ':aprovador_nome' => $aprovadorNome,
                    ':pontuacao_total' => $totalPontos,
                    ':pontuacao_maxima' => $maxPossivel,
                    ':percentual_atingido' => $percentual,
                    ':classificacao_pontos' => $classificacaoPontos,
                    ':conceito_letra' => $conceitoLetra,
                    ':conceito_descricao' => $conceitoDescricao,
                    ':foto_colaborador' => $fotoColaborador,
                    ':pontos_conhecimento' => $pilaresPontos['C'],
                    ':pontos_habilidade' => $pilaresPontos['H'],
                    ':pontos_atitude' => $pilaresPontos['A'],
                    ':pontos_postura' => $pilaresPontos['P'],
                    ':pontos_disciplina' => $pilaresPontos['D'],
                    ':pontos_fortes' => $pontosFortes,
                    ':pontos_desenvolver' => $pontosDesenvolver,
                    ':plano_acao' => $planoAcao,
                    ':observacoes_gerais' => $observacoes
                ]);
                $idAvaliacao = (int)$stIns->fetchColumn();
            }

            // Insere cada resposta
            $insResp = $db->prepare("
                INSERT INTO avaliacao_chapd_respostas (
                    idavaliacao, idpergunta, pilar, pontuacao, texto_opcao, observacao_item
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($respostasProcessadas as $rp) {
                $insResp->execute([
                    $idAvaliacao,
                    $rp['idpergunta'],
                    $rp['pilar'],
                    $rp['pontuacao'],
                    $rp['texto_opcao'],
                    $rp['observacao_item']
                ]);
            }

            $db->commit();

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Avaliação C.H.A.P.D salva com sucesso!',
                'id' => $idAvaliacao,
                'pontuacao_total' => $totalPontos,
                'percentual' => $percentual,
                'classificacao' => $classificacaoPontos,
                'conceito_letra' => $conceitoLetra,
                'pilares' => $pilaresPontos
            ]);

        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao salvar avaliação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/perguntas
     * Lista todas as perguntas cadastradas
     */
    public function listarPerguntas(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $perguntas = $db->query("SELECT * FROM avaliacao_chapd_perguntas ORDER BY ordem ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'sucesso' => true,
                'perguntas' => $perguntas
            ]);
        } catch (Throwable $e) {
            return $this->json($response, ['sucesso' => false, 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/perguntas/salvar
     * Cadastra ou atualiza uma pergunta
     */
    public function salvarPergunta(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $b = $request->getParsedBody() ?? [];

            $id = !empty($b['id']) ? (int)$b['id'] : null;
            $codigo = trim($b['codigo'] ?? '');
            $pilar = strtoupper(trim($b['pilar'] ?? 'C'));
            $pergunta = trim($b['pergunta'] ?? '');
            $descAux = trim($b['descricao_auxiliar'] ?? '');
            $peso = !empty($b['peso']) ? (float)$b['peso'] : 1.0;
            $ordem = !empty($b['ordem']) ? (int)$b['ordem'] : 1;
            $ativo = isset($b['ativo']) ? (bool)$b['ativo'] : true;

            if (empty($pergunta)) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'O texto da pergunta é obrigatório.'], 400);
            }

            if (empty($codigo)) {
                $codigo = $pilar . rand(10, 99);
            }

            if ($id) {
                $st = $db->prepare("
                    UPDATE avaliacao_chapd_perguntas SET
                        codigo = ?, pilar = ?, pergunta = ?, descricao_auxiliar = ?,
                        peso = ?, ordem = ?, ativo = ?, atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $st->execute([$codigo, $pilar, $pergunta, $descAux, $peso, $ordem, $ativo ? 1 : 0, $id]);
            } else {
                $st = $db->prepare("
                    INSERT INTO avaliacao_chapd_perguntas (codigo, pilar, pergunta, descricao_auxiliar, peso, ordem, ativo)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $st->execute([$codigo, $pilar, $pergunta, $descAux, $peso, $ordem, $ativo ? 1 : 0]);
                $id = (int)$db->lastInsertId();
            }

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Pergunta salva com sucesso!',
                'id' => $id
            ]);

        } catch (Throwable $e) {
            return $this->json($response, ['sucesso' => false, 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/perguntas/toggle
     * Ativa/Desativa uma pergunta
     */
    public function togglePergunta(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $b = $request->getParsedBody() ?? [];
            $id = (int)($b['id'] ?? 0);

            if ($id <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'ID inválido.'], 400);
            }

            $st = $db->prepare("UPDATE avaliacao_chapd_perguntas SET ativo = NOT ativo, atualizado_em = CURRENT_TIMESTAMP WHERE id = ? RETURNING ativo");
            $st->execute([$id]);
            $novoStatus = (bool)$st->fetchColumn();

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => $novoStatus ? 'Pergunta ativada!' : 'Pergunta inativada!',
                'ativo' => $novoStatus
            ]);
        } catch (Throwable $e) {
            return $this->json($response, ['sucesso' => false, 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/upload-foto
     * Upload ou captura de foto do colaborador avaliado
     */
    public function uploadFoto(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            if (empty($uploadedFiles['foto'])) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Nenhum arquivo de foto enviado.'], 400);
            }

            $foto = $uploadedFiles['foto'];
            if ($foto->getError() !== UPLOAD_ERR_OK) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Falha no upload da foto.'], 400);
            }

            $params = $request->getParsedBody() ?? [];
            $idcliforemp = (int)($params['idcliforemp'] ?? 0);
            $idavaliacao = (int)($params['idavaliacao'] ?? 0);

            if ($idcliforemp <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Colaborador não informado.'], 400);
            }

            if (($foto->getSize() ?? 0) > 10 * 1024 * 1024) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'A foto deve ter no máximo 10 MB.'], 413);
            }

            $stream = $foto->getStream();
            $conteudo = $stream->getContents();
            $stream->rewind();

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($conteudo);
            $extensoes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($extensoes[$mimeType])) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Formato não suportado. Envie imagem JPEG, PNG ou WEBP.'], 400);
            }

            $ext = $extensoes[$mimeType];
            $nomeArquivo = sprintf('colab_%d_%s.%s', $idcliforemp, date('Ymd_His') . '_' . substr((string)microtime(), 2, 4), $ext);
            $caminhoRelativo = 'uploads/chapd/' . $nomeArquivo;
            $caminhoAbsoluto = __DIR__ . '/../../../uploads/chapd/' . $nomeArquivo;

            $diretorio = dirname($caminhoAbsoluto);
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0755, true);
            }

            $foto->moveTo($caminhoAbsoluto);

            $db = $this->getDb();

            // Salva na tabela legada avaliacao_chapd_colaborador_foto
            $stFoto = $db->prepare("
                INSERT INTO avaliacao_chapd_colaborador_foto (idcliforemp, foto_url, atualizado_em)
                VALUES (:idcliforemp, :foto_url, CURRENT_TIMESTAMP)
                ON CONFLICT (idcliforemp) DO UPDATE 
                SET foto_url = EXCLUDED.foto_url, atualizado_em = CURRENT_TIMESTAMP
            ");
            $stFoto->execute([
                ':idcliforemp' => $idcliforemp,
                ':foto_url' => $caminhoRelativo
            ]);

            // Salva na tabela dedicada de perfil da área separada (JOIN com ERP)
            $stPerfilFoto = $db->prepare("
                INSERT INTO avaliacao_chapd_colaborador_perfil (idcliforemp, foto_url, atualizado_em)
                VALUES (:idcliforemp, :foto_url, CURRENT_TIMESTAMP)
                ON CONFLICT (idcliforemp) DO UPDATE 
                SET foto_url = EXCLUDED.foto_url, atualizado_em = CURRENT_TIMESTAMP
            ");
            $stPerfilFoto->execute([
                ':idcliforemp' => $idcliforemp,
                ':foto_url' => $caminhoRelativo
            ]);

            // Sincroniza também no Usuário do Portal se houver conta vinculada a este colaborador
            $stUsuarioFoto = $db->prepare("
                UPDATE usuario 
                SET foto_perfil = :foto_url, updated_at = CURRENT_TIMESTAMP 
                WHERE idcliforemp = :idcliforemp
            ");
            $stUsuarioFoto->execute([
                ':idcliforemp' => $idcliforemp,
                ':foto_url' => $caminhoRelativo
            ]);

            // Se idavaliacao fornecido, atualiza também a avaliação
            if ($idavaliacao > 0) {
                $db->prepare("UPDATE avaliacao_chapd SET foto_colaborador = ? WHERE id = ?")->execute([$caminhoRelativo, $idavaliacao]);
            }

            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            if (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/API/') !== false) {
                $baseUrl .= '/API';
            }
            $urlCompleta = $baseUrl . '/' . $caminhoRelativo;

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Foto do colaborador salva com sucesso!',
                'foto_url' => $caminhoRelativo,
                'url_completa' => $urlCompleta,
                'idcliforemp' => $idcliforemp
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao processar foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/colaborador/salvar-perfil
     * Salva ou atualiza os dados customizados do colaborador em tabela separada (JOIN com ERP)
     * Preserva 100% da integridade cadastral nativa do ERP legado
     */
    public function salvarPerfilColaborador(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $body = $request->getParsedBody() ?? [];
            $user = $this->getUsuarioSessao();

            $idcliforemp = (int)($body['idcliforemp'] ?? 0);
            if ($idcliforemp <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Colaborador não identificado.'], 400);
            }

            // Garante que o colaborador existe no ERP
            $stColab = $db->prepare("SELECT idcliforemp, TRIM(razao) AS nome_erp FROM cliforemp WHERE idcliforemp = ?");
            $stColab->execute([$idcliforemp]);
            $colabERP = $stColab->fetch(PDO::FETCH_ASSOC);
            if (!$colabERP) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Colaborador não encontrado no ERP.'], 404);
            }

            $nomeCustom = !empty($body['nome_custom']) ? trim($body['nome_custom']) : null;
            $cargoCustom = !empty($body['cargo_custom']) ? trim($body['cargo_custom']) : null;
            $setorCustom = !empty($body['setor_custom']) ? trim($body['setor_custom']) : null;
            $celulaCustom = !empty($body['celula_custom']) ? trim($body['celula_custom']) : null;
            $idfilialCustom = !empty($body['idfilial_custom']) ? (int)$body['idfilial_custom'] : null;
            $dataAdmissaoCustom = !empty($body['data_admissao_custom']) ? trim($body['data_admissao_custom']) : null;
            $fotoUrl = !empty($body['foto_url']) ? trim($body['foto_url']) : null;
            $observacoes = !empty($body['observacoes']) ? trim($body['observacoes']) : null;
            $atualizadoPor = $user['nome'] ?? 'Gestor C.H.A.P.D';

            // Se o usuário solicitou restaurar para o padrão nativo do ERP
            $restaurarPadrao = !empty($body['restaurar_padrao']);
            if ($restaurarPadrao) {
                $db->prepare("
                    UPDATE avaliacao_chapd_colaborador_perfil 
                    SET nome_custom = NULL, cargo_custom = NULL, setor_custom = NULL, celula_custom = NULL,
                        idfilial_custom = NULL, data_admissao_custom = NULL, observacoes = NULL,
                        atualizado_em = CURRENT_TIMESTAMP, atualizado_por = :por
                    WHERE idcliforemp = :id
                ")->execute([':id' => $idcliforemp, ':por' => $atualizadoPor]);
            } else {
                $sqlUpsert = "
                    INSERT INTO avaliacao_chapd_colaborador_perfil (
                        idcliforemp, nome_custom, cargo_custom, setor_custom, celula_custom,
                        idfilial_custom, data_admissao_custom, foto_url, observacoes,
                        atualizado_em, atualizado_por
                    ) VALUES (
                        :idcliforemp, :nome_custom, :cargo_custom, :setor_custom, :celula_custom,
                        :idfilial_custom, :data_admissao_custom, :foto_url, :observacoes,
                        CURRENT_TIMESTAMP, :atualizado_por
                    )
                    ON CONFLICT (idcliforemp) DO UPDATE SET
                        nome_custom = EXCLUDED.nome_custom,
                        cargo_custom = EXCLUDED.cargo_custom,
                        setor_custom = EXCLUDED.setor_custom,
                        celula_custom = EXCLUDED.celula_custom,
                        idfilial_custom = EXCLUDED.idfilial_custom,
                        data_admissao_custom = EXCLUDED.data_admissao_custom,
                        foto_url = COALESCE(EXCLUDED.foto_url, avaliacao_chapd_colaborador_perfil.foto_url),
                        observacoes = EXCLUDED.observacoes,
                        atualizado_em = CURRENT_TIMESTAMP,
                        atualizado_por = EXCLUDED.atualizado_por
                ";
                $stUpsert = $db->prepare($sqlUpsert);
                $stUpsert->execute([
                    ':idcliforemp' => $idcliforemp,
                    ':nome_custom' => $nomeCustom,
                    ':cargo_custom' => $cargoCustom,
                    ':setor_custom' => $setorCustom,
                    ':celula_custom' => $celulaCustom,
                    ':idfilial_custom' => $idfilialCustom,
                    ':data_admissao_custom' => $dataAdmissaoCustom,
                    ':foto_url' => $fotoUrl,
                    ':observacoes' => $observacoes,
                    ':atualizado_por' => $atualizadoPor
                ]);

                // Sincroniza foto também na tabela legada avaliacao_chapd_colaborador_foto se veio foto
                if ($fotoUrl) {
                    $db->prepare("
                        INSERT INTO avaliacao_chapd_colaborador_foto (idcliforemp, foto_url, atualizado_em)
                        VALUES (:id, :foto, CURRENT_TIMESTAMP)
                        ON CONFLICT (idcliforemp) DO UPDATE SET foto_url = EXCLUDED.foto_url, atualizado_em = CURRENT_TIMESTAMP
                    ")->execute([':id' => $idcliforemp, ':foto' => $fotoUrl]);

                    // Sincroniza com a conta de Usuário do Portal se existir vínculo
                    $db->prepare("
                        UPDATE usuario 
                        SET foto_perfil = :foto, updated_at = CURRENT_TIMESTAMP 
                        WHERE idcliforemp = :id
                    ")->execute([':id' => $idcliforemp, ':foto' => $fotoUrl]);
                }
            }

            // Retorna o perfil atualizado mesclado com o ERP via JOIN e Usuário do Portal
            $sqlGet = "
                SELECT 
                    c.idcliforemp, 
                    COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) AS nome, 
                    TRIM(c.razao) AS nome_erp,
                    COALESCE(cp.idfilial_custom, c.idfilial) AS idfilial,
                    c.idfilial AS idfilial_erp,
                    COALESCE(NULLIF(TRIM(cp.cargo_custom), ''), TRIM(e.cargo), 'Não informado') AS cargo,
                    TRIM(COALESCE(e.cargo, 'Não informado')) AS cargo_erp,
                    COALESCE(NULLIF(TRIM(cp.setor_custom), ''), '') AS setor_custom,
                    COALESCE(NULLIF(TRIM(cp.celula_custom), ''), '') AS celula_custom,
                    COALESCE(TO_CHAR(cp.data_admissao_custom, 'YYYY-MM-DD'), TO_CHAR(e.data_admissao, 'YYYY-MM-DD')) AS data_admissao,
                    TO_CHAR(e.data_admissao, 'YYYY-MM-DD') AS data_admissao_erp,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url,
                    u.idusuario,
                    u.username AS usuario_portal,
                    cp.observacoes AS perfil_observacoes,
                    (cp.idcliforemp IS NOT NULL AND (cp.nome_custom IS NOT NULL OR cp.cargo_custom IS NOT NULL OR cp.setor_custom IS NOT NULL OR cp.celula_custom IS NOT NULL OR cp.idfilial_custom IS NOT NULL)) AS perfil_editado
                FROM cliforemp c
                JOIN empregado e ON e.idcliforemp = c.idcliforemp 
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = c.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = c.idcliforemp
                WHERE c.idcliforemp = ?
            ";
            $stGet = $db->prepare($sqlGet);
            $stGet->execute([$idcliforemp]);
            $colabAtualizado = $stGet->fetch(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => $restaurarPadrao ? 'Dados restaurados para o padrão original do ERP!' : 'Perfil do colaborador atualizado com sucesso na área C.H.A.P.D!',
                'colaborador' => $colabAtualizado
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao salvar perfil do colaborador: ' . $e->getMessage()
            ], 500);
        }
    }
}
