<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    // ======================================================================
    // MÓDULOS
    // ======================================================================

    /**
     * GET /v1/admin/modulos
     * Lista todos os módulos cadastrados no sistema
     */
    public function getModulos(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT * FROM sistema_modulos WHERE ativo = true ORDER BY ordem ASC, nome ASC"
            );
            $modulos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, ['modulos' => $modulos]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
/**
 * GET /v1/admin/gestores
 * Lista gestores ativos da view vw_gestores
 */
public function getGestores(Request $request, Response $response): Response
{
    try {
        $sql = "SELECT idcliforemp, 
                       TRIM(REPLACE(fantasia, '*', '')) as nome
                FROM vw_gestores 
                WHERE inativo = 'N'
                ORDER BY nome";
        
        $stmt = $this->pdo->query($sql);
        $gestores = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Se não encontrou nada, retorna array vazio (não dados hardcoded!)
        if (empty($gestores)) {
            error_log('ATENÇÃO: Nenhum gestor encontrado na view vw_gestores');
            return $this->jsonResponse($response, ['gestores' => []]);
        }
        
        return $this->jsonResponse($response, ['gestores' => $gestores]);
        
    } catch (\Exception $e) {
        error_log('Erro em getGestores: ' . $e->getMessage());
        
        // Em produção, retorna erro 500, NUNCA dados hardcoded!
        return $this->jsonResponse($response, [
            'error' => 'Erro ao carregar gestores',
            'gestores' => []
        ], 500);
    }
}
/**
 * POST /v1/admin/upload-foto
 * Upload de foto de perfil do usuário
 */
public function uploadFotoPerfil(Request $request, Response $response): Response
{
    $uploadedFiles = $request->getUploadedFiles();
    
    if (empty($uploadedFiles['foto'])) {
        return $this->jsonResponse($response, ['error' => 'Nenhuma foto enviada'], 400);
    }

    $foto = $uploadedFiles['foto'];
    if ($foto->getError() !== UPLOAD_ERR_OK) {
        return $this->jsonResponse($response, ['error' => 'Erro no upload'], 500);
    }

    $params = $request->getParsedBody();
    $idusuario = (int)($params['idusuario'] ?? 0);
    
    // Valida tipo
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($foto->getClientMediaType(), $allowedTypes)) {
        return $this->jsonResponse($response, ['error' => 'Tipo não permitido. Use JPEG, PNG ou WEBP.'], 400);
    }

    // Gera nome único usando idusuario
    $ext = pathinfo($foto->getClientFilename(), PATHINFO_EXTENSION) ?: 'jpg';
    $nomeArquivo = 'perfil_' . $idusuario . '_' . time() . '.' . $ext;
    $caminhoRelativo = 'uploads/perfil/' . $nomeArquivo;
    $caminhoAbsoluto = __DIR__ . '/../../../uploads/perfil/' . $nomeArquivo;

    // Cria diretório se não existir
    $diretorio = dirname($caminhoAbsoluto);
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }

    try {
        $foto->moveTo($caminhoAbsoluto);

        // ✅ CORRIGIDO: Atualiza usando idusuario (que é a chave correta para a tabela usuario)
        $stmt = $this->pdo->prepare("UPDATE usuario SET foto_perfil = ? WHERE idusuario = ?");
        $stmt->execute([$caminhoRelativo, $idusuario]);
        
        // Log para debug
        error_log("Foto atualizada: idusuario={$idusuario}, path={$caminhoRelativo}, linhas afetadas=" . $stmt->rowCount());

        // ✅ Se não atualizou (idusuario não encontrado), tenta pelo idcliforemp
        if ($stmt->rowCount() === 0) {
            // Tenta com idcliforemp (fallback)
            $idcliforemp = (int)($params['idcliforemp'] ?? $params['idusuario'] ?? 0);
            $stmt2 = $this->pdo->prepare("UPDATE usuario SET foto_perfil = ? WHERE idcliforemp = ?");
            $stmt2->execute([$caminhoRelativo, $idcliforemp]);
            error_log("Tentativa fallback: idcliforemp={$idcliforemp}, linhas afetadas=" . $stmt2->rowCount());
        }

        $fotoUrl = 'https://api.nutricionalbr.com/' . $caminhoRelativo;
        
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Foto atualizada com sucesso!',
            'foto_url' => $fotoUrl,
            'caminho' => $caminhoRelativo
        ]);

    } catch (\Exception $e) {
        if (file_exists($caminhoAbsoluto)) @unlink($caminhoAbsoluto);
        error_log('Erro upload foto: ' . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}

/**
 * POST /v1/admin/usuarios/editar
 * Edita dados do usuário (filiais, gestores, senha, permite_ver_usuarios)
 */
public function editarUsuario(Request $request, Response $response): Response
{
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idcliforemp = (int)($input['idcliforemp'] ?? 0);
        $dashFiliais = $input['dash_filiais'] ?? '';
        $dashGestores = $input['dash_gestores'] ?? '';
        $senha = $input['senha'] ?? '';
        $permiteVerUsuarios = $input['permite_ver_usuarios'] ?? 'N';

        if (!$idcliforemp) {
            return $this->jsonResponse($response, ['error' => 'ID do usuário é obrigatório'], 400);
        }

        $this->pdo->beginTransaction();

        // Busca o idusuario
        $stmtId = $this->pdo->prepare("SELECT idusuario FROM usuario WHERE idcliforemp = :idcliforemp");
        $stmtId->execute(['idcliforemp' => $idcliforemp]);
        $idusuario = $stmtId->fetchColumn();

        if (!$idusuario) {
            throw new \Exception("Usuário não encontrado");
        }

        // Atualiza filiais, gestores e permite_ver_usuarios
        $sql = "UPDATE usuario SET 
                    dash_filiais = :filiais, 
                    dash_gestores = :gestores,
                    permite_ver_usuarios = :permite_ver
                WHERE idusuario = :idusuario";
        
        $params = [
            'filiais' => $dashFiliais ?: null,
            'gestores' => $dashGestores ?: null,
            'permite_ver' => $permiteVerUsuarios === 'S' ? 'S' : 'N',
            'idusuario' => $idusuario
        ];

        // Se informou senha, atualiza também
        if (!empty($senha)) {
            $stmtUser = $this->pdo->prepare("SELECT username FROM usuario WHERE idcliforemp = :idcliforemp");
            $stmtUser->execute(['idcliforemp' => $idcliforemp]);
            $username = $stmtUser->fetchColumn();
            
            if ($username) {
                $novaSenhaHash = strtoupper(md5(strtoupper($username) . $senha));
                $sql = "UPDATE usuario SET 
                            dash_filiais = :filiais, 
                            dash_gestores = :gestores, 
                            senha = :senha,
                            permite_ver_usuarios = :permite_ver
                        WHERE idusuario = :idusuario";
                $params['senha'] = $novaSenhaHash;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->pdo->commit();

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Usuário atualizado com sucesso'
        ]);
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
/**
 * POST /v1/admin/usuarios/visualizacao
 * Salva quais usuários um usuário pode visualizar
 */
public function salvarVisualizacao(Request $request, Response $response): Response
{
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idcliforemp = (int)($input['idcliforemp'] ?? 0);
        $usuariosVisualizar = $input['usuarios_visualizar'] ?? []; // Array de IDs (idusuario)
        $permiteVerTodos = $input['permite_ver_todos'] ?? 'N';

        if (!$idcliforemp) {
            return $this->jsonResponse($response, ['error' => 'ID do usuário é obrigatório'], 400);
        }

        // Busca o idusuario
        $stmtId = $this->pdo->prepare("SELECT idusuario FROM usuario WHERE idcliforemp = :idcliforemp");
        $stmtId->execute(['idcliforemp' => $idcliforemp]);
        $idusuario = $stmtId->fetchColumn();

        if (!$idusuario) {
            throw new \Exception("Usuário não encontrado");
        }

        // 🔥 Inicia transação
        $this->pdo->beginTransaction();

        try {
            // Remove todas as visualizações atuais
            $stmtDel = $this->pdo->prepare("DELETE FROM usuario_visualizacao WHERE idusuario = :idusuario");
            $stmtDel->execute(['idusuario' => $idusuario]);

            // Insere as novas visualizações (apenas se não for "ver todos")
            if ($permiteVerTodos !== 'S' && !empty($usuariosVisualizar)) {
                $stmtIns = $this->pdo->prepare("
                    INSERT INTO usuario_visualizacao (idusuario, idusuario_visualizar) 
                    VALUES (:idusuario, :visualizar)
                    ON CONFLICT (idusuario, idusuario_visualizar) DO NOTHING
                ");
                
                foreach ($usuariosVisualizar as $idVisualizar) {
                    $idVisualizar = (int)$idVisualizar;
                    if ($idVisualizar > 0 && $idVisualizar != $idusuario) {
                        $stmtIns->execute([
                            'idusuario' => $idusuario,
                            'visualizar' => $idVisualizar
                        ]);
                    }
                }
            }

            // Atualiza o campo permite_ver_usuarios
            $stmtUp = $this->pdo->prepare("
                UPDATE usuario SET permite_ver_usuarios = :permite WHERE idusuario = :idusuario
            ");
            $stmtUp->execute([
                'permite' => $permiteVerTodos === 'S' ? 'S' : 'N',
                'idusuario' => $idusuario
            ]);

            $this->pdo->commit();

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Visualização de usuários atualizada com sucesso',
                'permite_ver_todos' => $permiteVerTodos === 'S',
                'usuarios_selecionados' => count($usuariosVisualizar)
            ]);

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

    } catch (\Exception $e) {
        error_log('Erro em salvarVisualizacao: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
/**
 * GET /v1/admin/usuarios/{id}/visualizacao
 * Busca quais usuários um usuário pode visualizar
 */
public function getVisualizacao(Request $request, Response $response, array $args): Response
{
    try {
        $idcliforemp = (int)$args['id'];

        if (!$idcliforemp) {
            return $this->jsonResponse($response, ['error' => 'ID do usuário é obrigatório'], 400);
        }

        // Busca o idusuario
        $stmtId = $this->pdo->prepare("SELECT idusuario FROM usuario WHERE idcliforemp = :idcliforemp");
        $stmtId->execute(['idcliforemp' => $idcliforemp]);
        $idusuario = $stmtId->fetchColumn();

        if (!$idusuario) {
            return $this->jsonResponse($response, ['error' => 'Usuário não encontrado'], 404);
        }

        // Busca o campo permite_ver_usuarios
        $stmtPerm = $this->pdo->prepare("SELECT permite_ver_usuarios FROM usuario WHERE idusuario = :idusuario");
        $stmtPerm->execute(['idusuario' => $idusuario]);
        $permiteVerTodos = $stmtPerm->fetchColumn() === 'S';

        // Busca os usuários que este usuário pode visualizar
        $stmtVis = $this->pdo->prepare("
            SELECT 
                uv.idusuario_visualizar as id,
                u.username as nome,
                u.idcliforemp
            FROM usuario_visualizacao uv
            JOIN usuario u ON u.idusuario = uv.idusuario_visualizar
            WHERE uv.idusuario = :idusuario
            ORDER BY u.username
        ");
        $stmtVis->execute(['idusuario' => $idusuario]);
        $usuariosVisualizar = $stmtVis->fetchAll(\PDO::FETCH_ASSOC);

        return $this->jsonResponse($response, [
            'permite_ver_todos' => $permiteVerTodos,
            'usuarios_visualizar' => $usuariosVisualizar
        ]);

    } catch (\Exception $e) {
        error_log('Erro em getVisualizacao: ' . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
/**
 * GET /v1/admin/usuarios/lista-completa
 * Lista todos os usuários disponíveis para seleção (completo)
 */
public function getListaCompletaUsuarios(Request $request, Response $response): Response
{
    try {
        $sql = "SELECT 
                    u.idcliforemp,
                    u.idusuario,
                    u.username,
                    u.inativo
                FROM usuario u
                WHERE u.idcliforemp IS NOT NULL
                  AND u.idcliforemp > 0
                  AND u.idusuario IS NOT NULL
                ORDER BY u.inativo ASC, u.username ASC";
        
        $stmt = $this->pdo->query($sql);
        $usuarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->jsonResponse($response, ['usuarios' => $usuarios]);

    } catch (\Exception $e) {
        error_log('Erro em getListaCompletaUsuarios: ' . $e->getMessage());
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
/**
 * GET /v1/admin/setores
 * Lista setores com seus módulos
 */
public function getSetores(Request $request, Response $response): Response
{
    try {
        // Setores com contagem de módulos
        $sql = "SELECT ss.*, 
                       COUNT(sm.id) as total_modulos,
                       COALESCE(
                           (SELECT STRING_AGG(sm2.slug, ',') 
                            FROM sistema_modulos sm2 
                            WHERE sm2.setor = ss.nome AND sm2.ativo = true
                           ), ''
                       ) as modulos_slugs
                FROM sistema_setores ss
                LEFT JOIN sistema_modulos sm ON sm.setor = ss.nome AND sm.ativo = true
                WHERE ss.ativo = true
                GROUP BY ss.id
                ORDER BY ss.ordem ASC";
        
        $stmt = $this->pdo->query($sql);
        $setores = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, ['setores' => $setores]);
    } catch (\Exception $e) {
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}

/**
 * GET /v1/admin/setores/{id}/modulos
 * Lista módulos de um setor específico
 */
public function getModulosPorSetor(Request $request, Response $response, array $args): Response
{
    try {
        $setorId = (int)$args['id'];
        
        $stmt = $this->pdo->prepare("
            SELECT sm.* FROM sistema_modulos sm
            JOIN sistema_setores ss ON ss.nome = sm.setor
            WHERE ss.id = :setorId AND sm.ativo = true
            ORDER BY sm.ordem ASC
        ");
        $stmt->execute(['setorId' => $setorId]);
        $modulos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $this->jsonResponse($response, ['modulos' => $modulos]);
    } catch (\Exception $e) {
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}

/**
 * POST /v1/admin/permissoes-por-setor
 * Salva permissões baseado no setor (libera todos os módulos do setor)
 */
public function salvarPermissoesPorSetor(Request $request, Response $response): Response
{
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idcliforemp = (int)($input['idcliforemp'] ?? 0);
        $setoresSlugs = $input['setores'] ?? []; // Array de slugs de setores
        
        if (!$idcliforemp) {
            return $this->jsonResponse($response, ['error' => 'ID do usuário é obrigatório'], 400);
        }
        
        // Traduz idcliforemp para idusuario
        $stmtId = $this->pdo->prepare("SELECT idusuario FROM usuario WHERE idcliforemp = :idcliforemp");
        $stmtId->execute(['idcliforemp' => $idcliforemp]);
        $idusuario = $stmtId->fetchColumn();
        
        if (!$idusuario) {
            throw new \Exception("Usuário não encontrado: $idcliforemp");
        }
        
        $this->pdo->beginTransaction();
        
        // Remove permissões atuais
        $this->pdo->prepare("DELETE FROM usuario_permissoes WHERE idusuario = :idusuario")
            ->execute(['idusuario' => $idusuario]);
        
        // Busca todos os módulos dos setores selecionados
        if (!empty($setoresSlugs)) {
            $inSetores = implode("','", array_map(function($s) { return trim($s); }, $setoresSlugs));
            
            $sql = "INSERT INTO usuario_permissoes (idusuario, idmodulo)
                    SELECT :idusuario, sm.id 
                    FROM sistema_modulos sm
                    JOIN sistema_setores ss ON ss.nome = sm.setor
                    WHERE ss.slug IN ('{$inSetores}') AND sm.ativo = true
                    ON CONFLICT (idusuario, idmodulo) DO NOTHING";
            
            $this->pdo->prepare($sql)->execute(['idusuario' => $idusuario]);
        }
        
        // Atualiza filiais e gestores se enviados
        if (isset($input['dash_filiais']) || isset($input['dash_gestores'])) {
            $this->pdo->prepare("
                UPDATE usuario SET 
                    dash_filiais = COALESCE(:filiais, dash_filiais),
                    dash_gestores = COALESCE(:gestores, dash_gestores)
                WHERE idusuario = :idusuario
            ")->execute([
                'filiais' => $input['dash_filiais'] ?? null,
                'gestores' => $input['dash_gestores'] ?? null,
                'idusuario' => $idusuario
            ]);
        }
        
        $this->pdo->commit();
        
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Permissões atualizadas por setor com sucesso!'
        ]);
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
    /**
     * POST /v1/admin/modulos
     * Cadastra ou atualiza um módulo
     */
    public function salvarModulo(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            
            $sql = "INSERT INTO sistema_modulos (slug, nome, descricao, icon, cor_bg, cor_text, url, ordem) 
                    VALUES (:slug, :nome, :descricao, :icon, :cor_bg, :cor_text, :url, :ordem)
                    ON CONFLICT (slug) DO UPDATE SET 
                        nome = EXCLUDED.nome,
                        descricao = EXCLUDED.descricao,
                        icon = EXCLUDED.icon,
                        cor_bg = EXCLUDED.cor_bg,
                        cor_text = EXCLUDED.cor_text,
                        url = EXCLUDED.url,
                        ordem = EXCLUDED.ordem,
                        updated_at = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'slug' => $input['slug'] ?? '',
                'nome' => $input['nome'] ?? '',
                'descricao' => $input['descricao'] ?? '',
                'icon' => $input['icon'] ?? 'fa-cube',
                'cor_bg' => $input['cor_bg'] ?? 'bg-slate-50',
                'cor_text' => $input['cor_text'] ?? 'text-slate-600',
                'url' => $input['url'] ?? '',
                'ordem' => (int)($input['ordem'] ?? 0)
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Módulo salvo com sucesso'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // USUÁRIOS
    // ======================================================================

 
/**
 * GET /v1/admin/usuarios
 * Lista todos os usuários com permissões e tokens
 */
public function getUsuarios(Request $request, Response $response): Response
{
    try {
        $sql = "SELECT 
                    u.idcliforemp, 
                    u.idusuario,
                    u.username, 
                    u.inativo,
                    u.dash_filiais, 
                    u.dash_gestores,
                    u.foto_perfil,
                    u.permite_ver_usuarios,
                    COALESCE(
                        (SELECT STRING_AGG(DISTINCT vup.modulo_slug, ',') 
                         FROM vw_usuario_permissoes vup 
                         WHERE vup.idcliforemp = u.idcliforemp
                        ), ''
                    ) as permissoes,
                    COUNT(DISTINCT at.id) as total_tokens
                FROM usuario u
                LEFT JOIN api_tokens at ON at.criado_por = u.idusuario AND at.ativo = true
                WHERE u.idcliforemp IS NOT NULL
                GROUP BY u.idcliforemp, u.idusuario, u.username, u.inativo, u.dash_filiais, u.dash_gestores, u.foto_perfil, u.permite_ver_usuarios
                ORDER BY u.inativo ASC, u.username ASC";
        
        $stmt = $this->pdo->query($sql);
        $usuarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total = count($usuarios);
        $ativos = count(array_filter($usuarios, function($u) {
            return $u['inativo'] === 'N';
        }));

        $stmtTokens = $this->pdo->query("SELECT COUNT(*) FROM api_tokens WHERE ativo = true");
        $totalTokens = (int)$stmtTokens->fetchColumn();

        return $this->jsonResponse($response, [
            'total' => $total,
            'ativos' => $ativos,
            'tokens' => $totalTokens,
            'usuarios' => $usuarios,
            'ultimos' => array_slice($usuarios, 0, 10)
        ]);
    } catch (\Exception $e) {
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}
  
  /**
 * GET /v1/admin/usuarios/{id}/permissoes
 * Busca permissões detalhadas de um usuário específico
 */
public function getPermissoesUsuario(Request $request, Response $response, array $args): Response
{
    try {
        $idcliforemp = (int)$args['id'];
        
        // Todos os módulos disponíveis
        $stmtModulos = $this->pdo->query(
            "SELECT * FROM sistema_modulos WHERE ativo = true ORDER BY ordem ASC"
        );
        $todosModulos = $stmtModulos->fetchAll(\PDO::FETCH_ASSOC);
        
        // Módulos que o usuário tem permissão (usando a VIEW)
        $stmtPerm = $this->pdo->prepare(
            "SELECT DISTINCT sm.slug, sm.nome 
             FROM vw_usuario_permissoes vup
             JOIN sistema_modulos sm ON sm.slug = vup.modulo_slug
             WHERE vup.idcliforemp = :idcliforemp AND sm.ativo = true"
        );
        $stmtPerm->execute(['idcliforemp' => $idcliforemp]);
        $permissoesUsuario = $stmtPerm->fetchAll(\PDO::FETCH_ASSOC);
        
        $permSlugs = array_column($permissoesUsuario, 'slug');
        
        // Dados do usuário
        $stmtUser = $this->pdo->prepare(
            "SELECT idcliforemp, idusuario, username, dash_filiais, dash_gestores, inativo, permite_ver_usuarios
             FROM usuario WHERE idcliforemp = :idcliforemp"
        );
        $stmtUser->execute(['idcliforemp' => $idcliforemp]);
        $usuario = $stmtUser->fetch(\PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            return $this->jsonResponse($response, ['error' => 'Usuário não encontrado'], 404);
        }

        return $this->jsonResponse($response, [
            'usuario' => $usuario,
            'todos_modulos' => $todosModulos,
            'permissoes_atuais' => $permSlugs
        ]);
    } catch (\Exception $e) {
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}

  /**
 * POST /v1/admin/permissoes
 * Salva permissões de módulos, filiais, gestores e visualização de usuários
 */
public function salvarPermissoes(Request $request, Response $response): Response
{
    try {
        $input = json_decode($request->getBody()->getContents(), true) ?? [];
        $idcliforemp = (int)($input['idcliforemp'] ?? $input['idusuario'] ?? 0);
        $modulosPermitidos = $input['modulos'] ?? [];
        $dashFiliais = $input['dash_filiais'] ?? '';
        $dashGestores = $input['dash_gestores'] ?? '';
        $permiteVerUsuarios = $input['permite_ver_usuarios'] ?? 'N';

        if (!$idcliforemp) {
            return $this->jsonResponse($response, [
                'error' => 'ID do usuário é obrigatório'
            ], 400);
        }

        $this->pdo->beginTransaction();

        // Traduz idcliforemp para idusuario
        $stmtId = $this->pdo->prepare(
            "SELECT idusuario FROM usuario WHERE idcliforemp = :idcliforemp"
        );
        $stmtId->execute(['idcliforemp' => $idcliforemp]);
        $idusuario = $stmtId->fetchColumn();

        if (!$idusuario) {
            throw new \Exception("Usuário não encontrado: idcliforemp = $idcliforemp");
        }

        // Remove todas as permissões atuais
        $stmtDel = $this->pdo->prepare(
            "DELETE FROM usuario_permissoes WHERE idusuario = :idusuario"
        );
        $stmtDel->execute(['idusuario' => $idusuario]);

        // Insere as novas permissões
        if (!empty($modulosPermitidos)) {
            $stmtModulo = $this->pdo->prepare(
                "SELECT id FROM sistema_modulos WHERE slug = :slug"
            );
            $stmtIns = $this->pdo->prepare(
                "INSERT INTO usuario_permissoes (idusuario, idmodulo) VALUES (:idusuario, :idmodulo)"
            );
            
            foreach ($modulosPermitidos as $slug) {
                $stmtModulo->execute(['slug' => trim($slug)]);
                $modulo = $stmtModulo->fetch(\PDO::FETCH_ASSOC);
                
                if ($modulo) {
                    $stmtIns->execute([
                        'idusuario' => $idusuario,
                        'idmodulo' => $modulo['id']
                    ]);
                }
            }
        }

        // Atualiza filiais, gestores e permissão de ver usuários
        $stmtUp = $this->pdo->prepare(
            "UPDATE usuario SET 
                dash_filiais = :filiais, 
                dash_gestores = :gestores,
                permite_ver_usuarios = :permite_ver
             WHERE idusuario = :idusuario"
        );
        $stmtUp->execute([
            'filiais' => $dashFiliais ?: null,
            'gestores' => $dashGestores ?: null,
            'permite_ver' => $permiteVerUsuarios === 'S' ? 'S' : 'N',
            'idusuario' => $idusuario
        ]);

        $this->pdo->commit();

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Permissões atualizadas com sucesso'
        ]);
    } catch (\Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
}

    /**
     * POST /v1/admin/usuarios/{id}/toggle
     * Ativa/Desativa um usuário
     */
    public function toggleUsuario(Request $request, Response $response, array $args): Response
    {
        try {
            $idcliforemp = (int)$args['id'];
            
            // Não pode desativar o admin principal (TIAGO)
            if ($idcliforemp == 11258) {
                return $this->jsonResponse($response, [
                    'error' => 'Não é possível desativar o administrador principal'
                ], 400);
            }

            $this->pdo->beginTransaction();

            // Inverte o status
            $stmt = $this->pdo->prepare(
                "UPDATE usuario SET inativo = CASE WHEN inativo = 'N' THEN 'S' ELSE 'N' END WHERE idcliforemp = :idcliforemp"
            );
            $stmt->execute(['idcliforemp' => $idcliforemp]);

            // Se desativar, revoga todos os tokens do usuário
            $stmtTokens = $this->pdo->prepare(
                "UPDATE api_tokens SET ativo = false WHERE criado_por = (SELECT idusuario FROM usuario WHERE idcliforemp = :idcliforemp)"
            );
            $stmtTokens->execute(['idcliforemp' => $idcliforemp]);

            $this->pdo->commit();

            return $this->jsonResponse($response, ['success' => true]);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // API TOKENS
    // ======================================================================

    /**
     * GET /v1/admin/api-tokens
     * Lista todos os tokens da API com logs recentes
     */
    public function getTokens(Request $request, Response $response): Response
    {
        try {
            // Tokens
            $sql = "SELECT 
                        at.*,
                        u.username as criado_por_nome
                    FROM api_tokens at
                    LEFT JOIN usuario u ON u.idusuario = at.criado_por
                    ORDER BY at.ativo DESC, at.created_at DESC";
            
            $stmt = $this->pdo->query($sql);
            $tokens = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Últimos logs
            $sqlLogs = "SELECT 
                            al.*,
                            at.nome_cliente as cliente,
                            at.token_prefixo
                        FROM api_logs al
                        LEFT JOIN api_tokens at ON at.id = al.idtoken
                        ORDER BY al.created_at DESC 
                        LIMIT 50";
            
            $stmtLogs = $this->pdo->query($sqlLogs);
            $logs = $stmtLogs->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, [
                'tokens' => $tokens,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/admin/api-tokens
     * Cria um novo token de API
     */
    public function criarToken(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user') ?? [];
            $input = json_decode($request->getBody()->getContents(), true) ?? [];
            
            $nomeCliente = trim($input['nome_cliente'] ?? '');
            $permissoes = $input['permissoes'] ?? '[]';
            $diasExpirar = (int)($input['dias_expirar'] ?? 0);
            
            if (empty($nomeCliente)) {
                return $this->jsonResponse($response, [
                    'error' => 'Nome do cliente é obrigatório'
                ], 400);
            }

            // Gera token aleatório seguro
            $token = bin2hex(random_bytes(32));
            $tokenPrefixo = substr($token, 0, 8);
            
            $expiraEm = $diasExpirar > 0 
                ? date('Y-m-d H:i:s', strtotime("+{$diasExpirar} days")) 
                : null;

            $sql = "INSERT INTO api_tokens (nome_cliente, token, token_prefixo, permissoes_escopo, criado_por, expira_em) 
                    VALUES (:nome, :token, :prefixo, :permissoes, :criador, :expira)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'nome' => $nomeCliente,
                'token' => $token,
                'prefixo' => $tokenPrefixo,
                'permissoes' => is_array($permissoes) ? json_encode($permissoes) : $permissoes,
                'criador' => $user['idusuario'] ?? null,
                'expira' => $expiraEm
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'token' => $token,
                'token_prefixo' => $tokenPrefixo,
                'message' => 'Token gerado com sucesso. Copie agora, ele não será mostrado novamente!'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /v1/admin/api-tokens/{id}/revogar
     * Revoga um token de API
     */
    public function revogarToken(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)$args['id'];
            
            $stmt = $this->pdo->prepare(
                "UPDATE api_tokens SET ativo = false, updated_at = NOW() WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Token revogado com sucesso'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // ESCOPOS DE API
    // ======================================================================

    /**
     * GET /v1/admin/escopos
     * Lista todos os escopos de API disponíveis
     */
    public function getEscopos(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT * FROM api_escopos WHERE ativo = true ORDER BY nome ASC"
            );
            $escopos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, ['escopos' => $escopos]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // LOGS DE ACESSO
    // ======================================================================

    /**
     * GET /v1/admin/logs
     * Lista logs de acesso ao portal
     */
    public function getLogs(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $limit = min((int)($params['limit'] ?? 100), 500);
            
            $sql = "SELECT * FROM portal_acessos ORDER BY created_at DESC LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, ['logs' => $logs]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // MÉTODO AUXILIAR
    // ======================================================================

    /**
     * Retorna resposta JSON padronizada
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}