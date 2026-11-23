function programa365App() {
    return {
        aba: 'tatame',
        isAdmin: false,
        perfil: 'ADMIN',
        campanhas: [],
        campanha: null,
        filtros: {
            campanha_id: 0,
            ano: 2027,
            mes: new Date().getMonth() + 1,
            filial: 0,
            idgestor: 0,
            participante_id: 0,
        },
        meses: [
            { id: 1, nome: 'Janeiro' }, { id: 2, nome: 'Fevereiro' }, { id: 3, nome: 'Março' },
            { id: 4, nome: 'Abril' }, { id: 5, nome: 'Maio' }, { id: 6, nome: 'Junho' },
            { id: 7, nome: 'Julho' }, { id: 8, nome: 'Agosto' }, { id: 9, nome: 'Setembro' },
            { id: 10, nome: 'Outubro' }, { id: 11, nome: 'Novembro' }, { id: 12, nome: 'Dezembro' }
        ],
        filiaisDisponiveis: [],
        gestoresDisponiveis: [],
        participantesDisponiveis: [],
        equipeResumo: [],
        selecionado: null,
        faixa: { codigo: 'BRANCA', nome: 'Faixa Branca', grau: 1, cor: '#F8FAFC', borda: '#CBD5E1', texto: '#1E293B', pct_jornada: 0, xp_falta_proxima: 288, proxima_faixa: 'Faixa Azul' },
        xpAnual: 0,
        bonusAnual: 0,
        posicaoAnual: null,
        desafios: [],
        rankingRound: [],
        rankingAnual: [],
        catalogoPins: [],
        pinsDesbloqueados: [],
        extrato: { rounds: [], extras: [] },
        admin: { equipes: [], participantes: [], vendedores_erp: [] },
        adminMetas: [],
        formEquipe: { nome: '' },
        formPart: { idvendedor: '', idequipe: '', nome_exibicao: '', carteira_base: 60 },
        formDesafio: { numero_desafio: 1, titulo: '', meta_percentual: 15, itens_csv: '' },
        formExtra: { idparticipante: '', tipo: 'EXTRA_5', pontos: 5, valor_ajuste: 0, motivo: '' },

        async init() {
            const ctx = await apiFetch('v1/programa-365/contexto', 'GET');
            this.isAdmin = !!ctx.is_admin;
            this.campanhas = ctx.campanhas || [];
            if (ctx.campanha_ativa) {
                this.campanha = ctx.campanha_ativa;
                this.filtros.campanha_id = Number(ctx.campanha_ativa.id);
                this.filtros.ano = Number(ctx.campanha_ativa.temporada_ano);
            }
            await this.carregarTudo();
        },

        async carregarTudo() {
            await this.carregarDashboard();
        },

        async carregarDashboard() {
            const qs = `campanha_id=${this.filtros.campanha_id}&ano=${this.filtros.ano}&mes=${this.filtros.mes}&filial=${this.filtros.filial || 0}&idgestor=${this.filtros.idgestor || 0}&participante_id=${this.filtros.participante_id || 0}`;
            const d = await apiFetch(`v1/programa-365/dashboard?${qs}`, 'GET');
            this.campanha = d.campanha || this.campanha;
            this.perfil = d.perfil || (this.isAdmin ? 'ADMIN' : 'REPRESENTANTE');
            this.filiaisDisponiveis = d.filiais_disponiveis || [];
            this.gestoresDisponiveis = d.gestores_disponiveis || [];
            this.participantesDisponiveis = d.participantes_disponiveis || [];
            this.equipeResumo = d.equipe_resumo || [];
            this.selecionado = d.selecionado || null;
            if (this.selecionado && (!this.filtros.participante_id || !this.participantesDisponiveis.some(p => Number(p.id) === Number(this.filtros.participante_id)))) {
                this.filtros.participante_id = Number(this.selecionado.idparticipante);
            }
            this.faixa = d.faixa || this.faixa;
            this.xpAnual = d.xp_anual || 0;
            this.bonusAnual = d.bonus_anual || 0;
            this.posicaoAnual = d.posicao_anual || null;
            this.desafios = d.desafios || [];
        },

        async abrirTatameRepresentante(idparticipante) {
            this.filtros.participante_id = Number(idparticipante);
            this.aba = 'tatame';
            await this.carregarDashboard();
        },

        async carregarRanking() {
            const qs = `campanha_id=${this.filtros.campanha_id}&ano=${this.filtros.ano}&mes=${this.filtros.mes}`;
            const d = await apiFetch(`v1/programa-365/ranking?${qs}`, 'GET');
            this.rankingRound = d.ranking_round || [];
            this.rankingAnual = d.ranking_anual || [];
        },

        async carregarPins() {
            const qs = `campanha_id=${this.filtros.campanha_id}&participante_id=${this.filtros.participante_id || 0}`;
            const d = await apiFetch(`v1/programa-365/pins?${qs}`, 'GET');
            this.catalogoPins = d.catalogo || [];
            this.pinsDesbloqueados = d.desbloqueados || [];
        },

        async carregarExtrato() {
            const qs = `campanha_id=${this.filtros.campanha_id}&ano=${this.filtros.ano}&participante_id=${this.filtros.participante_id || 0}`;
            const d = await apiFetch(`v1/programa-365/extrato?${qs}`, 'GET');
            this.extrato = d || { rounds: [], extras: [] };
        },

        async carregarAdmin() {
            if (!this.isAdmin) return;
            const d = await apiFetch(`v1/programa-365/admin/estrutura?campanha_id=${this.filtros.campanha_id}`, 'GET');
            this.admin = d || { equipes: [], participantes: [], vendedores_erp: [] };
            this.adminMetas = await apiFetch(`v1/programa-365/admin/metas?campanha_id=${this.filtros.campanha_id}&ano=${this.filtros.ano}&mes=${this.filtros.mes}`, 'GET');
        },

        async sincronizarErp() {
            const r = await apiFetch('v1/programa-365/admin/sincronizar-erp', 'POST', { idcampanha: this.filtros.campanha_id });
            Swal.fire('ERP Sincronizado!', `${r.participantes_sincronizados || 0} representantes e ${r.equipes_criadas || 0} novas equipes importadas.`, 'success');
            await this.carregarAdmin();
            await this.carregarDashboard();
        },

        preencherNomeVendedor() {
            const v = (this.admin.vendedores_erp || []).find(x => Number(x.idvendedor) === Number(this.formPart.idvendedor));
            if (v) this.formPart.nome_exibicao = v.nome;
        },

        async salvarEquipe() {
            if (!this.formEquipe.nome.trim()) return;
            await apiFetch('v1/programa-365/admin/equipe', 'POST', { idcampanha: this.filtros.campanha_id, nome: this.formEquipe.nome });
            this.formEquipe.nome = '';
            await this.carregarAdmin();
        },

        async salvarParticipante() {
            if (!this.formPart.idvendedor || !this.formPart.nome_exibicao) return;
            await apiFetch('v1/programa-365/admin/participante', 'POST', {
                idcampanha: this.filtros.campanha_id,
                idvendedor: this.formPart.idvendedor,
                idequipe: this.formPart.idequipe || null,
                nome_exibicao: this.formPart.nome_exibicao,
                carteira_base: this.formPart.carteira_base || 60,
            });
            await this.carregarAdmin();
            await this.carregarDashboard();
        },

        async salvarMetas() {
            await apiFetch('v1/programa-365/admin/metas', 'POST', {
                idcampanha: this.filtros.campanha_id,
                ano: this.filtros.ano,
                mes: this.filtros.mes,
                metas: this.adminMetas,
            });
            Swal.fire('Salvo!', 'Metas do Round atualizadas.', 'success');
            await this.carregarDashboard();
        },

        async salvarDesafio() {
            const itens = (this.formDesafio.itens_csv || '').split(',').map(x => parseInt(x.trim(), 10)).filter(x => x > 0);
            await apiFetch('v1/programa-365/admin/desafio', 'POST', {
                idcampanha: this.filtros.campanha_id,
                ano: this.filtros.ano,
                mes: this.filtros.mes,
                numero_desafio: this.formDesafio.numero_desafio,
                titulo: this.formDesafio.titulo || ('Desafio 0' + this.formDesafio.numero_desafio),
                meta_percentual: this.formDesafio.meta_percentual,
                itens,
            });
            Swal.fire('Salvo!', 'Desafio atualizado para o Round.', 'success');
            await this.carregarDashboard();
        },

        async lancarExtra() {
            if (!this.formExtra.idparticipante || !this.formExtra.motivo) return;
            await apiFetch('v1/programa-365/admin/extra', 'POST', {
                idcampanha: this.filtros.campanha_id,
                ano: this.filtros.ano,
                mes: this.filtros.mes,
                ...this.formExtra,
            });
            this.formExtra.motivo = '';
            Swal.fire('Lançado!', 'Ponto extra / auditoria registrado.', 'success');
            await this.carregarDashboard();
        },

        async fecharRound() {
            const ok = await Swal.fire({
                title: `Fechar Round ${this.filtros.mes}/${this.filtros.ano}?`,
                text: 'Isso consolida o snapshot de faturamento/pontos e desbloqueia os PINs conquistados.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, fechar round'
            });
            if (!ok.isConfirmed) return;
            const r = await apiFetch('v1/programa-365/admin/fechar-round', 'POST', {
                idcampanha: this.filtros.campanha_id,
                ano: this.filtros.ano,
                mes: this.filtros.mes,
            });
            Swal.fire('Round Fechado!', `${r.fechados || 0} participantes consolidados.`, 'success');
            await this.carregarDashboard();
        },

        pinDesbloqueado(codigo) {
            return (this.pinsDesbloqueados || []).some(p => p.pin_codigo === codigo);
        },

        getDesafioTitulo(num, fallback) {
            const d = (this.desafios || []).find(x => Number(x.numero_desafio) === Number(num));
            return d ? d.titulo : fallback;
        },

        brl(v) {
            return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }
    };
}
