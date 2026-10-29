# Feature Specification: Planejamento de entregas e roteirização

**Feature Branch**: `002-delivery-planning`

**Created**: 2026-10-29

**Status**: Implemented (documenta o comportamento atual; a busca de endereços muda nas specs 005 e 006)

**Input**: User description: "Importação de pedidos por planilha, localização dos clientes, mapa, criação de rotas por setor com ordem de paradas otimizada e reotimização."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Importar os pedidos do dia (Priority: P1)

O administrador escolhe a data e envia uma planilha (.xlsx ou .csv, modelo disponível para download)
com código e nome do cliente, endereço, número do pedido, valor, setor, janela de horário e,
opcionalmente, latitude e longitude. O sistema cria ou atualiza clientes e pedidos e informa as linhas
recusadas com o motivo.

**Why this priority**: sem pedidos não há rota.

**Independent Test**: importar uma planilha com 10 linhas, 2 inválidas; o sistema importa 8 e lista as 2 com o motivo.

**Acceptance Scenarios**:

1. **Given** uma planilha válida, **When** importada, **Then** os pedidos aparecem na data escolhida.
2. **Given** um número de pedido repetido na planilha, **When** importada, **Then** a linha é recusada com "Número do pedido repetido".
3. **Given** um cliente já cadastrado com o mesmo código, **When** importado de novo, **Then** os dados são atualizados sem duplicar o cliente.

---

### User Story 2 - Garantir a localização de cada cliente (Priority: P1)

Cada cliente precisa de coordenadas: vindas da planilha, da busca automática de endereços, do pino
arrastado no mapa pelo administrador ou do GPS do motorista na entrega (permanente). Clientes sem
localização ou com localização aproximada ficam destacados para revisão.

**Independent Test**: um cliente "Aproximado" é ajustado arrastando o pino e passa a "Ajustado manualmente".

**Acceptance Scenarios**:

1. **Given** um cliente com localização aproximada, **When** o administrador arrasta o pino, **Then** a nova posição é salva como permanente.
2. **Given** um endereço que não foi encontrado, **When** o administrador clica em "Tentar de novo", **Then** a busca é refeita.

---

### User Story 3 - Criar a rota com paradas ordenadas (Priority: P1)

O administrador seleciona um setor (ou pedidos na tabela), define nome, motorista e veículo, e o
sistema cria a rota com as paradas ordenadas pelo menor percurso a partir do centro de distribuição,
estimando km de cada trecho e o total.

**Acceptance Scenarios**:

1. **Given** pedidos localizados no setor Sul, **When** o administrador cria a rota, **Then** as paradas ficam numeradas na ordem calculada e os pedidos passam a "Roteirizado".
2. **Given** um pedido sem localização na seleção, **When** tenta criar a rota, **Then** o sistema recusa e informa quantos pedidos estão sem localização.
3. **Given** uma rota em andamento, **When** o administrador reotimiza, **Then** as entregas já feitas mantêm a posição e as restantes são reordenadas.

---

### User Story 4 - Corrigir o endereço de um cliente (Priority: P1)

Quando um endereço não é encontrado por erro de digitação, o administrador clica em "Editar endereço"
no pedido, corrige rua, número, bairro, cidade, UF, CEP ou referência e salva. Se o endereço mudou, a
localização antiga é descartada e a busca é refeita (base de endereços primeiro, provedor depois).

**Acceptance Scenarios**:

1. **Given** um cliente "Não localizado", **When** o administrador corrige a rua e salva, **Then** a localização é buscada de novo e o cliente sai de "Não localizado".
2. **Given** apenas a referência alterada, **When** salva, **Then** a localização continua a mesma e nenhuma busca é feita.
3. **Given** o pedido já está numa rota, **When** o endereço muda, **Then** o sistema avisa para reotimizar a rota depois que o endereço for localizado.

---

### User Story 5 - Excluir uma rota criada (Priority: P1)

O administrador exclui uma rota criada por engano. Os pedidos dela voltam para "Pendente" e podem
entrar em outra rota. Rotas com entregas já baixadas (entregue ou não entregue) não podem ser
excluídas, para não perder o histórico.

**Acceptance Scenarios**:

1. **Given** uma rota planejada, **When** o administrador confirma a exclusão, **Then** a rota some e os pedidos voltam a "Pendente".
2. **Given** uma rota com entregas baixadas, **When** tenta excluir, **Then** o sistema recusa e explica o motivo.
3. **Given** uma rota de outra empresa, **When** alguém tenta excluir, **Then** a ação é negada.

---

### User Story 6 - Encontrar pedidos na tabela (Priority: P2)

A tabela de pedidos do dia é paginada (15, 30, 50 ou 100), com busca por número do pedido, cliente,
código ou endereço, filtros por setor e situação, ordenação por coluna e seleção da página inteira.

### Edge Cases

- Planilha sem colunas obrigatórias: nada é importado e o motivo é exibido.
- Coordenadas fora do intervalo válido: ignoradas.
- Janelas de horário são importadas, mas ainda não são consideradas na ordem das paradas.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST importar pedidos de .xlsx/.csv no formato do modelo e relatar linhas recusadas com motivo.
- **FR-002**: O sistema MUST manter uma coordenada por cliente com origem identificada (planilha, busca automática, ajuste manual, GPS confirmado).
- **FR-003**: Coordenadas confirmadas (GPS ou ajuste manual) MUST ter prioridade sobre qualquer outra origem.
- **FR-004**: O sistema MUST criar rotas com paradas ordenadas pelo menor percurso a partir do centro de distribuição e estimar a distância.
- **FR-005**: O sistema MUST permitir reotimizar a rota mantendo as paradas concluídas.
- **FR-006**: A tabela de pedidos MUST ter paginação, busca, filtros, ordenação e seleção por página.
- **FR-007**: O mapa MUST mostrar os pedidos do dia com cores por situação e permitir arrastar o pino.
- **FR-008**: O administrador MUST poder editar o endereço do cliente; endereço alterado descarta a localização e refaz a busca.
- **FR-009**: O administrador MUST poder excluir rotas sem entregas baixadas; os pedidos voltam a "Pendente".
- **FR-010**: Edições de endereço e exclusões de rota MUST ficar no log de auditoria (spec 009).

### Key Entities

- **Cliente**: código externo, nome, endereço, coordenada e sua origem.
- **Importação**: arquivo, data, linhas totais, importadas, com erro e erros.
- **Pedido (entrega)**: número, cliente, data, valor, setor, janela, situação.
- **Rota**: nome, data, motorista, veículo, origem, distância total, situação.
- **Parada**: rota, pedido, sequência, distância desde a anterior, chegada.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Planejar as rotas de um dia com 100 pedidos leva menos de 10 minutos.
- **SC-002**: A rota de 40 paradas é calculada em menos de 2 segundos.
- **SC-003**: Nenhum pedido sem localização entra em uma rota.

## Assumptions

- Distância estimada em linha reta multiplicada por um fator de estrada.
- Centro de distribuição configurado por empresa ou no ambiente.
