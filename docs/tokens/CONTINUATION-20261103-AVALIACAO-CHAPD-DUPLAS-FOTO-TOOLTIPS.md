# Continuation Token — Avaliação C.H.A.P.D: Edição de Perfil em Área Separada (JOIN com ERP), Sincronização com Usuário do Portal, Painel de Colaboradores & Fotos

**Data/Hora:** 03/11/2026  
**Contexto:** Módulo Avaliação de Desempenho C.H.A.P.D & Gestão de Colaboradores (Nutricional Distribuidora)  
**Status:** Concluído com Sucesso e Validado

---

## 1. Resumo das Implementações

### A. Edição do Perfil do Colaborador em Área Separada (JOIN com ERP Legado)
- **Regra de Ouro da Arquitetura:** Para garantir total segurança e não corromper o cadastro fiscal/RH oficial do ERP legado (`cliforemp` e `empregado`), foi criada a tabela separada dedicada ao C.H.A.P.D:
  ```sql
  CREATE TABLE IF NOT EXISTS avaliacao_chapd_colaborador_perfil (
      idcliforemp INTEGER PRIMARY KEY,
      nome_custom VARCHAR(255),
      cargo_custom VARCHAR(150),
      setor_custom VARCHAR(100),
      celula_custom VARCHAR(100),
      idfilial_custom INTEGER,
      data_admissao_custom DATE,
      foto_url VARCHAR(255),
      observacoes TEXT,
      atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      atualizado_por VARCHAR(100)
  );
  ```
- **Backend Integrado via LEFT JOIN:**
  - Queries de `contexto()`, `matrizAnual()`, `historico()` e `salvarPerfilColaborador()` combinam a tabela `cliforemp` + `empregado` com `avaliacao_chapd_colaborador_perfil`.
  - Prioriza os dados personalizados quando existentes (`COALESCE(NULLIF(TRIM(cp.nome_custom), ''), TRIM(c.razao))`), mantendo os originais intactos em `nome_erp`, `cargo_erp`, etc.
  - Endpoint `POST /v1/avaliacao-chapd/colaborador/salvar-perfil` com suporte a atualização de dados, fotos e restauração ao padrão do ERP.

### B. Sincronização Bidirecional com a Tabela de Usuários do Portal (`usuario`)
- Vínculo direto `u.idcliforemp = c.idcliforemp`.
- Ao bater/enviar foto ou salvar o perfil no C.H.A.P.D, o sistema automaticamente sincroniza a coluna `usuario.foto_perfil` e `usuario.updated_at`.
- Fallback universal na consulta: `COALESCE(cp.foto_url, cf.foto_url, u.foto_perfil) AS foto_url`, permitindo que colaboradores que já possuem foto no portal automaticamente apareçam com a foto em todos os módulos do C.H.A.P.D.

### C. Nova Aba e Painel Dedicado: "Colaboradores & Equipe"
- Adicionada aba `#tabBtnColaboradores` e `#painelColaboradores` em `portal/modules/avaliacao-chapd.php`.
- **4 Métricas em Tempo Real:** Total de Colaboradores, Com Foto Cadastrada, Perfis Customizados (área separada) e Usuários Vinculados ao Portal.
- **Filtros Interativos:** Busca rápida por nome (ERP ou custom), cargo, setor, célula ou `@usuario_portal`, filtro por filial e filtro por foto/status.
- **Grid de Cards de Colaboradores:**
  - Avatar grande com foto do colaborador e botão de câmera rápido sobreposto.
  - Badges de Filial, Tag de Usuário do Portal (`@login`) e Tag `Custom C.H.A.P.D`.
  - Dados completos (cargo, setor, admissão).
  - Botão "Editar" (abre `#modalEditarPerfilColab`).
  - Botão "Foto" (abre `#modalFotoColab` direto para o colaborador).
  - Botão "Avaliar" (inicia wizard do C.H.A.P.D com o colaborador pré-selecionado).

### D. Módulo de Fotos (Câmera Webcam e Upload de Arquivo)
- Upload multipart e salvamento com resolução de URL absoluta universal (`formatarUrlFoto`) e cache-busting `?t=...`.
- Foto sincronizada no avatar do card de avaliação, no prontuário, na Ficha Oficial de Impressão/PDF, na Matriz Anual e no Histórico.

### E. Duplas de Conjuntos de Letras
- Soma máxima $\ge 115$ pts: **Conjunto A e B** (Alta Performance / Destaque).
- Meio dos pontos $85$ a $114.9$ pts: **Conjunto C e D** (Médio Potencial / Desenvolvimento).
- Últimos abaixo $< 85$ pts: **Conjunto E e F** (Atenção Crítica / PDI Necessário).

---

## 2. Arquivos para Deploy FTP

1. `v1/routes/api.php`
2. `v1/src/Controllers/AvaliacaoChapdController.php`
3. `portal/modules/avaliacao-chapd.php`
4. `portal/assets/js/avaliacao-chapd.js`
5. `uploads/chapd/` (Diretório no servidor com permissões 0755/0777)
6. Banco de Dados PostgreSQL: tabela `avaliacao_chapd_colaborador_perfil` criada.

---

## 3. Validação Técnica
- `php -l v1/routes/api.php`: Sem erros.
- `php -l v1/src/Controllers/AvaliacaoChapdController.php`: Sem erros.
- `php -l portal/modules/avaliacao-chapd.php`: Sem erros.
- `node -c portal/assets/js/avaliacao-chapd.js`: Sem erros.
- Teste de fluxo completo (sincronização de usuário, edição de perfil na área separada e exibição em grid) validado com sucesso.
