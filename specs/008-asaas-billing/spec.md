# Feature Specification: Cobrança das assinaturas com Asaas

**Feature Branch**: `008-asaas-billing`

**Created**: 2026-10-29

**Status**: Draft — último passo, depende da spec 007

**Input**: User description: "Implementação da forma de pagamento com a Asaas, que será o último passo."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Assinar um plano (Priority: P1)

O administrador escolhe o plano em "Plano e uso", informa dados de cobrança (razão social, CPF/CNPJ,
e-mail) e a forma de pagamento (Pix, boleto ou cartão de crédito). A assinatura mensal é criada no
Asaas e o plano é liberado quando o pagamento é confirmado.

**Why this priority**: é a receita da plataforma.

**Independent Test**: em ambiente de testes do Asaas, uma empresa assina o plano "Até 10 veículos" com Pix; ao confirmar o pagamento, os limites mudam.

**Acceptance Scenarios**:

1. **Given** um plano escolhido, **When** o administrador confirma com Pix, **Then** recebe o QR Code/código copia e cola e o plano fica "Aguardando pagamento".
2. **Given** o pagamento confirmado pelo Asaas, **When** o aviso chega, **Then** a assinatura fica "Ativa" e os limites do plano valem.
3. **Given** cartão recusado, **When** confirma, **Then** vê a mensagem e pode tentar de novo.

---

### User Story 2 - Cobranças mensais e inadimplência (Priority: P1)

A cada mês o Asaas gera a cobrança. Se vencer sem pagamento, a empresa vê aviso; após o prazo de
tolerância, a empresa volta aos limites do plano inicial, sem perder dados.

**Acceptance Scenarios**:

1. **Given** uma cobrança vencida, **When** o administrador entra, **Then** vê aviso com o link de pagamento.
2. **Given** a tolerância esgotada, **When** o sistema processa, **Then** os limites voltam ao plano inicial e os dados continuam.

---

### User Story 3 - Trocar de plano e ver faturas (Priority: P2)

O administrador troca de plano (a nova cobrança passa a valer no próximo ciclo) e vê o histórico de
faturas com situação e link para o comprovante.

### Edge Cases

- Aviso de pagamento repetido pelo Asaas: processado uma única vez.
- Aviso com assinatura desconhecida ou sem autenticação válida: recusado e registrado.
- Plano "Mais de 10 veículos": cobrança criada pelo dono da plataforma com o valor negociado.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST criar o cliente e a assinatura mensal no Asaas a partir do plano escolhido.
- **FR-002**: O sistema MUST aceitar Pix, boleto e cartão de crédito.
- **FR-003**: O sistema MUST receber e validar os avisos de pagamento do Asaas e atualizar a assinatura de forma idempotente.
- **FR-004**: A liberação e o bloqueio de limites MUST seguir a situação da assinatura (spec 007).
- **FR-005**: O administrador MUST ver faturas, situação e links de pagamento.
- **FR-006**: Dados de cartão MUST ser tratados apenas pelo Asaas; o sistema não guarda número de cartão.
- **FR-007**: Credenciais do Asaas MUST ficar no ambiente da plataforma, com ambiente de testes separado.

### Key Entities

- **Assinatura**: empresa, plano, id no Asaas, forma de pagamento, situação, próximo vencimento.
- **Fatura**: assinatura, id no Asaas, valor, vencimento, situação, link.
- **Aviso de pagamento**: id do evento, tipo, conteúdo, processado em.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Assinar um plano leva menos de 3 minutos.
- **SC-002**: A liberação do plano acontece em até 1 minuto após a confirmação do pagamento.
- **SC-003**: Nenhum aviso de pagamento é processado duas vezes.

## Assumptions

- Tolerância após o vencimento: 7 dias (a confirmar com o dono da plataforma).
- Valores dos planos vêm da spec 007.
