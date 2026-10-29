<!--
Sync Impact Report
- Version change: (template) → 1.0.0
- Principles defined: I. Números reconciliáveis; II. Multiempresa isolada; III. Custo operacional
  controlado; IV. Testes de feature obrigatórios; V. Verdade no produto; VI. Design governado por
  DESIGN.md e PRODUCT.md; VII. Brasil primeiro; VIII. Motorista e administrador com o mesmo peso
- Added sections: Restrições técnicas; Fluxo de desenvolvimento; Governance
- Removed sections: none
- Templates: none modified (read at runtime)
- Deferred TODOs: none
-->

# Fuelly Constitution

## Core Principles

### I. Números reconciliáveis

Todo valor exibido (custos, consumo, km, quantidades, valores de entrega, uso de serviços) MUST ser
calculado a partir de registros persistidos no banco. Nenhum número da interface pode ser digitado
à mão no template nem estimado sem que a tela diga que é estimativa.
Rationale: o administrador decide com base nesses números; eles precisam bater com o que foi lançado.

### II. Multiempresa isolada

Todo modelo de dado de cliente MUST usar `BelongsToTenant` (escopo global por `user_id`) e toda ação
MUST ser autorizada por policy ou por `tenantOwnerId()`. Consultas que atravessam empresas
(`withoutGlobalScopes`) só são permitidas em jobs, comandos e serviços da plataforma, nunca em
código que responde a uma requisição de usuário sem filtrar pela empresa dele.
Rationale: um vazamento entre empresas encerra a confiança no produto.

### III. Custo operacional controlado

Nenhum serviço externo pago pode ser chamado sem: (a) cota definida no plano da empresa, (b) registro
de cada chamada com empresa e resultado, e (c) consulta prévia aos dados já existentes. A
geocodificação usa uma conta única da plataforma e MUST consultar primeiro o cache de endereços já
localizados; só um endereço inédito gera chamada externa. Coordenadas confirmadas por GPS ou
ajuste manual são permanentes e prevalecem sobre qualquer provedor. O provedor escolhido MUST
permitir guardar o resultado e exibi-lo no mapa usado pelo produto (Leaflet/OpenStreetMap).
Rationale: a plataforma paga a conta; reaproveitar dados mantém o preço dos planos viável.

### IV. Testes de feature obrigatórios

Todo comportamento novo ou alterado MUST ter teste PHPUnit (feature, salvo lógica pura em unit)
cobrindo o caminho feliz, falhas e casos de borda. `vendor/bin/pint --dirty` MUST passar e a suíte
completa MUST continuar verde antes de qualquer commit. Chamadas HTTP externas são sempre
simuladas nos testes (`Http::fake` / `preventStrayRequests`).
Rationale: o sistema lida com dinheiro e operação diária; regressão silenciosa custa caro.

### V. Verdade no produto

A interface e a home MUST mostrar somente funcionalidades que existem. O que está em
desenvolvimento aparece rotulado como "Em breve"; dados de exemplo aparecem rotulados como
demonstração. É proibido inventar clientes, depoimentos, métricas de resultado ou preços.
Rationale: promessa falsa gera cancelamento e problema comercial.

### VI. Design governado por DESIGN.md e PRODUCT.md

Telas novas MUST seguir os tokens e regras do `DESIGN.md` (classes `fleet-*`, componentes de
`resources/css/app.css`) e o contexto do `PRODUCT.md`. Mudança de sistema visual exige atualizar o
`DESIGN.md` no mesmo trabalho.
Rationale: consistência entre telas e entre sessões de desenvolvimento.

### VII. Brasil primeiro

Interface em português (pt-BR); moeda em R$ e números no formato brasileiro (vírgula decimal);
datas em dd/mm/aaaa na interface; mensagens de erro dizem o problema e como resolver.
Rationale: o público é a pequena e média empresa brasileira.

### VIII. Motorista e administrador com o mesmo peso

Toda funcionalidade usada pelo motorista MUST funcionar em celular (390px de largura), com alvos de
toque de no mínimo 44px, contraste AA e tolerância a sinal fraco (envios que falham são guardados e
reenviados). Telas do administrador MUST funcionar no desktop e no celular.
Rationale: metade do uso acontece na rua, no celular.

## Restrições técnicas

- Stack: PHP 8.4, Laravel 13, Livewire 3, Alpine.js, Tailwind CSS 3, Vite, PHPUnit 12, SQLite em
  desenvolvimento. Mudança de dependência exige aprovação explícita do responsável pelo produto.
- Estrutura: seguir as pastas existentes (`app/Livewire`, `app/Services`, `app/Models`,
  `app/Enums`, `app/Jobs`); arquivos novos via `php artisan make:*`.
- Mapa: Leaflet com tiles do OpenStreetMap (URL configurável). Otimização de rotas calculada no
  próprio servidor, sem API paga.
- Segredos (chaves de provedores, gateway de pagamento) ficam no `.env` da plataforma; dados
  sensíveis de empresa ficam criptografados (`encrypted` cast) e nunca voltam inteiros para a tela.
- Mensagens de exceção vindas de bibliotecas HTTP nunca são gravadas nem exibidas sem remover
  chaves e URLs com credenciais.

## Fluxo de desenvolvimento

- Cada funcionalidade nasce como spec em `specs/NNN-nome/spec.md` (spec-kit) antes do código; specs
  de funcionalidades já existentes documentam o comportamento atual.
- Ordem: spec → plano → tarefas → implementação → testes → revisão visual quando há tela.
- Commits só com a suíte verde e pint aplicado; commit e push apenas quando o responsável pedir.
- Funcionalidades com custo externo (geocodificação, pagamentos) MUST ter tela de uso/consumo para a
  empresa e limite por plano antes de irem para produção.

## Governance

Esta constituição prevalece sobre preferências individuais de implementação. Alterações exigem:
descrição da mudança, atualização da versão e revisão das specs afetadas. Versionamento semântico:
MAJOR para remoção ou redefinição de princípio, MINOR para princípio ou seção nova, PATCH para
ajustes de texto. Toda spec, plano e revisão de código MUST verificar conformidade com os
princípios; exceções precisam estar justificadas no plano da funcionalidade. Orientação de
execução do dia a dia: `AGENTS.md`, `PRODUCT.md` e `DESIGN.md`.

**Version**: 1.0.0 | **Ratified**: 2026-10-29 | **Last Amended**: 2026-10-29
