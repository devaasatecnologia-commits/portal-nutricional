# MAPA OFICIAL DA REDE LOCAL — NUTRICIONAL (`192.168.1.0/24`)
**Versão:** 1.0 (23/11/2026) | **Chamado Relacionado:** `#77`  
**Documento Complementar:** [`docs/DRP_SERVER_NUTRICIONAL.md`](DRP_SERVER_NUTRICIONAL.md)

---

## 1. Arquitetura & Endereçamento IP
- **Sub-rede:** `192.168.1.0/24` (Máscara `255.255.255.0`) | **Gateway / DNS:** `192.168.1.1` (HUAWEI WiFi AX3S)
- **Faixa DHCP Dinâmica (HUAWEI AX3S):** **`192.168.1.10` até `192.168.1.90`** (ajustada em `23/11/2026` — antes era `.2`–`.254`, o que gerava conflito de IP com câmeras, impressoras e `SERVER`).
- **Faixa Fora do DHCP (IPs Fixos / Infraestrutura):** `192.168.1.91` até `192.168.1.254` + Reservas Estáticas no roteador.
- **Velocidade Verificada:** `1 Gbps` no `SERVER` (`Embedded LOM 1 Port 1`) e na estação de trabalho (`Realtek PCIe GbE`).

---

## 2. Inventário Real Levantado na Varredura (`23/11/2026`)

### 2.1 Roteador, Wi-Fi & Telefonia
| IP | MAC | Equipamento | Observação |
|---|---|---|---|
| `192.168.1.1` | `CC-B0-A8-D0-D6-EB` | **HUAWEI WiFi AX3S** | Roteador principal, DHCP `.10`–`.90`, DMZ OFF, Firewall ON |
| `192.168.1.20` | `F4-E2-C6-64-33-60` | **Ubiquiti UniFi AP** | Ativo (`Ping OK`) |
| `192.168.1.118` | `44-D2-44-0D-5C-62` | **Ubiquiti UniFi AP** | Reiniciado em `23/11/2026` — voltou a responder (`8ms`) |
| `192.168.1.192` | `F8-A0-3D-19-0F-48` | **ATA Handix (VoIP)** | Linhas telefônicas (`80, 443`) — não alterar IP |
| *(Outros APs)* | — | +1 UniFi AP e 1 Roteador TP-Link | Manter TP-Link sem DHCP e conectado via porta LAN |

### 2.2 Servidores
| IP | MAC | Equipamento | Associação Estática |
|---|---|---|---|
| `192.168.1.99` | `94-18-82-AA-58-48` | **`SERVER.local`** (ERP EMA / Postgres / Evolution / Mercos / HFS) | Reservado no HUAWEI AX3S |
| `192.168.1.55` | `94-18-82-AA-58-4B` | **`ILOBRC72991C5`** (Gerência HPE iLO) | Reservado no HUAWEI AX3S |

### 2.3 CFTV — NVR & Câmeras IP (`80, 554, 37777`)
| Faixa / IP | Quantidade / Equipamento | Observação |
|---|---|---|
| `192.168.1.210` | **NVR / DVR Principal Intelbras** | Portas `80, 443, 554, 37777` (`<1ms`) |
| `192.168.1.169` – `.202` e `.211` | **28 IPs de Câmeras IP** (`98-E5-5B-*` / `54-6C-AC-*`) | `.211` voltou após reinício em `23/11/2026` (`15ms`). *Atenção:* `.192` pertence ao ATA Handix. |

### 2.4 Impressoras
| IP Atual | MAC | Identificação | Recomendação |
|---|---|---|---|
| `192.168.1.68` | `F8-ED-FC-07-34-17` | `HP073417.local` | Saiu de `.184` após reinício; fixar reserva estática no roteador (`.68` ou `.140`) |
| `192.168.1.83` | `F8-ED-FC-0D-90-60` | `HP0D9060.local` | Fixar reserva estática no roteador (`.83` ou `.141`) |
| `192.168.1.121` | `E0-BB-9E-6F-F9-B2` | `IMPRESSORARH` | Fora do DHCP |
| `192.168.1.136` | `CC-6B-1E-43-97-40` | `BRWCC6B1E439740` (Brother) | Fora do DHCP |
| `192.168.1.153` | `38-9D-92-04-9A-87` | `EPSON049A87.local` | Fora do DHCP |
| `192.168.1.24` | `AC-18-26-06-81-86` | `EPSON068186` | Offline durante scan |
| `192.168.1.36` | `80-2B-F9-4B-61-26` | `BRW802BF94B6126` (Brother) | Offline durante scan |

### 2.5 Estações de Trabalho Mapeadas no Scan
- `.2` (`MARKETING.local`), `.22` (`Larissa.local`), `.32` (`FINANCEIRO2.local`), `.34` (`RECURSOS-HUMANOS.local`), `.35` (`DEPOSITO.local`), `.61` (`DESKTOP-20GKB64.local`), `.69` (`FINANCEIRO.local`), `.207` (`DESKTOP-1OL40KI`), `.236` (`FATURAMENTO-CAROL.local` — renovará para `.10`–`.90`).

---

## 3. Melhoria Estrutural Recomendada (Médio Prazo)
- Substituir o switch principal não-gerenciável por um **Switch Gerenciável Gigabit L2** para isolar o tráfego das **27+ Câmeras IP + NVR** em uma **VLAN CFTV** dedicada, separada da rede do **ERP EMA / Wi-Fi / VoIP**.
