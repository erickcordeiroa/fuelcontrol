# Feature Specification: App do motorista

**Feature Branch**: `004-driver-app`

**Created**: 2026-10-29

**Status**: API pronta (2026-10-29, Sanctum, `/api/v1/driver`); o app ainda não foi construído. Protótipo aprovado em 2026-10-29: [`prototype.html`](prototype.html) (abrir no navegador; também publicado como artefato em https://claude.ai/artifact/8EtEWjJnUX1YM29a8zQjie). Contrato da API: [`api-readiness.md`](api-readiness.md).

**Input**: User description: "O aplicativo vai pegar as informações do motorista via app, salvando no banco e mostrando na tela de rota, para que a cada 10 ou 15 minutos atualize de forma automática as informações na tela, de entrega, status e tudo mais. O motorista vê as entregas e marca se foi entregue ou não e o motivo." Ajustes do protótipo: "login pelo celular do motorista, listagem em pilha da rota, detalhes da entrega, mudança de status conforme temos no sistema, simples e objetivo. A localização é interna, não aparece para o motorista. Não é linha do tempo: finalizada ou não entregue vai para o final da fila. O motorista não pode mudar a ordem da entrega."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Entrar pelo celular (Priority: P1)

O motorista entra com o número de celular cadastrado no motorista (com DDD) e a senha. Esqueceu a
senha: o administrador gera uma nova.

**Why this priority**: sem entrar não há rota.

**Independent Test**: um motorista com celular (13) 99722-1580 vinculado a um usuário entra com esse número e a senha e vê a rota do dia.

**Acceptance Scenarios**:

1. **Given** um motorista com celular cadastrado e acesso vinculado, **When** entra com celular e senha corretos, **Then** vê a rota do dia.
2. **Given** celular sem DDD, **When** tenta entrar, **Then** vê "Digite o celular com DDD".
3. **Given** um administrador, **When** tenta entrar pelo app do motorista, **Then** o acesso é recusado (o app é só do motorista).

---

### User Story 2 - Ver a rota do dia em fila (Priority: P1)

O motorista vê a rota do dia: nome, data, situação da rota, número de paradas, km, veículo, valor
total e o progresso (baixadas, entregues, não entregues). Abaixo, as paradas em fila: primeiro as
pendentes na ordem calculada, com a próxima destacada; as já baixadas descem para o fim da fila, no
grupo "Baixadas". Não é uma linha do tempo.

**Acceptance Scenarios**:

1. **Given** uma rota atribuída ao motorista hoje, **When** ele abre o app, **Then** vê as paradas pendentes na ordem calculada e a próxima destacada.
2. **Given** uma parada baixada, **When** a fila é exibida, **Then** ela aparece no grupo "Baixadas", no fim, com o horário e o motivo quando houver.
3. **Given** nenhuma rota hoje, **When** abre o app, **Then** vê "Nenhuma rota para hoje".
4. **Given** a rota planejada, **When** toca em "Iniciar rota", **Then** a rota fica "Em andamento" e as paradas passam a "Em rota".

---

### User Story 3 - Detalhe da entrega e baixa na ordem (Priority: P1)

O motorista abre uma parada e vê pedido, cliente, endereço, referência, valor, janela, distância do
trecho e os botões para abrir no Google Maps ou no Waze. Só a próxima parada da fila aceita baixa:
"Entregue" ou "Não entregue" com motivo obrigatório (cliente ausente, estabelecimento fechado, recusou
o pedido, endereço não encontrado, outro motivo com texto). As demais mostram "Siga a ordem da rota".
A baixa não pode ser desfeita pelo motorista; correção é feita pelo administrador.

**Acceptance Scenarios**:

1. **Given** a próxima parada, **When** o motorista marca "Entregue", **Then** a entrega fica "Entregue" com horário e posição, a parada desce para "Baixadas" e a seguinte vira a próxima.
2. **Given** a próxima parada, **When** marca "Não entregue" sem motivo, **Then** o app não confirma até escolher o motivo.
3. **Given** uma parada que não é a próxima, **When** o motorista a abre, **Then** vê os dados e o aviso "Siga a ordem da rota", sem botões de baixa.
4. **Given** uma chamada direta tentando baixar fora da ordem, **When** chega ao sistema, **Then** é recusada.
5. **Given** todas as paradas baixadas, **When** toca em "Finalizar rota", **Then** a rota fica "Concluída" com o horário de término.

---

### User Story 4 - Localização interna (Priority: P1)

Com a rota em andamento e o app aberto, a posição é enviada sozinha a cada 10 minutos (configurável
entre 10 e 15), ao iniciar a rota e em toda baixa. O motorista não vê nada sobre isso: sem faixa de
"localização enviada", sem botão. A posição aparece só para a empresa, na tela de Rotas (spec 003).
Na primeira entrega bem-sucedida com posição precisa, a posição vira a coordenada confirmada do cliente.

**Acceptance Scenarios**:

1. **Given** rota em andamento e app aberto, **When** passam 10 minutos, **Then** uma nova posição é gravada e aparece no acompanhamento.
2. **Given** o motorista negou a permissão de localização, **Then** o app pede a permissão uma vez com explicação curta e as baixas continuam sem posição.

---

### User Story 5 - Sinal fraco (Priority: P2)

Sem internet, baixas e posições ficam guardadas no celular e são enviadas na ordem quando o sinal volta,
sem duplicar registros.

### Edge Cases

- Duas baixas na mesma parada (toque duplo ou reenvio): só a primeira vale.
- Motorista tenta baixar parada de rota de outro motorista: recusado.
- Motorista tenta baixar fora da ordem: recusado.
- Posição com precisão ruim (> 200 m): gravada, mas não usada para confirmar o cliente.
- Administrador reotimiza a rota em andamento: o app recebe a nova ordem na próxima atualização.
- Administrador exclui a rota: o app mostra "Nenhuma rota para hoje".
- Navegador não mantém envio em segundo plano com a tela apagada: o envio automático vale com o app aberto (limitação da versão web).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: O motorista MUST entrar com celular (DDD + número) e senha; só usuários com papel de motorista e motorista vinculado entram.
- **FR-002**: O motorista MUST ver somente as rotas atribuídas a ele no dia.
- **FR-003**: A fila MUST mostrar as pendentes na ordem calculada e as baixadas no fim; a ordem não pode ser alterada pelo motorista.
- **FR-004**: Só a próxima parada pendente MUST aceitar baixa, no app e no sistema.
- **FR-005**: O app MUST permitir iniciar e finalizar a rota, gravando os horários; finalizar só com todas as paradas baixadas.
- **FR-006**: A baixa MUST ser "Entregue" ou "Não entregue" com motivo obrigatório da lista ("Outro motivo" exige texto), gravando horário e posição.
- **FR-007**: O motorista MUST NOT desfazer uma baixa.
- **FR-008**: A primeira entrega bem-sucedida com posição precisa MUST confirmar a coordenada do cliente.
- **FR-009**: O app MUST enviar a posição automaticamente a cada 10 minutos (configurável 10–15) com a rota em andamento e o app aberto, ao iniciar a rota e em toda baixa, sem exibir isso ao motorista.
- **FR-010**: Baixas e posições sem conexão MUST ser guardadas e reenviadas sem duplicar.
- **FR-011**: A tela de Rotas (spec 003) MUST refletir as baixas e posições em menos de 1 minuto.
- **FR-012**: O app MUST funcionar em celular (390px), alvos de toque ≥ 44px, instalável na tela inicial.
- **FR-013**: Baixas, início e fim de rota MUST entrar no log de auditoria (spec 009) com o motorista como autor.

### Key Entities

- **Baixa de entrega**: entrega, motorista, situação, motivo, observação, horário, posição, chave de envio (para não duplicar).
- **Posição do motorista**: já existe; passa a ser alimentada pelo app.
- **Motivo de não entrega**: lista fixa com opção "Outro motivo".

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O motorista dá baixa em uma entrega em até 3 toques.
- **SC-002**: 95% das baixas chegam ao acompanhamento em menos de 1 minuto quando há sinal.
- **SC-003**: Nenhuma baixa feita sem sinal é perdida.
- **SC-004**: 100% das tentativas de baixa fora da ordem são recusadas.

## Assumptions

- Decidido em 2026-10-29: app separado consumindo a API com Sanctum; a primeira versão pode ser PWA e depois app nativo, sem mudar a API.
- O celular de login é o campo "telefone" do cadastro de motorista, que passa a ser único por empresa.
- Comprovante com foto ou assinatura fica para uma versão seguinte.
- Rastreamento com a tela apagada exige app nativo; fica fora desta versão.
- Telefone do cliente não existe no cadastro hoje; o protótipo mostra um campo de telefone que depende de incluir essa coluna na importação.
