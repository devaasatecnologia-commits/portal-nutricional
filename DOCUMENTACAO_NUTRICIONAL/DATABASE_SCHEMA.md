# 🗄️ SCHEMA DO BANCO - NUTRICIONAL PET

> **Sistema:** PostgreSQL  
> **Database:** ema

---

## ESTATÍSTICAS

| Tipo | Quantidade |
|------|------------|
| Tabelas | 1.341 |
| Views | 69 |
| Functions | 20 |
| Usuários | 82 |
| Clientes | 10.776 |

---

## TABELAS PRINCIPAIS

### usuario
- idusuario (PK)
- username
- senha (MD5)
- inativo

### token_blacklist
- id (PK)
- token_hash
- idusuario (FK)
- expiracao

### cliforemp
- idcliforemp (PK)
- fantasia
- cnpj
- email

### produto
- idproduto (PK)
- descricao
- peso

---

## SCRIPTS ÚTEIS

### Limpar tokens expirados
SELECT clean_expired_tokens();

### Backup
pg_dump -U postgres -d ema > backup.sql