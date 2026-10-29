# Feature Specification: Acompanhamento de rotas

**Feature Branch**: `003-route-monitoring`

**Created**: 2026-10-29

**Status**: Partially implemented — a tela existe e se atualiza sozinha; os dados do motorista dependem da spec 004 (app do motorista)

**Input**: User description: "Tela de rotas com mapa, motoristas e entregas, atualizando automaticamente com a posição do motorista, as entregas e seus status."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver o andamento das rotas do dia (Priority: P1)

O administrador abre Rotas, escolhe a data e vê o mapa em largura total com as paradas e a última
posição do motorista; abaixo, lado a lado, a lista de motoristas (progresso, situação, alerta de parado)
e as entregas da rota selecionada (situação, horário, motivo de não entrega, valor, link para navegar).
Indicadores no topo: entregas concluídas, valor entregue, próxima parada com previsão de chegada e km
restantes.

**Why this priority**: é a visão do dia para o administrador.

**Independent Test**: com uma rota em andamento e posições gravadas, a tela mostra a posição mais recente e os totais corretos.

**Acceptance Scenarios**:

1. **Given** uma rota em andamento, **When** o administrador abre a tela, **Then** vê a última posição do motorista e a próxima parada com previsão.
2. **Given** um motorista sem nova posição há mais tempo que o limite, **When** a tela atualiza, **Then** a rota aparece como "Parado há N min".

---

### User Story 2 - Atualização automática (Priority: P1)

A tela se atualiza sozinha enquanto está aberta e visível, sem recarregar a página, refletindo posições
e baixas enviadas pelo app do motorista.

**Acceptance Scenarios**:

1. **Given** a tela aberta, **When** o motorista dá baixa numa entrega, **Then** em até 1 minuto a entrega aparece com a nova situação.
2. **Given** a aba do navegador escondida, **When** passa o tempo, **Then** a tela não faz atualizações até voltar a ficar visível.

---

### User Story 3 - Mapa sem atrapalhar a rolagem (Priority: P2)

Rolar a página por cima do mapa não mexe no mapa; o zoom pela roda do mouse exige Ctrl (⌘ no Mac) e
um aviso explica isso.

### Edge Cases

- Nenhuma rota na data: mensagem orientando a criar rotas.
- Rota sem posição do motorista: indicadores mostram "Sem posição do motorista".
- O limite para "parado" precisa ser maior que o intervalo de envio automático de posição (spec 004).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: A tela MUST listar as rotas da data com progresso, situação e alerta de motorista parado.
- **FR-002**: A tela MUST mostrar no mapa as paradas por situação, o percurso e a última posição do motorista.
- **FR-003**: A tela MUST se atualizar automaticamente a cada 15 segundos enquanto visível (implementado).
- **FR-004**: O alerta de "parado" MUST considerar a última atividade do motorista (posição ou baixa) e um limite maior que o intervalo de envio automático.
- **FR-005**: Os dados exibidos MUST vir das posições e baixas gravadas pelo app do motorista (spec 004).

### Key Entities

- **Posição do motorista**: rota, motorista, latitude, longitude, precisão, velocidade, horário.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Uma baixa feita no app aparece na tela em menos de 1 minuto.
- **SC-002**: Nenhum alerta falso de "parado" para motoristas que enviam posição no intervalo combinado.

## Assumptions

- Hoje a tela só tem dados de posição/baixa gerados pelo seeder de demonstração; em produção ela depende da spec 004.
