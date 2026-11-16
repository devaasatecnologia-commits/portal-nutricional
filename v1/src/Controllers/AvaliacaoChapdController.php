<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Throwable;

class AvaliacaoChapdController
{
    private static bool $schemaChecked = false;

    /**
     * Obtém conexão PDO e garante schema atualizado de PDI / Plano de Ação
     */
    private function getDb(): PDO
    {
        $db = getPDO();
        $this->ensureSchema($db);
        return $db;
    }

    /**
     * Garante de forma idempotente as colunas e tabelas do Plano de Ação 5W2H / PDI
     */
    private function ensureSchema(PDO $db): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $db->exec("ALTER TABLE avaliacao_chapd DROP CONSTRAINT IF EXISTS uk_chapd_colab_periodo");
            $db->exec("ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS requer_pdi BOOLEAN DEFAULT FALSE");
            $db->exec("ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS feedback_colaborador TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS data_ciencia_colaborador TIMESTAMP NULL");
            $db->exec("ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS finalizado BOOLEAN DEFAULT FALSE");
            $db->exec("ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS finalizado_em TIMESTAMP NULL");
            $db->exec("ALTER TABLE avaliacao_chapd ADD COLUMN IF NOT EXISTS finalizado_por TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd ALTER COLUMN nome_colaborador TYPE TEXT, ALTER COLUMN cargo TYPE TEXT, ALTER COLUMN setor TYPE TEXT, ALTER COLUMN celula_trabalho TYPE TEXT, ALTER COLUMN avaliador_nome TYPE TEXT, ALTER COLUMN avaliador_usuario TYPE TEXT, ALTER COLUMN aprovador_nome TYPE TEXT, ALTER COLUMN status TYPE VARCHAR(50), ALTER COLUMN classificacao_pontos TYPE TEXT, ALTER COLUMN conceito_letra TYPE VARCHAR(20), ALTER COLUMN foto_colaborador TYPE TEXT, ALTER COLUMN finalizado_por TYPE TEXT");
            $db->exec("ALTER TABLE avaliacao_chapd_respostas ALTER COLUMN texto_opcao TYPE VARCHAR(255)");
            $db->exec("ALTER TABLE avaliacao_chapd_colaborador_perfil ALTER COLUMN nome_custom TYPE TEXT, ALTER COLUMN cargo_custom TYPE TEXT, ALTER COLUMN setor_custom TYPE TEXT, ALTER COLUMN celula_custom TYPE TEXT, ALTER COLUMN foto_url TYPE TEXT, ALTER COLUMN atualizado_por TYPE TEXT");
            $db->exec("ALTER TABLE avaliacao_chapd_colaborador_foto ALTER COLUMN foto_url TYPE TEXT");
            $db->exec("
                CREATE TABLE IF NOT EXISTS avaliacao_chapd_plano_acao (
                    id SERIAL PRIMARY KEY,
                    idavaliacao INTEGER NULL REFERENCES avaliacao_chapd(id) ON DELETE SET NULL,
                    idcliforemp INTEGER NULL,
                    nome_colaborador TEXT,
                    cargo TEXT,
                    setor TEXT,
                    idfilial INTEGER DEFAULT 1,
                    ano INTEGER,
                    mes INTEGER,
                    pontuacao_origem NUMERIC(6,2),
                    conceito_origem TEXT,
                    origem_tipo TEXT DEFAULT 'PDI_ABAIXO_85',
                    problema TEXT NOT NULL,
                    objetivo TEXT NOT NULL,
                    responsavel TEXT NOT NULL DEFAULT 'Sandro Rafo',
                    aprovador TEXT DEFAULT 'Robson Becker',
                    revisao TEXT,
                    status_geral TEXT DEFAULT 'DENTRO_DO_PRAZO',
                    observacoes_gerais TEXT,
                    meta_pontuacao NUMERIC(6,2) DEFAULT 90.00,
                    prazo_dias_reavaliacao INTEGER DEFAULT 60,
                    pilares_criticos TEXT NULL,
                    feedback_colaborador TEXT NULL,
                    data_ciencia_colaborador TIMESTAMP NULL,
                    situacao_ciclo VARCHAR(30) DEFAULT 'EM_ELABORACAO',
                    impresso BOOLEAN DEFAULT FALSE,
                    impresso_em TIMESTAMP NULL,
                    impresso_por TEXT NULL,
                    fechado BOOLEAN DEFAULT FALSE,
                    fechado_em TIMESTAMP NULL,
                    fechado_por TEXT NULL,
                    parecer_fechamento TEXT NULL,
                    criado_por TEXT,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS meta_pontuacao NUMERIC(6,2) DEFAULT 90.00");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS prazo_dias_reavaliacao INTEGER DEFAULT 60");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS pilares_criticos TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS feedback_colaborador TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS data_ciencia_colaborador TIMESTAMP NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS situacao_ciclo VARCHAR(30) DEFAULT 'EM_ELABORACAO'");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS impresso BOOLEAN DEFAULT FALSE");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS impresso_em TIMESTAMP NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS impresso_por TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS fechado BOOLEAN DEFAULT FALSE");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS fechado_em TIMESTAMP NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS fechado_por TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ADD COLUMN IF NOT EXISTS parecer_fechamento TEXT NULL");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao ALTER COLUMN pilares_criticos TYPE TEXT, ALTER COLUMN conceito_origem TYPE TEXT, ALTER COLUMN origem_tipo TYPE TEXT, ALTER COLUMN status_geral TYPE TEXT, ALTER COLUMN revisao TYPE TEXT, ALTER COLUMN nome_colaborador TYPE TEXT, ALTER COLUMN cargo TYPE TEXT, ALTER COLUMN setor TYPE TEXT, ALTER COLUMN responsavel TYPE TEXT, ALTER COLUMN aprovador TYPE TEXT, ALTER COLUMN criado_por TYPE TEXT, ALTER COLUMN impresso_por TYPE TEXT, ALTER COLUMN fechado_por TYPE TEXT");
            $db->exec("
                CREATE TABLE IF NOT EXISTS avaliacao_chapd_plano_acao_item (
                    id SERIAL PRIMARY KEY,
                    idplano INTEGER NOT NULL REFERENCES avaliacao_chapd_plano_acao(id) ON DELETE CASCADE,
                    ordem INTEGER DEFAULT 1,
                    pilar_chapd VARCHAR(30) DEFAULT 'GERAL',
                    tipo_702010 VARCHAR(50) DEFAULT '70_PRATICA',
                    o_que TEXT NOT NULL,
                    como TEXT,
                    onde TEXT DEFAULT 'NTD',
                    quem TEXT,
                    data_inicio DATE,
                    data_termino DATE,
                    status TEXT DEFAULT 'Dentro do Prazo',
                    observacao TEXT,
                    evidencia_status VARCHAR(30) DEFAULT 'P',
                    evidencia_auditoria TEXT,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao_item ADD COLUMN IF NOT EXISTS pilar_chapd VARCHAR(30) DEFAULT 'GERAL'");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao_item ADD COLUMN IF NOT EXISTS tipo_702010 VARCHAR(50) DEFAULT '70_PRATICA'");
            $db->exec("ALTER TABLE avaliacao_chapd_plano_acao_item ALTER COLUMN status TYPE TEXT, ALTER COLUMN pilar_chapd TYPE VARCHAR(30), ALTER COLUMN tipo_702010 TYPE VARCHAR(50), ALTER COLUMN onde TYPE TEXT, ALTER COLUMN quem TYPE TEXT, ALTER COLUMN evidencia_status TYPE VARCHAR(30)");
        } catch (Throwable $e) {
            // Silencioso caso o usuário do banco tenha restrição de DDL em runtime
        }
    }

    /**
     * Extrai query params garantindo compatibilidade quando api_route traz query string embutida (?api_route=/rota?param=1)
     */
    private function getParams(Request $request): array
    {
        $params = $request->getQueryParams() ?? [];
        if (!empty($params['api_route']) && strpos((string)$params['api_route'], '?') !== false) {
            $parts = explode('?', (string)$params['api_route'], 2);
            if (!empty($parts[1])) {
                $extra = [];
                parse_str($parts[1], $extra);
                if (is_array($extra)) {
                    $params = array_merge($extra, $params);
                }
            }
        }
        return $params;
    }

    /**
     * Retorna dados do usuário autenticado via JWT ou sessão
     */
    private function getUsuarioSessao(?Request $request = null): array
    {
        $nome = 'Avaliador';
        $login = 'sistema';

        if ($request !== null) {
            $jwtUser = $request->getAttribute('user');
            if (is_array($jwtUser) && !empty($jwtUser['username'])) {
                $login = trim((string)$jwtUser['username']);
                $nome = $login;
                try {
                    $db = getPDO();
                    $idUsr = (int)($jwtUser['idusuario'] ?? $jwtUser['uid'] ?? 0);
                    if ($idUsr > 0) {
                        $st = $db->prepare("
                            SELECT COALESCE(NULLIF(TRIM(c.razao), ''), u.username) AS nome_completo
                            FROM usuario u
                            LEFT JOIN cliforemp c ON c.idcliforemp = u.idcliforemp
                            WHERE u.idusuario = ?
                        ");
                        $st->execute([$idUsr]);
                        $nomeDb = $st->fetchColumn();
                        if ($nomeDb) {
                            $nome = trim((string)$nomeDb);
                        }
                    }
                } catch (Throwable $e) {
                    // Mantém username
                }
                return ['login' => $login, 'nome' => $nome];
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $usuario = $_SESSION['usuario'] ?? $_SESSION['user'] ?? null;

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
            $user = $this->getUsuarioSessao($request);

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
                  AND COALESCE(cp.idfilial_custom, c.idfilial) IN (1, 2, 6)
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

            // 6. Contagem rápida de colaboradores/avaliações principais vigentes que necessitam PDI/Plano de Ação
            $pendentesPdiCount = (int)$db->query("
                WITH ultimas AS (
                    SELECT DISTINCT ON (idcliforemp, ano, mes)
                        id, pontuacao_total, requer_pdi
                    FROM avaliacao_chapd
                    ORDER BY idcliforemp, ano, mes, id DESC
                )
                SELECT COUNT(*)
                FROM ultimas a
                WHERE (a.pontuacao_total < 85 OR COALESCE(a.requer_pdi, FALSE) = TRUE)
            ")->fetchColumn();

            return $this->json($response, [
                'sucesso' => true,
                'usuario' => $user,
                'ano_atual' => (int)date('Y'),
                'mes_atual' => (int)date('n'),
                'pendentes_pdi_count' => $pendentesPdiCount,
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
     * People Analytics e KPIs de desempenho (considerando sempre a última avaliação principal de cada mês/colaborador)
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $this->getParams($request);

            $ano = !empty($params['ano']) ? (int)$params['ano'] : (int)date('Y');
            $mes = !empty($params['mes']) ? (int)$params['mes'] : null;
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            $where = ['a.ano = :ano', 'a.idfilial IN (1, 2, 6)'];
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
            $cteUltimas = "
                WITH ultimas_avaliacoes AS (
                    SELECT DISTINCT ON (idcliforemp, ano, mes) *
                    FROM avaliacao_chapd
                    ORDER BY idcliforemp, ano, mes, id DESC
                )
            ";

            // 1. Métricas Gerais (Total, Média, Máxima, Mínima, Total PDI)
            $sqlMetricas = "
                {$cteUltimas}
                SELECT 
                    COUNT(*) AS total_avaliacoes,
                    COALESCE(SUM(CASE WHEN a.pontuacao_total < 85 OR COALESCE(a.requer_pdi, FALSE) = TRUE THEN 1 ELSE 0 END), 0) AS total_requer_pdi,
                    COALESCE(ROUND(AVG(a.pontuacao_total), 1), 0) AS media_pontos,
                    COALESCE(ROUND(AVG(a.percentual_atingido), 1), 0) AS media_percentual,
                    COALESCE(ROUND(AVG(a.pontos_conhecimento), 1), 0) AS media_c,
                    COALESCE(ROUND(AVG(a.pontos_habilidade), 1), 0) AS media_h,
                    COALESCE(ROUND(AVG(a.pontos_atitude), 1), 0) AS media_a,
                    COALESCE(ROUND(AVG(a.pontos_postura), 1), 0) AS media_p,
                    COALESCE(ROUND(AVG(a.pontos_disciplina), 1), 0) AS media_d
                FROM ultimas_avaliacoes a
                WHERE {$whereStr}
            ";
            $stMetricas = $db->prepare($sqlMetricas);
            $stMetricas->execute($binds);
            $metricas = $stMetricas->fetch(PDO::FETCH_ASSOC);

            // 2. Distribuição por Faixa de Pontuação
            $sqlFaixas = "
                {$cteUltimas}
                SELECT 
                    CASE 
                        WHEN a.pontuacao_total >= 125 THEN 'Excelente (> 125)'
                        WHEN a.pontuacao_total >= 100 THEN 'Aceitável e com potencial (100 a 125)'
                        WHEN a.pontuacao_total >= 85  THEN 'Médio potencial (85 a 99)'
                        ELSE 'Baixo potencial (< 85)'
                    END AS faixa,
                    COUNT(*) AS quantidade
                FROM ultimas_avaliacoes a
                WHERE {$whereStr}
                GROUP BY 1
                ORDER BY MIN(a.pontuacao_total) DESC
            ";
            $stFaixas = $db->prepare($sqlFaixas);
            $stFaixas->execute($binds);
            $faixas = $stFaixas->fetchAll(PDO::FETCH_ASSOC);

            // 3. Distribuição por Letra (A, B, C, D, E, F)
            $sqlLetras = "
                {$cteUltimas}
                SELECT 
                    COALESCE(a.conceito_letra, 'N/D') AS letra,
                    COUNT(*) AS quantidade
                FROM ultimas_avaliacoes a
                WHERE {$whereStr}
                GROUP BY a.conceito_letra
                ORDER BY a.conceito_letra ASC
            ";
            $stLetras = $db->prepare($sqlLetras);
            $stLetras->execute($binds);
            $distrLetras = $stLetras->fetchAll(PDO::FETCH_ASSOC);

            // 4. Top Colaboradores (Destaques) com Foto
            $sqlTop = "
                {$cteUltimas}
                SELECT 
                    a.id, a.idcliforemp, a.nome_colaborador, a.cargo, a.pontuacao_total, 
                    a.conceito_letra, a.classificacao_pontos, a.mes, a.ano,
                    a.status, COALESCE(a.finalizado, FALSE) AS finalizado,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil, a.foto_colaborador) AS foto_url
                FROM ultimas_avaliacoes a
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = a.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = a.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = a.idcliforemp
                WHERE {$whereStr}
                ORDER BY a.pontuacao_total DESC, a.data_avaliacao DESC
                LIMIT 5
            ";
            $stTop = $db->prepare($sqlTop);
            $stTop->execute($binds);
            $topColaboradores = $stTop->fetchAll(PDO::FETCH_ASSOC);

            // 5. Colaboradores que Requerem PDI / Plano de Ação (< 85 ou indicados pelo avaliador) com Foto e Status do Plano
            $sqlAtencao = "
                {$cteUltimas}
                SELECT 
                    a.id, a.idcliforemp, a.nome_colaborador, a.cargo, a.pontuacao_total, 
                    a.conceito_letra, a.classificacao_pontos, a.pontos_desenvolver, a.plano_acao, a.mes, a.ano,
                    a.status, COALESCE(a.finalizado, FALSE) AS finalizado,
                    COALESCE(a.requer_pdi, FALSE) AS requer_pdi,
                    pa.id AS idplano,
                    pa.status_geral AS plano_status,
                    pa.situacao_ciclo AS plano_situacao_ciclo,
                    COALESCE(pa.impresso, FALSE) AS plano_impresso,
                    COALESCE(pa.fechado, FALSE) AS plano_fechado,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil, a.foto_colaborador) AS foto_url
                FROM ultimas_avaliacoes a
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = a.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = a.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = a.idcliforemp
                LEFT JOIN LATERAL (
                    SELECT p.id, p.status_geral,
                           COALESCE(p.situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo,
                           COALESCE(p.impresso, FALSE) AS impresso,
                           COALESCE(p.fechado, FALSE) AS fechado
                    FROM avaliacao_chapd_plano_acao p
                    WHERE p.idavaliacao = a.id OR (p.idcliforemp = a.idcliforemp AND p.ano = a.ano AND p.mes = a.mes)
                    ORDER BY p.id DESC
                    LIMIT 1
                ) pa ON TRUE
                WHERE {$whereStr} AND (a.pontuacao_total < 85 OR COALESCE(a.requer_pdi, FALSE) = TRUE)
                ORDER BY a.pontuacao_total ASC, a.id DESC
                LIMIT 12
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
     * Visão idêntica à planilha: cada colaborador com colunas Jan..Dez (mostrando sempre a última avaliação principal de cada mês)
     */
    public function matrizAnual(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $this->getParams($request);
            $ano = !empty($params['ano']) ? (int)$params['ano'] : (int)date('Y');
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            // Busca todos os colaboradores ativos das filiais 1, 2 e 6 com JOIN de perfil personalizado
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
                WHERE c.inativo = 'N'
                  AND COALESCE(cp.idfilial_custom, c.idfilial) IN (1, 2, 6)
                  " . ($filial ? "AND COALESCE(cp.idfilial_custom, c.idfilial) = :filial " : "") . "
                ORDER BY COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) ASC
            ";
            $stColab = $db->prepare($sqlColab);
            if ($filial) {
                $stColab->bindValue(':filial', $filial, PDO::PARAM_INT);
            }
            $stColab->execute();
            $colaboradores = $stColab->fetchAll(PDO::FETCH_ASSOC);

            // Busca a última avaliação (principal) de cada mês no ano, mais o total de avaliações feitas no mês
            $sqlAval = "
                SELECT DISTINCT ON (a.idcliforemp, a.mes)
                    a.id, a.idcliforemp, a.mes, a.pontuacao_total, a.conceito_letra, a.classificacao_pontos,
                    a.foto_colaborador, COALESCE(a.requer_pdi, FALSE) AS requer_pdi,
                    a.status, COALESCE(a.finalizado, FALSE) AS finalizado,
                    TO_CHAR(a.finalizado_em, 'DD/MM/YYYY HH24:MI') AS finalizado_em_fmt,
                    a.finalizado_por,
                    COUNT(*) OVER (PARTITION BY a.idcliforemp, a.mes) AS qtd_no_mes
                FROM avaliacao_chapd a
                WHERE a.ano = :ano
                ORDER BY a.idcliforemp, a.mes, a.id DESC
            ";
            $stAval = $db->prepare($sqlAval);
            $stAval->execute([':ano' => $ano]);
            $avaliacoes = $stAval->fetchAll(PDO::FETCH_ASSOC);

            // Mapeia por [idcliforemp][mes]
            $mapa = [];
            foreach ($avaliacoes as $av) {
                $pts = (float)$av['pontuacao_total'];
                $conjunto = $pts >= 115 ? 'AB' : ($pts >= 85 ? 'CD' : 'EF');
                $isFin = !empty($av['finalizado']) || ($av['status'] ?? '') === 'finalizada';
                $mapa[$av['idcliforemp']][(int)$av['mes']] = [
                    'id' => (int)$av['id'],
                    'pontos' => $pts,
                    'letra' => $av['conceito_letra'],
                    'conjunto' => $conjunto,
                    'classificacao' => $av['classificacao_pontos'],
                    'status' => $isFin ? 'finalizada' : 'em_edicao',
                    'finalizado' => $isFin,
                    'finalizado_em_fmt' => $av['finalizado_em_fmt'] ?? null,
                    'finalizado_por' => $av['finalizado_por'] ?? null,
                    'requer_pdi' => !empty($av['requer_pdi']) || $pts < 85,
                    'qtd_no_mes' => (int)($av['qtd_no_mes'] ?? 1),
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
     * Lista filtrada de avaliações realizadas (mantém todas as avaliações no histórico e identifica a Principal de cada mês)
     */
    public function historico(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $this->getParams($request);

            $ano = !empty($params['ano']) ? (int)$params['ano'] : null;
            $mes = !empty($params['mes']) ? (int)$params['mes'] : null;
            $idcliforemp = !empty($params['idcliforemp']) ? (int)$params['idcliforemp'] : null;
            $letra = !empty($params['letra']) ? trim($params['letra']) : null;
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            $where = ['a.idfilial IN (1, 2, 6)'];
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
            if (!empty($params['apenas_pdi'])) {
                $where[] = '(a.pontuacao_total < 85 OR COALESCE(a.requer_pdi, FALSE) = TRUE)';
            }

            $sql = "
                SELECT 
                    a.id, a.idcliforemp, a.nome_colaborador, a.cargo, a.idfilial, a.setor, a.celula_trabalho,
                    a.ano, a.mes, TO_CHAR(a.data_avaliacao, 'DD/MM/YYYY') AS data_formatada,
                    TO_CHAR(a.criado_em, 'DD/MM/YYYY HH24:MI') AS criado_em_fmt,
                    a.avaliador_nome, a.aprovador_nome,
                    a.pontuacao_total, a.pontuacao_maxima, a.percentual_atingido,
                    a.classificacao_pontos, a.conceito_letra, a.conceito_descricao,
                    a.pontos_conhecimento, a.pontos_habilidade, a.pontos_atitude, a.pontos_postura, a.pontos_disciplina,
                    a.status, COALESCE(a.requer_pdi, FALSE) AS requer_pdi,
                    COALESCE(a.finalizado, FALSE) AS finalizado,
                    TO_CHAR(a.finalizado_em, 'DD/MM/YYYY HH24:MI') AS finalizado_em_fmt,
                    a.finalizado_por,
                    a.feedback_colaborador,
                    TO_CHAR(a.data_ciencia_colaborador, 'DD/MM/YYYY HH24:MI') AS data_ciencia_fmt,
                    ROW_NUMBER() OVER (PARTITION BY a.idcliforemp, a.ano, a.mes ORDER BY a.id DESC) AS rk_mes,
                    COUNT(*) OVER (PARTITION BY a.idcliforemp, a.ano, a.mes) AS total_no_mes,
                    pa.id AS idplano, pa.status_geral AS plano_status,
                    pa.situacao_ciclo AS plano_situacao_ciclo,
                    COALESCE(pa.impresso, FALSE) AS plano_impresso,
                    COALESCE(pa.fechado, FALSE) AS plano_fechado,
                    COALESCE(a.foto_colaborador, cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url
                FROM avaliacao_chapd a
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = a.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = a.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = a.idcliforemp
                LEFT JOIN LATERAL (
                    SELECT p.id, p.status_geral,
                           COALESCE(p.situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo,
                           COALESCE(p.impresso, FALSE) AS impresso,
                           COALESCE(p.fechado, FALSE) AS fechado
                    FROM avaliacao_chapd_plano_acao p
                    WHERE p.idavaliacao = a.id
                    ORDER BY p.id DESC
                    LIMIT 1
                ) pa ON TRUE
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.ano DESC, a.mes DESC, a.id DESC
                LIMIT 200
            ";

            $st = $db->prepare($sql);
            $st->execute($binds);
            $lista = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lista as &$row) {
                $pts = (float)$row['pontuacao_total'];
                $row['conjunto'] = $pts >= 115 ? 'AB' : ($pts >= 85 ? 'CD' : 'EF');
                $row['requer_pdi'] = !empty($row['requer_pdi']) || $pts < 85;
                $row['finalizado'] = !empty($row['finalizado']) || ($row['status'] ?? '') === 'finalizada';
                $row['status'] = $row['finalizado'] ? 'finalizada' : ($row['status'] ?: 'em_edicao');
                $rkMes = (int)($row['rk_mes'] ?? 1);
                $totMes = (int)($row['total_no_mes'] ?? 1);
                $row['is_principal'] = ($rkMes === 1);
                $row['versao_mes'] = ($totMes - $rkMes + 1);
                $row['total_no_mes'] = $totMes;
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
     * GET /v1/avaliacao-chapd/detalhes/{id}
     * Detalhes de uma avaliação individual com todas as perguntas e respostas
     */
    public function detalhes(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDb();
            $id = (int)$args['id'];

            $stAval = $db->prepare("
                SELECT *,
                       TO_CHAR(finalizado_em, 'DD/MM/YYYY HH24:MI') AS finalizado_em_fmt,
                       TO_CHAR(data_ciencia_colaborador, 'DD/MM/YYYY HH24:MI') AS data_ciencia_fmt
                FROM avaliacao_chapd
                WHERE id = ?
            ");
            $stAval->execute([$id]);
            $avaliacao = $stAval->fetch(PDO::FETCH_ASSOC);

            if (!$avaliacao) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Avaliação não encontrada.'], 404);
            }

            $stColInfo = $db->prepare("
                SELECT
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url,
                    COALESCE(TO_CHAR(cp.data_admissao_custom, 'DD/MM/YYYY'), TO_CHAR(e.data_admissao, 'DD/MM/YYYY')) AS data_admissao_fmt
                FROM cliforemp c
                LEFT JOIN empregado e ON e.idcliforemp = c.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = c.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = c.idcliforemp
                WHERE c.idcliforemp = ?
            ");
            $stColInfo->execute([$avaliacao['idcliforemp']]);
            $colInfo = $stColInfo->fetch(PDO::FETCH_ASSOC);
            if (empty($avaliacao['foto_colaborador']) && !empty($colInfo['foto_url'])) {
                $avaliacao['foto_colaborador'] = $colInfo['foto_url'];
            }
            $avaliacao['data_admissao_fmt'] = $colInfo['data_admissao_fmt'] ?? null;
            $pts = (float)$avaliacao['pontuacao_total'];
            $avaliacao['conjunto'] = $pts >= 115 ? 'AB' : ($pts >= 85 ? 'CD' : 'EF');
            $avaliacao['requer_pdi'] = !empty($avaliacao['requer_pdi']) || $pts < 85;
            $avaliacao['finalizado'] = !empty($avaliacao['finalizado']) || ($avaliacao['status'] ?? '') === 'finalizada';
            $avaliacao['status'] = $avaliacao['finalizado'] ? 'finalizada' : ($avaliacao['status'] ?: 'em_edicao');

            $stPlano = $db->prepare("
                SELECT id, status_geral,
                       COALESCE(situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo,
                       COALESCE(impresso, FALSE) AS impresso,
                       COALESCE(fechado, FALSE) AS fechado
                FROM avaliacao_chapd_plano_acao
                WHERE idavaliacao = ?
                ORDER BY id DESC LIMIT 1
            ");
            $stPlano->execute([$id]);
            $planoVinc = $stPlano->fetch(PDO::FETCH_ASSOC);
            $avaliacao['idplano'] = $planoVinc ? (int)$planoVinc['id'] : null;
            $avaliacao['plano_status'] = $planoVinc['status_geral'] ?? null;
            $avaliacao['plano_situacao_ciclo'] = $planoVinc['situacao_ciclo'] ?? null;
            $avaliacao['plano_impresso'] = $planoVinc ? !empty($planoVinc['impresso']) : false;
            $avaliacao['plano_fechado'] = $planoVinc ? !empty($planoVinc['fechado']) : false;

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
            $user = $this->getUsuarioSessao($request);
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
            $requerPdiInput = !empty($body['requer_pdi']) && $body['requer_pdi'] !== 'false' && $body['requer_pdi'] !== '0';

            if ($idcliforemp <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Selecione o colaborador a ser avaliado.'], 400);
            }

            if (empty($respostas) || !is_array($respostas)) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Nenhuma resposta foi enviada.'], 400);
            }

            // Busca dados do colaborador no ERP mesclado com o perfil personalizado da área separada
            $stColab = $db->prepare("
                SELECT 
                    COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) AS razao,
                    COALESCE(cp.idfilial_custom, c.idfilial) AS idfilial,
                    COALESCE(NULLIF(TRIM(cp.cargo_custom), ''), TRIM(e.cargo)) AS cargo
                FROM cliforemp c 
                JOIN empregado e ON e.idcliforemp = c.idcliforemp 
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
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

            // PDI obrigatório se pontuação < 85 ou se marcado pelo avaliador ou se houver texto de plano de ação
            $requerPdi = ($totalPontos < 85) || $requerPdiInput || ($planoAcao !== '');
            $finalizar = !empty($body['finalizar']) && $body['finalizar'] !== 'false' && $body['finalizar'] !== '0';
            $statusNovo = $finalizar ? 'finalizada' : 'em_edicao';
            $finalizadoEm = $finalizar ? date('Y-m-d H:i:s') : null;
            $finalizadoPor = $finalizar ? ($user['nome'] ?? $user['login'] ?? 'Avaliador') : null;

            $idAvaliacaoExistente = !empty($body['id']) ? (int)$body['id'] : 0;

            if ($idAvaliacaoExistente > 0) {
                $stCheck = $db->prepare("SELECT id, status, COALESCE(finalizado, FALSE) AS finalizado FROM avaliacao_chapd WHERE id = ?");
                $stCheck->execute([$idAvaliacaoExistente]);
                $avAtual = $stCheck->fetch(PDO::FETCH_ASSOC);
                if (!$avAtual) {
                    return $this->json($response, ['sucesso' => false, 'mensagem' => 'Avaliação não encontrada para edição.'], 404);
                }
                if (!empty($avAtual['finalizado']) || ($avAtual['status'] ?? '') === 'finalizada') {
                    return $this->json($response, [
                        'sucesso' => false,
                        'mensagem' => 'Esta avaliação já foi finalizada para impressão e assinatura e não pode mais ser editada.'
                    ], 422);
                }
            }

            $db->beginTransaction();

            if ($idAvaliacaoExistente > 0) {
                // Atualização de avaliação em edição (antes da finalização/impressão)
                $idAvaliacao = (int)$idAvaliacaoExistente;
                $sqlUpd = "
                    UPDATE avaliacao_chapd SET
                        idcliforemp = :idcliforemp,
                        ano = :ano,
                        mes = :mes,
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
                        requer_pdi = :requer_pdi,
                        status = :status,
                        finalizado = :finalizado,
                        finalizado_em = :finalizado_em,
                        finalizado_por = :finalizado_por,
                        atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = :id
                ";
                $stUpd = $db->prepare($sqlUpd);
                $stUpd->execute([
                    ':idcliforemp' => $idcliforemp,
                    ':ano' => $ano,
                    ':mes' => $mes,
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
                    ':requer_pdi' => $requerPdi ? 'true' : 'false',
                    ':status' => $statusNovo,
                    ':finalizado' => $finalizar ? 'true' : 'false',
                    ':finalizado_em' => $finalizadoEm,
                    ':finalizado_por' => $finalizadoPor,
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
                        pontos_fortes, pontos_desenvolver, plano_acao, observacoes_gerais, requer_pdi,
                        status, finalizado, finalizado_em, finalizado_por
                    ) VALUES (
                        :idcliforemp, :nome_colaborador, :cargo, :idfilial, :setor, :celula_trabalho,
                        :ano, :mes, :avaliador_nome, :avaliador_usuario, :aprovador_nome,
                        :pontuacao_total, :pontuacao_maxima, :percentual_atingido,
                        :classificacao_pontos, :conceito_letra, :conceito_descricao, :foto_colaborador,
                        :pontos_conhecimento, :pontos_habilidade, :pontos_atitude, :pontos_postura, :pontos_disciplina,
                        :pontos_fortes, :pontos_desenvolver, :plano_acao, :observacoes_gerais, :requer_pdi,
                        :status, :finalizado, :finalizado_em, :finalizado_por
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
                    ':observacoes_gerais' => $observacoes,
                    ':requer_pdi' => $requerPdi ? 'true' : 'false',
                    ':status' => $statusNovo,
                    ':finalizado' => $finalizar ? 'true' : 'false',
                    ':finalizado_em' => $finalizadoEm,
                    ':finalizado_por' => $finalizadoPor
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
                'mensagem' => $finalizar
                    ? 'Avaliação C.H.A.P.D finalizada e travada para impressão/assinatura!'
                    : 'Avaliação C.H.A.P.D salva em modo editável!',
                'id' => $idAvaliacao,
                'status' => $statusNovo,
                'finalizado' => $finalizar,
                'pontuacao_total' => $totalPontos,
                'percentual' => $percentual,
                'classificacao' => $classificacaoPontos,
                'conceito_letra' => $conceitoLetra,
                'requer_pdi' => $requerPdi,
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
     * POST /v1/avaliacao-chapd/finalizar
     * Finaliza a avaliação C.H.A.P.D travando futuras edições/exclusões e liberando para impressão/assinaturas
     */
    public function finalizarAvaliacao(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $user = $this->getUsuarioSessao($request);
            $body = $request->getParsedBody() ?? [];
            $id = (int)($body['id'] ?? 0);

            if ($id <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Avaliação não informada.'], 400);
            }

            $stCheck = $db->prepare("SELECT id, status, COALESCE(finalizado, FALSE) AS finalizado FROM avaliacao_chapd WHERE id = ?");
            $stCheck->execute([$id]);
            $atual = $stCheck->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Avaliação não encontrada.'], 404);
            }

            $responsavelFin = $user['nome'] ?? $user['login'] ?? 'Gestor';
            $db->prepare("
                UPDATE avaliacao_chapd
                SET status = 'finalizada',
                    finalizado = TRUE,
                    finalizado_em = COALESCE(finalizado_em, CURRENT_TIMESTAMP),
                    finalizado_por = COALESCE(NULLIF(finalizado_por, ''), ?),
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$responsavelFin, $id]);

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Avaliação finalizada com sucesso! Agora está liberada apenas para visualização e impressão para assinatura.',
                'id' => $id,
                'status' => 'finalizada',
                'finalizado' => true,
                'finalizado_em_fmt' => date('d/m/Y H:i'),
                'finalizado_por' => $responsavelFin
            ]);
        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao finalizar avaliação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /v1/avaliacao-chapd/{id} ou POST /v1/avaliacao-chapd/excluir
     * Exclui uma avaliação que ainda não foi finalizada/impressa
     */
    public function excluirAvaliacao(Request $request, Response $response, array $args = []): Response
    {
        $db = $this->getDb();
        try {
            $body = $request->getParsedBody() ?? [];
            $id = (int)($args['id'] ?? ($body['id'] ?? 0));

            if ($id <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'ID da avaliação inválido.'], 400);
            }

            $stCheck = $db->prepare("SELECT id, status, COALESCE(finalizado, FALSE) AS finalizado FROM avaliacao_chapd WHERE id = ?");
            $stCheck->execute([$id]);
            $atual = $stCheck->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Avaliação não encontrada.'], 404);
            }

            if (!empty($atual['finalizado']) || ($atual['status'] ?? '') === 'finalizada') {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Avaliação já finalizada para impressão e assinatura não pode ser excluída.'
                ], 422);
            }

            $db->beginTransaction();
            $db->prepare("DELETE FROM avaliacao_chapd_respostas WHERE idavaliacao = ?")->execute([$id]);
            $db->prepare("DELETE FROM avaliacao_chapd WHERE id = ?")->execute([$id]);
            $db->commit();

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Avaliação excluída com sucesso!'
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao excluir avaliação: ' . $e->getMessage()
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
            $user = $this->getUsuarioSessao($request);

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

    /**
     * GET /v1/avaliacao-chapd/planos-acao/pendentes
     * Lista colaboradores e avaliações principais vigentes que caíram em < 85 pontos ou foram marcados pelo avaliador como requer_pdi = true
     */
    public function listarPendentesPdi(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $this->getParams($request);

            $ano = !empty($params['ano']) ? (int)$params['ano'] : null;
            $mes = !empty($params['mes']) ? (int)$params['mes'] : null;
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;

            $where = [
                'a.idfilial IN (1, 2, 6)',
                '(a.pontuacao_total < 85 OR COALESCE(a.requer_pdi, FALSE) = TRUE)'
            ];
            $binds = [];

            if ($ano) {
                $where[] = 'a.ano = :ano';
                $binds[':ano'] = $ano;
            }
            if ($mes) {
                $where[] = 'a.mes = :mes';
                $binds[':mes'] = $mes;
            }
            if ($filial) {
                $where[] = 'a.idfilial = :filial';
                $binds[':filial'] = $filial;
            }

            $sql = "
                WITH ultimas_avaliacoes AS (
                    SELECT DISTINCT ON (idcliforemp, ano, mes) *
                    FROM avaliacao_chapd
                    ORDER BY idcliforemp, ano, mes, id DESC
                )
                SELECT 
                    a.id AS idavaliacao,
                    a.idcliforemp,
                    a.nome_colaborador,
                    a.cargo,
                    a.idfilial,
                    a.setor,
                    a.celula_trabalho,
                    a.ano,
                    a.mes,
                    TO_CHAR(a.data_avaliacao, 'DD/MM/YYYY') AS data_formatada,
                    a.avaliador_nome,
                    a.aprovador_nome,
                    a.pontuacao_total,
                    a.pontuacao_maxima,
                    a.percentual_atingido,
                    a.classificacao_pontos,
                    a.conceito_letra,
                    a.pontos_conhecimento,
                    a.pontos_habilidade,
                    a.pontos_atitude,
                    a.pontos_postura,
                    a.pontos_disciplina,
                    a.pontos_fortes,
                    a.pontos_desenvolver,
                    a.plano_acao AS plano_acao_resumo,
                    COALESCE(a.requer_pdi, FALSE) AS requer_pdi,
                    CASE 
                        WHEN a.pontuacao_total < 85 THEN 'PDI_ABAIXO_85'
                        ELSE 'PDI_INDICACAO_AVALIADOR'
                    END AS origem_tipo,
                    COALESCE(a.foto_colaborador, cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url,
                    pa.id AS idplano,
                    pa.status_geral AS plano_status,
                    pa.situacao_ciclo AS plano_situacao_ciclo,
                    COALESCE(pa.impresso, FALSE) AS plano_impresso,
                    COALESCE(pa.fechado, FALSE) AS plano_fechado,
                    pa.responsavel AS plano_responsavel,
                    COALESCE(pa_stats.total_itens, 0) AS total_itens,
                    COALESCE(pa_stats.itens_concluidos, 0) AS itens_concluidos
                FROM ultimas_avaliacoes a
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = a.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = a.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = a.idcliforemp
                LEFT JOIN LATERAL (
                    SELECT p.id, p.status_geral, p.responsavel,
                           COALESCE(p.situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo,
                           COALESCE(p.impresso, FALSE) AS impresso,
                           COALESCE(p.fechado, FALSE) AS fechado
                    FROM avaliacao_chapd_plano_acao p
                    WHERE p.idavaliacao = a.id OR (p.idcliforemp = a.idcliforemp AND p.ano = a.ano AND p.mes = a.mes)
                    ORDER BY p.id DESC
                    LIMIT 1
                ) pa ON TRUE
                LEFT JOIN LATERAL (
                    SELECT 
                        COUNT(*) AS total_itens,
                        SUM(CASE WHEN i.status = 'Ação Concluída' THEN 1 ELSE 0 END) AS itens_concluidos
                    FROM avaliacao_chapd_plano_acao_item i
                    WHERE i.idplano = pa.id
                ) pa_stats ON TRUE
                WHERE " . implode(' AND ', $where) . "
                ORDER BY (CASE WHEN pa.id IS NULL THEN 0 ELSE 1 END) ASC, a.pontuacao_total ASC, a.ano DESC, a.mes DESC
            ";

            $st = $db->prepare($sql);
            $st->execute($binds);
            $pendentes = $st->fetchAll(PDO::FETCH_ASSOC);

            $semPlano = 0;
            $comPlano = 0;
            foreach ($pendentes as $p) {
                if (empty($p['idplano'])) {
                    $semPlano++;
                } else {
                    $comPlano++;
                }
            }

            return $this->json($response, [
                'sucesso' => true,
                'total' => count($pendentes),
                'sem_plano' => $semPlano,
                'com_plano' => $comPlano,
                'pendentes' => $pendentes
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao listar colaboradores para PDI: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/planos-acao
     * Lista todos os Planos de Ação 5W2H cadastrados + KPIs das 4 legendas da planilha
     */
    public function listarPlanosAcao(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $params = $this->getParams($request);

            $ano = !empty($params['ano']) ? (int)$params['ano'] : null;
            $mes = !empty($params['mes']) ? (int)$params['mes'] : null;
            $filial = !empty($params['filial']) ? (int)$params['filial'] : null;
            $status = !empty($params['status']) ? trim($params['status']) : null;
            $situacaoCiclo = !empty($params['situacao_ciclo']) ? trim($params['situacao_ciclo']) : null;
            $idcliforemp = !empty($params['idcliforemp']) ? (int)$params['idcliforemp'] : null;
            $busca = !empty($params['busca']) ? trim($params['busca']) : null;

            $where = ['COALESCE(p.idfilial, 1) IN (1, 2, 6)'];
            $binds = [];

            if ($ano) {
                $where[] = 'COALESCE(p.ano, EXTRACT(YEAR FROM p.criado_em)::INT) = :ano';
                $binds[':ano'] = $ano;
            }
            if ($mes) {
                $where[] = 'p.mes = :mes';
                $binds[':mes'] = $mes;
            }
            if ($filial) {
                $where[] = 'p.idfilial = :filial';
                $binds[':filial'] = $filial;
            }
            if ($status) {
                $where[] = 'p.status_geral = :status';
                $binds[':status'] = $status;
            }
            if ($situacaoCiclo) {
                $where[] = "COALESCE(p.situacao_ciclo, 'EM_ELABORACAO') = :situacao_ciclo";
                $binds[':situacao_ciclo'] = $situacaoCiclo;
            }
            if ($idcliforemp) {
                $where[] = 'p.idcliforemp = :idcliforemp';
                $binds[':idcliforemp'] = $idcliforemp;
            }
            if ($busca) {
                $where[] = '(p.nome_colaborador ILIKE :busca OR p.problema ILIKE :busca OR p.objetivo ILIKE :busca OR p.responsavel ILIKE :busca)';
                $binds[':busca'] = '%' . $busca . '%';
            }

            $sql = "
                SELECT 
                    p.*,
                    COALESCE(p.situacao_ciclo, CASE WHEN COALESCE(p.fechado, FALSE) THEN 'FECHADO' WHEN COALESCE(p.impresso, FALSE) THEN 'IMPRESSO' ELSE 'EM_ELABORACAO' END) AS situacao_ciclo,
                    COALESCE(p.impresso, FALSE) AS impresso,
                    TO_CHAR(p.impresso_em, 'DD/MM/YYYY HH24:MI') AS impresso_em_fmt,
                    COALESCE(p.fechado, FALSE) AS fechado,
                    TO_CHAR(p.fechado_em, 'DD/MM/YYYY HH24:MI') AS fechado_em_fmt,
                    TO_CHAR(p.criado_em, 'DD/MM/YYYY') AS criado_em_fmt,
                    TO_CHAR(p.atualizado_em, 'DD/MM/YYYY HH24:MI') AS atualizado_em_fmt,
                    TO_CHAR(p.data_ciencia_colaborador, 'DD/MM/YYYY HH24:MI') AS data_ciencia_fmt,
                    a.pontos_conhecimento,
                    a.pontos_habilidade,
                    a.pontos_atitude,
                    a.pontos_postura,
                    a.pontos_disciplina,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil, a.foto_colaborador) AS foto_url,
                    COALESCE(stats.total_itens, 0) AS total_itens,
                    COALESCE(stats.dentro_prazo, 0) AS dentro_prazo,
                    COALESCE(stats.concluidas, 0) AS concluidas,
                    COALESCE(stats.atraso_recuperavel, 0) AS atraso_recuperavel,
                    COALESCE(stats.comprometido, 0) AS comprometido,
                    COALESCE(stats.evidencia_ok, 0) AS evidencia_ok,
                    TO_CHAR(stats.prox_vencimento, 'DD/MM/YYYY') AS prox_vencimento_fmt
                FROM avaliacao_chapd_plano_acao p
                LEFT JOIN avaliacao_chapd a ON a.id = p.idavaliacao
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = p.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = p.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = p.idcliforemp
                LEFT JOIN LATERAL (
                    SELECT 
                        COUNT(*) AS total_itens,
                        SUM(CASE WHEN i.status = 'Dentro do Prazo' THEN 1 ELSE 0 END) AS dentro_prazo,
                        SUM(CASE WHEN i.status = 'Ação Concluída' THEN 1 ELSE 0 END) AS concluidas,
                        SUM(CASE WHEN i.status = 'Atraso Recuperável' THEN 1 ELSE 0 END) AS atraso_recuperavel,
                        SUM(CASE WHEN i.status = 'Cronograma Comprometido' THEN 1 ELSE 0 END) AS comprometido,
                        SUM(CASE WHEN i.evidencia_status = 'OK' THEN 1 ELSE 0 END) AS evidencia_ok,
                        MIN(CASE WHEN i.status <> 'Ação Concluída' THEN i.data_termino ELSE NULL END) AS prox_vencimento
                    FROM avaliacao_chapd_plano_acao_item i
                    WHERE i.idplano = p.id
                ) stats ON TRUE
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.atualizado_em DESC, p.id DESC
                LIMIT 200
            ";

            $st = $db->prepare($sql);
            $st->execute($binds);
            $planos = $st->fetchAll(PDO::FETCH_ASSOC);

            // Totais das 4 legendas da planilha considerando todas as ações cadastradas nos planos listados
            $kpis = [
                'total_planos' => count($planos),
                'total_etapas' => 0,
                'dentro_prazo' => 0,
                'concluidas' => 0,
                'atraso_recuperavel' => 0,
                'comprometido' => 0
            ];

            foreach ($planos as &$pl) {
                $pl['impresso'] = !empty($pl['impresso']) || ($pl['situacao_ciclo'] ?? '') === 'IMPRESSO' || ($pl['situacao_ciclo'] ?? '') === 'FECHADO';
                $pl['fechado'] = !empty($pl['fechado']) || ($pl['situacao_ciclo'] ?? '') === 'FECHADO';
                $pl['situacao_ciclo'] = $pl['fechado'] ? 'FECHADO' : ($pl['impresso'] ? 'IMPRESSO' : 'EM_ELABORACAO');

                $kpis['total_etapas'] += (int)$pl['total_itens'];
                $kpis['dentro_prazo'] += (int)$pl['dentro_prazo'];
                $kpis['concluidas'] += (int)$pl['concluidas'];
                $kpis['atraso_recuperavel'] += (int)$pl['atraso_recuperavel'];
                $kpis['comprometido'] += (int)$pl['comprometido'];
            }
            unset($pl);

            return $this->json($response, [
                'sucesso' => true,
                'kpis' => $kpis,
                'planos' => $planos
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao listar planos de ação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/avaliacao-chapd/planos-acao/{id}
     * Retorna os detalhes de um Plano de Ação 5W2H e todas as suas etapas
     */
    public function detalhesPlanoAcao(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDb();
            $id = (int)($args['id'] ?? 0);

            $st = $db->prepare("
                SELECT 
                    p.*,
                    COALESCE(p.situacao_ciclo, CASE WHEN COALESCE(p.fechado, FALSE) THEN 'FECHADO' WHEN COALESCE(p.impresso, FALSE) THEN 'IMPRESSO' ELSE 'EM_ELABORACAO' END) AS situacao_ciclo,
                    COALESCE(p.impresso, FALSE) AS impresso,
                    TO_CHAR(p.impresso_em, 'DD/MM/YYYY HH24:MI') AS impresso_em_fmt,
                    COALESCE(p.fechado, FALSE) AS fechado,
                    TO_CHAR(p.fechado_em, 'DD/MM/YYYY HH24:MI') AS fechado_em_fmt,
                    TO_CHAR(p.criado_em, 'DD/MM/YYYY') AS criado_em_fmt,
                    TO_CHAR(p.atualizado_em, 'DD/MM/YYYY HH24:MI') AS atualizado_em_fmt,
                    TO_CHAR(p.data_ciencia_colaborador, 'DD/MM/YYYY HH24:MI') AS data_ciencia_fmt,
                    a.pontos_conhecimento,
                    a.pontos_habilidade,
                    a.pontos_atitude,
                    a.pontos_postura,
                    a.pontos_disciplina,
                    a.pontos_fortes AS aval_pontos_fortes,
                    a.pontos_desenvolver AS aval_pontos_desenvolver,
                    COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil, a.foto_colaborador) AS foto_url,
                    COALESCE(TO_CHAR(cp.data_admissao_custom, 'DD/MM/YYYY'), TO_CHAR(e.data_admissao, 'DD/MM/YYYY')) AS data_admissao_fmt
                FROM avaliacao_chapd_plano_acao p
                LEFT JOIN avaliacao_chapd a ON a.id = p.idavaliacao
                LEFT JOIN empregado e ON e.idcliforemp = p.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = p.idcliforemp
                LEFT JOIN avaliacao_chapd_colaborador_foto cf ON cf.idcliforemp = p.idcliforemp
                LEFT JOIN usuario u ON u.idcliforemp = p.idcliforemp
                WHERE p.id = ?
            ");
            $st->execute([$id]);
            $plano = $st->fetch(PDO::FETCH_ASSOC);

            if (!$plano) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não encontrado.'], 404);
            }

            $plano['impresso'] = !empty($plano['impresso']) || ($plano['situacao_ciclo'] ?? '') === 'IMPRESSO' || ($plano['situacao_ciclo'] ?? '') === 'FECHADO';
            $plano['fechado'] = !empty($plano['fechado']) || ($plano['situacao_ciclo'] ?? '') === 'FECHADO';
            $plano['situacao_ciclo'] = $plano['fechado'] ? 'FECHADO' : ($plano['impresso'] ? 'IMPRESSO' : 'EM_ELABORACAO');

            $stItens = $db->prepare("
                SELECT 
                    id, idplano, ordem,
                    COALESCE(pilar_chapd, 'GERAL') AS pilar_chapd,
                    COALESCE(tipo_702010, '70_PRATICA') AS tipo_702010,
                    o_que, como, onde, quem,
                    TO_CHAR(data_inicio, 'YYYY-MM-DD') AS data_inicio,
                    TO_CHAR(data_termino, 'YYYY-MM-DD') AS data_termino,
                    TO_CHAR(data_inicio, 'DD/MM/YYYY') AS data_inicio_fmt,
                    TO_CHAR(data_termino, 'DD/MM/YYYY') AS data_termino_fmt,
                    status, observacao, evidencia_status, evidencia_auditoria
                FROM avaliacao_chapd_plano_acao_item
                WHERE idplano = ?
                ORDER BY ordem ASC, id ASC
            ");
            $stItens->execute([$id]);
            $itens = $stItens->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'sucesso' => true,
                'plano' => $plano,
                'itens' => $itens
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao buscar detalhes do Plano de Ação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/planos-acao/salvar
     * Cria ou atualiza um Plano de Ação 5W2H completo (cabeçalho + etapas da planilha + estrutura especialista PDI)
     */
    public function salvarPlanoAcao(Request $request, Response $response): Response
    {
        $db = $this->getDb();
        try {
            $user = $this->getUsuarioSessao($request);
            $body = $request->getParsedBody() ?? [];

            $id = !empty($body['id']) ? (int)$body['id'] : null;
            $idavaliacao = !empty($body['idavaliacao']) ? (int)$body['idavaliacao'] : null;
            $idcliforemp = !empty($body['idcliforemp']) ? (int)$body['idcliforemp'] : null;
            $nomeColaborador = trim($body['nome_colaborador'] ?? '');
            $cargo = trim($body['cargo'] ?? '');
            $setor = trim($body['setor'] ?? '');
            $idfilial = !empty($body['idfilial']) ? (int)$body['idfilial'] : 1;
            $ano = !empty($body['ano']) ? (int)$body['ano'] : (int)date('Y');
            $mes = !empty($body['mes']) ? (int)$body['mes'] : (int)date('n');
            $pontuacaoOrigem = isset($body['pontuacao_origem']) && $body['pontuacao_origem'] !== '' ? (float)$body['pontuacao_origem'] : null;
            $conceitoOrigem = !empty($body['conceito_origem']) ? trim($body['conceito_origem']) : null;
            $origemTipo = !empty($body['origem_tipo']) ? trim($body['origem_tipo']) : 'PDI_ABAIXO_85';

            $problema = trim($body['problema'] ?? '');
            $objetivo = trim($body['objetivo'] ?? '');
            $responsavel = trim($body['responsavel'] ?? $user['nome']);
            $aprovador = trim($body['aprovador'] ?? 'Robson Becker');
            $revisao = trim($body['revisao'] ?? date('d/m/Y'));
            $observacoesGerais = trim($body['observacoes_gerais'] ?? '');
            $metaPontuacao = isset($body['meta_pontuacao']) && $body['meta_pontuacao'] !== '' ? (float)$body['meta_pontuacao'] : 90.00;
            $prazoDiasReavaliacao = !empty($body['prazo_dias_reavaliacao']) ? (int)$body['prazo_dias_reavaliacao'] : 60;
            $pilaresCriticos = !empty($body['pilares_criticos']) ? trim((string)$body['pilares_criticos']) : null;
            $feedbackColaborador = array_key_exists('feedback_colaborador', $body) ? trim((string)$body['feedback_colaborador']) : null;
            $finalizarImprimir = !empty($body['finalizar_imprimir']) && $body['finalizar_imprimir'] !== 'false' && $body['finalizar_imprimir'] !== '0';
            $itens = is_array($body['itens'] ?? null) ? $body['itens'] : [];

            $situacaoCicloNova = $finalizarImprimir ? 'IMPRESSO' : 'EM_ELABORACAO';
            $impressoNovo = $finalizarImprimir;
            $impressoEm = $finalizarImprimir ? date('Y-m-d H:i:s') : null;
            $impressoPor = $finalizarImprimir ? ($user['nome'] ?? $user['login'] ?? 'Gestor') : null;

            if ($id) {
                $stCheckPlano = $db->prepare("
                    SELECT id,
                           COALESCE(situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo,
                           COALESCE(impresso, FALSE) AS impresso,
                           COALESCE(fechado, FALSE) AS fechado,
                           impresso_em, impresso_por
                    FROM avaliacao_chapd_plano_acao
                    WHERE id = ?
                ");
                $stCheckPlano->execute([$id]);
                $planoAtual = $stCheckPlano->fetch(PDO::FETCH_ASSOC);

                if (!$planoAtual) {
                    return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não encontrado.'], 404);
                }

                if (!empty($planoAtual['fechado']) || ($planoAtual['situacao_ciclo'] ?? '') === 'FECHADO') {
                    return $this->json($response, [
                        'sucesso' => false,
                        'mensagem' => 'Este Plano de Ação / PDI já foi FECHADO e encerrado e não permite mais alterações.'
                    ], 422);
                }

                if (!empty($planoAtual['impresso']) || ($planoAtual['situacao_ciclo'] ?? '') === 'IMPRESSO') {
                    $situacaoCicloNova = 'IMPRESSO';
                    $impressoNovo = true;
                    $impressoEm = $planoAtual['impresso_em'] ?: date('Y-m-d H:i:s');
                    $impressoPor = $planoAtual['impresso_por'] ?: ($user['nome'] ?? $user['login'] ?? 'Gestor');
                }
            }

            if ($problema === '') {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Informe o Problema / Ponto de Desenvolvimento no cabeçalho do plano.'], 400);
            }
            if ($objetivo === '') {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Informe o OBJETIVO esperado do Plano de Ação.'], 400);
            }

            // Se colaborador informado e nome vazio, busca do ERP + perfil custom
            if ($idcliforemp && $nomeColaborador === '') {
                $stCol = $db->prepare("
                    SELECT 
                        COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao)) AS nome,
                        COALESCE(NULLIF(TRIM(cp.cargo_custom), ''), TRIM(e.cargo), '') AS cargo,
                        COALESCE(NULLIF(TRIM(cp.setor_custom), ''), '') AS setor,
                        COALESCE(cp.idfilial_custom, c.idfilial, 1) AS idfilial
                    FROM cliforemp c
                    LEFT JOIN empregado e ON e.idcliforemp = c.idcliforemp
                    LEFT JOIN avaliacao_chapd_colaborador_perfil cp ON cp.idcliforemp = c.idcliforemp
                    WHERE c.idcliforemp = ?
                ");
                $stCol->execute([$idcliforemp]);
                $col = $stCol->fetch(PDO::FETCH_ASSOC);
                if ($col) {
                    $nomeColaborador = $col['nome'];
                    if ($cargo === '') $cargo = $col['cargo'];
                    if ($setor === '') $setor = $col['setor'];
                    $idfilial = (int)$col['idfilial'];
                }
            }

            // Determina status_geral automaticamente a partir das etapas 5W2H
            $itensValidos = [];
            $qtdConcluidas = 0;
            $qtdComprometido = 0;
            $qtdAtraso = 0;

            foreach ($itens as $idx => $it) {
                $oQue = trim($it['o_que'] ?? '');
                if ($oQue === '') {
                    continue;
                }
                $stItem = trim($it['status'] ?? 'Dentro do Prazo');
                if ($stItem === 'Ação Concluída') $qtdConcluidas++;
                elseif ($stItem === 'Cronograma Comprometido') $qtdComprometido++;
                elseif ($stItem === 'Atraso Recuperável') $qtdAtraso++;

                $pilarChapd = strtoupper(trim($it['pilar_chapd'] ?? 'GERAL'));
                if (!in_array($pilarChapd, ['C', 'H', 'A', 'P', 'D', 'GERAL'], true)) {
                    $pilarChapd = 'GERAL';
                }
                $tipo702010 = strtoupper(trim($it['tipo_702010'] ?? '70_PRATICA'));
                if (!in_array($tipo702010, ['70_PRATICA', '20_MENTORIA', '10_TREINAMENTO'], true)) {
                    $tipo702010 = '70_PRATICA';
                }

                $dataIni = !empty($it['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$it['data_inicio'])) ? trim((string)$it['data_inicio']) : null;
                $dataFim = !empty($it['data_termino']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)$it['data_termino'])) ? trim((string)$it['data_termino']) : null;

                $itensValidos[] = [
                    'ordem' => (int)($it['ordem'] ?? ($idx + 1)),
                    'pilar_chapd' => $pilarChapd,
                    'tipo_702010' => $tipo702010,
                    'o_que' => $oQue,
                    'como' => trim($it['como'] ?? ''),
                    'onde' => trim($it['onde'] ?? 'NTD') ?: 'NTD',
                    'quem' => trim($it['quem'] ?? $responsavel),
                    'data_inicio' => $dataIni,
                    'data_termino' => $dataFim,
                    'status' => $stItem,
                    'observacao' => trim($it['observacao'] ?? ''),
                    'evidencia_status' => in_array(strtoupper(trim($it['evidencia_status'] ?? 'P')), ['P', 'OK', 'NOK'], true)
                        ? strtoupper(trim($it['evidencia_status'] ?? 'P'))
                        : 'P',
                    'evidencia_auditoria' => trim($it['evidencia_auditoria'] ?? '')
                ];
            }

            if (empty($itensValidos)) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Adicione pelo menos 1 etapa ("O QUE") na tabela 5W2H do Plano de Ação.'], 400);
            }

            $statusGeral = trim($body['status_geral'] ?? '');
            if ($statusGeral === '' || $statusGeral === 'AUTO') {
                if ($qtdConcluidas === count($itensValidos)) {
                    $statusGeral = 'ACAO_CONCLUIDA';
                } elseif ($qtdComprometido > 0) {
                    $statusGeral = 'CRONOGRAMA_COMPROMETIDO';
                } elseif ($qtdAtraso > 0) {
                    $statusGeral = 'ATRASO_RECUPERAVEL';
                } else {
                    $statusGeral = 'DENTRO_DO_PRAZO';
                }
            }

            $db->beginTransaction();

            if ($id) {
                $stUpd = $db->prepare("
                    UPDATE avaliacao_chapd_plano_acao SET
                        idavaliacao = :idavaliacao,
                        idcliforemp = :idcliforemp,
                        nome_colaborador = :nome_colaborador,
                        cargo = :cargo,
                        setor = :setor,
                        idfilial = :idfilial,
                        ano = :ano,
                        mes = :mes,
                        pontuacao_origem = :pontuacao_origem,
                        conceito_origem = :conceito_origem,
                        origem_tipo = :origem_tipo,
                        problema = :problema,
                        objetivo = :objetivo,
                        responsavel = :responsavel,
                        aprovador = :aprovador,
                        revisao = :revisao,
                        status_geral = :status_geral,
                        observacoes_gerais = :observacoes_gerais,
                        meta_pontuacao = :meta_pontuacao,
                        prazo_dias_reavaliacao = :prazo_dias_reavaliacao,
                        pilares_criticos = :pilares_criticos,
                        feedback_colaborador = COALESCE(:feedback_colaborador, feedback_colaborador),
                        situacao_ciclo = :situacao_ciclo,
                        impresso = :impresso,
                        impresso_em = :impresso_em,
                        impresso_por = :impresso_por,
                        atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stUpd->execute([
                    ':idavaliacao' => $idavaliacao,
                    ':idcliforemp' => $idcliforemp,
                    ':nome_colaborador' => $nomeColaborador,
                    ':cargo' => $cargo,
                    ':setor' => $setor,
                    ':idfilial' => $idfilial,
                    ':ano' => $ano,
                    ':mes' => $mes,
                    ':pontuacao_origem' => $pontuacaoOrigem,
                    ':conceito_origem' => $conceitoOrigem,
                    ':origem_tipo' => $origemTipo,
                    ':problema' => $problema,
                    ':objetivo' => $objetivo,
                    ':responsavel' => $responsavel,
                    ':aprovador' => $aprovador,
                    ':revisao' => $revisao,
                    ':status_geral' => $statusGeral,
                    ':observacoes_gerais' => $observacoesGerais,
                    ':meta_pontuacao' => $metaPontuacao,
                    ':prazo_dias_reavaliacao' => $prazoDiasReavaliacao,
                    ':pilares_criticos' => $pilaresCriticos,
                    ':feedback_colaborador' => $feedbackColaborador,
                    ':situacao_ciclo' => $situacaoCicloNova,
                    ':impresso' => $impressoNovo ? 'true' : 'false',
                    ':impresso_em' => $impressoEm,
                    ':impresso_por' => $impressoPor,
                    ':id' => $id
                ]);

                $db->prepare("DELETE FROM avaliacao_chapd_plano_acao_item WHERE idplano = ?")->execute([$id]);
            } else {
                $stIns = $db->prepare("
                    INSERT INTO avaliacao_chapd_plano_acao (
                        idavaliacao, idcliforemp, nome_colaborador, cargo, setor, idfilial,
                        ano, mes, pontuacao_origem, conceito_origem, origem_tipo,
                        problema, objetivo, responsavel, aprovador, revisao,
                        status_geral, observacoes_gerais,
                        meta_pontuacao, prazo_dias_reavaliacao, pilares_criticos, feedback_colaborador,
                        situacao_ciclo, impresso, impresso_em, impresso_por,
                        criado_por
                    ) VALUES (
                        :idavaliacao, :idcliforemp, :nome_colaborador, :cargo, :setor, :idfilial,
                        :ano, :mes, :pontuacao_origem, :conceito_origem, :origem_tipo,
                        :problema, :objetivo, :responsavel, :aprovador, :revisao,
                        :status_geral, :observacoes_gerais,
                        :meta_pontuacao, :prazo_dias_reavaliacao, :pilares_criticos, :feedback_colaborador,
                        :situacao_ciclo, :impresso, :impresso_em, :impresso_por,
                        :criado_por
                    ) RETURNING id
                ");
                $stIns->execute([
                    ':idavaliacao' => $idavaliacao,
                    ':idcliforemp' => $idcliforemp,
                    ':nome_colaborador' => $nomeColaborador,
                    ':cargo' => $cargo,
                    ':setor' => $setor,
                    ':idfilial' => $idfilial,
                    ':ano' => $ano,
                    ':mes' => $mes,
                    ':pontuacao_origem' => $pontuacaoOrigem,
                    ':conceito_origem' => $conceitoOrigem,
                    ':origem_tipo' => $origemTipo,
                    ':problema' => $problema,
                    ':objetivo' => $objetivo,
                    ':responsavel' => $responsavel,
                    ':aprovador' => $aprovador,
                    ':revisao' => $revisao,
                    ':status_geral' => $statusGeral,
                    ':observacoes_gerais' => $observacoesGerais,
                    ':meta_pontuacao' => $metaPontuacao,
                    ':prazo_dias_reavaliacao' => $prazoDiasReavaliacao,
                    ':pilares_criticos' => $pilaresCriticos,
                    ':feedback_colaborador' => $feedbackColaborador,
                    ':situacao_ciclo' => $situacaoCicloNova,
                    ':impresso' => $impressoNovo ? 'true' : 'false',
                    ':impresso_em' => $impressoEm,
                    ':impresso_por' => $impressoPor,
                    ':criado_por' => $user['login']
                ]);
                $id = (int)$stIns->fetchColumn();
            }

            $stInsItem = $db->prepare("
                INSERT INTO avaliacao_chapd_plano_acao_item (
                    idplano, ordem, pilar_chapd, tipo_702010, o_que, como, onde, quem,
                    data_inicio, data_termino, status, observacao,
                    evidencia_status, evidencia_auditoria
                ) VALUES (
                    :idplano, :ordem, :pilar_chapd, :tipo_702010, :o_que, :como, :onde, :quem,
                    :data_inicio, :data_termino, :status, :observacao,
                    :evidencia_status, :evidencia_auditoria
                )
            ");

            foreach ($itensValidos as $item) {
                $stInsItem->execute([
                    ':idplano' => $id,
                    ':ordem' => $item['ordem'],
                    ':pilar_chapd' => $item['pilar_chapd'],
                    ':tipo_702010' => $item['tipo_702010'],
                    ':o_que' => $item['o_que'],
                    ':como' => $item['como'],
                    ':onde' => $item['onde'],
                    ':quem' => $item['quem'],
                    ':data_inicio' => $item['data_inicio'],
                    ':data_termino' => $item['data_termino'],
                    ':status' => $item['status'],
                    ':observacao' => $item['observacao'],
                    ':evidencia_status' => $item['evidencia_status'],
                    ':evidencia_auditoria' => $item['evidencia_auditoria']
                ]);
            }

            if ($idavaliacao) {
                $db->prepare("UPDATE avaliacao_chapd SET requer_pdi = TRUE, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?")->execute([$idavaliacao]);
            }

            $db->commit();

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => $finalizarImprimir
                    ? 'PDI & Plano de Ação finalizado e liberado para impressão/assinatura!'
                    : 'PDI & Plano de Ação 5W2H salvo em elaboração com sucesso!',
                'id' => $id,
                'status_geral' => $statusGeral,
                'situacao_ciclo' => $situacaoCicloNova,
                'impresso' => $impressoNovo,
                'fechado' => false
            ]);

        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao salvar Plano de Ação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/planos-acao/finalizar-impressao
     * Marca o Plano de Ação / PDI como IMPRESSO (assinatura / em execução), bloqueando exclusão e alteração de escopo
     */
    public function finalizarImpressaoPlanoAcao(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $user = $this->getUsuarioSessao($request);
            $body = $request->getParsedBody() ?? [];
            $id = (int)($body['id'] ?? 0);

            if ($id <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não informado.'], 400);
            }

            $st = $db->prepare("SELECT id, COALESCE(situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo, COALESCE(fechado, FALSE) AS fechado FROM avaliacao_chapd_plano_acao WHERE id = ?");
            $st->execute([$id]);
            $atual = $st->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não encontrado.'], 404);
            }

            if (!empty($atual['fechado']) || ($atual['situacao_ciclo'] ?? '') === 'FECHADO') {
                return $this->json($response, [
                    'sucesso' => true,
                    'mensagem' => 'Plano já está fechado; liberando reimpressão.',
                    'id' => $id,
                    'situacao_ciclo' => 'FECHADO',
                    'impresso' => true,
                    'fechado' => true
                ]);
            }

            $resp = $user['nome'] ?? $user['login'] ?? 'Gestor';
            $db->prepare("
                UPDATE avaliacao_chapd_plano_acao
                SET situacao_ciclo = 'IMPRESSO',
                    impresso = TRUE,
                    impresso_em = COALESCE(impresso_em, CURRENT_TIMESTAMP),
                    impresso_por = COALESCE(NULLIF(impresso_por, ''), ?),
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$resp, $id]);

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Plano marcado como Impresso/Em Execução! Escopo protegido contra exclusão.',
                'id' => $id,
                'situacao_ciclo' => 'IMPRESSO',
                'impresso' => true,
                'impresso_em_fmt' => date('d/m/Y H:i'),
                'impresso_por' => $resp
            ]);
        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao finalizar impressão do Plano: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/planos-acao/fechar
     * Encerra definitivamente o ciclo do PDI/Plano de Ação (mesmo que já impresso), registrando parecer e travando em leitura
     */
    public function fecharPlanoAcao(Request $request, Response $response): Response
    {
        $db = $this->getDb();
        try {
            $user = $this->getUsuarioSessao($request);
            $body = $request->getParsedBody() ?? [];
            $id = (int)($body['id'] ?? 0);
            $parecer = trim((string)($body['parecer_fechamento'] ?? ''));
            $concluirEtapas = !isset($body['concluir_etapas']) || ($body['concluir_etapas'] !== false && $body['concluir_etapas'] !== 'false' && $body['concluir_etapas'] !== 0 && $body['concluir_etapas'] !== '0');

            if ($id <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não informado.'], 400);
            }

            $st = $db->prepare("SELECT id, COALESCE(situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo, COALESCE(fechado, FALSE) AS fechado FROM avaliacao_chapd_plano_acao WHERE id = ?");
            $st->execute([$id]);
            $atual = $st->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não encontrado.'], 404);
            }

            if (!empty($atual['fechado']) || ($atual['situacao_ciclo'] ?? '') === 'FECHADO') {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Este PDI já está fechado e encerrado.'], 422);
            }

            $resp = $user['nome'] ?? $user['login'] ?? 'Gestor';

            $db->beginTransaction();

            if ($concluirEtapas) {
                $db->prepare("
                    UPDATE avaliacao_chapd_plano_acao_item
                    SET status = 'Ação Concluída',
                        atualizado_em = CURRENT_TIMESTAMP
                    WHERE idplano = ? AND status <> 'Ação Concluída'
                ")->execute([$id]);
            }

            $db->prepare("
                UPDATE avaliacao_chapd_plano_acao
                SET situacao_ciclo = 'FECHADO',
                    fechado = TRUE,
                    fechado_em = CURRENT_TIMESTAMP,
                    fechado_por = ?,
                    parecer_fechamento = ?,
                    status_geral = 'ACAO_CONCLUIDA',
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$resp, $parecer !== '' ? $parecer : 'Ciclo PDI encerrado pelo gestor.', $id]);

            $db->commit();

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Ciclo do PDI / Plano de Ação fechado com sucesso!',
                'id' => $id,
                'situacao_ciclo' => 'FECHADO',
                'fechado' => true,
                'fechado_em_fmt' => date('d/m/Y H:i'),
                'fechado_por' => $resp
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao fechar Plano de Ação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/planos-acao/item/status
     * Atualiza rapidamente o status ou evidência de uma etapa 5W2H
     */
    public function atualizarStatusItemPlano(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $body = $request->getParsedBody() ?? [];
            $idItem = (int)($body['id'] ?? 0);

            if ($idItem <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Etapa não informada.'], 400);
            }

            $stGet = $db->prepare("
                SELECT i.idplano, i.status, i.evidencia_status, i.observacao, i.evidencia_auditoria,
                       COALESCE(p.fechado, FALSE) AS plano_fechado,
                       COALESCE(p.situacao_ciclo, 'EM_ELABORACAO') AS plano_situacao_ciclo
                FROM avaliacao_chapd_plano_acao_item i
                JOIN avaliacao_chapd_plano_acao p ON p.id = i.idplano
                WHERE i.id = ?
            ");
            $stGet->execute([$idItem]);
            $atual = $stGet->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Etapa não encontrada.'], 404);
            }

            if (!empty($atual['plano_fechado']) || ($atual['plano_situacao_ciclo'] ?? '') === 'FECHADO') {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Este Plano de Ação está FECHADO e não permite alterar etapas.'
                ], 422);
            }

            $novoStatus = !empty($body['status']) ? trim($body['status']) : $atual['status'];
            $novaEvidStatus = !empty($body['evidencia_status']) ? strtoupper(trim($body['evidencia_status'])) : $atual['evidencia_status'];
            $novaObs = array_key_exists('observacao', $body) ? trim((string)$body['observacao']) : $atual['observacao'];
            $novaEvidAud = array_key_exists('evidencia_auditoria', $body) ? trim((string)$body['evidencia_auditoria']) : $atual['evidencia_auditoria'];

            $stUpd = $db->prepare("
                UPDATE avaliacao_chapd_plano_acao_item
                SET status = ?, evidencia_status = ?, observacao = ?, evidencia_auditoria = ?, atualizado_em = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stUpd->execute([$novoStatus, $novaEvidStatus, $novaObs, $novaEvidAud, $idItem]);

            // Recalcula status geral do plano pai
            $idPlano = (int)$atual['idplano'];
            $stStats = $db->prepare("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Ação Concluída' THEN 1 ELSE 0 END) AS concluidas,
                    SUM(CASE WHEN status = 'Cronograma Comprometido' THEN 1 ELSE 0 END) AS comprometido,
                    SUM(CASE WHEN status = 'Atraso Recuperável' THEN 1 ELSE 0 END) AS atraso
                FROM avaliacao_chapd_plano_acao_item
                WHERE idplano = ?
            ");
            $stStats->execute([$idPlano]);
            $s = $stStats->fetch(PDO::FETCH_ASSOC);

            $statusGeral = 'DENTRO_DO_PRAZO';
            if ((int)$s['total'] > 0 && (int)$s['concluidas'] === (int)$s['total']) {
                $statusGeral = 'ACAO_CONCLUIDA';
            } elseif ((int)$s['comprometido'] > 0) {
                $statusGeral = 'CRONOGRAMA_COMPROMETIDO';
            } elseif ((int)$s['atraso'] > 0) {
                $statusGeral = 'ATRASO_RECUPERAVEL';
            }

            $db->prepare("UPDATE avaliacao_chapd_plano_acao SET status_geral = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?")->execute([$statusGeral, $idPlano]);

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Status da etapa atualizado!',
                'status_geral' => $statusGeral
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao atualizar etapa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /v1/avaliacao-chapd/planos-acao/{id}
     * Exclui um Plano de Ação e suas etapas 5W2H (bloqueado se já impresso ou fechado)
     */
    public function excluirPlanoAcao(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDb();
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'ID inválido.'], 400);
            }

            $st = $db->prepare("
                SELECT id,
                       COALESCE(situacao_ciclo, 'EM_ELABORACAO') AS situacao_ciclo,
                       COALESCE(impresso, FALSE) AS impresso,
                       COALESCE(fechado, FALSE) AS fechado
                FROM avaliacao_chapd_plano_acao
                WHERE id = ?
            ");
            $st->execute([$id]);
            $atual = $st->fetch(PDO::FETCH_ASSOC);

            if (!$atual) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Plano de Ação não encontrado.'], 404);
            }

            if (!empty($atual['impresso']) || !empty($atual['fechado']) || in_array($atual['situacao_ciclo'] ?? '', ['IMPRESSO', 'FECHADO'], true)) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Planos de Ação já impressos para assinatura ou fechados não podem ser excluídos.'
                ], 422);
            }

            $db->prepare("DELETE FROM avaliacao_chapd_plano_acao WHERE id = ?")->execute([$id]);

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Plano de Ação excluído com sucesso!'
            ]);
        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao excluir Plano de Ação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/avaliacao-chapd/feedback-colaborador
     * Registra o feedback e a ciência do colaborador na avaliação ou no PDI/Plano de Ação
     */
    public function salvarFeedbackColaborador(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $body = $request->getParsedBody() ?? [];

            $idavaliacao = !empty($body['idavaliacao']) ? (int)$body['idavaliacao'] : 0;
            $idplano = !empty($body['idplano']) ? (int)$body['idplano'] : 0;
            $feedback = trim((string)($body['feedback_colaborador'] ?? ''));

            if ($idavaliacao <= 0 && $idplano <= 0) {
                return $this->json($response, ['sucesso' => false, 'mensagem' => 'Informe a avaliação ou o PDI para registrar o feedback.'], 400);
            }

            if ($idavaliacao > 0) {
                $db->prepare("
                    UPDATE avaliacao_chapd
                    SET feedback_colaborador = ?, data_ciencia_colaborador = CURRENT_TIMESTAMP, atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([$feedback, $idavaliacao]);
            }

            if ($idplano > 0) {
                $db->prepare("
                    UPDATE avaliacao_chapd_plano_acao
                    SET feedback_colaborador = ?, data_ciencia_colaborador = CURRENT_TIMESTAMP, atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([$feedback, $idplano]);
            }

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Feedback e ciência do colaborador registrados com sucesso!',
                'data_ciencia_fmt' => date('d/m/Y H:i')
            ]);
        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao salvar feedback do colaborador: ' . $e->getMessage()
            ], 500);
        }
    }
}
