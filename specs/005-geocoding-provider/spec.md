# Feature Specification: Busca de endereços com conta única da plataforma

**Feature Branch**: `005-geocoding-provider`

**Created**: 2026-10-29

**Status**: Implemented (2026-10-29) — cota mensal provisória por configuração até a spec 007

**Input**: User description: "Trocar o Google por um provedor que permita usar o resultado no mapa OpenStreetMap, com conta única da plataforma, custo incluído no plano, sem o cliente cadastrar chave."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Endereço novo localizado sem configuração (Priority: P1)

A empresa importa pedidos com clientes novos e os endereços são localizados automaticamente, sem que
ela crie conta ou cadastre chave em serviço de mapas.

**Why this priority**: remove uma barreira de cadastro e corrige o uso do resultado no mapa atual.

**Independent Test**: uma empresa sem nenhuma configuração importa um cliente novo e ele recebe coordenada.

**Acceptance Scenarios**:

1. **Given** um cliente novo, **When** a importação termina, **Then** o endereço é localizado pela conta da plataforma.
2. **Given** um resultado impreciso (só a rua, sem o número), **When** gravado, **Then** o cliente fica "Revisar posição".
3. **Given** o provedor indisponível, **When** a busca falha, **Then** ela é tentada de novo depois, sem expor credenciais na mensagem.

---

### User Story 2 - Cota de endereços por plano (Priority: P1)

Cada empresa tem uma cota mensal de buscas de endereços conforme o plano (spec 007). Ao atingir a cota,
novos endereços ficam "Aguardando cota" e o administrador pode informar coordenadas na planilha ou
arrastar o pino.

**Acceptance Scenarios**:

1. **Given** uma empresa no limite da cota, **When** importa um cliente novo, **Then** nenhuma chamada externa é feita e o cliente fica pendente com explicação.
2. **Given** um novo mês, **When** a cota renova, **Then** os pendentes voltam a ser processados.

---

### User Story 3 - Uso visível em Integrações (Priority: P2)

A tela de Integrações deixa de pedir chave e mostra o uso do mês da empresa (buscas feitas, cota do
plano, histórico dos últimos meses). O dono da plataforma vê o uso total e o custo estimado pelo
comando de uso.

### Edge Cases

- Endereço já localizado antes (por qualquer empresa): não gera chamada (spec 006).
- Provedor devolve resultado em outra cidade: resultado descartado se fora do município/UF informado.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST usar um provedor cujos termos permitam guardar o resultado e exibi-lo no mapa OpenStreetMap.
- **FR-002**: A credencial do provedor MUST ser única da plataforma, configurada no ambiente; a empresa não cadastra chave.
- **FR-003**: Antes de qualquer chamada, o sistema MUST consultar o cache de endereços (spec 006).
- **FR-004**: Cada chamada MUST ser registrada com empresa, cliente e resultado, e contar na cota do plano.
- **FR-005**: O sistema MUST respeitar a cota mensal da empresa e não chamar o provedor acima dela.
- **FR-006**: Resultados MUST indicar precisão (número da casa, rua, bairro); sem número, o cliente fica para revisão.
- **FR-007**: Resultados fora da cidade e UF informadas MUST ser descartados.
- **FR-008**: A tela de Integrações MUST mostrar o uso e a cota da empresa, sem campo de chave.
- **FR-009**: Deve ser possível trocar de provedor alterando a configuração, sem mudar telas.
- **FR-010**: A conta da plataforma MUST ter um teto diário de buscas somando todas as empresas (padrão 3.000, o plano gratuito do Geoapify). Ao atingir, o cliente fica "Pendente" com o aviso e a busca volta para a fila sozinha no dia seguinte (00:10); o mesmo reenfileiramento vale para quem ficou sem cota mensal.

### Key Entities

- **Consulta de endereço**: empresa, cliente, provedor, tipo de busca, resultado, se contou na cota, data.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Empresas novas localizam clientes sem nenhum passo de configuração.
- **SC-002**: Nenhuma chamada ao provedor para endereço já existente no cache.
- **SC-003**: Custo mensal do provedor conhecido e dentro do previsto nos planos.

## Assumptions

- Provedor inicial: Geoapify (plano gratuito de 3.000 consultas/dia com crédito "Powered by Geoapify", plano pago a partir de US$ 59/mês). Termos de armazenamento conferidos antes de produção.
- Dados de chave Google já cadastrados por empresas deixam de ser usados.
