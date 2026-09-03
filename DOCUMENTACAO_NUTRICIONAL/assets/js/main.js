// Função para copiar token
function copyToken() {
    const token = 'NUTRICIONAL_V2_COMPLETE_2026_FINAL_V3';
    navigator.clipboard.writeText(token).then(() => {
        alert('✅ Token copiado com sucesso!\n\n' + token);
    }).catch(() => {
        alert('❌ Erro ao copiar. Copie manualmente:\n\n' + token);
    });
}

// Função para copiar comandos
function copyCommand(element) {
    const code = element.querySelector('code').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const hint = element.querySelector('.copy-hint');
        const originalText = hint.innerHTML;
        hint.innerHTML = '✅';
        setTimeout(() => {
            hint.innerHTML = originalText;
        }, 2000);
    });
}

// Atualizar data automaticamente
document.addEventListener('DOMContentLoaded', () => {
    const dateElement = document.querySelector('.date');
    if (dateElement) {
        const now = new Date();
        const dia = String(now.getDate()).padStart(2, '0');
        const mes = String(now.getMonth() + 1).padStart(2, '0');
        const ano = now.getFullYear();
        dateElement.innerHTML = `📅 Atualizado: ${dia}/${mes}/${ano}`;
    }
    
    // Animar números das estatísticas
    const numbers = document.querySelectorAll('.stat-card .number');
    numbers.forEach(number => {
        const finalValue = parseInt(number.innerText.replace(/[^0-9]/g, ''));
        if (!isNaN(finalValue)) {
            let current = 0;
            const increment = finalValue / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    number.innerText = finalValue.toLocaleString();
                    clearInterval(timer);
                } else {
                    number.innerText = Math.floor(current).toLocaleString();
                }
            }, 20);
        }
    });
});

// Atalhos de teclado
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 'k') {
        e.preventDefault();
        copyToken();
    }
});

// Console info
console.log('%c🐾 SISTEMA NUTRICIONAL PET', 'color: #1e3c72; font-size: 16px; font-weight: bold');
console.log('%cVersão: 2.0 FINAL V3', 'color: #10b981');
console.log('%cStatus: PRODUÇÃO READY ✅', 'color: #10b981');
console.log('%cSegmento: Alimentos para animais', 'color: #f59e0b');
console.log('%cToken: NUTRICIONAL_V2_COMPLETE_2026_FINAL_V3', 'color: #ef4444');