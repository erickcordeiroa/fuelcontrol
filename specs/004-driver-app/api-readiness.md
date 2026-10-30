# App do motorista: API

Atualizado em 2026-10-29. A API do app (spec 004) está pronta no backend; o app em si ainda não foi construído.
Protótipo de referência: [`prototype.html`](prototype.html).

## Decisão

App separado consumindo API, com **Laravel Sanctum** (token por aparelho). Serve a PWA agora e um app nativo depois.

## Autenticação

- Login pelo **celular do cadastro de motorista** (`drivers.phone`, só dígitos, com DDD, único por empresa) e a
  **senha do app**, definida pelo administrador no próprio cadastro de motorista. O acesso (usuário sem e-mail, papel
  motorista) é criado sozinho; trocar a senha encerra as sessões abertas no celular; excluir o motorista remove o acesso.
- O motorista **não tem login web**: o login do site recusa usuários motoristas.
- Token com a habilidade `driver`. Toda rota protegida exige `auth:sanctum` + middleware `driver`
  (papel motorista, token com `driver` e motorista vinculado).
- Login limitado a 5 tentativas por minuto por celular e IP.
- Enviar o token no cabeçalho `Authorization: Bearer <token>`.

## Endpoints (`/api/v1/driver`)

| Método | Caminho | Corpo | Responde |
|---|---|---|---|
| `POST` | `/login` | `phone`, `password`, `device_name?` | `{ token, driver: { id, name } }` |
| `POST` | `/logout` | — | 204, revoga o token |
| `GET` | `/route/today` | — | Rota do dia ou `{ data: null, message: "Nenhuma rota para hoje." }` |
| `POST` | `/routes/{id}/start` | `position?` | Rota; paradas passam a "Em rota" |
| `POST` | `/stops/{id}/deliver` | `client_uuid`, `position?`, `completed_at?` | Rota inteira (fila redesenhada) |
| `POST` | `/stops/{id}/fail` | `client_uuid`, `reason`, `note?`, `position?`, `completed_at?` | Rota inteira |
| `POST` | `/routes/{id}/finish` | — | Rota "Concluída" |
| `POST` | `/routes/{id}/locations` | `positions[]` (`latitude`, `longitude`, `accuracy_m?`, `speed_kmh?`, `recorded_at`) | 201 `{ stored }` |
| `GET` | `/stops/{id}/directions` | `latitude`, `longitude` (posição atual) | Novo trajeto até a parada: `distance_km`, `duration_min`, `path`, `steps` |

`position` = `{ latitude, longitude, accuracy_m? }`. `reason` = `customer_absent`, `closed`, `refused`,
`address_not_found` ou `other` (`other` exige `note`, 3 a 140 caracteres).

### GPS no app

Cada parada traz `navigation.path` (trajeto pelas ruas desde a parada anterior, **Encoded Polyline precisão 5**, o
mesmo formato do Google; decodifique com `@mapbox/polyline` ou similar) e `navigation.steps` (instruções em português:
`instruction`, `type`, `distance_m`, `time_s`, `from_index`, `to_index`, índices dentro do `path`). A rota traz
`origin`, `return_path`, `total_duration_min` e `road_routing` (`false` quando o trajeto foi estimado em linha reta).

O app mostra o trajeto da próxima parada e a instrução do passo atual. Se o motorista sair do caminho (mais de ~50 m
do `path`), pede `GET /stops/{id}/directions` com a posição atual: 1 crédito por chamada, até 6 por minuto por
motorista; com o teto diário esgotado responde 422 e o app segue o trajeto já traçado ou abre o Google Maps/Waze.

### Rota do dia

Pendentes primeiro, na ordem calculada; baixadas no fim, na ordem em que foram baixadas. Campos principais:
`status`, `progress { total, delivered, failed }`, `next_stop_id`, `can_finish`, `vehicle`, `total_distance_km`,
`total_amount` e `stops[]` com `is_next`, `status`, `failure_reason`, `order { number, amount, window_start, window_end }`,
`customer { name, endereço, reference, phone, latitude, longitude }` e `navigation { google_maps, waze }`.

## Regras garantidas pelo servidor

- Motorista só vê e mexe na rota atribuída a ele (403 nas demais).
- Baixa só com a rota em andamento e **só na próxima parada**; fora da ordem responde 422 com
  "Siga a ordem da rota: dê baixa na parada N antes desta."
- Sem desfazer: parada baixada não aceita nova baixa.
- `client_uuid` torna a baixa idempotente: o mesmo envio repetido (sem sinal) não muda nada.
- `completed_at` aceita o horário do aparelho para baixas guardadas sem sinal (não pode ser no futuro).
- Primeira entrega com precisão ≤ 200 m confirma a coordenada do cliente e alimenta a base de endereços.
- Toda baixa grava uma posição em `driver_locations`; posições repetidas (mesmo `recorded_at`) são ignoradas.
- Finalizar só com todas as paradas baixadas.
- Baixas, início e fim entram no log de auditoria com o motorista como autor.
- A tela de Rotas do administrador já atualiza a cada 15 s e mostra as baixas e as posições.

## Banco

- `deliveries`: `failure_reason_code`, `failure_note`, `completed_by_driver_id`, `completed_latitude`,
  `completed_longitude`, `completed_accuracy_m`, `client_uuid` (único). `failure_reason` continua com o texto
  exibido no painel.
- `drivers.phone`: normalizado para dígitos e único por empresa (cadastro valida DDD e repetição).
- `customers.phone`: opcional, coluna `telefone` na planilha de importação.
- `personal_access_tokens`: tokens do Sanctum.

## Fora desta entrega

- O app (PWA ou nativo) consumindo a API.
- Envio de posição com a tela apagada (exige app nativo).
