<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use Throwable;

class TreinamentoController
{
    private function getDb(): PDO
    {
        $db = getPDO();
        $db->exec("SET client_encoding = 'UTF8'");
        return $db;
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }

    /**
     * Extrai permissões ativas do usuário e verifica se é admin
     */
    private function resolverPermissoesUsuario(Request $request, PDO $db): array
    {
        $user = (array)($request->getAttribute('user') ?? []);
        $idusuario = (int)($user['idusuario'] ?? 0);
        $permissoes = (array)($user['permissoes'] ?? []);

        // É admin se flag estiver true, ou se tiver 'admin' no array de permissões
        $isAdmin = !empty($user['is_admin']) || in_array('admin', $permissoes, true);

        // Se tiver idusuario, faz verificação em tempo real no banco
        if ($idusuario > 0) {
            try {
                // Checa tabela de admins
                $stAdmin = $db->prepare("SELECT 1 FROM usuarios_admin WHERE idusuario = ? AND ativo = TRUE LIMIT 1");
                $stAdmin->execute([$idusuario]);
                if ($stAdmin->fetchColumn()) {
                    $isAdmin = true;
                }

                // Busca slugs autorizados em usuario_permissoes
                $stPerm = $db->prepare("
                    SELECT sm.slug 
                    FROM usuario_permissoes up
                    JOIN sistema_modulos sm ON sm.id = up.idmodulo
                    WHERE up.idusuario = ? AND sm.ativo = TRUE
                ");
                $stPerm->execute([$idusuario]);
                $dbSlugs = $stPerm->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($dbSlugs)) {
                    $permissoes = array_unique(array_merge($permissoes, $dbSlugs));
                }
            } catch (Throwable $e) {
                error_log("Erro ao resolver permissoes do usuario no TreinamentoController: " . $e->getMessage());
            }
        }

        // Aliases e equivalências
        if (in_array('transferencia-matriz-filial', $permissoes, true) || in_array('transferencia-filial-matriz', $permissoes, true)) {
            $permissoes[] = 'transferencia-compra';
        }
        if (in_array('transferencia-compra', $permissoes, true)) {
            $permissoes[] = 'transferencia-matriz-filial';
            $permissoes[] = 'transferencia-filial-matriz';
        }

        // Central de treinamentos em si
        $permissoes[] = 'treinamentos';

        return [
            'isAdmin' => $isAdmin,
            'idusuario' => $idusuario,
            'permissoes' => array_values(array_unique($permissoes))
        ];
    }

    /**
     * GET /v1/treinamentos
     * Lista os manuais ativos aos quais o usuário logado tem permissão
     */
    public function listar(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $auth = $this->resolverPermissoesUsuario($request, $db);
            $params = $request->getQueryParams();
            $termo = trim($params['q'] ?? '');
            $setor = trim($params['setor'] ?? '');

            $where = ['ativo = TRUE'];
            $binds = [];

            // Se NÃO for admin, restringe estritamente aos módulos permitidos
            if (!$auth['isAdmin']) {
                $slugs = $auth['permissoes'];
                if (empty($slugs)) {
                    return $this->json($response, [
                        'sucesso' => true,
                        'total' => 0,
                        'setores' => [],
                        'manuais' => []
                    ]);
                }
                $placeholders = [];
                foreach ($slugs as $idx => $slug) {
                    $key = ":perm_{$idx}";
                    $placeholders[] = $key;
                    $binds[$key] = $slug;
                }
                $where[] = "modulo_slug IN (" . implode(', ', $placeholders) . ")";
            }

            if (!empty($setor)) {
                $where[] = 'setor = :setor';
                $binds[':setor'] = $setor;
            }

            if (!empty($termo)) {
                $where[] = '(titulo ILIKE :termo OR subtitulo ILIKE :termo OR objetivo_geral ILIKE :termo OR modulo_slug ILIKE :termo)';
                $binds[':termo'] = "%{$termo}%";
            }

            $sql = "
                SELECT 
                    id, modulo_slug, titulo, subtitulo, setor, icone, versao, autor,
                    objetivo_geral, ordem,
                    TO_CHAR(atualizado_em, 'DD/MM/YYYY') AS data_atualizacao
                FROM sistema_manuais
                WHERE " . implode(' AND ', $where) . "
                ORDER BY setor ASC, ordem ASC, titulo ASC
            ";
            $st = $db->prepare($sql);
            $st->execute($binds);
            $manuais = $st->fetchAll(PDO::FETCH_ASSOC);

            // Setores disponíveis para este usuário
            $setores = array_values(array_unique(array_filter(array_map(fn($m) => $m['setor'] ?? null, $manuais))));
            sort($setores);

            return $this->json($response, [
                'sucesso' => true,
                'total' => count($manuais),
                'is_admin' => $auth['isAdmin'],
                'setores' => $setores,
                'manuais' => $manuais
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao listar manuais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/treinamentos/{slug}
     * Retorna o manual completo com validação de permissão
     */
    public function obterPorSlug(Request $request, Response $response, array $args): Response
    {
        try {
            $db = $this->getDb();
            $auth = $this->resolverPermissoesUsuario($request, $db);
            $slug = trim($args['slug'] ?? '');

            // Trata aliases conhecidos e normaliza underscore para hífen e vice-versa
            $slugHifen = str_replace('_', '-', $slug);
            $slugUnder = str_replace('-', '_', $slug);
            $slugBusca = array_values(array_unique([$slug, $slugHifen, $slugUnder]));
            if (in_array('transferencia-compra', $slugBusca, true) || in_array('transferencia_compra', $slugBusca, true)) {
                $slugBusca[] = 'transferencia-matriz-filial';
                $slugBusca[] = 'transferencia_matriz_filial';
            } elseif (in_array('transferencia-matriz-filial', $slugBusca, true) || in_array('transferencia_matriz_filial', $slugBusca, true)) {
                $slugBusca[] = 'transferencia-compra';
                $slugBusca[] = 'transferencia_compra';
            }
            $slugBusca = array_values(array_unique($slugBusca));

            // Se NÃO for admin, verifica se o usuário tem permissão para este slug
            if (!$auth['isAdmin']) {
                $autorizado = false;
                foreach ($slugBusca as $s) {
                    if (in_array($s, $auth['permissoes'], true)) {
                        $autorizado = true;
                        break;
                    }
                }

                if (!$autorizado) {
                    return $this->json($response, [
                        'sucesso' => false,
                        'bloqueado' => true,
                        'mensagem' => "Acesso restrito: Você não possui permissão para visualizar o treinamento do módulo '{$slug}'."
                    ], 403);
                }
            }

            $inPlaceholders = implode(',', array_fill(0, count($slugBusca), '?'));
            $st = $db->prepare("SELECT * FROM sistema_manuais WHERE modulo_slug IN ({$inPlaceholders}) AND ativo = TRUE ORDER BY id ASC LIMIT 1");
            $st->execute($slugBusca);
            $manual = $st->fetch(PDO::FETCH_ASSOC);

            if (!$manual) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => "Manual para o módulo '{$slug}' não encontrado."
                ], 404);
            }

            // Decodifica JSONBs
            $manual['cards_kpis'] = json_decode($manual['cards_kpis'] ?? '[]', true) ?: [];
            $manual['acoes_botoes'] = json_decode($manual['acoes_botoes'] ?? '[]', true) ?: [];
            $manual['relatorios_tabelas'] = json_decode($manual['relatorios_tabelas'] ?? '[]', true) ?: [];
            $manual['duvidas_frequentes'] = json_decode($manual['duvidas_frequentes'] ?? '[]', true) ?: [];

            return $this->json($response, [
                'sucesso' => true,
                'is_admin' => $auth['isAdmin'],
                'manual' => $manual
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao buscar manual: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/treinamentos/salvar
     * Atualização do manual (restrito a administradores)
     */
    public function salvar(Request $request, Response $response): Response
    {
        try {
            $db = $this->getDb();
            $auth = $this->resolverPermissoesUsuario($request, $db);

            if (!$auth['isAdmin']) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Apenas administradores podem atualizar manuais de treinamento.'
                ], 403);
            }

            $body = $request->getParsedBody() ?? [];

            $slug = trim($body['modulo_slug'] ?? '');
            $titulo = trim($body['titulo'] ?? '');
            $subtitulo = trim($body['subtitulo'] ?? '');
            $setor = trim($body['setor'] ?? 'Geral');
            $icone = trim($body['icone'] ?? 'fa-book-bookmark');
            $versao = trim($body['versao'] ?? '1.0');
            $autor = trim($body['autor'] ?? 'Tech Lead');
            $objetivo = trim($body['objetivo_geral'] ?? '');

            $cardsKpis = is_array($body['cards_kpis'] ?? null) ? json_encode($body['cards_kpis'], JSON_UNESCAPED_UNICODE) : ($body['cards_kpis'] ?? '[]');
            $acoesBotoes = is_array($body['acoes_botoes'] ?? null) ? json_encode($body['acoes_botoes'], JSON_UNESCAPED_UNICODE) : ($body['acoes_botoes'] ?? '[]');
            $relatorios = is_array($body['relatorios_tabelas'] ?? null) ? json_encode($body['relatorios_tabelas'], JSON_UNESCAPED_UNICODE) : ($body['relatorios_tabelas'] ?? '[]');
            $duvidas = is_array($body['duvidas_frequentes'] ?? null) ? json_encode($body['duvidas_frequentes'], JSON_UNESCAPED_UNICODE) : ($body['duvidas_frequentes'] ?? '[]');

            if (empty($slug) || empty($titulo) || empty($objetivo)) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Slug, título e objetivo geral são campos obrigatórios.'
                ], 400);
            }

            $sql = "
                INSERT INTO sistema_manuais (
                    modulo_slug, titulo, subtitulo, setor, icone, versao, autor, objetivo_geral,
                    cards_kpis, acoes_botoes, relatorios_tabelas, duvidas_frequentes, ativo
                ) VALUES (
                    :modulo_slug, :titulo, :subtitulo, :setor, :icone, :versao, :autor, :objetivo_geral,
                    :cards_kpis, :acoes_botoes, :relatorios_tabelas, :duvidas_frequentes, TRUE
                )
                ON CONFLICT (modulo_slug) DO UPDATE SET
                    titulo = EXCLUDED.titulo,
                    subtitulo = EXCLUDED.subtitulo,
                    setor = EXCLUDED.setor,
                    icone = EXCLUDED.icone,
                    versao = EXCLUDED.versao,
                    autor = EXCLUDED.autor,
                    objetivo_geral = EXCLUDED.objetivo_geral,
                    cards_kpis = EXCLUDED.cards_kpis,
                    acoes_botoes = EXCLUDED.acoes_botoes,
                    relatorios_tabelas = EXCLUDED.relatorios_tabelas,
                    duvidas_frequentes = EXCLUDED.duvidas_frequentes,
                    atualizado_em = CURRENT_TIMESTAMP
            ";
            $st = $db->prepare($sql);
            $st->execute([
                ':modulo_slug' => $slug,
                ':titulo' => $titulo,
                ':subtitulo' => $subtitulo,
                ':setor' => $setor,
                ':icone' => $icone,
                ':versao' => $versao,
                ':autor' => $autor,
                ':objetivo_geral' => $objetivo,
                ':cards_kpis' => $cardsKpis,
                ':acoes_botoes' => $acoesBotoes,
                ':relatorios_tabelas' => $relatorios,
                ':duvidas_frequentes' => $duvidas
            ]);

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => 'Manual de treinamento atualizado com sucesso!'
            ]);

        } catch (Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao salvar manual: ' . $e->getMessage()
            ], 500);
        }
    }
}

