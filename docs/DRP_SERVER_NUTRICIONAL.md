# PLANO OFICIAL DE CONTINUIDADE E RECUPERAÇÃO DE DESASTRES (DRP) — `SERVER`
**Documento Corporativo — Versão 1.0 (19/11/2026)**  
**Governança:** [`NUT-CORE-001`](tokens/NUT-CORE-001.md) | [`CONSTITUICAO_IA_NUTRICIONAL.md`](CONSTITUICAO_IA_NUTRICIONAL.md)

---

## 1. Inventário Real do Servidor Principal (`SERVER`)
- **Hostname / Rede:** `SERVER` | IP LAN: `192.168.1.99` | IP Público: `200.53.15.132` | Gateway: `192.168.1.1` (HUAWEI WiFi AX3S).
- **Hardware / SO:** Intel Xeon E3-1220 v6 (4C/4T @ 3.00GHz), `31,91 GB` RAM, Microsoft Windows 10 Pro (`10.0.18362`).
- **Discos:**
  - `C:` (`893,62 GB`) — Sistema, ERP EMA, PostgreSQL 16.3 (`ema` ~`86 GB`), Firebird, Mercos F1/F6, Evolution API, HFS.
  - `F: BKP` (`464,92 GB`) — Disco físico dedicado para cópia local `F:\Backup_EMA\` (`.dmp` + `.FDB`).
- **Serviços Críticos:**
  - `postgresql-x64-16` (porta `5432`)
  - `FirebirdServerDefaultInstance` (porta `3050`)
  - `ema-mercos-integration-F1` (`8081`) e `ema-mercos-integration-F6` (`8086`)
  - `EvolutionAPI` (`localhost:8080`, Node 22.14.0 via PM2 + tarefa `EvolutionAPI-AutoStart`)
  - `HFS_Servidor_Nutricional` (`C:\hfs\hfs.exe`, portas `2053` / `9898`)
  - `Cloudflared` (`Servidor-Principal`, rotas `remoto.nutricionalbr.com`, `whats.nutricionalbr.com`, `192.168.1.0/24`)

---

## 2. Blindagem de Perímetro e Acesso (Aplicada em 19/11/2026)
1. **Roteador HUAWEI AX3S (`192.168.1.1`):**
   - `Host DMZ` **DESLIGADO** e `Firewall` **LIGADO**.
   - Regras NAT ativas apenas para: `BANCODADOS` (`5432`), `Regra HTTPS` (`2053` HFS), `MERCOSF1` (`8081`), `MERCOSF6` (`8086`), `WEB`/`Regra HTTP`.
2. **Firewall do Windows (`SERVER`):**
   - **RDP (`52465` e `3389`):** restrito exclusivamente a `192.168.1.0/24` e `127.0.0.1` (tentativas externas `Event 4625` zeradas após `14.264`/24h).
   - **OpenSSH (`22`):** restrito exclusivamente a `192.168.1.0/24` + chave `ed25519`.
   - **PostgreSQL (`5432`):** restrito a `127.0.0.1`, `192.168.1.0/24`, `191.252.128.2` (cPanel `api.nutricionalbr.com`) e `200.53.15.132` (hairpin LAN).
3. **Acesso Externo Zero Trust (`remoto.nutricionalbr.com`):**
   - Browser RDP protegido por Cloudflare Access (`alan@nutricionalbr.com`, `robson@nutricionalbr.com`), sessão de `8h`, **One-time PIN por e-mail + MFA TOTP (`Authenticator application` / `Biometrics`) em todo acesso**.
4. **Compartilhamentos SMB:**
   - `Ema Software`, `Logicsoft`, `NUTRI`: `BUILTIN\Administradores = Full` e `Everyone = Read`.

---

## 3. Estratégia de Backup 3-2-1 & Monitoramento
- **Cópia 1 (Local `C:`):** `EMA_Backup_Diario_ManhaTarde` (`12:20` e `18:30`) gera `ema-DD-MM-AAAA-HH-MM.dmp` (`~3,3 GB`) em `C:\Ema Software\Ferramentas\Backup\` (retenção 7 cópias).
- **Cópia 2 (Disco físico separado `F:`):** o mesmo script grava `.dmp` + `DE_PARA_MERCOS*.FDB` em `F:\Backup_EMA\` (retenção 7 cópias).
- **Cópia 3 (Nuvem Imutável / Append-Only sem letra de drive):** `NEXUS_Backup_Nuvem_Diario` (`13:00` e `19:30`, `C:\rclone\backup_nuvem.ps1`) envia via `rclone copy` (sem comando `delete`) para `nutricional_bkp:Backup_Imutavel_EMA/YYYY-MM-DD_HHmm/` (conta Google Drive `2 TB`, `1,394 TB` livres), incluindo `.dmp`, `DE_PARA_MERCOS.FDB`, `DE_PARA_MERCOS-F6.FDB`, `application.properties`, `dump.pm2` e configs HFS. `GoogleDriveFS` (`G:`/`H:`) foi desativado na inicialização.
- **Cópias na Rede Interna:** `BACKUP INDIVIDUAL` (`destinos.bat`, corrigido sem `pause`) replica para `\\tecnologia`, `\\Financeiro`, `\\robson`, `\\deposito`.
- **Observabilidade WhatsApp:**
  - `NEXUS_Alerta_Saude_WhatsApp` (`C:\Monitoramento\Alerta_Saude.ps1`, `13:30` e `20:00`): resumo diário de serviços, discos, backups e `Event 4625` para `5548996426445`.
  - `NEXUS_Monitor_Erros_15min` (`Alerta_Saude.ps1 -AlertOnly`, a cada 15 min): alerta imediato apenas em caso de falha de serviço, disco `< 15%` ou `4625 > 20/h`.
  - `NEXUS_Alerta_AcessoRemoto` (`C:\Monitoramento\Alerta_AcessoRemoto.ps1`, gatilho `Event 4624 / LogonType 10`): avisa na hora no WhatsApp quando houver login RDP (local ou Cloudflare Zero Trust).
  - `NEXUS_Alerta_Boot` (`C:\Monitoramento\Alerta_Boot.ps1`, gatilho `AtStartup`): avisa após 60s do boot informando se o reinício foi normal ou inesperado (`Event 41 / 6008`) e o estado dos serviços.
  - `backup_nuvem.ps1`: envia confirmação imediata (`BACKUP NUVEM OK` ou `ERRO`) ao concluir o envio `rclone`.

**RPO / RTO:**
- **RPO (Perda máxima em caso de desastre):** ~`6h` a `12h` em horário comercial (2 backups/dia às `12:20`/`13:00` e `18:30`/`19:30`).
- **RTO (Tempo estimado para subir banco + serviços em máquina limpa):** `2h` a `4h`.

---

## 4. Se o `SERVER` Parar Agora — O Que Para, O Que Continua e Como Recuperar
- **O que continua no ar:** site estático / painel cPanel (`api.nutricionalbr.com`) responde HTTP, mas consultas ao banco `ema`, Evolution WhatsApp e HFS param até a restauração ou apontamento de contingência.
- **Passo a Passo de Restauração (DRP):**
  1. Subir Windows + instalar **PostgreSQL 16** (mesmo encoding/locale compatível `WIN1252`).
  2. Baixar o último `ema-*.dmp` de `F:\Backup_EMA\` ou do Google Drive (`Backup_Imutavel_EMA/.../EMA_DMP/`) e restaurar com `pg_restore --dbname=ema --no-owner`.
  3. Restaurar `C:\Ema Software\` (`Integrador Mercos-F1` / `F6` com `DE_PARA_MERCOS*.FDB`), `C:\hfs\`, Evolution API (`.env` + `dump.pm2`) e reiniciar `Cloudflared` com o token do túnel `Servidor-Principal`.
  4. Validar `api.nutricionalbr.com`, `whats.nutricionalbr.com` e `mercosf1.nutricionalbr.com:8081/:8086`.

---

## 5. Pendências Conhecidas (Backlog de Segurança Contínua)
1. Criar `client_id` próprio no Google Cloud para `rclone` antes da descontinuação do `client_id` compartilhado em 2026.
2. Planejar atualização do Windows 10 Build `18362` (EOL) para Windows Server LTS ou Windows 11/10 LTSC licenciado (removendo `KMS-R@1n` na porta `1688`).
3. Avaliar ativação de `ssl = on` + `scram-sha-256` explícito no `pg_hba.conf` do PostgreSQL 16 (após validar compatibilidade com o ERP EMA desktop).
4. Realizar teste prático semestral de restauração (`pg_restore`) do `.dmp` da nuvem em base de homologação.
