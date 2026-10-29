# Feature Specification: App do motorista

**Feature Branch**: `004-driver-app`

**Created**: 2026-10-29

**Status**: Draft — não implementado

**Input**: User description: "O aplicativo vai pegar as informações do motorista via app, salvando no banco e mostrando na tela de rota, para que a cada 10 ou 15 minutos atualize de forma automática as informações na tela, de entrega, status e tudo mais. O motorista vê as entregas e marca se foi entregue ou não e o motivo; botão para atualizar a localização manualmente."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver as paradas do dia no celular (Priority: P1)

O motorista entra com o próprio usuário no celular e vê a rota do dia: paradas na ordem, endereço,
referência, valor, janela de horário e um botão para abrir a navegação (Google Maps ou Waze). Ele
inicia a rota com um botão.

**Why this priority**: é o que dá dados reais ao acompanhamento.

**Independent Test**: um motorista com rota atribuída hoje entra no celular, vê as paradas na ordem e inicia a rota; a rota passa a "Em andamento" na tela do administrador.

**Acceptance Scenarios**:

1. **Given** uma rota atribuída ao motorista hoje, **When** ele abre o app, **Then** vê as paradas na ordem calculada.
2. **Given** nenhuma rota hoje, **When** ele abre o app, **Then** vê a mensagem "Nenhuma rota para hoje".
3. **Given** a rota planejada, **When** toca em "Iniciar rota", **Then** a rota fica "Em andamento" com o horário de início.

---

### User Story 2 - Dar baixa em cada entrega (Priority: P1)

Em cada parada, o motorista marca "Entregue" ou "Não entregue". Para "Não entregue", escolhe um motivo
de uma lista (cliente ausente, estabelecimento fechado, recusou o pedido, endereço não encontrado,
outro com texto). A baixa grava horário e posição. Na primeira entrega bem-sucedida, a posição vira a
coordenada confirmada do cliente.

**Acceptance Scenarios**:

1. **Given** uma parada pendente, **When** o motorista marca "Entregue", **Then** a entrega fica "Entregue" com horário e posição, e a coordenada do cliente fica confirmada.
2. **Given** uma parada, **When** marca "Não entregue" sem motivo, **Then** o app pede o motivo.
3. **Given** todas as paradas baixadas, **When** toca em "Finalizar rota", **Then** a rota fica "Concluída" com o horário de término.

---

### User Story 3 - Localização automática e manual (Priority: P1)

Enquanto a rota está em andamento e o app aberto, a posição é enviada automaticamente a cada 10
minutos (configurável entre 10 e 15) e em toda baixa. Um botão "Atualizar localização" envia na hora e
mostra o horário do último envio.

**Acceptance Scenarios**:

1. **Given** rota em andamento e app aberto, **When** passam 10 minutos, **Then** uma nova posição é gravada e aparece no acompanhamento.
2. **Given** o motorista toca em "Atualizar localização", **Then** a posição é enviada e o horário do último envio é atualizado.
3. **Given** o motorista negou a permissão de localização, **Then** o app explica como liberar e as baixas continuam sem posição.

---

### User Story 4 - Sinal fraco (Priority: P2)

Sem internet, baixas e posições ficam guardadas no celular e são enviadas na ordem quando o sinal volta,
sem duplicar registros.

### Edge Cases

- Duas baixas na mesma parada (toque duplo ou reenvio): só a primeira vale.
- Motorista tenta baixar parada de rota de outro motorista: recusado.
- Posição com precisão ruim (> 200 m): gravada, mas não usada para confirmar o cliente.
- Navegador não mantém envio em segundo plano com a tela apagada: o envio automático vale com o app aberto (limitação da versão web).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O motorista MUST ver somente as rotas atribuídas a ele, com as paradas na ordem.
- **FR-002**: O app MUST permitir iniciar e finalizar a rota, gravando os horários.
- **FR-003**: O app MUST permitir baixa "Entregue" ou "Não entregue" com motivo obrigatório da lista, gravando horário e posição.
- **FR-004**: A primeira entrega bem-sucedida com posição precisa MUST confirmar a coordenada do cliente.
- **FR-005**: O app MUST enviar a posição automaticamente a cada 10 minutos (configurável 10–15) com a rota em andamento e o app aberto, e em toda baixa.
- **FR-006**: O app MUST ter o botão "Atualizar localização" e exibir o horário do último envio.
- **FR-007**: Baixas e posições sem conexão MUST ser guardadas e reenviadas sem duplicar.
- **FR-008**: A tela de Rotas (spec 003) MUST refletir as baixas e posições em menos de 1 minuto.
- **FR-009**: O app MUST funcionar em celular (390px), alvos de toque ≥ 44px, instalável na tela inicial.

### Key Entities

- **Baixa de entrega**: entrega, motorista, situação, motivo, observação, horário, posição.
- **Posição do motorista**: já existe; passa a ser alimentada pelo app.
- **Motivo de não entrega**: lista fixa com opção "Outro".

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O motorista dá baixa em uma entrega em até 3 toques.
- **SC-002**: 95% das baixas chegam ao acompanhamento em menos de 1 minuto quando há sinal.
- **SC-003**: Nenhuma baixa feita sem sinal é perdida.

## Assumptions

- Primeira versão: app web instalável (PWA) dentro do sistema, usando o login atual do motorista.
- Comprovante com foto ou assinatura fica para uma versão seguinte; nesta versão há campo opcional "quem recebeu".
- Rastreamento com a tela apagada exige app nativo; fica fora desta versão.
