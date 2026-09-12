# Valhalla self-hosted

O projeto usa Valhalla como provedor opcional de rotas para caminhão. Sem o serviço disponível, o app continua usando a sequência salva e o cálculo offline.

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

O endpoint do projeto é:

```text
GET /v1/frota/embarques/{id}/rota-valhalla
```

Ele retorna distância, duração, geometria e pernas da rota usando `costing: truck`.

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
- Se Valhalla estiver indisponível, a API retorna `503` e o fluxo atual permanece funcional.
- A chave GraphHopper não é necessária para esta solução.

Antes de produção, configurar autenticação/rede privada para o serviço Valhalla e monitorar uso de CPU, memória e armazenamento dos tiles.
