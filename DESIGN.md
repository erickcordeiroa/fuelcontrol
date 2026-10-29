---
version: alpha
name: Fuelly
description: Gestão de frota, combustível e rotas de entrega — tela creme quente, tinta quase preta e um único azul elétrico para a ação principal.
colors:
  primary: "#0061FE"
  primary-press: "#0044AF"
  on-primary: "#F7F5F2"
  ink: "#1E1919"
  ink-mute: "#524A3E"
  ink-soft: "#6B6560"
  ink-warm: "#393633"
  hairline: "#A69171"
  hairline-soft: "#D3CEC9"
  canvas: "#F7F5F2"
  canvas-lift: "#EEE9E2"
  paper: "#FFFFFF"
  surface-dark: "#000000"
  surface-dark-elevated: "#292C31"
  on-dark: "#F7F5F2"
  on-dark-mute: "#8E8B87"
  surface-info-soft: "#DEEBFF"
  success: "#175641"
  error: "#B42318"
  warning: "#EECC5D"
  warning-ink: "#7A5A00"
typography:
  display-xl:
    fontFamily: "\"Space Grotesk Variable\", \"Space Grotesk\", system-ui, sans-serif"
    fontSize: 48px
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.5px"
  display-lg:
    fontFamily: "\"Space Grotesk Variable\", \"Space Grotesk\", system-ui, sans-serif"
    fontSize: 32px
    fontWeight: 400
    lineHeight: 1.2
    letterSpacing: "-0.2px"
  display-md:
    fontFamily: "\"Space Grotesk Variable\", \"Space Grotesk\", system-ui, sans-serif"
    fontSize: 26px
    fontWeight: 500
    lineHeight: 1.3
  heading-md:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 20px
    fontWeight: 500
    lineHeight: 1.5
  heading-sm:
    fontFamily: "\"Space Grotesk Variable\", \"Space Grotesk\", system-ui, sans-serif"
    fontSize: 18px
    fontWeight: 500
    lineHeight: 1.2
  body-lg:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 20px
    fontWeight: 400
    lineHeight: 1.5
  body-md:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
  body-sm:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.57
  label-md:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 500
    lineHeight: 1.2
  label-sm:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.43
  button-md:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 500
    lineHeight: 1.2
  caption:
    fontFamily: "\"Inter Variable\", Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.2
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  pill: "100px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  base: "16px"
  lg: "24px"
  xl: "32px"
  xxl: "48px"
  huge: "64px"
components:
  button-primary-pill:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button-md}"
    rounded: "{rounded.pill}"
    padding: "8px 24px"
    height: "40px"
  button-primary-pill-pressed:
    backgroundColor: "{colors.primary-press}"
  button-secondary-outline:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.button-md}"
    rounded: "{rounded.pill}"
    padding: "8px 24px"
    height: "40px"
  button-ghost-on-dark:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.button-md}"
    rounded: "{rounded.pill}"
    padding: "8px 24px"
    height: "40px"
  text-input:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
    rounded: "{rounded.sm}"
    padding: "10px 14px"
    height: "44px"
  top-nav:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    height: "60px"
  side-nav:
    backgroundColor: "{colors.canvas-lift}"
    textColor: "{colors.ink}"
    width: "256px"
  nav-item-active:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.on-dark}"
    rounded: "{rounded.pill}"
  panel:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "24px"
  card-feature:
    backgroundColor: "{colors.canvas-lift}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "24px"
  band-dark:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark}"
    rounded: "{rounded.lg}"
    padding: "64px 24px"
  card-dark:
    backgroundColor: "{colors.surface-dark-elevated}"
    textColor: "{colors.on-dark}"
    rounded: "{rounded.md}"
    padding: "24px"
  info-bar:
    backgroundColor: "{colors.surface-info-soft}"
    textColor: "{colors.primary-press}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  footer-dark:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.on-dark-mute}"
    padding: "48px 24px"
---

# Design System: Fuelly

Sistema adotado em 29/10/2026 a partir de uma referência visual enviada pelo usuário (o DESIGN.md de uma empresa de armazenamento em nuvem). Ficaram as decisões de cor, raio, escala e ritmo; ficaram de fora o nome, os textos e as fontes proprietárias da marca de origem. A marca continua sendo **Fuelly**.

## Overview

**Creative North Star: "Escritório quente, um só azul"**

A tela é creme (`{colors.canvas}` #F7F5F2), não branca, com texto em tinta quase preta (`{colors.ink}` #1E1919). Isso deixa o sistema com cara de escritório de trabalho, não de painel frio. O branco puro aparece só onde está o "produto": painéis, tabelas e campos das telas internas, e o cartão de rota na home.

O azul elétrico (`{colors.primary}` #0061FE) é a única voltagem: um botão preenchido por área, sempre em pílula. Todo o resto vive numa escada de neutros quentes — creme → creme elevado → marrom-acinzentado → tinta. A profundidade vem de uma faixa escura de largura total, usada uma vez por página, e não de sombras fortes.

**Key Characteristics:**
- Fundo creme `#F7F5F2` no lugar do branco; branco só nos painéis do produto.
- Um único botão azul preenchido por área; o par dele é o botão de contorno em tinta.
- Botões em pílula (100px); cartões com 12–16px; campos com 8px.
- Títulos em Space Grotesk 500; texto, rótulos e botões em Inter (400/500).
- Uma faixa escura de largura total por página, e depois de volta ao creme.

## Colors

### Brand
- **Azul elétrico** (`{colors.primary}` #0061FE): o botão principal de cada área, item selecionado, foco, rota roteirizada no mapa.
- **Azul pressionado** (`{colors.primary-press}` #0044AF): hover/pressionado do botão principal e cor de links em texto (contraste maior sobre creme).
- **Faixa informativa** (`{colors.surface-info-soft}` #DEEBFF): avisos de configuração (ex.: chave do Google não cadastrada).

### Surface
- **Creme** (`{colors.canvas}` #F7F5F2): fundo de todas as páginas, topo, home.
- **Creme elevado** (`{colors.canvas-lift}` #EEE9E2): menu lateral, cartões de destaque, cabeçalhos de tabela, hover de itens.
- **Papel** (`{colors.paper}` #FFFFFF): painéis, tabelas e campos das telas internas; cartões de "produto" na home.
- **Escuro** (`{colors.surface-dark}` #000000) e **escuro elevado** (`{colors.surface-dark-elevated}` #292C31): a faixa escura da home, o rodapé e o painel lateral do login.

### Text
- **Tinta** (`{colors.ink}` #1E1919): títulos e texto.
- **Tinta suave** (`{colors.ink-mute}` #524A3E): descrições, subtítulos, rótulos secundários.
- **Tinta clara** (`{colors.ink-soft}` #6B6560): placeholders e metadados (≥ 4,5:1 sobre creme).
- **Sobre escuro** (`{colors.on-dark}` #F7F5F2) e **sobre escuro suave** (`{colors.on-dark-mute}` #8E8B87): texto na faixa escura — creme, nunca branco puro.

### Hairline
- **Fio suave** (`{colors.hairline-soft}` #D3CEC9): bordas de campos, painéis e divisórias.
- **Fio** (`{colors.hairline}` #A69171): separação creme sobre creme e hover de bordas.

### Estados (só no produto)
- **Sucesso** (`{colors.success}` #175641): entregue, confirmado, chave funcionando.
- **Erro** (`{colors.error}` #B42318): não entregue, falha, ação destrutiva.
- **Atenção** (`{colors.warning}` #EECC5D como preenchimento; `{colors.warning-ink}` #7A5A00 como texto): em rota, revisar endereço, motorista parado.

### Named Rules
**The One Blue Rule.** Um só botão azul preenchido por área. A segunda ação é sempre o contorno em tinta.

**The Cream Canvas Rule.** A página é creme. Branco puro só dentro do produto (painéis, tabelas, campos, cartão de rota).

**The Logo Exception.** O verde do logo Fuelly vive só no logo.

## Typography

**Display:** Space Grotesk Variable (`@fontsource-variable/space-grotesk`), substituta gratuita da grotesca de display da referência, sempre em 400–500.
**Corpo:** Inter Variable (`@fontsource-variable/inter`).
**Ícones:** desenhos SVG em `resources/views/components/icons` (traço de 1,5px). Exceção: o botão de mostrar senha do login usa a fonte de ícones Material Symbols Outlined.

### Hierarchy
| Token | Família | Tamanho | Peso | Uso |
|---|---|---|---|---|
| `display-xl` | Space Grotesk | 48px (até 56px no desktop) | 500 | Título do topo da home |
| `display-lg` | Space Grotesk | 32px | 400 | Títulos de seção da home |
| `display-md` | Space Grotesk | 26px | 500 | Título de página nas telas internas (32px a partir de 640px, como `display-lg` em 500) |
| `heading-sm` | Space Grotesk | 18px | 500 | Títulos de painel e de cartão |
| `body-lg` | Inter | 20px | 400 | Lead da home |
| `body-md` | Inter | 16px | 400 | Texto corrido |
| `body-sm` | Inter | 14px | 400 | Tabelas, descrições, rodapé |
| `label-sm` | Inter | 14px | 500 | Rótulos de campo, cabeçalhos de tabela |
| `button-md` | Inter | 16px | 500 | Botões (14px no tamanho pequeno) |

### Named Rules
**The 500 Ceiling Rule.** Nada acima de peso 500 em títulos; rótulos e botões em 500, texto em 400.

**The Two Families Rule.** Space Grotesk só em títulos; tudo o que se lê ou se clica é Inter.

## Layout

- Telas internas: menu lateral creme elevado de 256px + área de trabalho creme, margens de 16px (celular) e 40px (desktop).
- Home: contêiner de 1248px; seções com 64–96px de respiro; topo dividido (texto à esquerda, cartão de produto à direita); funcionalidades em linhas alternadas texto + cartão de produto; uma faixa escura de largura total.
- Base de 4px; ritmo de 8 / 12 / 16 / 24px.
- Abaixo de 1024px tudo vira uma coluna; tabelas largas rolam dentro do painel.
- Camadas: topo fixo `z-40`, modais `z-50` a `z-70`.

## Elevation & Depth

| Nível | Tratamento | Uso |
|---|---|---|
| 0 | Plano | Fundo creme, cartões creme elevado |
| 1 | `0 1px 2px rgb(0 0 0 / 0.04)` + fio suave | Painéis brancos das telas internas |
| 2 | `0 8px 24px rgb(0 0 0 / 0.08)` | Cartão de produto na home, menus suspensos, modais |
| 3 | Faixa escura | A profundidade da home vem da inversão para o escuro, não de sombra |

## Shapes

- `{rounded.sm}` 8px: campos, chips, itens pequenos.
- `{rounded.md}` 12px: cartões de destaque, cartões da faixa escura.
- `{rounded.lg}` 16px: painéis, cartão de produto, faixa escura.
- `{rounded.pill}` 100px: todos os botões e o item ativo do menu.

## Components

### Buttons
- **Principal** (`.fleet-btn--primary`): pílula azul elétrico, texto creme, 500, 40px (36px pequeno, 48px grande). Hover/pressionado: azul pressionado.
- **Contorno** (`.fleet-btn--outline`): pílula creme com borda de 1px em tinta, texto em tinta; hover em creme elevado.
- **Neutro** (`.fleet-btn--muted`): pílula branca com fio suave.
- **Perigo** (`.fleet-btn--danger`): pílula em `{colors.error}`.
- Foco: anel azul de 2px com afastamento.

### Cards / Containers
- **Painel** (`.fleet-panel`): branco, 16px, fio suave + sombra nível 1.
- **Destaque** (`.fleet-panel--inverted`): creme elevado, 12px, sem sombra — para o número principal da tela.

### Inputs / Fields
- Rótulo acima (`label-sm`, tinta); campo branco, fio suave, 8px, 44px. Foco: borda de 2px azul. Erro: borda e mensagem em `{colors.error}`.

### Navigation
- **Menu lateral:** creme elevado; logo no topo; grupos com rótulo em tinta suave; item ativo em pílula de tinta com texto creme.
- **Topo:** creme, 60px, fio suave inferior; busca rápida por categoria.

## Do's and Don'ts

### Do:
- **Do** manter um só botão azul preenchido por área.
- **Do** usar creme como fundo e branco só nos painéis do produto.
- **Do** usar pílula em todo botão, 8px em campo, 12–16px em cartão.
- **Do** usar uma faixa escura de largura total por página e voltar ao creme em seguida.
- **Do** marcar dados de exemplo como "Dados de demonstração" e funcionalidades futuras como "Em breve".

### Don't:
- **Don't** colocar dois botões azuis preenchidos lado a lado.
- **Don't** usar peso acima de 500 em títulos.
- **Don't** trocar o fundo creme por branco puro.
- **Don't** usar a faixa escura como tema global.
- **Don't** colocar rótulo pequeno acima de títulos (eyebrow): a referência usa, mas o padrão de qualidade do projeto não aceita.
- **Don't** usar o verde do logo na interface, nem nome, textos ou fontes da marca de referência.
- **Don't** inventar clientes, logos de clientes, números de resultado ou preços.
