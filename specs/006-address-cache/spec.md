# Feature Specification: Base de endereços já localizados

**Feature Branch**: `006-address-cache`

**Created**: 2026-10-29

**Status**: Implemented (2026-10-29)

**Input**: User description: "Tabela de backup dos endereços, para não precisar ficar recalculando com a API: se o endereço já está cadastrado e coordenado, usa o que temos no banco; se não tem, registro novo, geolocaliza."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reaproveitar endereço já localizado (Priority: P1)

Quando um cliente (de qualquer empresa) tem um endereço que o sistema já localizou antes, a coordenada
é reaproveitada na hora, sem chamar o provedor.

**Why this priority**: corta o custo de geolocalização e acelera a importação.

**Independent Test**: dois clientes de empresas diferentes com o mesmo endereço geram uma única consulta ao provedor.

**Acceptance Scenarios**:

1. **Given** "Rua Shitiro Maeji, 410, Centro, Registro/SP" já localizado, **When** outro cliente com o mesmo endereço é importado, **Then** recebe a coordenada da base e nenhuma consulta externa é feita.
2. **Given** o mesmo endereço escrito com diferenças de maiúsculas, acentos, espaços ou "R." x "Rua", **When** importado, **Then** é reconhecido como o mesmo endereço.
3. **Given** um endereço inédito, **When** importado, **Then** é localizado pelo provedor e entra na base.

---

### User Story 2 - A base melhora com o uso (Priority: P2)

Quando um motorista confirma a posição na entrega ou o administrador arrasta o pino, a posição
confirmada também atualiza a base da própria empresa, e futuros clientes dela com o mesmo endereço já
nascem com a posição boa. Outras empresas não recebem essa posição.

**Acceptance Scenarios**:

1. **Given** um endereço com posição aproximada na base, **When** o motorista confirma a entrega com GPS preciso, **Then** a base passa a ter a posição confirmada.

### Edge Cases

- Mesmo endereço em cidades diferentes: são endereços distintos (cidade e UF fazem parte da chave).
- Endereço sem número: tratado como endereço de rua, nunca reaproveitado para um endereço com número.
- Endereço que o provedor não encontrou: registrado como "não encontrado" para não repetir a consulta por um período.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O sistema MUST manter uma base de endereços normalizados com coordenada, precisão, origem e data.
- **FR-002**: A normalização MUST ignorar maiúsculas, acentos, pontuação, espaços extras e abreviações comuns de logradouro (R., Av., Al., Tv., Rod., Est., Pç.). A chave é logradouro + número + cidade + UF; bairro e CEP são guardados, mas ficam fora da chave porque variam entre planilhas para o mesmo lugar.
- **FR-003**: Antes de chamar o provedor, o sistema MUST procurar o endereço na base e reaproveitar a coordenada encontrada.
- **FR-004**: Coordenadas confirmadas (GPS preciso ou ajuste manual) MUST valer só para a empresa que confirmou e prevalecer, para ela, sobre o resultado do provedor. Uma empresa nunca altera a posição usada pelos clientes de outra; resultados do provedor são compartilhados.
- **FR-005**: Endereços não encontrados MUST ficar registrados para não gerar nova consulta por 30 dias.
- **FR-006**: A base MUST guardar só dados de endereço e coordenada, nunca nome do cliente ou dados da empresa.

### Key Entities

- **Endereço localizado**: chave normalizada, logradouro, número, bairro, cidade, UF, CEP, latitude, longitude, precisão, origem (provedor, GPS, manual), última confirmação.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Reimportar a mesma planilha não gera nenhuma consulta externa.
- **SC-002**: Após 3 meses de uso, mais de 70% dos clientes novos são resolvidos pela base.

## Assumptions

- Só os resultados do provedor são compartilhados entre empresas; posições confirmadas ficam na empresa (correção de segurança: evita que um ajuste errado ou malicioso afete outras empresas).
- Endereços já localizados hoje nos clientes são carregados na base na implantação.
