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

O computador Windows usado na revisao nao possui Docker e nao tem
`VALHALLA_URL` configurada. A integracao PHP esta implementada, mas ainda nao
foi validada contra uma instancia Valhalla real; portanto, otimizacao rodoviaria
nao esta pronta para operacao ate existir um endpoint acessivel e o teste de
homologacao passar.

A cobertura aprovada para planejamento e **Sul com corredores frequentes ate
SP/MS**. O recorte precisa incluir as areas de origem/destino e margens
suficientes nos limites para que uma rota nao seja cortada. O extrato oficial
Geofabrik consultado em 2026-10-07 oferecia o Brasil inteiro em PBF com cerca de
1,9 GB; esse arquivo nacional nao equivale ao recorte desejado, e o tamanho dos
tiles Valhalla gerados sera diferente e maior que o arquivo de entrada.
Fonte consultada: [Geofabrik — extrato do Brasil](https://download.geofabrik.de/south-america/brazil.html).

Nao iniciar a carga do Brasil inteiro por conveniencia. Antes de baixar ou
gerar tiles, definir o servidor Linux/Docker de destino, memoria, CPU e espaco
disponivel, preparar e validar o poligono regional, estimar o espaco final e
definir atualizacao dos dados OSM. Persistir os tiles em volume duravel; nao os
regenerar a cada reinicio do container.

Na implantacao, publicar o Valhalla apenas em rede privada, limitar quem pode
consultar o servico e configurar `VALHALLA_URL` para um endpoint acessivel pela
API. Nao expor a porta de roteamento diretamente a internet. Configurar timeout
e limites de recurso do servidor conforme testes com rotas representativas.

## Integração

- O backend nunca expõe a URL interna ao motorista.
- O app usa os dados da rota já cacheados quando estiver offline.
- Se Valhalla estiver indisponível, a otimizacao retorna `503`; a ordem e as
  entregas atuais permanecem funcionais, mas nao se confirma uma otimizacao.
- A chave GraphHopper não é necessária para esta solução.

Antes de producao, validar rotas com os tipos de caminhao reais e restricoes
disponiveis, confirmar cobertura nos corredores usados, monitorar uso de CPU,
memoria, armazenamento e latencia e documentar como atualizar/voltar os tiles.
Nenhum download OSM ou deploy foi executado nesta revisao.
