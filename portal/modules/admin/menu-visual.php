<?php
define('ADMIN_AREA', true);
$pageTitle = 'Aparência do Menu | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="glass rounded-3xl p-5 mb-6 flex flex-wrap gap-3 justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-pink-100 text-pink-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-palette text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">APARÊNCIA DO MENU</h2>
            <span class="text-xs text-slate-400 font-medium">Nome, ícone e cores de setores e módulos do portal</span>
        </div>
    </div>
    <a href="https://fontawesome.com/search?o=r&m=free&s=solid" target="_blank" rel="noopener"
       class="text-xs font-bold text-slate-500 hover:text-slate-800">
        <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Catálogo de ícones (use o nome, ex.: fa-truck)
    </a>
</div>

<div id="conteudo" class="space-y-6">
    <p class="text-sm text-slate-400 text-center py-8">Carregando...</p>
</div>

<script>
let dados = { setores: [], modulos: [] };

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const hex = (v, pad) => /^#[0-9a-fA-F]{6}$/.test(v || '') ? v : pad;
const normIcone = v => {
    const i = String(v || '').split(/\s+/).find(c => c.startsWith('fa-') && !['fa-solid','fa-regular','fa-brands','fa-fw'].includes(c));
    return i || 'fa-cube';
};

function linha(tipo, item) {
    const id = `${tipo}-${item.id}`;
    const icone = tipo === 'setor' ? item.icone : item.icon;
    const from = hex(item.cor_from, '#375a4b');
    const to = hex(item.cor_to, from);
    return `
    <div class="flex flex-wrap items-center gap-3 p-3 rounded-2xl border border-slate-100 bg-white" id="row-${id}">
        <div id="prev-${id}" class="w-11 h-11 rounded-xl flex items-center justify-center text-white shadow"
             style="background:linear-gradient(135deg,${from},${to})">
            <i class="fa-solid ${esc(normIcone(icone))}"></i>
        </div>
        <input id="nome-${id}" value="${esc(item.nome)}" placeholder="Nome"
               class="flex-1 min-w-[160px] p-2.5 border border-slate-200 rounded-xl text-sm font-semibold">
        <input id="icone-${id}" value="${esc(normIcone(icone))}" placeholder="fa-cube" oninput="previa('${id}')"
               class="w-40 p-2.5 border border-slate-200 rounded-xl text-sm font-mono">
        <label class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1">De
            <input type="color" id="from-${id}" value="${from}" oninput="previa('${id}')" class="w-9 h-9 rounded cursor-pointer border-0 p-0"></label>
        <label class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1">Até
            <input type="color" id="to-${id}" value="${to}" oninput="previa('${id}')" class="w-9 h-9 rounded cursor-pointer border-0 p-0"></label>
        <label class="text-[10px] font-bold text-slate-400 uppercase flex items-center gap-1">Ordem
            <input type="number" id="ordem-${id}" value="${Number(item.ordem) || 0}" class="w-16 p-2 border border-slate-200 rounded-xl text-sm"></label>
        <button onclick="salvar('${tipo}', ${Number(item.id)})"
                class="px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900">
            <i class="fa-solid fa-floppy-disk mr-1"></i>Salvar</button>
        ${tipo === 'modulo' ? `<input id="desc-${id}" value="${esc(item.descricao)}" placeholder="Descrição"
               class="w-full p-2.5 border border-slate-200 rounded-xl text-xs text-slate-500">` : ''}
    </div>`;
}

function previa(id) {
    const el = document.getElementById('prev-' + id);
    el.style.background = `linear-gradient(135deg,${document.getElementById('from-' + id).value},${document.getElementById('to-' + id).value})`;
    el.innerHTML = `<i class="fa-solid ${esc(normIcone(document.getElementById('icone-' + id).value))}"></i>`;
}

function desenhar() {
    const box = document.getElementById('conteudo');
    const nomesSetores = new Set(dados.setores.map(s => s.nome));
    let html = '';
    dados.setores.forEach(s => {
        const mods = dados.modulos.filter(m => m.setor === s.nome);
        html += `<section class="bg-slate-50 rounded-3xl p-4 border border-slate-100">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Setor</p>
            ${linha('setor', s)}
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-4 mb-2">Módulos (${mods.length})</p>
            <div class="space-y-2">${mods.map(m => linha('modulo', m)).join('') || '<p class="text-xs text-slate-400">Nenhum módulo</p>'}</div>
        </section>`;
    });
    const orfaos = dados.modulos.filter(m => !nomesSetores.has(m.setor));
    if (orfaos.length) {
        html += `<section class="bg-amber-50 rounded-3xl p-4 border border-amber-100">
            <p class="text-xs font-bold text-amber-700 mb-2">Módulos em setores não cadastrados em sistema_setores</p>
            <div class="space-y-2">${orfaos.map(m => linha('modulo', m)).join('')}</div></section>`;
    }
    box.innerHTML = html;
}

async function carregar() {
    try {
        const r = await apiFetch('/admin/menu-visual', 'GET');
        if (r.error) throw new Error(r.error);
        dados = r;
        desenhar();
    } catch (e) {
        showError('Erro', e.message || 'Falha ao carregar');
    }
}

async function salvar(tipo, id) {
    const k = `${tipo}-${id}`;
    const v = n => document.getElementById(`${n}-${k}`).value;
    const corpo = { nome: v('nome'), cor_from: v('from'), cor_to: v('to'), ordem: v('ordem') };
    if (tipo === 'setor') corpo.icone = normIcone(v('icone')); else { corpo.icon = normIcone(v('icone')); corpo.descricao = v('desc'); }
    if (tipo === 'setor') {
        const atual = dados.setores.find(s => s.id == id);
        corpo.descricao = atual ? atual.descricao : '';
    }
    try {
        showLoading('Salvando...');
        const r = await apiFetch(`/admin/menu-visual/${tipo}/${id}`, 'POST', corpo);
        Swal.close();
        if (!r.success) return showError('Erro', r.error || 'Falha ao salvar');
        await showSuccess('Salvo!', 'O portal já usa a nova aparência (atualize a página inicial).');
        carregar();
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

document.addEventListener('DOMContentLoaded', carregar);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
