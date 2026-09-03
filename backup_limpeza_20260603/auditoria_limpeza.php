<?php
/**
 * AUDITORIA DE LIMPEZA - SISTEMA NUTRICIONAL
 * Identifica arquivos não utilizados, duplicados e órfãos
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "🧹 AUDITORIA DE LIMPEZA - SISTEMA NUTRICIONAL\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

$results = [
    'unused' => [],
    'duplicated' => [],
    'orphan' => [],
    'ok' => [],
    'warnings' => []
];

// ======================================================================
// 1. MAPEAR TODOS OS ARQUIVOS PHP DO PROJETO
// ======================================================================
echo "📁 MAPEANDO ARQUIVOS DO PROJETO...\n\n";

$projectRoot = __DIR__;
$allPhpFiles = [];

function scanPhpFiles($dir, $baseDir = '', &$allPhpFiles) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($item === 'vendor') continue;
        if ($item === 'docs') continue;
        if ($item === 'cache') continue;
        if ($item === 'logs') continue;
        if ($item === 'temp') continue;
        
        $path = $dir . '/' . $item;
        $relativePath = ltrim(str_replace($baseDir, '', $path), '/');
        
        if (is_dir($path)) {
            scanPhpFiles($path, $baseDir, $allPhpFiles);
        } elseif (str_ends_with($item, '.php')) {
            $allPhpFiles[$relativePath] = [
                'size' => filesize($path),
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
                'content' => file_get_contents($path)
            ];
        }
    }
}

scanPhpFiles($projectRoot, $projectRoot, $allPhpFiles);

echo "✅ Total de arquivos PHP encontrados: " . count($allPhpFiles) . "\n\n";

// ======================================================================
// 2. IDENTIFICAR CONTROLLERS NÃO UTILIZADOS
// ======================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 1. CONTROLLERS NÃO UTILIZADOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$controllersDir = $projectRoot . '/v1/src/Controllers';
$routesFile = $projectRoot . '/v1/routes/api.php';
$usedControllers = [];

if (file_exists($routesFile)) {
    $routesContent = file_get_contents($routesFile);
    preg_match_all('/([A-Z][a-zA-Z]+Controller)::class/', $routesContent, $matches);
    $usedControllers = array_unique($matches[1] ?? []);
    
    echo "✅ Controllers utilizados nas rotas: " . count($usedControllers) . "\n";
    foreach ($usedControllers as $ctrl) {
        echo "   ✅ {$ctrl}\n";
    }
}

// Verificar controllers não utilizados
if (is_dir($controllersDir)) {
    $controllers = scandir($controllersDir);
    $controllers = array_filter($controllers, fn($f) => str_ends_with($f, '.php'));
    
    $unusedControllers = [];
    foreach ($controllers as $ctrl) {
        $ctrlName = str_replace('.php', '', $ctrl);
        if (!in_array($ctrlName, $usedControllers)) {
            $unusedControllers[] = $ctrl;
        }
    }
    
    if (count($unusedControllers) > 0) {
        echo "\n⚠️ Controllers NÃO utilizados nas rotas:\n";
        foreach ($unusedControllers as $ctrl) {
            echo "   ❌ {$ctrl}\n";
            $results['unused'][] = "v1/src/Controllers/{$ctrl}";
        }
    } else {
        echo "\n✅ Todos os controllers estão sendo utilizados!\n";
    }
}

// ======================================================================
// 3. IDENTIFICAR MÓDULOS FRONTEND SEM JS CORRESPONDENTE
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 2. MÓDULOS SEM ARQUIVO JS CORRESPONDENTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$modulesDir = $projectRoot . '/portal/modules';
$jsDir = $projectRoot . '/portal/assets/js';

$modules = [];
$jsFiles = [];

if (is_dir($modulesDir)) {
    $modules = array_filter(scandir($modulesDir), fn($f) => str_ends_with($f, '.php'));
}

if (is_dir($jsDir)) {
    $jsFiles = array_filter(scandir($jsDir), fn($f) => str_ends_with($f, '.js'));
}

$modulesWithoutJs = [];
foreach ($modules as $module) {
    $jsName = str_replace('.php', '.js', $module);
    if (!in_array($jsName, $jsFiles) && $module !== 'catalogo.php') {
        $modulesWithoutJs[] = $module;
    }
}

if (count($modulesWithoutJs) > 0) {
    echo "⚠️ Módulos sem arquivo JS correspondente:\n";
    foreach ($modulesWithoutJs as $module) {
        echo "   ❌ {$module}\n";
        $results['orphan'][] = "portal/modules/{$module} (sem JS)";
    }
} else {
    echo "✅ Todos os módulos têm arquivo JS correspondente!\n";
}

// ======================================================================
// 4. IDENTIFICAR JS ÓRFÃOS (SEM MÓDULO CORRESPONDENTE)
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 3. ARQUIVOS JS SEM MÓDULO CORRESPONDENTE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$jsWithoutModule = [];
$coreJs = ['auth.js', 'core.js', 'config.js', 'portal.js', 'chat.js', 'crona.js', 'marketing-utils.js', 'crons.js'];

foreach ($jsFiles as $js) {
    if (in_array($js, $coreJs)) continue;
    
    $moduleName = str_replace('.js', '.php', $js);
    if (!in_array($moduleName, $modules)) {
        $jsWithoutModule[] = $js;
    }
}

if (count($jsWithoutModule) > 0) {
    echo "⚠️ Arquivos JS sem módulo correspondente:\n";
    foreach ($jsWithoutModule as $js) {
        echo "   ⚠️ {$js}\n";
        $results['orphan'][] = "portal/assets/js/{$js} (sem módulo)";
    }
} else {
    echo "✅ Todos os JS têm módulo correspondente!\n";
}

// ======================================================================
// 5. IDENTIFICAR ARQUIVOS DUPLICADOS
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 4. ARQUIVOS DUPLICADOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$filesByContent = [];
foreach ($allPhpFiles as $path => $info) {
    $hash = md5($info['content']);
    if (!isset($filesByContent[$hash])) {
        $filesByContent[$hash] = [];
    }
    $filesByContent[$hash][] = $path;
}

$duplicates = [];
foreach ($filesByContent as $hash => $files) {
    if (count($files) > 1) {
        $duplicates[] = $files;
    }
}

if (count($duplicates) > 0) {
    echo "⚠️ Arquivos duplicados encontrados:\n";
    foreach ($duplicates as $dupe) {
        echo "   📄 Conteúdo idêntico:\n";
        foreach ($dupe as $file) {
            echo "      - {$file}\n";
            $results['duplicated'][] = $file;
        }
        echo "\n";
    }
} else {
    echo "✅ Nenhum arquivo duplicado encontrado!\n";
}

// ======================================================================
// 6. VERIFICAR ARQUIVOS DE TESTE E LEGADO
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 5. ARQUIVOS DE TESTE E LEGADO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$legacyFiles = ['index_legado.php', 'sql.php', 'test_logout.php', 'diagnostico.php', 'diagnostico_avancado.php', 'diagnostico_v2.php', 'auditoria_limpeza.php'];
$foundLegacy = [];

foreach ($legacyFiles as $legacy) {
    if (file_exists($projectRoot . '/' . $legacy)) {
        $foundLegacy[] = $legacy;
    }
}

if (count($foundLegacy) > 0) {
    echo "⚠️ Arquivos de teste/legado encontrados:\n";
    foreach ($foundLegacy as $legacy) {
        echo "   🗑️ {$legacy}\n";
        $results['unused'][] = $legacy;
    }
} else {
    echo "✅ Nenhum arquivo de teste/legado encontrado!\n";
}

// ======================================================================
// 7. VERIFICAR ARQUIVOS COMPONENTS NÃO UTILIZADOS
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 6. COMPONENTS NÃO UTILIZADOS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$componentsDir = $projectRoot . '/portal/estrutura';
$adminComponentsDir = $projectRoot . '/portal/modules/admin/components';

$usedComponents = [];

// Verificar uso nos módulos
foreach ($modules as $module) {
    $content = file_get_contents($modulesDir . '/' . $module);
    if (strpos($content, "require_once __DIR__ . '/../estrutura/header.php'") !== false) {
        $usedComponents['header.php'] = true;
    }
    if (strpos($content, "require_once __DIR__ . '/../estrutura/footer.php'") !== false) {
        $usedComponents['footer.php'] = true;
    }
}

// Verificar admin components
if (is_dir($adminComponentsDir)) {
    $adminFiles = scandir($adminComponentsDir);
    $adminPhp = array_filter($adminFiles, fn($f) => str_ends_with($f, '.php'));
    
    echo "📁 Admin components encontrados:\n";
    foreach ($adminPhp as $comp) {
        echo "   📄 admin/components/{$comp}\n";
        $results['ok'][] = "portal/modules/admin/components/{$comp}";
    }
}

// ======================================================================
// 8. VERIFICAR IMAGENS NÃO UTILIZADAS
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎯 7. IMAGENS NÃO UTILIZADAS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$imgDir = $projectRoot . '/portal/assets/img';
if (is_dir($imgDir)) {
    $images = scandir($imgDir);
    $images = array_filter($images, fn($f) => !in_array($f, ['.', '..', '.htaccess']));
    
    // Procurar referências às imagens nos arquivos PHP/JS
    $allContent = '';
    foreach ($allPhpFiles as $path => $info) {
        $allContent .= $info['content'];
    }
    
    $unusedImages = [];
    foreach ($images as $img) {
        if (strpos($allContent, $img) === false && $img !== 'no-image.png' && $img !== 'logo.png') {
            $unusedImages[] = $img;
        }
    }
    
    if (count($unusedImages) > 0) {
        echo "⚠️ Imens não utilizadas:\n";
        foreach ($unusedImages as $img) {
            echo "   🖼️ {$img}\n";
            $results['unused'][] = "portal/assets/img/{$img}";
        }
    } else {
        echo "✅ Todas as imagens são utilizadas!\n";
    }
}

// ======================================================================
// 9. RESUMO FINAL
// ======================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 RESUMO FINAL - ARQUIVOS PARA LIMPEZA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🗑️ ARQUIVOS NÃO UTILIZADOS (para remover):\n";
if (count($results['unused']) > 0) {
    foreach ($results['unused'] as $file) {
        echo "   - {$file}\n";
    }
} else {
    echo "   ✅ Nenhum arquivo não utilizado encontrado!\n";
}

echo "\n🔄 ARQUIVOS DUPLICADOS:\n";
if (count($results['duplicated']) > 0) {
    foreach ($results['duplicated'] as $file) {
        echo "   - {$file}\n";
    }
} else {
    echo "   ✅ Nenhum arquivo duplicado encontrado!\n";
}

echo "\n📄 ARQUIVOS ÓRFÃOS:\n";
if (count($results['orphan']) > 0) {
    foreach ($results['orphan'] as $file) {
        echo "   - {$file}\n";
    }
} else {
    echo "   ✅ Nenhum arquivo órfão encontrado!\n";
}

echo "\n✅ ARQUIVOS VERIFICADOS E OK:\n";
echo "   - " . count($results['ok']) . " arquivos components\n";
echo "   - " . count($usedControllers) . " controllers em uso\n";
echo "   - " . (count($modules) - count($modulesWithoutJs)) . " módulos com JS\n";

echo "\n═══════════════════════════════════════════════════════════════════════════════\n";
echo "🎯 RECOMENDAÇÕES\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n\n";

$totalIssues = count($results['unused']) + count($results['duplicated']) + count($results['orphan']);

if ($totalIssues === 0) {
    echo "🏆 SISTEMA LIMPO! Nenhum arquivo problemático encontrado.\n";
} else {
    echo "⚠️ Total de itens para revisar: {$totalIssues}\n\n";
    echo "Ações recomendadas:\n";
    
    if (count($results['unused']) > 0) {
        echo "   1. Remover arquivos não utilizados (testes/legado)\n";
    }
    if (count($results['duplicated']) > 0) {
        echo "   2. Remover arquivos duplicados, manter apenas um\n";
    }
    if (count($results['orphan']) > 0) {
        echo "   3. Revisar arquivos órfãos (podem ser removidos ou renomeados)\n";
    }
}

echo "\n💾 Backup recomendado antes de remover arquivos:\n";
echo "   mkdir backup_limpeza_$(date +%Y%m%d)\n";
echo "   mv arquivo_nao_utilizado.php backup_limpeza_$(date +%Y%m%d)/\n\n";

echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "✅ AUDITORIA CONCLUÍDA\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";