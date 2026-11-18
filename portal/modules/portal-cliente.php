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
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="<?= $appBase ?>/portal/assets/js/config.js?v=<?= $version ?>"></script>
    <script src="<?= $appBase ?>/portal/assets/js/portal-cliente.js?v=<?= $version ?>"></script>
    <style>
        [x-cloak]{display:none !important;}
        @media print {
            body.print-danfe > *:not(#modal-danfe-b2b),
            body.print-boleto > *:not(#modal-boleto-b2b) { display: none !important; }
            #modal-danfe-b2b, #modal-boleto-b2b { position: static !important; background: #fff !important; padding: 0 !important; }
            #modal-danfe-b2b .no-print, #modal-boleto-b2b .no-print { display: none !important; }
            #modal-danfe-b2b .print-box, #modal-boleto-b2b .print-box { max-width: 100% !important; max-height: none !important; box-shadow: none !important; border: none !important; overflow: visible !important; }
        }
    </style>
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

                <!-- Widget Cloudflare Turnstile compartilhado entre Entrar / Primeiro Acesso / Cadastro -->
                <div id="turnstile-container" class="pt-3" x-show="turnstileAtivo && !etapa2fa && !etapaReset"></div>
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

        <!-- DASHBOARD DE TRANSPARÊNCIA + LIMITE DE CRÉDITO -->
        <div x-show="clienteAtual" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Limite de Crédito -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Limite de Crédito</span>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase"
                              :class="clienteAtual?.credito_bloqueado === 'S' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                              x-text="clienteAtual?.situacao_credito || 'Liberado'"></span>
                    </div>
                    <div class="text-xl font-black font-mono text-slate-900 mt-1" x-text="fmtMoeda(resumoCli.limite_credito)"></div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden mt-2">
                        <div class="h-full rounded-full transition-all"
                             :class="resumoCli.limite_credito > 0 && (resumoCli.limite_utilizado / resumoCli.limite_credito) > 0.85 ? 'bg-rose-500' : 'bg-emerald-600'"
                             :style="'width:' + (resumoCli.limite_credito > 0 ? Math.min(100, Math.round((resumoCli.limite_utilizado / resumoCli.limite_credito) * 100)) : 0) + '%'"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-500 mt-1.5 font-semibold">
                        <span x-text="'Disponível: ' + fmtMoeda(resumoCli.limite_disponivel)"></span>
                        <span x-text="'Uso: ' + fmtMoeda(resumoCli.limite_utilizado)"></span>
                    </div>
                </div>

                <!-- Card 2: Títulos em Aberto -->
                <div @click="aba = 'boletos'" class="bg-white rounded-2xl p-4 border border-amber-200 shadow-sm cursor-pointer hover:border-amber-400">
                    <div class="text-[10px] font-bold uppercase text-amber-700">Títulos / Boletos em Aberto (<span x-text="resumoCli.boletos_qtd"></span>)</div>
                    <div class="text-xl font-black font-mono text-slate-900 mt-1" x-text="fmtMoeda(resumoCli.boletos_aberto)"></div>
                    <div class="text-[11px] font-bold mt-1.5" :class="resumoCli.boletos_vencido > 0 ? 'text-rose-600' : 'text-emerald-700'"
                         x-text="resumoCli.boletos_vencido > 0 ? ('Vencido: ' + fmtMoeda(resumoCli.boletos_vencido)) : 'Nenhum título vencido'"></div>
                </div>

                <!-- Card 3: Compras 12M -->
                <div @click="aba = 'itens'" class="bg-white rounded-2xl p-4 border border-emerald-200 shadow-sm cursor-pointer hover:border-emerald-400">
                    <div class="text-[10px] font-bold uppercase text-emerald-700">Compras (Últimos 12 Meses)</div>
                    <div class="text-xl font-black font-mono text-emerald-700 mt-1" x-text="fmtMoeda(resumoCli.total_comprado_12m)"></div>
                    <div class="text-[11px] text-slate-500 mt-1.5" x-text="'Última compra: ' + fmtData(resumoCli.ultima_compra) + ' • ' + resumoCli.itens_qtd + ' produtos'"></div>
                </div>

                <!-- Card 4: Notas & Pedidos -->
                <div @click="aba = 'notas'" class="bg-white rounded-2xl p-4 border border-indigo-200 shadow-sm cursor-pointer hover:border-indigo-400">
                    <div class="text-[10px] font-bold uppercase text-indigo-700">Notas &amp; Pedidos</div>
                    <div class="flex items-baseline gap-3 mt-1">
                        <div><span class="text-xl font-black font-mono text-indigo-700" x-text="resumoCli.notas_qtd"></span> <span class="text-[10px] text-slate-500 font-bold">Notas</span></div>
                        <div><span class="text-xl font-black font-mono text-slate-800" x-text="resumoCli.pedidos_qtd"></span> <span class="text-[10px] text-slate-500 font-bold">Pedidos</span></div>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-1.5 truncate" x-text="'Consultor: ' + (clienteAtual?.vendedor_nome || '-')"></div>
                </div>
            </div>

            <!-- Dados Cadastrais do Cliente -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                <div class="text-[10px] font-black uppercase text-slate-400 mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-address-card text-emerald-600"></i> Dados Cadastrais no ERP
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                    <div><span class="text-[10px] text-slate-400 block">Razão Social / Fantasia</span><strong class="text-slate-800" x-text="clienteAtual?.razao"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">CNPJ/CPF • IE</span><strong class="font-mono text-slate-800" x-text="(clienteAtual?.doc || '-') + ' • IE: ' + (clienteAtual?.ie || '-')"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Endereço</span><strong class="text-slate-700" x-text="(clienteAtual?.endereco || '-') + ', ' + (clienteAtual?.bairro || '') + ' — ' + (clienteAtual?.cidade || '') + '/' + (clienteAtual?.uf || '') + ' (' + (clienteAtual?.cep || '') + ')'"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Contato</span><strong class="text-slate-700" x-text="(clienteAtual?.email || '-') + ' • ' + (clienteAtual?.fone || '-')"></strong></div>
                </div>
            </div>

            <!-- 2 CARDS DE ATENDIMENTO DIRETO (CAPA DO CLIENTE: REPRESENTANTE + FINANCEIRO NUTRICIONAL) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Card Representante -->
                <div class="bg-gradient-to-br from-emerald-50 to-white rounded-2xl p-4 border border-emerald-200 shadow-sm flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-black uppercase text-emerald-700 flex items-center gap-1.5">
                                <i class="fa-solid fa-user-tie"></i> Seu Consultor / Representante
                            </div>
                            <div class="text-sm font-black text-slate-900 mt-1" x-text="clienteAtual?.vendedor_nome || 'Atendimento Comercial Nutricional'"></div>
                            <div class="text-[11px] text-slate-600 mt-0.5" x-text="(clienteAtual?.vendedor_celular || clienteAtual?.vendedor_fone || '(48) 3524-6543') + (clienteAtual?.vendedor_email ? ' • ' + clienteAtual.vendedor_email : '')"></div>
                        </div>
                        <span class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-handshake"></i></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="linkWhats(clienteAtual?.vendedor_celular || clienteAtual?.vendedor_fone || '4835246543', 'Olá! Gostaria de falar sobre meus pedidos na Nutricional.')" target="_blank"
                           class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold no-underline inline-flex items-center gap-1.5">
                            <i class="fa-brands fa-whatsapp text-sm"></i> WhatsApp
                        </a>
                        <a :href="'tel:' + limparFone(clienteAtual?.vendedor_celular || clienteAtual?.vendedor_fone || '4835246543')"
                           class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 text-[11px] font-bold no-underline inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-phone"></i> Ligar
                        </a>
                        <a x-show="clienteAtual?.vendedor_email" :href="'mailto:' + clienteAtual?.vendedor_email"
                           class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 text-[11px] font-bold no-underline inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-envelope"></i> E-mail
                        </a>
                    </div>
                </div>

                <!-- Card Financeiro Nutricional -->
                <div class="bg-gradient-to-br from-indigo-50 to-white rounded-2xl p-4 border border-indigo-200 shadow-sm flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-black uppercase text-indigo-700 flex items-center gap-1.5">
                                <i class="fa-solid fa-building-columns"></i> Financeiro Nutricional
                            </div>
                            <div class="text-sm font-black text-slate-900 mt-1">Setor Financeiro &amp; Cobrança</div>
                            <div class="text-[11px] text-slate-600 mt-0.5">(48) 3524-6543 • financeiro@nutricionalbr.com</div>
                        </div>
                        <span class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-headset"></i></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="linkWhats('4835246543', 'Olá Financeiro Nutricional! Preciso de suporte sobre um boleto/título.')" target="_blank"
                           class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold no-underline inline-flex items-center gap-1.5">
                            <i class="fa-brands fa-whatsapp text-sm"></i> WhatsApp Financeiro
                        </a>
                        <a href="tel:4835246543"
                           class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 text-[11px] font-bold no-underline inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-phone"></i> (48) 3524-6543
                        </a>
                        <a href="mailto:financeiro@nutricionalbr.com"
                           class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 text-[11px] font-bold no-underline inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-envelope"></i> E-mail
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ABAS + FILTROS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex flex-wrap border-b border-slate-200 bg-slate-50 px-4 pt-2 gap-2 text-xs font-extrabold">
                <button @click="aba = 'boletos'" :class="aba === 'boletos' ? 'bg-white text-emerald-700 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-barcode mr-1"></i> Títulos / Boletos (<span x-text="boletosFiltrados().length"></span>)
                </button>
                <button @click="aba = 'notas'" :class="aba === 'notas' ? 'bg-white text-indigo-700 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-file-invoice mr-1"></i> Notas Fiscais (<span x-text="notasFiltradas().length"></span>)
                </button>
                <button @click="aba = 'pedidos'" :class="aba === 'pedidos' ? 'bg-white text-slate-900 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-cart-shopping mr-1"></i> Pedidos (<span x-text="pedidosFiltrados().length"></span>)
                </button>
                <button @click="aba = 'itens'" :class="aba === 'itens' ? 'bg-white text-emerald-800 border border-b-0 border-slate-200 rounded-t-xl px-4 py-2.5' : 'text-slate-500 px-4 py-2.5'">
                    <i class="fa-solid fa-boxes-stacked mr-1"></i> Produtos Comprados (<span x-text="itensFiltrados().length"></span>)
                </button>
            </div>

            <!-- Barra de Filtros -->
            <div class="p-3 bg-slate-50/70 border-b border-slate-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-center text-xs">
                <div class="lg:col-span-2">
                    <input type="text" x-model="filtroTexto" placeholder="Filtrar por número, documento, produto, marca..."
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold">
                </div>
                <div x-show="aba !== 'itens'">
                    <input type="date" x-model="filtroDataIni" title="Data inicial" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold">
                </div>
                <div x-show="aba !== 'itens'">
                    <input type="date" x-model="filtroDataFim" title="Data final" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold">
                </div>
                <div class="flex gap-2">
                    <select x-show="aba === 'boletos'" x-model="filtroTituloTipo" class="flex-1 px-2.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-bold">
                        <option value="todos">Todos os Títulos</option>
                        <option value="avencer">A Vencer</option>
                        <option value="vencidos">Vencidos</option>
                        <option value="boleto">Com Boleto</option>
                        <option value="outros">Renegociação / Outros</option>
                    </select>
                    <button @click="filtroTexto=''; filtroDataIni=''; filtroDataFim=''; filtroTituloTipo='todos'"
                            class="px-3 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs">Limpar</button>
                </div>
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
                            <th class="px-3 py-2 text-left">Portador / Forma</th>
                            <th class="px-3 py-2 text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="b in boletosFiltrados()" :key="b.idfilial + '-' + b.idreceber">
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-bold" x-text="b.documento + ' / ' + b.parcela"></td>
                                <td class="px-3 py-2" x-text="fmtData(b.dataemissao)"></td>
                                <td class="px-3 py-2 font-bold" :class="b.dias_atraso > 0 ? 'text-rose-600' : 'text-emerald-700'"
                                    x-text="fmtData(b.vencimento) + (b.dias_atraso > 0 ? ' (' + b.dias_atraso + 'd atraso)' : '')"></td>
                                <td class="px-3 py-2 text-right font-mono" x-text="fmtMoeda(b.valororiginal)"></td>
                                <td class="px-3 py-2 text-right font-mono font-extrabold" x-text="fmtMoeda(b.valorsaldo)"></td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                          :class="b.tem_boleto ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'"
                                          x-text="b.portador_nome || (b.tem_boleto ? 'Boleto' : 'Outras Formas')"></span>
                                    <div x-show="b.nossonumero" class="font-mono text-[10px] text-slate-400 mt-0.5" x-text="'NN: ' + b.nossonumero"></div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <template x-if="b.tem_boleto">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button @click="abrirBoleto(b)" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px]">
                                                <i class="fa-solid fa-barcode mr-1"></i> Baixar Boleto
                                            </button>
                                            <button @click="copiarTexto(b.linha_digitavel || b.codigobarra)" class="px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px]" title="Copiar Linha Digitável">
                                                <i class="fa-regular fa-copy"></i>
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="!b.tem_boleto">
                                        <button @click="abrirTituloDetalhe(b)" class="px-2.5 py-1 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[10px]">
                                            <i class="fa-solid fa-circle-info mr-1"></i> Ver Detalhes
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="boletosFiltrados().length === 0"><td colspan="7" class="py-8 text-center text-slate-400">Nenhum título encontrado para os filtros atuais.</td></tr>
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
                        <template x-for="n in notasFiltradas()" :key="n.idfilial + '-' + n.idnfs">
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-extrabold" x-text="'NF ' + n.numeronf + ' (F' + n.idfilial + ')'"></td>
                                <td class="px-3 py-2" x-text="fmtData(n.dataemissao)"></td>
                                <td class="px-3 py-2 font-mono text-[10px]">
                                    <span x-text="n.chave_nfe || '-'"></span>
                                    <button x-show="n.chave_nfe" @click="copiarTexto(n.chave_nfe)" class="ml-1 text-indigo-600 hover:underline font-sans font-bold">[Copiar]</button>
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(n.valortotal)"></td>
                                <td class="px-3 py-2 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button @click="abrirDanfe(n)" class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px]">
                                            <i class="fa-solid fa-file-pdf mr-1"></i> Baixar DANFE
                                        </button>
                                        <button @click="abrirItens('nota', n)" class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px]">Itens</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="notasFiltradas().length === 0"><td colspan="5" class="py-8 text-center text-slate-400">Nenhuma nota fiscal encontrada.</td></tr>
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
                            <th class="px-3 py-2 text-center">Status</th>
                            <th class="px-3 py-2 text-left">Representante</th>
                            <th class="px-3 py-2 text-left">Nota / Entrega (Frota)</th>
                            <th class="px-3 py-2 text-right">Valor Pedido</th>
                            <th class="px-3 py-2 text-center">Itens</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="p in pedidosFiltrados()" :key="p.idfilial + '-' + p.idpedido">
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-extrabold" x-text="'#' + p.idpedido + ' (F' + p.idfilial + ')'"></td>
                                <td class="px-3 py-2" x-text="fmtData(p.data)"></td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase"
                                          :class="p.status === 5 ? 'bg-emerald-100 text-emerald-800' : (p.status === 3 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')"
                                          x-text="p.status_label || (p.status === 5 ? 'Faturado' : (p.status === 3 ? 'Cancelado' : 'Em Aberto'))"></span>
                                </td>
                                <td class="px-3 py-2 font-semibold text-slate-700" x-text="p.vendedor || '-'"></td>
                                <td class="px-3 py-2">
                                    <template x-if="p.numeronf > 0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <button @click="abrirDanfe({idfilial: p.idfilial, idnfs: p.idnfs, numeronf: p.numeronf})"
                                                    class="px-2 py-0.5 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-mono font-bold text-[10px]">
                                                <i class="fa-solid fa-file-invoice mr-1"></i> NF <span x-text="p.numeronf"></span>
                                            </button>
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold"
                                                  x-text="p.idembarque > 0 ? ('Embarque #' + p.idembarque) : 'Faturado • Logística'"></span>
                                        </div>
                                    </template>
                                    <span x-show="!p.numeronf" class="text-[10px] text-slate-400" x-text="p.status === 3 ? 'Cancelado' : 'Aguardando faturamento'"></span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(p.valortotal)"></td>
                                <td class="px-3 py-2 text-center">
                                    <button @click="abrirItens('pedido', p)" class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px]">Ver Itens</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="pedidosFiltrados().length === 0"><td colspan="7" class="py-8 text-center text-slate-400">Nenhum pedido encontrado.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- PRODUTOS COMPRADOS (COM IMAGENS) -->
            <div x-show="aba === 'itens'" class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <template x-for="it in itensFiltrados()" :key="it.iditem">
                        <div class="border border-slate-200 rounded-2xl p-3 flex gap-3 items-center hover:shadow-md transition bg-white">
                            <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                                <img x-show="formatarUrlImagem(it.foto)" :src="formatarUrlImagem(it.foto)" :alt="it.descricao"
                                     class="w-full h-full object-contain p-1" onerror="this.style.display='none'">
                                <i x-show="!formatarUrlImagem(it.foto)" class="fa-solid fa-box-open text-slate-300 text-xl"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] font-bold uppercase text-emerald-700 truncate" x-text="it.marca || 'Nutricional'"></div>
                                <div class="text-xs font-extrabold text-slate-900 line-clamp-2 leading-tight" x-text="it.descricao"></div>
                                <div class="text-[10px] font-mono text-slate-400 mt-0.5" x-text="'Cód: ' + it.iditem + (it.referencia ? ' | Ref: ' + it.referencia : '')"></div>
                                <div class="flex items-center justify-between mt-1.5 text-[11px]">
                                    <span class="font-bold text-slate-700" x-text="it.qtd_total + ' un'"></span>
                                    <span class="font-mono font-black text-emerald-700" x-text="fmtMoeda(it.valor_total)"></span>
                                </div>
                                <div class="text-[9px] text-slate-400 mt-0.5" x-text="'Última compra: ' + fmtData(it.ultima_compra)"></div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="itensFiltrados().length === 0" class="py-10 text-center text-xs text-slate-400">Nenhum produto comprado encontrado.</div>
            </div>
        </div>
    </div>

    <!-- MODAL DETALHES DE TÍTULO SEM BOLETO (RENEGOCIAÇÃO / OUTRAS FORMAS, z-[70]) -->
    <div x-show="modalTituloDetalhe" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 p-4">
        <div @click.away="modalTituloDetalhe = false" class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
            <template x-if="tituloDetalhe">
                <div>
            <div class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between">
                <h3 class="text-sm font-extrabold"><i class="fa-solid fa-circle-info text-amber-400 mr-1.5"></i> Detalhes do Título</h3>
                <button @click="modalTituloDetalhe = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-5 space-y-3 text-xs">
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 font-semibold">
                    Este título está registrado como <strong x-text="tituloDetalhe.portador_nome"></strong> (sem boleto bancário emitido).
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="text-[10px] text-slate-400 block">Documento / Parcela</span><strong class="font-mono" x-text="tituloDetalhe.documento + ' / ' + tituloDetalhe.parcela"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Filial</span><strong x-text="'Filial ' + tituloDetalhe.idfilial"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Data Emissão</span><strong x-text="fmtData(tituloDetalhe.dataemissao)"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Vencimento</span><strong :class="tituloDetalhe.dias_atraso > 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="fmtData(tituloDetalhe.vencimento)"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Valor Original</span><strong class="font-mono" x-text="fmtMoeda(tituloDetalhe.valororiginal)"></strong></div>
                    <div><span class="text-[10px] text-slate-400 block">Saldo Aberto</span><strong class="font-mono text-sm text-slate-900" x-text="fmtMoeda(tituloDetalhe.valorsaldo)"></strong></div>
                </div>
                <div x-show="tituloDetalhe.observacao" class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Observação</span>
                    <div class="text-slate-700" x-text="tituloDetalhe.observacao"></div>
                </div>
            </div>
                </div>
            </template>
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

    <!-- MODAL BOLETO BANCÁRIO FEBRABAN (z-[70]) -->
    <div id="modal-boleto-b2b" x-show="modalBoleto" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/75 p-4">
        <div @click.away="modalBoleto = false" class="print-box bg-white rounded-2xl max-w-4xl w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="no-print bg-slate-900 text-white px-5 py-3 flex items-center justify-between">
                <div class="text-xs font-extrabold flex items-center gap-2">
                    <i class="fa-solid fa-barcode text-emerald-400"></i>
                    <span x-text="boletoDoc ? ('Boleto ' + boletoDoc.banco_sigla + ' (' + boletoDoc.banco_codigo_dv + ') — Doc ' + boletoDoc.documento) : 'Boleto'"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button x-show="boletoDoc && boletoDoc.linha_digitavel" @click="copiarTexto(boletoDoc.linha_digitavel)" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold">
                        <i class="fa-regular fa-copy mr-1"></i> Copiar Linha Digitável
                    </button>
                    <button @click="imprimirDocumento('boleto')" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir / Salvar PDF
                    </button>
                    <button @click="modalBoleto = false" class="text-white/70 hover:text-white px-2"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto text-[10px] text-black font-sans">
                <template x-if="boletoDoc">
                    <div>
                <!-- Recibo do Pagador -->
                <div class="mb-4 pb-3 border-b-2 border-dashed border-slate-400">
                    <div class="flex items-center justify-between border-b-2 pb-1.5 mb-2" :style="'border-color:' + boletoDoc.banco_cor">
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 text-white font-black text-xs rounded" :style="'background:' + boletoDoc.banco_cor" x-text="boletoDoc.banco_sigla"></span>
                            <span class="text-base font-black border-l-2 border-r-2 border-black px-2.5" x-text="boletoDoc.banco_codigo_dv"></span>
                            <span class="text-[11px] font-bold text-slate-600">RECIBO DO PAGADOR</span>
                        </div>
                        <div class="font-mono font-bold text-[11px]" x-text="boletoDoc.linha_digitavel || boletoDoc.codigobarra"></div>
                    </div>
                    <table class="w-full border-collapse border border-black text-[10px]">
                        <tr>
                            <td class="border border-black p-1.5 w-[55%]"><div class="text-[8px] uppercase text-slate-500">Beneficiário</div><div class="font-bold" x-text="boletoDoc.cedente + ' — CNPJ: ' + boletoDoc.cedente_cnpj"></div></td>
                            <td class="border border-black p-1.5 w-[20%]"><div class="text-[8px] uppercase text-slate-500">Agência / Código Benef.</div><div class="font-mono font-bold" x-text="(boletoDoc.nragencia || '') + (boletoDoc.digitoagencia ? '-' + boletoDoc.digitoagencia : '') + ' / ' + (boletoDoc.nrconta || '') + (boletoDoc.digitoconta ? '-' + boletoDoc.digitoconta : '')"></div></td>
                            <td class="border border-black p-1.5 w-[25%]"><div class="text-[8px] uppercase text-slate-500">Vencimento</div><div class="font-bold text-right text-xs" x-text="fmtData(boletoDoc.vencimento)"></div></td>
                        </tr>
                        <tr>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Pagador</div><div class="font-bold" x-text="boletoDoc.sacado_nome + ' — ' + boletoDoc.sacado_doc"></div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Nosso Número</div><div class="font-mono font-bold" x-text="boletoDoc.nossonumero || '-'"></div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Valor do Documento</div><div class="font-bold text-right text-xs" x-text="fmtMoeda(boletoDoc.valorsaldo)"></div></td>
                        </tr>
                    </table>
                </div>

                <!-- Ficha de Compensação -->
                <div>
                    <div class="flex items-center justify-between border-b-2 pb-1.5 mb-2" :style="'border-color:' + boletoDoc.banco_cor">
                        <div class="flex items-center gap-3">
                            <span class="px-2 py-0.5 text-white font-black text-xs rounded" :style="'background:' + boletoDoc.banco_cor" x-text="boletoDoc.banco_sigla"></span>
                            <span class="text-lg font-black border-l-2 border-r-2 border-black px-2.5" x-text="boletoDoc.banco_codigo_dv"></span>
                        </div>
                        <div class="font-mono font-bold text-xs" x-text="boletoDoc.linha_digitavel || boletoDoc.codigobarra"></div>
                    </div>
                    <table class="w-full border-collapse border border-black text-[10px]">
                        <tr>
                            <td colspan="5" class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Local de Pagamento</div><div class="font-bold" x-text="boletoDoc.local_pagamento"></div></td>
                            <td class="border border-black p-1.5 w-[24%] bg-slate-50"><div class="text-[8px] uppercase text-slate-500">Vencimento</div><div class="font-black text-right text-xs" x-text="fmtData(boletoDoc.vencimento)"></div></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Beneficiário</div><div class="font-bold" x-text="boletoDoc.cedente + ' (' + boletoDoc.cedente_cnpj + ')'"></div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Agência / Código Beneficiário</div><div class="font-mono font-bold text-right" x-text="(boletoDoc.nragencia || '') + (boletoDoc.digitoagencia ? '-' + boletoDoc.digitoagencia : '') + ' / ' + (boletoDoc.nrconta || '') + (boletoDoc.digitoconta ? '-' + boletoDoc.digitoconta : '')"></div></td>
                        </tr>
                        <tr>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Data Documento</div><div class="font-bold" x-text="fmtData(boletoDoc.dataemissao)"></div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Nº Documento</div><div class="font-mono font-bold" x-text="boletoDoc.documento + '/' + boletoDoc.parcela"></div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Espécie Doc.</div><div class="font-bold">DM</div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Aceite</div><div class="font-bold">N</div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Carteira</div><div class="font-mono font-bold" x-text="boletoDoc.carteira || '-'"></div></td>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Nosso Número</div><div class="font-mono font-bold text-right" x-text="boletoDoc.nossonumero || '-'"></div></td>
                        </tr>
                        <tr>
                            <td colspan="5" rowspan="2" class="border border-black p-2 align-top">
                                <div class="text-[8px] uppercase text-slate-500 mb-1">Instruções (Texto de responsabilidade do Beneficiário)</div>
                                <div class="font-semibold text-[10px]" x-text="boletoDoc.mensagem || 'Não receber após o vencimento sem atualização de encargos. Em caso de dúvidas contate o financeiro Nutricional.'"></div>
                                <div x-show="boletoDoc.convenio" class="text-[9px] text-slate-500 mt-1" x-text="'Convênio: ' + boletoDoc.convenio"></div>
                            </td>
                            <td class="border border-black p-1.5 bg-slate-50"><div class="text-[8px] uppercase text-slate-500">(=) Valor do Documento</div><div class="font-black text-right text-xs" x-text="fmtMoeda(boletoDoc.valorsaldo)"></div></td>
                        </tr>
                        <tr>
                            <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">(=) Valor Cobrado</div><div class="font-bold text-right">&nbsp;</div></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="border border-black p-2">
                                <div class="text-[8px] uppercase text-slate-500">Pagador</div>
                                <div class="font-bold" x-text="boletoDoc.sacado_nome + ' — CPF/CNPJ: ' + boletoDoc.sacado_doc"></div>
                                <div class="text-[9px]" x-text="(boletoDoc.sacado_end || '') + ' - ' + (boletoDoc.sacado_bairro || '') + ' — ' + (boletoDoc.sacado_cidade || '') + '/' + (boletoDoc.sacado_uf || '') + ' CEP: ' + (boletoDoc.sacado_cep || '')"></div>
                            </td>
                        </tr>
                    </table>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex-1" x-html="boletoBarcodeSvg"></div>
                        <div x-show="boletoDoc.shipayqrcode" class="flex items-center gap-3 border border-emerald-300 bg-emerald-50/50 rounded-xl p-2">
                            <div id="boleto-qrcode-box" class="bg-white p-1 rounded border border-slate-200"></div>
                            <div class="text-[9px]">
                                <div class="font-black text-emerald-800 uppercase"><i class="fa-brands fa-pix mr-1"></i> Pague via PIX</div>
                                <div class="text-slate-600 mt-0.5 max-w-[140px]">Escaneie o QR Code no app do seu banco.</div>
                                <button @click="copiarTexto(boletoDoc.shipayqrcode)" class="no-print mt-1.5 px-2 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px]">
                                    Copiar PIX Copia e Cola
                                </button>
                            </div>
                        </div>
                        <div x-show="!boletoDoc.shipayqrcode" class="text-[9px] text-right text-slate-500 font-bold">Autenticação Mecânica / Ficha de Compensação</div>
                    </div>
                </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- MODAL DANFE ESPELHO (MESMO LAYOUT CONTÁBIL, z-[70]) -->
    <div id="modal-danfe-b2b" x-show="modalDanfe" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/75 p-4">
        <div @click.away="modalDanfe = false" class="print-box bg-white rounded-2xl max-w-5xl w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="no-print bg-slate-900 text-white px-5 py-3 flex items-center justify-between">
                <div class="text-xs font-extrabold flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-emerald-400"></i>
                    <span x-text="danfeNota ? ('DANFE Espelho — NF ' + danfeNota.numeronf + ' (Filial ' + danfeNota.idfilial + ')') : 'DANFE'"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="imprimirDocumento('danfe')" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir / Salvar PDF
                    </button>
                    <button @click="modalDanfe = false" class="text-white/70 hover:text-white px-2"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto text-[10px] text-black font-sans">
                <template x-if="danfeNota">
                    <div>
                <table class="w-full border-collapse border border-black mb-2">
                    <tr>
                        <td class="border border-black p-2 w-[40%] align-middle">
                            <div class="flex items-center gap-3">
                                <img src="<?= $appBase ?>/portal/assets/img/logo-danfe-nutricional.png" alt="Nutricional" class="h-11 w-auto max-w-[140px] object-contain shrink-0">
                                <div class="text-[9px] leading-snug">
                                    <div class="font-bold text-[10px]" x-text="danfeNota.filial_razao"></div>
                                    <div x-text="(danfeNota.filial_end || '') + ' - ' + (danfeNota.filial_bairro || '') + ' / ' + (danfeNota.filial_uf || '')"></div>
                                    <div x-text="'CNPJ: ' + (danfeNota.filial_cnpj || '') + ' | IE: ' + (danfeNota.filial_ie || '')"></div>
                                </div>
                            </div>
                        </td>
                        <td class="border border-black p-2 w-[22%] text-center align-middle">
                            <div class="font-black text-sm tracking-wide">DANFE</div>
                            <div class="text-[8px] uppercase">Documento Auxiliar da NF-e</div>
                            <div class="mt-1 font-bold text-xs" x-text="'Nº ' + danfeNota.numeronf + ' | Série ' + (danfeNota.idserie || '1')"></div>
                        </td>
                        <td class="border border-black p-2 w-[38%] align-middle">
                            <div class="text-[8px] font-bold uppercase text-slate-500">Chave de Acesso</div>
                            <div class="font-mono font-bold text-[10px] break-all mt-0.5" x-text="fmtChave(danfeNota.chave_nfe)"></div>
                            <div class="text-[8px] font-bold uppercase text-slate-500 mt-1.5">Protocolo de Autorização SEFAZ</div>
                            <div class="font-mono text-[9px]" x-text="(danfeNota.protocolo || '-') + (danfeNota.datahora_sefaz ? ' — ' + danfeNota.datahora_sefaz : '')"></div>
                        </td>
                    </tr>
                </table>

                <table class="w-full border-collapse border border-black mb-2">
                    <tr>
                        <td class="border border-black p-1.5 w-[65%]"><div class="text-[8px] uppercase text-slate-500">Natureza da Operação</div><div class="font-bold" x-text="danfeNota.natureza_operacao || '-'"></div></td>
                        <td class="border border-black p-1.5 w-[35%]"><div class="text-[8px] uppercase text-slate-500">Data Emissão / Saída</div><div class="font-bold" x-text="fmtData(danfeNota.dataemissao) + ' / ' + fmtData(danfeNota.data_mov)"></div></td>
                    </tr>
                </table>

                <div class="font-black text-[9px] uppercase bg-slate-100 border border-b-0 border-black px-2 py-0.5">Destinatário / Remetente</div>
                <table class="w-full border-collapse border border-black mb-2">
                    <tr>
                        <td class="border border-black p-1.5 w-[55%]"><div class="text-[8px] uppercase text-slate-500">Nome / Razão Social</div><div class="font-bold" x-text="danfeNota.parc_razao"></div></td>
                        <td class="border border-black p-1.5 w-[25%]"><div class="text-[8px] uppercase text-slate-500">CNPJ / CPF</div><div class="font-mono font-bold" x-text="danfeNota.parc_doc"></div></td>
                        <td class="border border-black p-1.5 w-[20%]"><div class="text-[8px] uppercase text-slate-500">Inscrição Estadual</div><div class="font-mono" x-text="danfeNota.parc_ie || '-'"></div></td>
                    </tr>
                    <tr>
                        <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Endereço</div><div x-text="(danfeNota.parc_end || '') + ' - ' + (danfeNota.parc_bairro || '')"></div></td>
                        <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">Município / UF</div><div x-text="(danfeNota.parc_cidade || '') + ' / ' + (danfeNota.parc_uf || '')"></div></td>
                        <td class="border border-black p-1.5"><div class="text-[8px] uppercase text-slate-500">CEP / Fone</div><div x-text="(danfeNota.parc_cep || '') + ' ' + (danfeNota.parc_fone || '')"></div></td>
                    </tr>
                </table>

                <div class="font-black text-[9px] uppercase bg-slate-100 border border-b-0 border-black px-2 py-0.5">Cálculo do Imposto</div>
                <table class="w-full border-collapse border border-black mb-2 text-right">
                    <tr>
                        <td class="border border-black p-1.5"><div class="text-[8px] text-left uppercase text-slate-500">Base ICMS</div><div class="font-mono font-bold" x-text="fmtMoeda(danfeNota.baseicms)"></div></td>
                        <td class="border border-black p-1.5"><div class="text-[8px] text-left uppercase text-slate-500">Valor ICMS</div><div class="font-mono font-bold" x-text="fmtMoeda(danfeNota.valoricms)"></div></td>
                        <td class="border border-black p-1.5"><div class="text-[8px] text-left uppercase text-slate-500">Valor IPI</div><div class="font-mono font-bold" x-text="fmtMoeda(danfeNota.valoripi)"></div></td>
                        <td class="border border-black p-1.5"><div class="text-[8px] text-left uppercase text-slate-500">Valor Frete</div><div class="font-mono font-bold" x-text="fmtMoeda(danfeNota.valorfrete)"></div></td>
                        <td class="border border-black p-1.5"><div class="text-[8px] text-left uppercase text-slate-500">Desconto</div><div class="font-mono font-bold" x-text="fmtMoeda(danfeNota.valortotaldesconto)"></div></td>
                        <td class="border border-black p-1.5 bg-slate-50"><div class="text-[8px] text-left uppercase text-slate-500">Total da Nota</div><div class="font-mono font-black text-xs" x-text="fmtMoeda(danfeNota.valortotalnota)"></div></td>
                    </tr>
                </table>

                <div class="font-black text-[9px] uppercase bg-slate-100 border border-b-0 border-black px-2 py-0.5">Dados dos Produtos / Serviços</div>
                <table class="w-full border-collapse border border-black mb-2 text-[9px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="border border-black p-1 text-left">Código</th>
                            <th class="border border-black p-1 text-left">Descrição do Produto</th>
                            <th class="border border-black p-1 text-center">NCM</th>
                            <th class="border border-black p-1 text-center">CFOP</th>
                            <th class="border border-black p-1 text-right">Qtd</th>
                            <th class="border border-black p-1 text-right">Vlr Unit</th>
                            <th class="border border-black p-1 text-right">Vlr Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="it in danfeItens" :key="it.iditem + '-' + it.valor_total">
                            <tr>
                                <td class="border border-black p-1 font-mono" x-text="it.referencia || it.iditem"></td>
                                <td class="border border-black p-1" x-text="it.descricao"></td>
                                <td class="border border-black p-1 text-center font-mono" x-text="it.ncm || '-'"></td>
                                <td class="border border-black p-1 text-center font-mono" x-text="it.idcfop"></td>
                                <td class="border border-black p-1 text-right font-mono" x-text="it.quantidade"></td>
                                <td class="border border-black p-1 text-right font-mono" x-text="fmtMoeda(it.valor_unitario)"></td>
                                <td class="border border-black p-1 text-right font-mono font-bold" x-text="fmtMoeda(it.valor_total)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div x-show="danfeNota.observacao" class="border border-black p-2 text-[9px]">
                    <div class="text-[8px] font-bold uppercase text-slate-500">Dados Adicionais / Observações</div>
                    <div x-text="danfeNota.observacao"></div>
                </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</body>
</html>
