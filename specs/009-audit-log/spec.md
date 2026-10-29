# Feature Specification: Log de auditoria

**Feature Branch**: `009-audit-log`

**Created**: 2026-10-29

**Status**: Implemented (2026-10-29) — registro automático; tela de consulta fica para depois

**Input**: User description: "Todas essas alterações precisam ter um log para auditar caso precise, e a tabela precisa ser simples, só empresa, e um metadata em json com todas as mudanças, isso também nos lançamentos e tudo mais, pois se um profissional fizer coisa errada, dá problema."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Saber quem mudou o quê (Priority: P1)

Quando um registro da empresa é criado, alterado ou excluído (lançamentos do Diário de Bordo,
abastecimentos, despesas, trocas de óleo, veículos, motoristas, postos, clientes, pedidos, rotas,
importações), o sistema guarda um registro com o que mudou, quem fez, quando e de onde.

**Why this priority**: permite descobrir e corrigir um erro de um profissional.

**Independent Test**: um administrador altera o km final de uma viagem; o log da empresa mostra o
valor antigo e o novo, o usuário e o horário.

**Acceptance Scenarios**:

1. **Given** uma viagem com km final 50.420, **When** um usuário muda para 50.520, **Then** o log guarda `{km_end: {old: 50420, new: 50520}}`, o usuário e o horário.
2. **Given** um pedido roteirizado, **When** a rota é excluída, **Then** o log guarda a exclusão da rota com os dados que ela tinha e a volta dos pedidos para "Pendente".
3. **Given** uma alteração feita pelo sistema (busca de endereço em segundo plano), **Then** o log registra a origem como sistema, sem usuário.

---

### User Story 2 - Isolamento por empresa (Priority: P1)

Cada registro do log pertence à empresa dona do dado. Uma empresa nunca vê o log de outra.

### Edge Cases

- Salvar sem mudar nada não gera registro.
- Só a data de atualização mudou: não gera registro.
- Dados de alto volume gerados pelo sistema (posições do motorista, consultas de endereço, base de endereços) não entram no log.
- Excluir a empresa não apaga o log (ele fica sem empresa vinculada).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O log MUST ter só: empresa, metadados em JSON e data de criação.
- **FR-002**: Os metadados MUST conter o evento (criado, alterado, excluído), o tipo e o id do registro, as mudanças (valor antigo e novo por campo), o usuário (id, nome e papel) ou "sistema", a origem (tela, fila, comando) e o IP quando houver.
- **FR-003**: O registro MUST ser automático para todos os modelos de dado da empresa listados na User Story 1, sem depender de cada tela.
- **FR-004**: O log MUST ser somente de inclusão: nenhuma tela ou ação do sistema altera ou apaga registros do log.
- **FR-005**: Consultas ao log MUST ser filtradas pela empresa do usuário.

### Key Entities

- **Registro de auditoria**: empresa, metadados (JSON), criado em.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das criações, alterações e exclusões feitas pelas telas geram um registro.
- **SC-002**: Para qualquer alteração, é possível saber o valor anterior, o novo, quem fez e quando.

## Assumptions

- Alterações em massa feitas direto no banco (fora dos modelos) não são capturadas; o código do produto usa os modelos.
- O log de alterações de viagem que já existe (TripChangeLog, exibido em Relatórios) continua funcionando; o novo log é geral.
- A tela para consultar o log será especificada à parte.
