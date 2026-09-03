// ==========================================================================
// MARKETING UTILS - FUNÇÕES COMPARTILHADAS
// ==========================================================================

// ✅ Verificar se já foi carregado para evitar duplicação
if (typeof window.MarketingUtils !== 'undefined') {
    console.log('ℹ️ MarketingUtils já estava carregado, ignorando...');
} else {

// ==========================================================================
// DEFINIÇÃO DO OBJETO
// ==========================================================================
const MarketingUtils = {
    /**
     * Obtém token de autenticação
     */
    getToken() {
        return localStorage.getItem('authToken');
    },

    /**
     * Fetch com autenticação
     */
    async fetchWithAuth(url, options = {}) {
        const token = this.getToken();
        if (!token) {
            window.location.href = '/portal/login.php';
            throw new Error('Token não encontrado');
        }

        const response = await fetch(url, {
            ...options,
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json',
                ...(options.headers || {})
            }
        });

        if (response.status === 401) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada');
        }

        return response;
    },

    /**
     * Escape HTML para prevenir XSS (segurança)
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Formata data
     */
    formatarData(data) {
        if (!data) return '-';
        return new Date(data).toLocaleDateString('pt-BR');
    },

    /**
     * Formata data e hora
     */
    formatarDateTime(data) {
        if (!data) return '-';
        return new Date(data).toLocaleString('pt-BR');
    },

    /**
     * Formata valor conforme tipo
     */
    formatarValor(valor, tipo = 'padrao') {
        const num = parseFloat(valor);
        if (isNaN(num)) return valor || '0';

        switch (tipo) {
            case 'moeda':
                return num.toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                    minimumFractionDigits: 2
                });
            case 'percentual':
                return num.toFixed(1) + '%';
            case 'roas':
                return num.toFixed(2) + 'x';
            default:
                return num.toLocaleString('pt-BR');
        }
    },

    /**
     * Formata data relativa (Hoje, Ontem, X dias)
     */
    formatarDataRelativa(data) {
        if (!data) return '—';
        const dataObj = new Date(data);
        const hoje = new Date();
        const diff = Math.floor((hoje - dataObj) / (1000 * 60 * 60 * 24));

        if (diff === 0) return 'Hoje';
        if (diff === 1) return 'Ontem';
        if (diff < 7) return `${diff} dias`;
        return this.formatarData(data);
    },

    /**
     * Calcula progresso de forma consistente
     */
    calcularProgresso(meta) {
        const taxaInicial = parseFloat(meta.taxa_inicial) || 0;
        const metaFinal = parseFloat(meta.meta_final) || 0;
        const valorAtual = parseFloat(meta.valor_acumulado) || 0;

        if (metaFinal <= 0) return 0;

        if (metaFinal > taxaInicial && taxaInicial > 0) {
            const necessario = metaFinal - taxaInicial;
            const conquistado = valorAtual - taxaInicial;
            return Math.min(100, Math.round((conquistado / necessario) * 100));
        }

        return Math.min(100, Math.round((valorAtual / metaFinal) * 100));
    },

    /**
     * Calcula dias restantes
     */
    diasRestantes(dataFim) {
        if (!dataFim) return null;
        const fim = new Date(dataFim);
        const hoje = new Date();
        const diff = Math.ceil((fim - hoje) / (1000 * 60 * 60 * 24));
        return diff;
    },

    /**
     * Retorna classe CSS para status
     */
    getStatusClass(status) {
        const classes = {
            'ativa': 'bg-emerald-100 text-emerald-700',
            'pausada': 'bg-amber-100 text-amber-700',
            'concluida': 'bg-blue-100 text-blue-700',
            'cancelada': 'bg-rose-100 text-rose-700'
        };
        return classes[status] || 'bg-slate-100 text-slate-600';
    },

    /**
     * Retorna texto do status
     */
    getStatusText(status) {
        const textos = {
            'ativa': '✅ Ativa',
            'pausada': '⏸️ Pausada',
            'concluida': '🏁 Concluída',
            'cancelada': '❌ Cancelada'
        };
        return textos[status] || status;
    },

    /**
     * Mostra toast de sucesso/erro
     */
    showToast(message, type = 'success') {
        if (typeof Swal === 'undefined') {
            console.log(message);
            return;
        }
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    },

    /**
     * Mostra loading
     */
    showLoading(title = 'Carregando...') {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            title: title,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    },

    /**
     * Fecha loading
     */
    closeLoading() {
        if (typeof Swal === 'undefined') return;
        Swal.close();
    },

    /**
     * Confirma ação
     */
    async confirm(title, text, confirmText = 'Sim') {
        if (typeof Swal === 'undefined') {
            return confirm(`Confirme: ${title}`);
        }

        const result = await Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444'
        });
        return result.isConfirmed;
    },

    /**
     * Carrega progresso de uma meta via API
     */
    async carregarProgressoMeta(idMeta) {
        try {
            const resp = await this.fetchWithAuth(`/v1/meta-builder/progresso/${idMeta}`);
            const data = await resp.json();
            return data;
        } catch (e) {
            console.error('Erro ao carregar progresso:', e);
            return { success: false, error: e.message };
        }
    },

    /**
     * Carrega campos editáveis de uma meta
     */
    async carregarCamposMeta(idMeta) {
        try {
            const resp = await this.fetchWithAuth(`/v1/meta-builder/campos/${idMeta}`);
            const data = await resp.json();
            return data.success ? data.data : [];
        } catch (e) {
            console.error('Erro ao carregar campos:', e);
            return [];
        }
    },

    /**
     * Salva alimentação de meta
     */
    async salvarAlimentacao(idMeta, dataRegistro, valores) {
        const userData = JSON.parse(localStorage.getItem('userData') || '{}');
        const usuarioId = userData.uid || userData.idusuario || 0;

        const resp = await this.fetchWithAuth('/v1/meta-builder/alimentar', {
            method: 'POST',
            body: JSON.stringify({
                id_meta_instancia: idMeta,
                data_registro: dataRegistro,
                valores: valores,
                usuario_id: usuarioId
            })
        });

        return resp.json();
    }
};

// Exportar para uso global
window.MarketingUtils = MarketingUtils;

// Log de confirmação
console.log('✅ MarketingUtils carregado com sucesso!');

} // Fim do if de verificação de duplicação