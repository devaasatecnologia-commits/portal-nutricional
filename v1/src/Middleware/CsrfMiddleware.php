<?php

namespace Nutricional\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

class CsrfMiddleware
{
    /**
     * Rotas exatas isentas de CSRF.
     */
    private $excludedRoutes = [
        '/v1/auth/login',
        '/v1/auth/alterar-senha',
        '/v1/cron/executar',
        '/v1/perfil',                // perfil do usuário (upload de foto, dados, etc)
        '/v1/admin/upload-foto',     // upload de foto de perfil
    ];

    /**
     * Prefixos de rota isentos de CSRF.
     * O app do motorista (frota) usa JWT no header Authorization,
     * não cookie de sessão, então não é vulnerável a CSRF.
     */
    private $excludedPrefixes = [
        '/v1/frota/',                // módulo Frota inteiro (embarques, entregas, motoristas, cobli, acerto, importar)
        '/v1/portal-cliente/',       // Portal do Cliente B2B usa Bearer JWT (não cookie de sessão)
    ];

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Apenas para métodos que modificam dados
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {

            // Rota exata isenta
            foreach ($this->excludedRoutes as $excluded) {
                if (strpos($path, $excluded) === 0) {
                    return $handler->handle($request);
                }
            }

            // Prefixo isento
            foreach ($this->excludedPrefixes as $prefix) {
                if (strpos($path, $prefix) === 0) {
                    return $handler->handle($request);
                }
            }

            // Validar CSRF
            $tokenHeader = trim($request->getHeaderLine('X-CSRF-Token'));

            $user = $request->getAttribute('user');
            $uid = $user ? (string)($user['uid'] ?? '') : '';

            // Aceita a data no timezone configurado do servidor (America/Sao_Paulo)
            // e em UTC (gmdate) para evitar falha na janela 21:00-23:59 BRT ou em clientes
            // com cache antigo de JS / fuso horário divergente.
            $validDates = array_values(array_unique([
                date('Y-m-d'),
                gmdate('Y-m-d'),
            ]));

            $isValid = false;
            if ($tokenHeader !== '' && $uid !== '') {
                foreach ($validDates as $dateStr) {
                    $expectedToken = md5($uid . $dateStr);
                    $fallbackToken = substr(base64_encode($uid . $dateStr), 0, 32);
                    if (hash_equals($expectedToken, $tokenHeader) || hash_equals($fallbackToken, $tokenHeader)) {
                        $isValid = true;
                        break;
                    }
                }
            }

            if (!$isValid) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['error' => 'CSRF token inválido']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }
}