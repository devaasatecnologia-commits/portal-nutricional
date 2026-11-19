<?php
namespace Nutricional\Controllers;

use Nutricional\Services\SegurancaUsuario;
use Nutricional\Services\WhatsAppCodigo;
use Nutricional\Services\WhatsAppService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * 2FA do proprio usuario logado (qualquer perfil autenticado).
 *
 * Reaproveita a logica do AdminSegredosController, mas nao exige admin:
 * iniciar/confirmar/desativar operam sempre sobre $u['idusuario'] do token.
 *
 * A barreira admin do AdminSegredosController continua valendo para
 * cofre de segredos, API tokens, auditoria e WhatsApp.
 */
class Meu2faController extends AdminSegredosController
{
    /** GET /v1/2fa/status */
    public function status(Request $request, Response $response): Response
    {
        $u = $this->usuario($request);
        if ($u['idusuario'] <= 0) {
            return $this->json($response, ['error' => 'Nao autenticado'], 401);
        }
        $r = $this->buscar2fa($u['idusuario']);
        $seg = SegurancaUsuario::config($this->pdo, (int)$u['idusuario']);
        $numWhats = WhatsAppCodigo::numero($this->pdo, (int)$u['idusuario']);
        return $this->json($response, [
            'ativo'             => (bool)($r['ativo'] ?? false),
            'pendente'          => (bool)($r && !$r['ativo']),
            'exige_2fa_login'   => (bool)$seg['exige_2fa_login'],
            'tipo_2fa'          => $seg['tipo_2fa'],
            'provedor_whatsapp' => WhatsAppService::provedor() !== null,
            'whatsapp_destino'  => $numWhats ? WhatsAppService::mascarar($numWhats) : null,
        ]);
    }

    /** POST /v1/2fa/iniciar  {senha} */
    public function iniciar(Request $request, Response $response): Response
    {
        return $this->iniciarDoisFatores($request, $response);
    }

    /** POST /v1/2fa/confirmar  {codigo} */
    public function confirmar(Request $request, Response $response): Response
    {
        return $this->confirmarDoisFatores($request, $response);
    }

    /** POST /v1/2fa/desativar  {senha, codigo} */
    public function desativar(Request $request, Response $response): Response
    {
        return $this->desativarDoisFatores($request, $response);
    }
}