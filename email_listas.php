<?php
/**
 * email_listas.php
 * 
 * GERENCIAMENTO DE LISTAS DE E-MAILS POR MÓDULO
 * 
 * COMO USAR:
 * require_once __DIR__ . '/email_listas.php';
 * $emails = getEmails('representantes_cc');
 */

// ============================================================
// LISTAS DE E-MAILS POR CONTEXTO
// ============================================================

class EmailListas {
    
    public static function get($contexto = 'default') {
        
        $listas = [
            
            // 1. GESTORES / LÍDERES
            'gestores' => [
                'alan@nutricionalbr.com',
                'robson@nutricionalbr.com',
                'tiago@nutricionalbr.com',
                'financeiro@nutricionalbr.com',
                'michel@nutricionalbr.com',
                'tales@nutricionalbr.com',
                'a.eleodoro@nutricionalbr.com'
            ],
            
            // 2. RELATÓRIO MENSAL - REPRESENTANTES (CÓPIAS CC)
            'representantes_cc' => [
                'alan@nutricionalbr.com',
                'robson@nutricionalbr.com'
            ],
            
            // 3. RELATÓRIO MENSAL - CONSOLIDADO
            'consolidado_representantes' => [
                'alan@nutricionalbr.com',
                'robson@nutricionalbr.com',
                'tiago@nutricionalbr.com'
            ],
            
            // 4. RELATÓRIO DE GESTORES (DESTINATÁRIOS)
            'gestores_destino' => [
                'alan@nutricionalbr.com',
                'robson@nutricionalbr.com',
                'tiago@nutricionalbr.com',
                'financeiro@nutricionalbr.com',
                'michel@nutricionalbr.com',
                'tales@nutricionalbr.com',
                'a.eleodoro@nutricionalbr.com'
            ],
            
          
            // 5. ALTERAÇÕES DE PEDIDOS (CÓPIAS CC)
            'alteracoes_cc' => [
                'alan@nutricionalbr.com'
            ],
            
            // 6. ALTERAÇÕES DE PEDIDOS - CONSOLIDADO
            'alteracoes_consolidado' => [
                'alan@nutricionalbr.com',
                'robson@nutricionalbr.com'
            ],
            
            // 7. DIVERGÊNCIA DE XML
            'divergencia_xml' => [
                'alan@nutricionalbr.com',
                'robson@nutricionalbr.com',
                'faturamento@nutricionalbr.com'
            ],
			// 8. PEDIDOS AGUARDANDO APROVAÇÃO (CÓPIAS CC)
'pedidos_aguardando_cc' => [
    'alan@nutricionalbr.com',
    'robson@nutricionalbr.com'
],

// 9. PEDIDOS AGUARDANDO APROVAÇÃO - CONSOLIDADO
'pedidos_aguardando_consolidado' => [
    'alan@nutricionalbr.com',
    'robson@nutricionalbr.com',
    'tiago@nutricionalbr.com'
],
            
            // 10. DEFAULT
            'default' => [
                'alan@nutricionalbr.com'
            ]
        ];
        
        $emails = isset($listas[$contexto]) ? $listas[$contexto] : $listas['default'];
        return self::validar($emails);
    }
    
    private static function validar($emails) {
        $validos = [];
        foreach ($emails as $email) {
            $email = trim($email);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validos[] = $email;
            }
        }
        return array_unique($validos);
    }
    
    public static function debug() {
        $contextos = [
            'gestores' => '👔 Gestores (Principal)',
            'representantes_cc' => '📧 Representantes (CC)',
            'consolidado_representantes' => '📊 Consolidado Representantes',
            'gestores_destino' => '👔 Gestores (Destino)',
            'divergencia_xml' => '⚠️ Divergência XML',
            'default' => '📌 Default'
        ];
        
        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                h1 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
                table th { background: #0066cc; color: white; padding: 12px; text-align: left; }
                table td { padding: 10px; border-bottom: 1px solid #ddd; }
                table tr:hover { background: #f5f5f5; }
                .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
                .badge-success { background: #d4edda; color: #155724; }
                .badge-info { background: #d1ecf1; color: #0c5460; }
                .btn { display: inline-block; padding: 10px 20px; background: #0066cc; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
                .btn:hover { background: #004499; }
                .total { text-align: center; font-weight: bold; }
            </style>
        </head>
        <body>
        <div class='container'>
            <h1>📧 Listas de E-mails Configuradas</h1>
            <p>Gerencie os destinatários de cada módulo do sistema.</p>
            <hr>";
            
        echo "<table>";
        echo "<tr><th>Contexto</th><th>Destinatários</th><th>Total</th></tr>";
        
        foreach ($contextos as $contexto => $label) {
            $emails = self::get($contexto);
            $total = count($emails);
            $lista = implode('<br>', array_map('htmlspecialchars', $emails));
            $badge = $total > 0 ? 'badge-success' : 'badge-info';
            echo "<tr>
                    <td><strong>{$label}</strong><br><small style='color:#999;'>{$contexto}</small></td>
                    <td>{$lista}</td>
                    <td class='total'><span class='badge {$badge}'>{$total}</span></td>
                  </tr>";
        }
        
        echo "</table>";
        echo "
            <hr>
            <a href='?acao=home' class='btn'>← Voltar</a>
        </div>
        </body>
        </html>";
    }
}

// ============================================================
// FUNÇÕES DE ATAJO
// ============================================================

function getEmails($contexto = 'default') {
    return EmailListas::get($contexto);
}

function debugEmails() {
    EmailListas::debug();
}