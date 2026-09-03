<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;

class SistemaController
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = \getPDO();
    }
    
    /**
     * GET /v1/sistema/modulos-setores
     * Retorna setores e módulos ativos (rota pública, sem JWT)
     */
    public function getModulosSetores(Request $request, Response $response): Response
    {
        try {
            // Busca setores ativos
            $stmtSetores = $this->pdo->query("
                SELECT id, nome, slug, icone, cor_bg as corBg, cor_text as corTexto, descricao
                FROM sistema_setores 
                WHERE ativo = true 
                ORDER BY ordem
            ");
            $setores = $stmtSetores->fetchAll(\PDO::FETCH_ASSOC);
            
            // Busca módulos ativos
            $stmtModulos = $this->pdo->query("
                SELECT id, slug, nome, descricao as desc, icon, cor_bg as corBg, cor_text as corTexto, url, setor
                FROM sistema_modulos 
                WHERE ativo = true 
                ORDER BY ordem
            ");
            $modulos = $stmtModulos->fetchAll(\PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode([
                'setores' => $setores,
                'modulos' => $modulos
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}