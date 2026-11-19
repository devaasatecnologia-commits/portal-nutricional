# TOKEN DE CONTINUIDADE — ETAPA 2 (`SERVER` DRP, Segurança & Backup Anti-Ransomware)
**Data:** 19/11/2026 | **Responsável:** `ALAN` (`idcliforemp = 5166`)  
**Documento DRP:** [`docs/DRP_SERVER_NUTRICIONAL.md`](../DRP_SERVER_NUTRICIONAL.md) | **ADR:** [`ADR-006`](NUT-CORE-001.md)

## O Que Foi Executado e Validado no `SERVER` (`192.168.1.99` / `200.53.15.132`)
1. **Perímetro & Roteador HUAWEI AX3S (`192.168.1.1`):**
   - `Host DMZ` **desligado**, `Firewall` **ligado**, e removido `Host remoto = 192.168.1.99` de `BANCODADOS` (`5432`), `MERCOSF1` (`8081`) e `MERCOSF6` (`8086`).
   - Testado externamente após mudanças: `https://api.nutricionalbr.com/v1/portal-cliente/auth/config` (`200`), `http://mercosf1.nutricionalbr.com:8081/report` (`200`), `:8086/report` (`200`).
2. **Firewall do Windows (`SERVER`) & Acesso SSH / Zero Trust:**
   - OpenSSH Server local-only (`22`) restrito a `192.168.1.0/24` com chave `ed25519`.
   - Regras RDP (`52465`, `3389`) restritas a `192.168.1.0/24` + `127.0.0.1` (tentativas `Event 4625` caíram de `14.264`/24h para **`0`**).
   - Regra `PostgreSQL 5432 - cPanel e LAN` criada com `127.0.0.1`, `192.168.1.0/24`, `191.252.128.2` (cPanel) e `200.53.15.132` (hairpin local).
   - Cloudflare Zero Trust (`remoto.nutricionalbr.com`): política `Acesso permitido` (`alan@nutricionalbr.com`, `robson@nutricionalbr.com`, `8h`) + MFA **`Authenticator application` + `Biometrics` (`Requires MFA on every access`)**; `AUTENTICADOR ALAN` (`TOTP`) cadastrado e validado.
3. **Backup 3-2-1 Anti-Ransomware & SMB:**
   - `destinos.bat` corrigido (removido `pause`) e tarefa `BACKUP INDIVIDUAL` destravada.
   - `Backup Banco.bat` atualizado para copiar `.dmp` + `DE_PARA_MERCOS*.FDB` também para `F:\Backup_EMA\` (com `.bak_20261119` salvos).
   - `rclone v1.76.0` instalado em `C:\rclone\`; criado `C:\rclone\backup_nuvem.ps1` (com `C:\rclone\tmp\` para `.FDB` em uso) e tarefa `NEXUS_Backup_Nuvem_Diario` (`13:00` e `19:30` como `SYSTEM`) enviando Append-Only para `nutricional_bkp:Backup_Imutavel_EMA/`. Validado envio de `ema-19-11-2026-12-20.dmp` (`3,30 GB`) e `DE_PARA_MERCOS.FDB` (`157 MB`).
   - `GoogleDriveFS` (`G:`/`H:`) encerrado e removido da inicialização (`RAM Livre: 19,26 GB`).
   - SMB (`Ema Software`, `Logicsoft`, `NUTRI`) endurecido para `Everyone = Read` + `BUILTIN\Administradores = Full`.
4. **Observabilidade WhatsApp:**
   - Criado `C:\Monitoramento\Alerta_Saude.ps1` + tarefa `NEXUS_Alerta_Saude_WhatsApp` (`13:30` e `20:00` como `SYSTEM`), enviando resumo/alerta para `5548996426445` via Evolution API local (`http://localhost:8080`). Testado ao vivo com sucesso.

## Deploy FTP (cPanel) Pendente da Etapa 1
- Conferir envio dos arquivos listados em [`CONTINUATION-2026-11-19-ETAPA1-2FA-WHATS-MOBILE.md`](CONTINUATION-2026-11-19-ETAPA1-2FA-WHATS-MOBILE.md) + novo PDF `portal/uploads/chamados/manual_drp_seguranca_server_etapa2.pdf` caso deseje visualizar no módulo Chamados em produção.

## Próximos Passos Sugeridos
1. Iniciar **Fase 1 do NEXUS no Portal Web** (`NexusService`, `NexusController`, tabelas `nexus_*`, `SecretVault`, Context Builders com `UserScope`).
2. Backlog de infraestrutura (`docs/DRP_SERVER_NUTRICIONAL.md` Seção 5): criar `client_id` próprio no Google Cloud para `rclone` antes do fim de 2026; planejar upgrade do Windows 10 `18362` (removendo `KMS-R@1n`); avaliar `ssl = on` no PostgreSQL 16; e teste semestral de `pg_restore`.
