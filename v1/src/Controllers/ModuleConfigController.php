<?php
namespace Nutricional\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ModuleConfigController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->getAttribute('user') ?? [];
        $user = is_object($user) ? (array)$user : (array)$user;
        return !empty($user['is_admin']) || in_array('admin', (array)($user['permissoes'] ?? []), true);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function exigirAdmin(Request $request, Response $response): ?Response
    {
        return $this->isAdmin($request)
            ? null
            : $this->json($response, ['success' => false, 'error' => 'Acesso restrito a administradores.'], 403);
    }

    private function pgBool($v): bool
    {
        return $v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1';
    }

    private function body(Request $request): array
    {
        $data = json_decode((string)$request->getBody(), true);
        return is_array($data) ? $data : [];
    }

    /** GET /v1/admin/config-modulos/guia */
    public function listarGuia(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        try {
            $rows = $this->pdo->query(
                'SELECT id, ncm, descricao, st_pr, mva_percentual, aliquota_interna_percentual,
                        credito_interestadual_percentual, operador_mva, operador_guia,
                        vigencia_inicio, ativo, atualizado_por, atualizado_em
                 FROM guia_transferencia_ncm_regra
                 WHERE st_pr = TRUE
                 ORDER BY ncm, vigencia_inicio DESC, id DESC'
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->json($response, ['success' => true, 'regras' => $rows]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao carregar regras da guia: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível carregar as regras da guia.'], 500);
        }
    }

    /** GET /v1/admin/config-modulos/guia/ncms */
    public function listarNcms(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $query = $request->getQueryParams();
        $rawImposto = trim((string)($query['idimposto'] ?? ''));
        if ($rawImposto !== '' && !ctype_digit($rawImposto)) {
            return $this->json($response, ['success' => false, 'error' => 'Imposto inválido.'], 400);
        }

        try {
            $sql = "SELECT n.ncm, COUNT(DISTINCT n.iditem) AS quantidade_itens, MIN(n.descricao) AS exemplo_item,
                           MAX(n.mva_erp) AS mva_erp,
                           EXISTS (SELECT 1 FROM guia_transferencia_ncm_regra r
                                   WHERE r.ncm = n.ncm AND r.ativo AND r.st_pr) AS participa
                    FROM (
                        SELECT regexp_replace(cf.classificacao, '\\D', '', 'g') AS ncm, i.iditem, i.descricao,
                               ie.percmargemst_interno AS mva_erp
                        FROM estoque_filial ef
                        JOIN item i ON i.iditem = ef.iditem AND i.inativo = 'N'
                        JOIN classificacaofiscal cf ON cf.idclassificacaofiscal = i.idclassfiscal
                        LEFT JOIN imposto_estado ie ON ie.idimposto = ef.idimposto AND ie.uf = 'PR'
                             AND ie.tipo_enquadramento = 1 AND ie.idsituacaotributaria = 10
                        WHERE ef.idfilial = 1
                          AND regexp_replace(cf.classificacao, '\\D', '', 'g') ~ '^[0-9]{8}$'
                          AND regexp_replace(cf.classificacao, '\\D', '', 'g') <> '00000000'";
            $params = [];
            if ($rawImposto !== '') {
                $sql .= ' AND ef.idimposto = ?';
                $params[] = (int)$rawImposto;
            }
            $stmt = $this->pdo->prepare($sql . ') n GROUP BY n.ncm ORDER BY n.ncm');
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $impostos = $this->pdo->query(
                "SELECT im.idimposto, TRIM(im.descricao) AS descricao,
                        COUNT(DISTINCT ef.iditem) AS quantidade_itens,
                        COALESCE(BOOL_OR(ie.idsituacaotributaria = 10), FALSE) AS st_pr,
                        MAX(ie.percmargemst_interno) AS mva_erp
                 FROM imposto im
                 JOIN estoque_filial ef ON ef.idimposto = im.idimposto AND ef.idfilial = 1
                 JOIN item i ON i.iditem = ef.iditem AND i.inativo = 'N'
                 LEFT JOIN imposto_estado ie ON ie.idimposto = im.idimposto AND ie.uf = 'PR'
                      AND ie.tipo_enquadramento = 1
                 GROUP BY im.idimposto, im.descricao
                 ORDER BY st_pr DESC, im.descricao"
            )->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, ['success' => true, 'ncms' => $rows, 'impostos' => $impostos]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao carregar NCMs dos itens: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível carregar os NCMs dos itens.'], 500);
        }
    }

    /** POST /v1/admin/config-modulos/guia */
    public function salvarGuia(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $in = $this->body($request);
        $user = $request->getAttribute('user') ?? [];
        $user = is_object($user) ? (array)$user : (array)$user;
        $usuario = (string)($user['username'] ?? 'admin');

        try {
            if (array_key_exists('desativar_id', $in)) {
                $rawId = (string)$in['desativar_id'];
                if (!ctype_digit($rawId) || (int)$rawId < 1) {
                    return $this->json($response, ['success' => false, 'error' => 'Identificador da regra inválido.'], 400);
                }
                $stmt = $this->pdo->prepare(
                    'UPDATE guia_transferencia_ncm_regra
                     SET ativo = FALSE, atualizado_por = ?, atualizado_em = NOW()
                     WHERE id = ? AND ativo = TRUE'
                );
                $stmt->execute([$usuario, (int)$rawId]);
                if ($stmt->rowCount() === 0) {
                    $exists = $this->pdo->prepare('SELECT 1 FROM guia_transferencia_ncm_regra WHERE id = ?');
                    $exists->execute([(int)$rawId]);
                    if (!$exists->fetchColumn()) {
                        return $this->json($response, ['success' => false, 'error' => 'Regra da guia não encontrada.'], 404);
                    }
                }
                return $this->json($response, ['success' => true]);
            }

            $lista = isset($in['ncms']) && is_array($in['ncms']) ? $in['ncms'] : [$in['ncm'] ?? ''];
            $ncms = [];
            foreach ($lista as $item) {
                $limpo = preg_replace('/\D/', '', (string)$item);
                if (strlen($limpo) !== 8) {
                    return $this->json($response, ['success' => false, 'error' => 'Selecione NCMs válidos com 8 dígitos.'], 400);
                }
                $ncms[$limpo] = true;
            }
            $ncms = array_keys($ncms);
            if (!$ncms || count($ncms) > 500) {
                return $this->json($response, ['success' => false, 'error' => 'Selecione de 1 a 500 NCMs.'], 400);
            }

            $vigencia = (string)($in['vigencia_inicio'] ?? '');
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $vigencia, $date)
                || !checkdate((int)$date[2], (int)$date[3], (int)$date[1])) {
                return $this->json($response, ['success' => false, 'error' => 'Informe uma data válida para o início da vigência.'], 400);
            }

            $percentuais = [];
            foreach ([
                'mva_percentual' => ['MVA', 0, 1000],
                'aliquota_interna_percentual' => ['alíquota interna', 0, 100],
                'credito_interestadual_percentual' => ['crédito interestadual', 0, 100],
            ] as $field => [$label, $min, $max]) {
                $raw = str_replace(',', '.', trim((string)($in[$field] ?? '')));
                if (!is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < $min || (float)$raw > $max) {
                    return $this->json($response, ['success' => false, 'error' => "Informe um percentual válido para $label."], 400);
                }
                $percentuais[$field] = round((float)$raw, 4);
            }

            $operadorMva = (string)($in['operador_mva'] ?? '*');
            $operadorGuia = (string)($in['operador_guia'] ?? '-');
            if (!in_array($operadorMva, ['+', '-', '*', '/'], true)
                || !in_array($operadorGuia, ['+', '-', '*', '/'], true)) {
                return $this->json($response, ['success' => false, 'error' => 'Selecione operadores aritméticos válidos.'], 400);
            }

            $descricao = mb_substr(trim((string)($in['descricao'] ?? '')), 0, 120);
            $existe = $this->pdo->prepare(
                'SELECT id, st_pr FROM guia_transferencia_ncm_regra
                 WHERE ncm = ? AND vigencia_inicio = ? AND ativo = TRUE'
            );
            $atualiza = $this->pdo->prepare(
                'UPDATE guia_transferencia_ncm_regra
                 SET descricao = ?, st_pr = TRUE, mva_percentual = ?, aliquota_interna_percentual = ?,
                     credito_interestadual_percentual = ?, operador_mva = ?, operador_guia = ?,
                     atualizado_por = ?, atualizado_em = NOW()
                 WHERE id = ?'
            );
            $insere = $this->pdo->prepare(
                'INSERT INTO guia_transferencia_ncm_regra
                    (ncm, descricao, st_pr, mva_percentual, aliquota_interna_percentual,
                     credito_interestadual_percentual, operador_mva, operador_guia,
                     vigencia_inicio, ativo, atualizado_por)
                 VALUES (?, ?, TRUE, ?, ?, ?, ?, ?, ?, TRUE, ?)'
            );
            $params = [
                $percentuais['mva_percentual'], $percentuais['aliquota_interna_percentual'],
                $percentuais['credito_interestadual_percentual'], $operadorMva, $operadorGuia,
            ];

            $criados = 0;
            $jaExistiam = 0;
            $this->pdo->beginTransaction();
            foreach ($ncms as $ncm) {
                $existe->execute([$ncm, $vigencia]);
                $atual = $existe->fetch(PDO::FETCH_ASSOC);
                if ($atual && $this->pgBool($atual['st_pr'])) {
                    $jaExistiam++;
                } elseif ($atual) {
                    $atualiza->execute(array_merge([$descricao], $params, [$usuario, (int)$atual['id']]));
                    $criados++;
                } else {
                    $insere->execute(array_merge([$ncm, $descricao], $params, [$vigencia, $usuario]));
                    $criados++;
                }
            }
            $this->pdo->commit();

            if ($criados === 0 && count($ncms) === 1) {
                return $this->json($response, ['success' => false, 'error' => 'Esse NCM já participa da guia nessa vigência.'], 409);
            }
            return $this->json($response, ['success' => true, 'criados' => $criados, 'ja_existiam' => $jaExistiam]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[admin-config] erro ao salvar regra da guia: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível salvar a regra da guia.'], 500);
        }
    }

    /** GET /v1/admin/config-modulos/flex-minimo */
    public function listarFlexMinimo(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        try {
            $rows = $this->pdo->query(
                'SELECT id, gestor_id, nome, filial, valor_minimo, valor_emprestimo, max_multiplos,
                        motivo, emails, falhas_para_alertar, ativo, ultima_verificacao, ultimo_saldo,
                        ultimo_emprestimo_em, falhas_seguidas, ultimo_erro, ultimo_erro_em
                 FROM flex_minimo_config
                 ORDER BY filial, nome'
            )->fetchAll(PDO::FETCH_ASSOC);
            return $this->json($response, ['success' => true, 'configuracoes' => $rows]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao carregar Flex Mínimo: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível carregar as configurações do Flex Mínimo.'], 500);
        }
    }

    /** POST /v1/admin/config-modulos/flex-minimo */
    public function salvarFlexMinimo(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $in = $this->body($request);
        $rawId = trim((string)($in['id'] ?? ''));
        $rawGestor = trim((string)($in['gestor_id'] ?? ''));
        $rawFilial = trim((string)($in['filial'] ?? ''));
        $rawMaxMultiplos = trim((string)($in['max_multiplos'] ?? '5'));
        $rawFalhas = trim((string)($in['falhas_para_alertar'] ?? '5'));
        if (($rawId !== '' && !ctype_digit($rawId))
            || !ctype_digit($rawGestor)
            || !ctype_digit($rawFilial)
            || !ctype_digit($rawMaxMultiplos)
            || !ctype_digit($rawFalhas)) {
            return $this->json($response, ['success' => false, 'error' => 'Informe identificadores e limites inteiros válidos.'], 400);
        }

        $id = $rawId === '' ? 0 : (int)$rawId;
        $gestor = (int)$rawGestor;
        $nome = trim((string)($in['nome'] ?? ''));
        $filial = (int)$rawFilial;
        $minimo = str_replace(',', '.', trim((string)($in['valor_minimo'] ?? '')));
        $emprestimo = str_replace(',', '.', trim((string)($in['valor_emprestimo'] ?? '')));
        $maxMultiplos = (int)$rawMaxMultiplos;
        $falhasParaAlertar = (int)$rawFalhas;
        $motivo = trim((string)($in['motivo'] ?? ''));
        $emails = trim((string)($in['emails'] ?? ''));
        $rawAtivo = $in['ativo'] ?? false;
        if (!is_bool($rawAtivo) && !in_array($rawAtivo, ['0', '1', 0, 1, 'false', 'true'], true)) {
            return $this->json($response, ['success' => false, 'error' => 'Estado ativo inválido.'], 400);
        }
        $ativo = filter_var($rawAtivo, FILTER_VALIDATE_BOOLEAN);

        if ($gestor <= 0 || $nome === '' || mb_strlen($nome) > 120) {
            return $this->json($response, ['success' => false, 'error' => 'Informe o código e o nome do gestor (máximo 120 caracteres).'], 400);
        }
        if (!in_array($filial, [1, 6], true)) {
            return $this->json($response, ['success' => false, 'error' => 'A integração Flex está configurada para as filiais 1 e 6.'], 400);
        }
        if (!is_numeric($minimo) || !is_finite((float)$minimo) || (float)$minimo <= 0 || (float)$minimo > 999999999999.99
            || !is_numeric($emprestimo) || !is_finite((float)$emprestimo) || (float)$emprestimo <= 0 || (float)$emprestimo > 999999999999.99) {
            return $this->json($response, ['success' => false, 'error' => 'Os valores mínimo e de empréstimo devem ser maiores que zero.'], 400);
        }
        if ($maxMultiplos < 1 || $maxMultiplos > 50 || $falhasParaAlertar < 1 || $falhasParaAlertar > 100) {
            return $this->json($response, ['success' => false, 'error' => 'Limite de múltiplos ou de falhas para alerta fora do intervalo permitido.'], 400);
        }
        if ($motivo === '' || mb_strlen($motivo) > 1000) {
            return $this->json($response, ['success' => false, 'error' => 'Informe o motivo do empréstimo (máximo 1000 caracteres).'], 400);
        }

        if ($emails !== '') {
            $destinos = array_filter(array_map('trim', explode(',', $emails)), static fn($email) => $email !== '');
            foreach ($destinos as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->json($response, ['success' => false, 'error' => 'Revise os e-mails: informe endereços válidos separados por vírgula.'], 400);
                }
            }
            $emails = implode(',', $destinos);
        }

        try {
            if ($id > 0) {
                $stmt = $this->pdo->prepare(
                    'UPDATE flex_minimo_config
                     SET gestor_id = ?, nome = ?, filial = ?, valor_minimo = ?, valor_emprestimo = ?,
                         max_multiplos = ?, motivo = ?, emails = ?, falhas_para_alertar = ?, ativo = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $gestor, mb_substr($nome, 0, 120), $filial, round((float)$minimo, 2), round((float)$emprestimo, 2),
                    $maxMultiplos, $motivo, $emails, $falhasParaAlertar, $ativo ? 'true' : 'false', $id,
                ]);
                if ($stmt->rowCount() === 0) {
                    $exists = $this->pdo->prepare('SELECT 1 FROM flex_minimo_config WHERE id = ?');
                    $exists->execute([$id]);
                    if (!$exists->fetchColumn()) {
                        return $this->json($response, ['success' => false, 'error' => 'Configuração do Flex Mínimo não encontrada.'], 404);
                    }
                }
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO flex_minimo_config
                        (gestor_id, nome, filial, valor_minimo, valor_emprestimo, max_multiplos,
                         motivo, emails, falhas_para_alertar, ativo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $gestor, mb_substr($nome, 0, 120), $filial, round((float)$minimo, 2), round((float)$emprestimo, 2),
                    $maxMultiplos, $motivo, $emails, $falhasParaAlertar, $ativo ? 'true' : 'false',
                ]);
            }
            return $this->json($response, ['success' => true]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23505') {
                return $this->json($response, ['success' => false, 'error' => 'Já existe uma configuração para esse gestor e filial.'], 409);
            }
            error_log('[admin-config] erro ao salvar Flex Mínimo: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível salvar a configuração do Flex Mínimo.'], 500);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao salvar Flex Mínimo: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível salvar a configuração do Flex Mínimo.'], 500);
        }
    }

    private function processarImagem(?string $path_foto_master): string
    {
        $sem = 'https://placehold.co/100x100?text=S/F';
        if (empty($path_foto_master) || in_array(trim($path_foto_master), ['.', '-', 'null', 'undefined'], true)) {
            return $sem;
        }
        $n = str_replace('\\', '/', trim($path_foto_master));
        if (preg_match('/^https?:\/\//i', $n)) {
            return str_replace(' ', '%20', $n);
        }
        $marker = 'fotos para o site/';
        $pos = stripos($n, $marker);
        $rel = $pos !== false ? substr($n, $pos + strlen($marker)) : ltrim(preg_replace('/^fotos\//i', '', $n), '/');
        if (empty($rel)) {
            return $sem;
        }
        $parts = array_map('rawurlencode', explode('/', $rel));
        return 'https://acesso.nutricionalbr.com:2053/fotos/' . implode('/', $parts);
    }

    /** GET /v1/admin/config-modulos/amostra */
    public function listarAmostraConfig(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        try {
            $stmt = $this->pdo->query('
                SELECT id, filial_origem, filial_destino, percentual_padrao, atualizado_por, atualizado_em
                FROM amostra_estoque_config
                WHERE filial_origem = 1 AND filial_destino = 6
                ORDER BY id LIMIT 1
            ');
            $geral = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'filial_origem' => 1,
                'filial_destino' => 6,
                'percentual_padrao' => 30.00,
                'atualizado_por' => 'SISTEMA',
                'atualizado_em' => null,
            ];

            $regras = $this->pdo->query('
                SELECT r.id, r.iditem, r.filial_destino, r.percentual_liberado, r.ativo, r.motivo,
                       r.atualizado_por, r.atualizado_em,
                       i.descricao, i.referencia, i.path_foto_master
                FROM amostra_estoque_regra_item r
                JOIN item i ON i.iditem = r.iditem
                WHERE r.filial_destino = 6
                ORDER BY r.ativo DESC, i.descricao ASC
            ')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($regras as &$reg) {
                $reg['foto_url'] = $this->processarImagem($reg['path_foto_master'] ?? null);
            }

            return $this->json($response, [
                'success' => true,
                'geral' => $geral,
                'regras' => $regras,
            ]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao listar config amostra: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao carregar configurações de amostra.'], 500);
        }
    }

    /** POST /v1/admin/config-modulos/amostra/geral */
    public function salvarAmostraGeral(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $b = $this->body($request);
        $percentual = (float)($b['percentual_padrao'] ?? 30.00);
        if ($percentual < 0 || $percentual > 100) {
            return $this->json($response, ['success' => false, 'error' => 'O percentual padrão deve estar entre 0% e 100%.'], 422);
        }

        try {
            $user = $request->getAttribute('user') ?? [];
            $user = is_object($user) ? (array)$user : (array)$user;
            $usuario = $user['username'] ?? 'ADMIN';

            $stmt = $this->pdo->prepare('
                UPDATE amostra_estoque_config
                SET percentual_padrao = :perc, atualizado_por = :usuario, atualizado_em = NOW()
                WHERE filial_origem = 1 AND filial_destino = 6
            ');
            $stmt->execute(['perc' => round($percentual, 2), 'usuario' => $usuario]);

            if ($stmt->rowCount() === 0) {
                $stmtIns = $this->pdo->prepare('
                    INSERT INTO amostra_estoque_config (filial_origem, filial_destino, percentual_padrao, atualizado_por)
                    VALUES (1, 6, :perc, :usuario)
                ');
                $stmtIns->execute(['perc' => round($percentual, 2), 'usuario' => $usuario]);
            }

            return $this->json($response, ['success' => true, 'percentual_padrao' => $percentual]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao salvar geral amostra: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível salvar a configuração.'], 500);
        }
    }

    /** GET /v1/admin/config-modulos/amostra/itens-busca */
    public function buscarItensAmostra(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $q = trim((string)($request->getQueryParams()['q'] ?? ''));
        try {
            $sql = "
                SELECT i.iditem, i.descricao, i.referencia, i.path_foto_master
                FROM item i
                WHERE i.tipo = 11 AND i.inativo = 'N'
            ";
            $params = [];
            if ($q !== '') {
                $sql .= " AND (i.iditem::text = :exato OR i.referencia ILIKE :parcial OR i.descricao ILIKE :parcial)";
                $params['exato'] = $q;
                $params['parcial'] = '%' . $q . '%';
            }
            $sql .= " ORDER BY i.descricao LIMIT 40";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itens as &$it) {
                $it['foto_url'] = $this->processarImagem($it['path_foto_master'] ?? null);
            }

            return $this->json($response, ['success' => true, 'itens' => $itens]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao buscar itens amostra: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Erro ao buscar itens.'], 500);
        }
    }

    /** POST /v1/admin/config-modulos/amostra/regra-item */
    public function salvarAmostraRegraItem(Request $request, Response $response): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $b = $this->body($request);
        $iditem = (int)($b['iditem'] ?? 0);
        $percentual = (float)($b['percentual_liberado'] ?? 0);
        $ativo = $this->pgBool($b['ativo'] ?? true);
        $motivo = trim((string)($b['motivo'] ?? ''));

        if ($iditem <= 0) {
            return $this->json($response, ['success' => false, 'error' => 'Item inválido.'], 422);
        }
        if ($percentual < 0 || $percentual > 100) {
            return $this->json($response, ['success' => false, 'error' => 'O percentual liberado deve estar entre 0% e 100%.'], 422);
        }

        try {
            $user = $request->getAttribute('user') ?? [];
            $user = is_object($user) ? (array)$user : (array)$user;
            $usuario = $user['username'] ?? 'ADMIN';

            $stmt = $this->pdo->prepare('
                INSERT INTO amostra_estoque_regra_item (iditem, filial_destino, percentual_liberado, ativo, motivo, atualizado_por, atualizado_em)
                VALUES (:iditem, 6, :perc, :ativo, :motivo, :usuario, NOW())
                ON CONFLICT (iditem, filial_destino) DO UPDATE
                SET percentual_liberado = EXCLUDED.percentual_liberado,
                    ativo = EXCLUDED.ativo,
                    motivo = EXCLUDED.motivo,
                    atualizado_por = EXCLUDED.atualizado_por,
                    atualizado_em = NOW()
            ');
            $stmt->execute([
                'iditem' => $iditem,
                'perc' => round($percentual, 2),
                'ativo' => $ativo ? 'true' : 'false',
                'motivo' => $motivo,
                'usuario' => $usuario,
            ]);

            return $this->json($response, ['success' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao salvar regra de item amostra: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível salvar a regra do item.'], 500);
        }
    }

    /** DELETE /v1/admin/config-modulos/amostra/regra-item/{id} */
    public function excluirAmostraRegraItem(Request $request, Response $response, array $args): Response
    {
        if ($denied = $this->exigirAdmin($request, $response)) {
            return $denied;
        }

        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['success' => false, 'error' => 'ID inválido.'], 422);
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM amostra_estoque_regra_item WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $this->json($response, ['success' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-config] erro ao excluir regra de item amostra: ' . $e->getMessage());
            return $this->json($response, ['success' => false, 'error' => 'Não foi possível excluir a regra.'], 500);
        }
    }
}
