# Feature Specification: Planos e limites por quantidade de veículos

**Feature Branch**: `007-plan-limits`

**Created**: 2026-10-29

**Status**: Implemented (2026-10-29) — valores de exemplo em `config/plans.php`; troca de plano sem pagamento até a spec 008; gestão de planos pelo dono da plataforma ainda por configuração

**Input**: User description: "Preços por quantidade de veículos: até 2, até 10 e acima de 10 sob consulta. Limitar as configurações por plano e mostrar na tela."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Saber o plano e o uso (Priority: P1)

O administrador abre "Plano e uso" e vê o plano atual e barras de uso: veículos, motoristas com acesso,
rotas do mês e endereços localizados no mês, cada um com o limite do plano.

**Why this priority**: o cliente precisa entender o que contratou antes de esbarrar num limite.

**Independent Test**: uma empresa no plano de até 2 veículos com 1 veículo vê "1 de 2".

**Acceptance Scenarios**:

1. **Given** o plano "Até 2 veículos" com 2 veículos, **When** abre Plano e uso, **Then** vê "2 de 2" em veículos.
2. **Given** 80% de qualquer limite, **When** navega no sistema, **Then** vê um aviso no topo com link para Plano e uso.

---

### User Story 2 - Bloqueio ao chegar no limite (Priority: P1)

Ao tentar ultrapassar um limite (cadastrar o 3º veículo no plano de até 2, por exemplo), a ação é
recusada com a mensagem "Seu plano permite N veículos" e um botão para mudar de plano.

**Acceptance Scenarios**:

1. **Given** o limite de veículos atingido, **When** tenta cadastrar outro, **Then** a ação é recusada com a mensagem e o botão.
2. **Given** um recurso fora do plano, **When** acessa a tela, **Then** vê explicação e o botão para mudar de plano.

---

### User Story 3 - Dono da plataforma gerencia planos (Priority: P2)

O dono da plataforma define os planos (nome, preço, limites) e muda o plano de uma empresa; "Mais de 10
veículos" tem limites definidos caso a caso.

### Edge Cases

- Empresa rebaixada de plano com mais veículos que o novo limite: nada é apagado; novos cadastros ficam bloqueados até ajustar.
- Limites zerados mensalmente (rotas, endereços) renovam no dia 1.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Cada empresa MUST estar vinculada a um plano com limites de veículos, motoristas com acesso, rotas por mês, endereços por mês e recursos habilitados.
- **FR-002**: Planos iniciais: "Até 2 veículos", "Até 10 veículos" e "Mais de 10 veículos" (sob consulta, limites por empresa).
- **FR-002a**: Plano "Parceiros" (não público, R$ 0): 20 veículos, 20 motoristas, rotas sem limite, 5.000 buscas de endereço por mês e exportação. Não aparece na home nem pode ser escolhido pela tela; é atribuído pelo dono da plataforma com `php artisan subscription:assign {email} parceiros`.
- **FR-003**: O sistema MUST bloquear ações que ultrapassem limites, com mensagem clara e caminho para mudar de plano.
- **FR-004**: A tela "Plano e uso" MUST mostrar plano, preço e uso de cada limite.
- **FR-005**: O sistema MUST avisar a partir de 80% de qualquer limite.
- **FR-006**: Rebaixar o plano MUST preservar os dados existentes.
- **FR-007**: A home MUST mostrar os mesmos planos e limites cadastrados.

### Key Entities

- **Plano**: nome, preço mensal (ou sob consulta), limites, recursos habilitados, ativo.
- **Assinatura da empresa**: empresa, plano, limites personalizados, situação, datas.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% das tentativas acima do limite são bloqueadas com mensagem.
- **SC-002**: O administrador encontra o uso do plano em até 2 cliques.

## Assumptions

- Novas empresas começam no plano "Até 2 veículos" até a definição de cobrança (spec 008).
- Preços e cotas exatas definidos pelo dono da plataforma; a home mostra placeholders até lá.
