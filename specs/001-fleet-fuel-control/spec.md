# Feature Specification: Controle de frota e combustível

**Feature Branch**: `001-fleet-fuel-control`

**Created**: 2026-10-29

**Status**: Implemented (documenta o comportamento atual)

**Input**: User description: "Controle de frota e combustível (funcionalidade existente, documentar o comportamento atual): Diário de Bordo, relatórios de consumo e custo por km com exportação PDF/Excel e relatório mensal, trocas de óleo por veículo com alerta, cadastros de veículos, motoristas (com acesso vinculado) e postos com preço por litro."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Lançar uma viagem no Diário de Bordo (Priority: P1)

O motorista (ou o administrador) registra uma viagem: data, veículo, motorista, quilometragem inicial e
final, abastecimento (posto, combustível, litros, preço por litro) e despesas (pedágio, ajudante,
alimentação). O sistema calcula km rodados, custo de combustível, total de despesas e custo por km.

**Why this priority**: é a origem de todos os números de custo do produto.

**Independent Test**: lançar uma viagem e conferir que km, custo e custo por km aparecem no resumo e no relatório.

**Acceptance Scenarios**:

1. **Given** um veículo e um posto cadastrados, **When** o motorista informa km inicial 50.000, km final 50.420, 120 litros a R$ 6,29, **Then** o resumo mostra 420 km, R$ 754,80 de combustível e o custo por km correspondente.
2. **Given** um posto com preço cadastrado, **When** o motorista escolhe o posto e o combustível, **Then** o preço por litro é preenchido automaticamente e pode ser ajustado.
3. **Given** km final menor que o inicial, **When** o lançamento é salvo, **Then** o sistema recusa e explica o erro.

---

### User Story 2 - Conferir consumo e custo nos relatórios (Priority: P1)

O administrador filtra por período, veículos e motorista e vê custo de combustível, outras despesas,
total operacional, litros, consumo médio (km/L), preço médio por km, km rodados, gráficos, comparativo
por veículo e o detalhamento das viagens, com exportação em PDF e Excel.

**Why this priority**: é onde a empresa decide sobre a frota.

**Independent Test**: com viagens lançadas em dois veículos, filtrar o período e conferir que os totais batem com a soma das viagens.

**Acceptance Scenarios**:

1. **Given** viagens no período, **When** o administrador filtra, **Then** todos os totais são a soma dos lançamentos filtrados.
2. **Given** nenhum lançamento no período, **When** o relatório abre, **Then** os gráficos e tabelas mostram estado vazio com mensagem.
3. **Given** um motorista logado, **When** ele abre relatórios, **Then** vê apenas os próprios lançamentos.

---

### User Story 3 - Controlar trocas de óleo (Priority: P2)

O administrador registra trocas de óleo por veículo (km na troca, marca, intervalo em km, observações) e
recebe alerta no topo do sistema quando a próxima troca está próxima ou vencida.

**Why this priority**: evita manutenção atrasada, mas não bloqueia a operação diária.

**Independent Test**: registrar uma troca com intervalo de 10.000 km e lançar viagens até passar do limite; o alerta aparece.

**Acceptance Scenarios**:

1. **Given** uma troca registrada a 40.000 km com intervalo de 10.000 km, **When** a quilometragem do veículo chega perto de 50.000 km, **Then** o sistema mostra o alerta do veículo.

---

### User Story 4 - Manter os cadastros (Priority: P2)

O administrador cadastra veículos (placa, modelo, capacidade, combustível, média ideal), motoristas
(com usuário de acesso opcional) e postos (com combustíveis e preço por litro).

**Independent Test**: cadastrar, editar e excluir cada tipo; registros de outra empresa nunca aparecem.

**Acceptance Scenarios**:

1. **Given** duas empresas, **When** o administrador A lista veículos, **Then** não vê os veículos da empresa B.
2. **Given** um motorista com usuário vinculado, **When** ele entra no sistema, **Then** vê apenas Diário de Bordo, Relatórios e Perfil.

### Edge Cases

- Viagem sem abastecimento: custo de combustível zero, despesas ainda contam.
- Posto excluído depois do lançamento: o nome do posto continua registrado na viagem.
- Veículo sem média ideal: consumo exibido sem comparação.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST registrar viagens com data, veículo, motorista, km inicial/final, abastecimento e despesas por tipo.
- **FR-002**: O sistema MUST calcular km rodados, custo de combustível, despesas, total operacional e custo por km a partir dos lançamentos.
- **FR-003**: O sistema MUST preencher o preço por litro a partir do posto e combustível escolhidos, permitindo ajuste.
- **FR-004**: Relatórios MUST filtrar por período, veículos e motorista e exportar em PDF e Excel.
- **FR-005**: O sistema MUST registrar trocas de óleo por veículo e alertar a próxima troca pela quilometragem.
- **FR-006**: Administradores MUST gerenciar veículos, motoristas e postos; motoristas MUST acessar só o Diário de Bordo, Relatórios e Perfil.
- **FR-007**: Todos os dados MUST ficar isolados por empresa.

### Key Entities

- **Viagem**: data, veículo, motorista, km inicial/final/total, status.
- **Abastecimento**: viagem, posto, combustível, litros, preço por litro.
- **Despesa**: viagem, tipo (pedágio, ajudante, alimentação), valor.
- **Troca de óleo**: veículo, km na troca, marca, intervalo, observações.
- **Veículo, Motorista, Posto (e combustíveis do posto)**.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Um lançamento completo de viagem leva menos de 2 minutos no celular.
- **SC-002**: 100% dos totais dos relatórios coincidem com a soma dos lançamentos do filtro.
- **SC-003**: Nenhum registro de outra empresa aparece em qualquer listagem ou relatório.

## Assumptions

- Moeda em R$ e números no formato brasileiro.
- O relatório mensal de consumo faz parte de Relatórios.
