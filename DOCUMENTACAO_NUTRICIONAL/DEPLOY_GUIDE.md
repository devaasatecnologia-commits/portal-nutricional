# 🚀 GUIA DE DEPLOY - NUTRICIONAL PET

---

## PRÉ-REQUISITOS

- PHP 8.0+
- PostgreSQL 13+
- Composer 2.x

---

## PASSO A PASSO

### 1. Preparar ambiente
cd C:\xampp\htdocs\API
composer install



### 2. Configurar .env
APP_ENV=production
DB_HOST=localhost
DB_NAME=ema_producao
DB_USER=usuario
DB_PASS=senha



### 3. Enviar arquivos via FTP

- v1/
- portal/
- .env
- router.php

### 4. Testar
curl https://api.nutricionalbr.com/ping



---

## CHECKLIST

- [ ] .env configurado
- [ ] Vendor instalado
- [ ] Permissões 755
- [ ] Backup realizado


✅ RESULTADO FINAL
Após criar todos os arquivos manualmente, sua pasta DOCUMENTACAO_NUTRICIONAL ficará assim:


DOCUMENTACAO_NUTRICIONAL/
├── index.html
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── README.md           ✅
├── SUPPORT_TOKEN.md    ✅
├── CHANGELOG.md        ✅
├── API_REFERENCE.md    ✅
├── DATABASE_SCHEMA.md  ✅
├── DEPLOY_GUIDE.md     ✅
├── guides/
│   ├── TROUBLESHOOTING.md
│   └── SECURITY_CHECKLIST.md
└── scripts/
    └── backup.sql