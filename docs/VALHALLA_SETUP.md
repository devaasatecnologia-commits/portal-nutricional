# Valhalla self-hosted

O otimizador de embarques usa Valhalla para comparar tempos rodoviarios no
perfil de caminhao. O motorista continua usando a sequencia ja salva quando
estiver offline; a operacao de otimizar exige que a API alcance o Valhalla.

## Configuração da API

No `.env` da API:

```env
VALHALLA_URL=http://valhalla:8002
VALHALLA_TIMEOUT=12
TRUCK_HEIGHT=4.0
TRUCK_WIDTH=2.5
TRUCK_LENGTH=12.0
TRUCK_WEIGHT=3500
TRUCK_FIXED_SPEED=35
```

O endpoint da API usado pelo botao **Otimizar** e:

```text
POST /v1/frota/embarques/{id}/otimizar-rota
```

O backend consulta `/sources_to_targets` com `costing: truck`, calcula uma
sequencia de menor tempo estimado partindo do CD, visitando cada entrega uma
vez e retornando ao CD. A heuristica combina vizinho mais proximo e melhorias
2-opt; nao e uma garantia de otimo global e nao considera janelas de entrega,
pausas, transito ao vivo ou restricoes de capacidade nao configuradas.
Quando o servico de rota tambem responde, a tela substitui a linha esquematica
pela geometria viaria da sequencia otimizada e mostra as metricas do percurso.

Se o Valhalla estiver indisponivel ou nao encontrar caminho para todos os
trechos, a API responde com erro e preserva a ordem atual do embarque. Nao se
deve substituir esse comportamento por uma ordenacao em linha reta, pois ela
nao atende ao criterio de tempo rodoviario selecionado.

## Instalação recomendada

O Valhalla deve ser instalado em um servidor Linux ou Docker. O computador Windows local atual não possui Docker instalado.

Exemplo com a imagem oficial, em um servidor Linux com Docker:

```bash
docker run -d --name valhalla -p 8002:8002 \
  -e tile_urls=https://download.geofabrik.de/south-america/brazil-latest.osm.pbf \
  ghcr.io/valhalla/valhalla:latest
```

Para produção, prefira gerar os tiles da região necessária e persistir `/custom_files` em volume. O processo de geração dos tiles pode levar tempo e consumir bastante espaço.

## Integração

- O backend nunca expõe a URL interna ao motorista.
- O app usa os dados da rota já cacheados quando estiver offline.
- Se Valhalla estiver indisponível, a otimizacao retorna `503`; a ordem e as
  entregas atuais permanecem funcionais, mas nao se confirma uma otimizacao.
- A chave GraphHopper não é necessária para esta solução.

Antes de produção, configurar autenticação/rede privada para o serviço Valhalla e monitorar uso de CPU, memória e armazenamento dos tiles.
