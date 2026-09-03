# 📡 REFERÊNCIA COMPLETA DA API - NUTRICIONAL PET

> **Base URL:** https://api.nutricionalbr.com/v1/  
> **Total de rotas:** 427 endpoints  
> **Versão:** 2.0 FINAL V3  
> **Segmento:** Alimentos PET 🐾

---

## 🔐 AUTENTICAÇÃO

Todas as rotas (exceto /auth/login e /ping) exigem token JWT:

http
Authorization: Bearer <seu_token_jwt>


### Endpoints de Autenticação (5 rotas)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | /auth/login | Autenticar usuário |
| POST | /auth/logout | Revogar token atual |
| POST | /auth/logout-all | Revogar todos os tokens |
| POST | /auth/alterar-senha | Alterar senha do usuário |
| GET | /perfil/dados | Obter dados do perfil |

---

## 👤 ADMINISTRAÇÃO (19 rotas)

### Usuários

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /admin/usuarios | Listar todos os usuários |
| GET | /admin/usuarios/{id}/permissoes | Obter permissões do usuário |
| POST | /admin/usuarios/{id}/toggle | Ativar/desativar usuário |
| POST | /admin/usuarios/editar | Editar usuário |
| POST | /admin/upload-foto | Upload de foto de perfil |

### Permissões

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /admin/gestores | Listar gestores disponíveis |
| GET | /admin/setores | Listar setores |
| GET | /admin/setores/{id}/modulos | Módulos por setor |
| POST | /admin/permissoes | Salvar permissões |
| POST | /admin/permissoes-por-setor | Salvar permissões por setor |

### API Tokens

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /admin/api-tokens | Listar tokens API |
| POST | /admin/api-tokens | Criar novo token |
| POST | /admin/api-tokens/{id}/revogar | Revogar token |
| GET | /admin/escopos | Listar escopos disponíveis |

### Logs

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /admin/logs | Obter logs de acesso |

---

## ⚙️ CRON & AUTOMAÇÕES (8 rotas)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /cron/dashboard | Dashboard de execuções |
| GET | /cron/jobs | Listar todos os jobs |
| POST | /cron/jobs | Criar/editar job |
| DELETE | /cron/jobs/{id} | Excluir job |
| POST | /cron/executar | Executar job manualmente |
| GET | /cron/auditoria | Listar execuções |
| GET | /cron/auditoria/{id} | Detalhes da execução |
| POST | /cron/limpar-links | Limpar links expirados |

---

## 📊 MARKETING & CRM (50+ rotas)

### Dashboard

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /marketing/dashboard | Dashboard principal |
| GET | /marketing/kpis | Indicadores de performance |
| GET | /marketing/dados-grafico | Dados para gráficos |

### Clientes (PET shops, distribuidores)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /marketing/clientes | Listar clientes |
| GET | /marketing/clientes/consulta | Consultar clientes ERP |
| GET | /marketing/clientes/{id} | Detalhes do cliente |
| POST | /marketing/clientes | Criar cliente |
| PUT | /marketing/clientes/{id} | Atualizar cliente |
| DELETE | /marketing/clientes/{id} | Excluir cliente |
| POST | /marketing/clientes/importar-erp/{id} | Importar do ERP |
| POST | /marketing/clientes/sincronizar-todos | Sincronizar todos |
| GET | /marketing/clientes/exportar/{formato} | Exportar (PDF/Excel) |

### Metas de Vendas (ração PET)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /marketing/metas | Listar metas |
| GET | /marketing/metas/{id} | Detalhes da meta |
| POST | /marketing/metas | Criar meta |
| PUT | /marketing/metas/{id} | Atualizar meta |
| DELETE | /marketing/metas/{id} | Excluir meta |
| GET | /marketing/metas-progresso | Progresso das metas |

### Interações e Compromissos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /marketing/clientes/{id}/interacoes | Interações do cliente |
| POST | /marketing/clientes/{id}/interacoes | Nova interação |
| GET | /marketing/compromissos | Listar compromissos |
| POST | /marketing/compromissos | Criar compromisso |
| PUT | /marketing/compromissos/{id}/concluir | Concluir compromisso |

---

## 📦 OPERACIONAIS - LOGÍSTICA

### Separação (6 rotas)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /separacao/embarques | Embarques pendentes |
| GET | /separacao/itens/{idembarque} | Itens do embarque |
| POST | /separacao/confirmar | Confirmar item |
| DELETE | /separacao/estornar/{iditem}/{idembarque} | Estornar item |
| GET | /separacao/resumo/{idembarque} | Resumo do embarque |
| POST | /separacao/finalizar/{idembarque} | Finalizar separação |

### Carregamento (9 rotas)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /carregamento/embarques | Embarques para carregar |
| GET | /carregamento/itens/{idembarque} | Itens do embarque |
| POST | /carregamento/confirmar | Confirmar item |
| POST | /carregamento/finalizar/{idembarque} | Finalizar carga |
| GET | /carregamento/fotos/{idembarque} | Fotos do embarque |
| POST | /carregamento/foto | Upload de foto |

### Desembarque (8 rotas)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /desembarque/ordens-compra | Ordens de compra |
| GET | /desembarque/itens/{idoc} | Itens da OC |
| POST | /desembarque/confirmar | Confirmar item |
| POST | /desembarque/finalizar/{idoc} | Finalizar conferência |
| GET | /desembarque/secoes | Seções do depósito |
| POST | /desembarque/foto | Upload de foto |

---

## 📈 VENDAS (21 rotas)

### Cubo de Vendas

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /cubo/config | Configuração do cubo |
| POST | /cubo/data | Dados do cubo |
| POST | /cubo/ranking | Ranking de vendas (ração PET) |
| POST | /cubo/filiais | Vendas por filial |
| POST | /cubo/gestores | Vendas por gestor |
| POST | /cubo/exportar | Exportar dados |

### Dashboard de Vendas

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | /dashboard/kpis | Indicadores de venda |
| POST | /dashboard/kpis-detalhes | Detalhes dos KPIs |
| POST | /dashboard/produto-detalhes | Detalhes do produto |
| POST | /dashboard/cliente-detalhes | Detalhes do cliente |
| POST | /dashboard/insights | Insights automáticos |

---

## 🏥 HEALTH CHECK

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /ping | Verificar se API está online |

---

## 📊 RESUMO DE ROTAS POR CATEGORIA

| Categoria | Quantidade |
|-----------|------------|
| Autenticação | 5 |
| Administração | 19 |
| Cron/Automação | 8 |
| Marketing/CRM | 50+ |
| Separação | 6 |
| Carregamento | 9 |
| Desembarque | 8 |
| Gestão de Depósito | 8 |
| Inventário | 7 |
| Estoque/Previsão | 7 |
| Cubo de Vendas | 13 |
| Dashboard Vendas | 8 |
| Chat | 6 |
| Consulta | 5 |
| XML | 10 |
| Análise de Carteira | 6 |
| Auditoria | 8 |
| Financeiro | 4 |
| **TOTAL** | **~427** |

---

## 🔒 CÓDIGOS DE ERRO

| Código | Significado |
|--------|-------------|
| 200 | Sucesso |
| 400 | Requisição inválida |
| 401 | Não autenticado |
| 403 | Sem permissão |
| 404 | Recurso não encontrado |
| 429 | Muitas requisições (rate limit) |
| 500 | Erro interno do servidor |

---

**API do Sistema Nutricional PET - Alimentos para animais** 🐾
