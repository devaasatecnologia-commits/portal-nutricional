<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

class BaseController
{
    /**
     * Retorna resposta JSON
     */
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Retorna erro JSON
     */
    protected function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        return $this->jsonResponse($response, [
            'status' => 'error',
            'message' => $message
        ], $status);
    }

    /**
     * Valida campos obrigatórios
     */
    protected function validateRequired(array $data, array $fields): ?array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Campo '{$field}' é obrigatório";
            }
        }
        return empty($errors) ? null : $errors;
    }
}