// Mantém o campo do bipe (#barcode-input) sempre ativo para o coletor acoplado ao celular.
// Atua em qualquer módulo da logística que tenha o campo; não interfere em modais nem em outros campos.
(function () {
    'use strict';

    const CAMPOS = 'input:not([type=button]):not([type=submit]):not([type=checkbox]):not([type=radio]):not([type=file]):not([type=hidden]), textarea, select, [contenteditable=true]';

    const campo = () => document.getElementById('barcode-input');

    function ocupado() {
        if (document.getElementById('inatividade-overlay')) return true;
        if (document.querySelector('.swal2-container')) return true;
        const a = document.activeElement;
        return !!(a && a !== campo() && a.matches && a.matches(CAMPOS));
    }

    function focar() {
        const el = campo();
        if (!el || el.offsetParent === null || document.hidden || ocupado()) return;
        // Sem readonly/teclado virtual: o coletor digita como teclado e o campo aceita digitação manual
        el.removeAttribute('readonly');
        el.setAttribute('inputmode', 'none');
        if (document.activeElement !== el) el.focus({ preventScroll: true });
    }

    // Cliques em áreas não editáveis devolvem o foco ao bipe
    document.addEventListener('click', e => {
        if (e.target.closest && e.target.closest(CAMPOS)) return;
        setTimeout(focar, 120);
    });

    // Ao fechar qualquer modal, volta o foco ao bipe
    new MutationObserver(() => {
        if (!document.querySelector('.swal2-container')) setTimeout(focar, 100);
    }).observe(document.body, { childList: true });

    document.addEventListener('visibilitychange', focar);
    window.addEventListener('focus', focar);
    setInterval(focar, 2000);
    setTimeout(focar, 300);
})();