# Continuation Token — Correção do Menu C.H.A.P.D (Sem Scrollbars), Miniaturas de Fotos, Dashboard Analytics e Upload no Admin/Portal

**Data:** 04/11/2026  
**Responsável Técnico:** Tech Lead & Arquiteto Nutricional

---

## 1. Resumo das Alterações Realizadas

### 1.1. Rota Estática Direta para Miniaturas e Fotos (`formatarUrlFoto` & `getFotoUrl`)
- **Arquivos modificados:** `portal/assets/js/avaliacao-chapd.js`, `portal/modules/admin/usuarios.php` e `index.php`
- **Causa Raiz Identificada:**
  - Em ambiente local (`localhost:8080`), a variável global `window.API_URL` é definida como `${window.location.origin}/index.php?api_route=`.
  - A função `formatarUrlFoto` estava concatenando essa base nas fotos, gerando URLs inválidas como `http://localhost:8080/index.php?api_route=/uploads/perfil/...` em vez de servir o arquivo estático diretamente (`http://localhost:8080/uploads/perfil/...`).
- **Correções Aplicadas:**
  1. `formatarUrlFoto` e `getFotoUrl` corrigidas: uploads de mídia (`uploads/...`) são tratados como **arquivos estáticos diretos** na raiz da aplicação (`${window.location.origin}${basePrefix}/${cleanPath}`), sem jamais passar por `index.php?api_route=`.
  2. Suporte inteligente a ambiente local: se o registro vier do banco com o domínio remoto `https://api.nutricionalbr.com/uploads/...` e a aplicação estiver rodando em `localhost`/`127.0.0.1`, o frontend converte automaticamente para o host local.
  3. No gateway raiz `index.php`, foi adicionado um interceptador que atende imediatamente qualquer requisição que ainda chegue via `?api_route=/uploads/...`, servindo o binário da imagem com o Content-Type correto e eliminando o 404 do Slim.

### 1.2. Menu C.H.A.P.D sem Barras de Rolagem
- **Arquivo modificado:** `portal/modules/avaliacao-chapd.php`
- Ocultação cross-browser da barra nativa cinza no menu (`scrollbar-width: none; -ms-overflow-style: none; &::-webkit-scrollbar { display: none; }`).

### 1.3. Miniatura no Modal de Edição de Dados e no Modal de Troca de Foto
- **Arquivos modificados:** `portal/modules/avaliacao-chapd.php` e `portal/assets/js/avaliacao-chapd.js`
- Adicionado card no topo do `#modalEditarPerfilColab` com a foto miniatura do colaborador, iniciais fallback e botão "Alterar Foto".
- No `#modalFotoColab`, exibição da foto atual como referência e preview imediato na seleção de arquivo ou captura de câmera.

### 1.4. Fotos no Dashboard Analytics (C.H.A.P.D)
- **Arquivos modificados:** `v1/src/Controllers/AvaliacaoChapdController.php` e `portal/assets/js/avaliacao-chapd.js`
- `LEFT JOIN` nas consultas do Dashboard para trazer `foto_url` e inclusão dos avatares com foto em Top Colaboradores e Colaboradores em Atenção/PDI.

### 1.5. Upload de Foto no Admin e no Perfil do Usuário
- **Arquivos modificados:** `v1/src/Controllers/AdminController.php`, `v1/src/Controllers/AuthController.php`, `portal/modules/admin/usuarios.php` e `portal/index.php`
- Suporte a token JWT como fallback quando `idusuario` não vem no POST, envio de header CSRF e sincronização bidirecional com a área C.H.A.P.D.

---

## 2. Arquivos para Deploy FTP
1. `index.php`
2. `portal/index.php`
3. `portal/modules/avaliacao-chapd.php`
4. `portal/assets/js/avaliacao-chapd.js`
5. `portal/modules/admin/usuarios.php`
6. `v1/src/Controllers/AdminController.php`
7. `v1/src/Controllers/AuthController.php`
8. `v1/src/Controllers/AvaliacaoChapdController.php`
