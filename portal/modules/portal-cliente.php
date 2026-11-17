<?php
$pageTitle = 'Portal do Cliente & Hierarquia Comercial | Nutricional';
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$version = @filemtime(__DIR__ . '/../assets/js/portal-cliente.js') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="<?= $appBase ?>/portal/assets/js/config.js?v=<?= $version ?>"></script>
    <script src="<?= $appBase ?>/portal/assets/js/portal-cliente.js?v=<?= $version ?>"></script>
    <style>[x-cloak]{display:none !important;}</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen" x-data="portalClienteHandler('<?= $appBase ?>')" x-init="init()">

    <!-- ================================================================== -->
    <!-- TELA 1: AUTENTICAÇÃO B2B (E-MAIL/CNPJ + TURNSTILE + 2FA E-MAIL)    -->
    <!-- ================================================================== -->
    <div x-show="!autenticado" x-cloak class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900">
        <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-2 bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Coluna Branding -->
            <div class="bg-gradient-to-br from-emerald-900 to-slate-900 text-white p-8 flex flex-col justify-between">
                <div>
                    <img src="<?= $appBase ?>/portal/assets/img/logo-danfe-nutricional.png" alt="Nutricional" class="h-12 bg-white px-3 py-1.5 rounded-xl mb-6">
                    <h1 class="text-2xl font-black tracking-tight mb-2">Portal do Cliente &amp; Parceiro</h1>
                    <p class="text-xs text-emerald-100/80 leading-relaxed">
                        Consulte seus <strong>Boletos em Aberto</strong> (com código de barras para pagamento), <strong>Notas Fiscais</strong> e acompanhamento de <strong>Pedidos</strong> em tempo real.
                    </p>
                </div>
                <div class="space-y-2.5 text-xs text-emerald-100/90 mt-8">
                    <div class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-400"></i> Proteção Cloudflare Turnstile &amp; 2FA por E-mail</div>
                    <div class="flex items-center gap-2"><i class="fa-solid fa-sitemap text-emerald-400"></i> Hierarquia Gestor → Representante → Cliente</div>
                    <div class="flex items-center gap-2"><i class="fa-solid fa-barcode text-emerald-400"></i> Segunda via rápida de boletos e chaves NF-e</div>
                </div>
            </div>

            <!-- Coluna Formulários -->
            <div class="p-7 sm:p-8 flex flex-col justify-center">
                <div class="flex gap-3 mb-5 border-b border-slate-200 pb-2 text-xs font-extrabold">
                    <button @click="modoAuth = 'login'; erroAuth = ''; msgAuth = ''" :class="modoAuth === 'login' ? 'text-emerald-700 border-b-2 border-emerald-600 pb-1' : 'text-slate-400'">Entrar</button>
                    <button @click="modoAuth = 'esqueci'; etapaReset = false; erroAuth = ''; msgAuth = ''" :class="modoAuth === 'esqueci' ? 'text-emerald-700 border-b-2 border-emerald-600 pb-1' : 'text-slate-400'">Primeiro Acesso / Esqueci Senha</button>
                    <button @click="modoAuth = 'cadastro'; erroAuth = ''; msgAuth = ''" :class="modoAuth === 'cadastro' ? 'text-emerald-700 border-b-2 border-emerald-600 pb-1' : 'text-slate-400'">Solicitar Cadastro</button>
                </div>

                <div x-show="erroAuth" class="mb-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold" x-text="erroAuth"></div>
                <div x-show="msgAuth" class="mb-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold" x-text="msgAuth"></div>

                <!-- LOGIN + 2FA -->
                <div x-show="modoAuth === 'login'" class="space-y-3">
                    <template x-if="!etapa2fa">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">E-mail ou CNPJ/CPF</label>
                                <input type="text" x-model="formLogin.identificador" placeholder="seu@email.com ou 00.000.000/0001-00"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Senha</label>
                                <input type="password" x-model="formLogin.senha" @keyup.enter="enviarLogin()" placeholder="••••••••"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                            </div>
                            <div id="turnstile-container" class="pt-1" x-show="turnstileAtivo"></div>
                            <button @click="enviarLogin()" :disabled="loadingAuth"
                                    class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-extrabold transition">
                                <i class="fa-solid fa-right-to-bracket mr-1"></i> Entrar &amp; Enviar Código 2FA
                            </button>
                        </div>
                    </template>

                    <template x-if="etapa2fa">
                        <div class="space-y-3">
                            <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-xs text-indigo-900">
                                Enviamos um código de <strong>6 dígitos</strong> para <strong x-text="emailMascarado"></strong>.
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Código 2FA (6 dígitos)</label>
                                <input type="text" maxlength="6" x-model="form2fa.codigo" @keyup.enter="confirmar2fa()" placeholder="000000"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-center font-mono text-lg font-black tracking-widest">
                            </div>
                            <button @click="confirmar2fa()" :disabled="loadingAuth"
                                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold">
                                Validar Código &amp; Acessar
                            </button>
                            <button @click="etapa2fa = false" class="w-full text-xs text-slate-500 font-bold hover:underline">Voltar</button>
                        </div>
                    </template>

                    <div x-show="temTokenPortalInterno" class="pt-3 border-t border-slate-200 text-center">
                        <button @click="entrarComSessaoPortal()" class="w-full py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold">
                            <i class="fa-solid fa-user-tie mr-1"></i> Acessar como Gestor / Representante (Sessão Portal)
                        </button>
                    </div>
                </div>

                <!-- SOLICITAR CADASTRO -->
                <div x-show="modoAuth === 'cadastro'" class="space-y-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">CNPJ ou CPF Cadastrado</label>
                        <input type="text" x-model="formCadastro.cnpj_cpf" placeholder="Somente números ou formatado"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">E-mail de Acesso</label>
                        <input type="email" x-model="formCadastro.email" placeholder="financeiro@suaempresa.com.br"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Definir Senha (mín. 6 caracteres)</label>
                        <input type="password" x-model="formCadastro.senha" placeholder="••••••••"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                    </div>
                    <button @click="enviarCadastro()" :disabled="loadingAuth"
                            class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-extrabold">
                        Solicitar Cadastro B2B
                    </button>
                </div>

                <!-- PRIMEIRO ACESSO / ESQUECEU A SENHA -->
                <div x-show="modoAuth === 'esqueci'" class="space-y-3">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600 leading-relaxed">
                        <strong>Cliente ou Representante ativo:</strong> informe seu <strong>CNPJ/CPF</strong> ou <strong>E-mail cadastrado</strong> para receber o código de 6 dígitos e criar ou redefinir sua senha.
                    </div>
                    <template x-if="!etapaReset">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">CNPJ/CPF ou E-mail Cadastrado</label>
                                <input type="text" x-model="formReset.identificador" @keyup.enter="solicitarReset()" placeholder="00.000.000/0001-00 ou seu@email.com"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                            </div>
                            <button @click="solicitarReset()" :disabled="loadingAuth"
                                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold">
                                Enviar Código para o E-mail Cadastrado
                            </button>
                        </div>
                    </template>
                    <template x-if="etapaReset">
                        <div class="space-y-3">
                            <input type="text" maxlength="6" x-model="formReset.codigo" placeholder="Código 6 dígitos"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-center font-mono font-bold">
                            <input type="password" x-model="formReset.nova_senha" placeholder="Nova senha (mín. 6 caracteres)"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold">
                            <button @click="confirmarReset()" :disabled="loadingAuth"
                                    class="w-full py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-extrabold">
                                Salvar Nova Senha
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- TELA 2: ÁREA LOGADA (HIERARQUIA COMERCIAL + BOLETOS/NOTAS/PEDIDOS) -->
    <!-- ================================================================== -->
    <div x-show="autenticado" x-cloak class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
        <!-- HEADER -->
        <div class="bg-gradient-to-r from-slate-900 via-emerald-950 to-emerald-900 text-white rounded-2xl p-5 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a x-show="temTokenPortalInterno" href="<?= $appBase ?>/portal/" class="px-2.5 py-1 rounded-lg bg-white/15 hover:bg-white/25 text-white text-xs font-bold no-underline">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Portal
                    </a>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-500/30 border border-emerald-400/40" x-text="'PERFIL: ' + (escopo.perfil || 'CLIENTE')"></span>
                    <span class="text-xs font-bold text-emerald-200" x-text="escopo.nome"></span>
                </div>
                <h1 class="text-xl font-black tracking-tight">Portal do Cliente &amp; Carteira Comercial</h1>
            </div>
            <button @click="sair()" class="px-4 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/40 border border-rose-400/30 text-white text-xs font-extrabold self-start md:self-auto">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> Sair
            </button>
        </div>

        <!-- SELETOR DE HIERARQUIA (GESTOR / REPRESENTANTE) -->
        <div x-show="escopo.perfil !== 'CLIENTE'" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div x-show="escopo.perfil === 'ADMIN' || escopo.perfil === 'GESTOR'">
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Filtrar Representante</label>
                <select x-model="filtroRep" @change="carregarContexto()" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-bold">
                    <option value="0">Todos os Representantes Liberados</option>
                    <template x-for="r in representantes" :key="r.id">
                        <option :value="r.id" x-text="r.id + ' - ' + r.nome"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Buscar Cliente (Razão / CNPJ / ID)</label>
                <input type="text" x-model="buscaCliente" @input.debounce.350ms="carregarContexto()" placeholder="Digite para filtrar carteira..."
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Cliente Selecionado (<span x-text="clientes.length"></span> liberados)</label>
                <select x-model="clienteSelecionadoId" @change="carregarClienteResumo(clienteSelecionadoId)" class="w-full px-3 py-2 rounded-xl border border-emerald-400 bg-emerald-50/40 text-xs font-extrabold text-slate-900">
                    <template x-for="c in clientes" :key="c.idcliforemp">
                        <option :value="c.idcliforemp" x-text="c.idcliforemp + ' - ' + c.razao + ' (' + c.cidade + '/' + c.uf + ')'"></option>
                    </template>
                </select>
            </div>
        </div>

        <!-- KPIs DO CLIENTE -->
        <div x-show="clienteAtual" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                <div class="text-[10px] font-bold uppercase text-slate-400">Cliente Ativo</div>
                <div class="font-extrabold text-sm text-slate-900 truncate mt-0.5" x-text="clienteAtual?.razao"></div>
                <div class="text-[11px] font-mono text-slate-500 mt-1" x-text="(clienteAtual?.doc || '-') + ' • ' + (clienteAtual?.cidade || '') + '/' + (clienteAtual?.uf || '')"></div>
            </div>
            <div @click="aba = 'boletos'" class="bg-white rounded-2xl p-4 border border-amber-200 shadow-sm cursor-pointer hover:border-amber-400">
                <div class="text-[10px] font-bold uppercase text-amber-700">Boletos em Aberto (<span x-text="resumoCli.boletos_qtd"></span>)</div>
                <div class="text-xl font-black font-mono text-slate-900 mt-0.5" x-text="fmtMoeda(resumoCli.boletos_aberto)"></div>
                <div class="text-[11px] font-bold text-rose-600 mt-1" x-show="resumoCli.boletos_vencido > 0" x-text="'Vencido: ' + fmtMoeda(resumoCli.boletos_vencido)"></div>
            </div>
            <div @click="aba = 'notas'" class="bg-white rounded-2xl p-4 border border-indigo-200 shadow-sm cursor-pointer hover:border-indigo-400">
                <div class="text-[10px] font-bold uppercase text-indigo-700">Notas Fiscais</div>
                <div class="text-xl font-black font-mono text-indigo-700 mt-0.5" x-text="resumoCli.notas_qtd"></div>
                <div class="text-[11px] text-slate-400 mt-1">Últimas 50 notas emitidas</div>
            </div>
            <div @click="aba = 'pedidos'" class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm cursor-pointer hover:border-emerald-400">
                <div class="text-[10px] font-bold uppercase text-emerald-700">Pedidos</div>
                <div class="text-xl font-black font-mono text-emerald-700 mt-0.5" x-text="resumoCli.pedidos_qtd"></div>
                <div class="text-[11px] text-slate-400 mt-1">Últimos 50 pedidos</div>
            </div>
        </div>

        <!-- ABAS: BOLETOS / NOTAS / PEDIDOS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex border-b border-slate-200 bg-slate-50 px-4 pt-2 gap-2 text-xs font-extrabold">
                <button @click="aba = 'boletos'" :class="aba === 'boletos' ? 'bg-white text-emerald-700 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-barcode mr-1"></i> Boletos em Aberto
                </button>
                <button @click="aba = 'notas'" :class="aba === 'notas' ? 'bg-white text-indigo-700 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-file-invoice mr-1"></i> Notas Fiscais
                </button>
                <button @click="aba = 'pedidos'" :class="aba === 'pedidos' ? 'bg-white text-slate-900 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-cart-shopping mr-1"></i> Pedidos
                </button>
            </div>

            <!-- BOLETOS -->
            <div x-show="aba === 'boletos'" class="p-4 overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">Documento / Parcela</th>
                            <th class="px-3 py-2 text-left">Emissão</th>
                            <th class="px-3 py-2 text-left">Vencimento</th>
                            <th class="px-3 py-2 text-right">Valor Original</th>
                            <th class="px-3 py-2 text-right">Saldo Aberto</th>
                            <th class="px-3 py-2 text-left">Nosso Número</th>
                            <th class="px-3 py-2 text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="b in boletos" :key="b.idfilial + '-' + b.idreceber">
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-bold" x-text="b.documento + ' / ' + b.parcela"></td>
                                <td class="px-3 py-2" x-text="fmtData(b.dataemissao)"></td>
                                <td class="px-3 py-2 font-bold" :class="b.dias_atraso > 0 ? 'text-rose-600' : 'text-emerald-700'"
                                    x-text="fmtData(b.vencimento) + (b.dias_atraso > 0 ? ' (' + b.dias_atraso + 'd atraso)' : '')"></td>
                                <td class="px-3 py-2 text-right font-mono" x-text="fmtMoeda(b.valororiginal)"></td>
                                <td class="px-3 py-2 text-right font-mono font-extrabold" x-text="fmtMoeda(b.valorsaldo)"></td>
                                <td class="px-3 py-2 font-mono text-[11px]" x-text="b.nossonumero || '-'"></td>
                                <td class="px-3 py-2 text-center">
                                    <button x-show="b.codigobarra" @click="copiarTexto(b.codigobarra)" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px]">
                                        <i class="fa-regular fa-copy mr-1"></i> Copiar Cód. Barras
                                    </button>
                                    <span x-show="!b.codigobarra" class="text-[10px] text-slate-400">Sem linha digitável</span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="boletos.length === 0"><td colspan="7" class="py-8 text-center text-slate-400">Nenhum boleto em aberto.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- NOTAS FISCAIS -->
            <div x-show="aba === 'notas'" class="p-4 overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">NF / Filial</th>
                            <th class="px-3 py-2 text-left">Emissão</th>
                            <th class="px-3 py-2 text-left">Chave NF-e</th>
                            <th class="px-3 py-2 text-right">Valor Nota</th>
                            <th class="px-3 py-2 text-center">Itens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="n in notas" :key="n.idfilial + '-' + n.idnfs">
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-extrabold" x-text="'NF ' + n.numeronf + ' (F' + n.idfilial + ')'"></td>
                                <td class="px-3 py-2" x-text="fmtData(n.dataemissao)"></td>
                                <td class="px-3 py-2 font-mono text-[10px]">
                                    <span x-text="n.chave_nfe || '-'"></span>
                                    <button x-show="n.chave_nfe" @click="copiarTexto(n.chave_nfe)" class="ml-1 text-indigo-600 hover:underline font-sans font-bold">[Copiar]</button>
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(n.valortotal)"></td>
                                <td class="px-3 py-2 text-center">
                                    <button @click="abrirItens('nota', n)" class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px]">Ver Itens</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="notas.length === 0"><td colspan="5" class="py-8 text-center text-slate-400">Nenhuma nota fiscal encontrada.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- PEDIDOS -->
            <div x-show="aba === 'pedidos'" class="p-4 overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="px-3 py-2 text-left">Pedido</th>
                            <th class="px-3 py-2 text-left">Data</th>
                            <th class="px-3 py-2 text-left">Representante</th>
                            <th class="px-3 py-2 text-right">Valor Pedido</th>
                            <th class="px-3 py-2 text-center">Itens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="p in pedidos" :key="p.idfilial + '-' + p.idpedido">
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-extrabold" x-text="'#' + p.idpedido + ' (F' + p.idfilial + ')'"></td>
                                <td class="px-3 py-2" x-text="fmtData(p.data)"></td>
                                <td class="px-3 py-2" x-text="p.vendedor || '-'"></td>
                                <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(p.valortotal)"></td>
                                <td class="px-3 py-2 text-center">
                                    <button @click="abrirItens('pedido', p)" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px]">Ver Itens</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="pedidos.length === 0"><td colspan="5" class="py-8 text-center text-slate-400">Nenhum pedido encontrado.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL ITENS (z-[70]) -->
    <div x-show="modalItens" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 p-4">
        <div @click.away="modalItens = false" class="bg-white rounded-2xl max-w-3xl w-full max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between">
                <h3 class="text-sm font-extrabold" x-text="modalTitulo"></h3>
                <button @click="modalItens = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-4 overflow-y-auto">
                <table class="w-full text-xs">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr><th class="px-2 py-2 text-left">Item</th><th class="px-2 py-2 text-right">Qtd</th><th class="px-2 py-2 text-right">Valor Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="it in itensModal" :key="it.iditem + '-' + it.valor_total">
                            <tr>
                                <td class="px-2 py-2" x-text="it.iditem + ' - ' + it.descricao + (it.referencia ? ' (' + it.referencia + ')' : '')"></td>
                                <td class="px-2 py-2 text-right font-mono" x-text="it.quantidade"></td>
                                <td class="px-2 py-2 text-right font-mono font-bold" x-text="fmtMoeda(it.valor_total)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
