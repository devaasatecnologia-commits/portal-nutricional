# Diagnóstico de Devoluções e Comissões

Este diretório contém um script PHP que executa a consulta de devoluções/comissões para a filial **1** no período **01/09/2026 a 30/09/2026** e exibe o resultado como JSON.

## Como usar

1. **Instalar dependências (se ainda não houver)**
   ```powershell
   cd C:\xampp\htdocs\API
   composer require vlucas/phpdotenv
   ```
   O script já carrega o `.env` da raiz do projeto, caso exista.

2. **Configurar variáveis de ambiente**
   Crie um arquivo `.env` na raiz do projeto (ao lado da pasta `API`) com as credenciais do banco:
   ```env
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=seu_banco
   DB_USER=seu_usuario
   DB_PASS=sua_senha
   DB_DRIVER=pgsql   # ou mysql se for MySQL
   ```
   Ajuste os valores conforme seu ambiente.

3. **Executar o script**
   ```powershell
   php -f docs\cubo_devolucao_test.php
   ```
   A saída será um JSON semelhante a:
   ```json
   {
     "status": "success",
     "filial": 1,
     "period": {"start": "2026-09-01", "end": "2026-09-30"},
     "count": 42,
     "data": [ ... ],
     "runtime": 0.1234
   }
   ```

4. **Comparar com a API do Cubo de Vendas**
   Use `curl` (ou outra ferramenta) para obter os dados da API do Cubo e compare os campos `VALOR_FATURADO`, `VALOR_DEVOLVIDO` e `VALOR_TOTAL` com o resultado acima.
   ```powershell
   curl -s "http://localhost:8080/v1/vendas/cubo/data" -H "X-API-Key: <seu_token>" -d "{ \"filial\": 1, \"data_inicio\": \"2026-09-01\", \"data_fim\": \"2026-09-30\" }"
   ```

## Notas
- O script depende da função `getPDO()` definida em `v1/bootstrap/app.php`. Essa função lê as variáveis de ambiente carregadas pelo `.env`.
- Caso o script retorne **"connection refused"**, verifique se o banco está rodando e se as credenciais/host/porta estão corretas.
- O script já define atributos PDO para lançar exceções e buscar resultados como arrays associativos.

---
*Este arquivo foi gerado pelo assistente AI Antigravity.*
