# Specs do Fuelly

Visão geral do sistema por funcionalidade (spec-kit). Princípios do projeto em
[`.specify/memory/constitution.md`](../.specify/memory/constitution.md); contexto de produto em
[`PRODUCT.md`](../PRODUCT.md) e sistema visual em [`DESIGN.md`](../DESIGN.md).

| # | Funcionalidade | Situação |
|---|---|---|
| [001](001-fleet-fuel-control/spec.md) | Controle de frota e combustível (Diário de Bordo, relatórios, trocas de óleo, cadastros) | Implementado |
| [002](002-delivery-planning/spec.md) | Planejamento de entregas e roteirização | Implementado |
| [003](003-route-monitoring/spec.md) | Acompanhamento de rotas | Parcial: tela pronta, depende da 004 |
| [004](004-driver-app/spec.md) | App do motorista (baixas, motivos, localização) | Não iniciado |
| [005](005-geocoding-provider/spec.md) | Busca de endereços com conta única da plataforma | Implementado (cota provisória até a 007) |
| [006](006-address-cache/spec.md) | Base de endereços já localizados | Implementado |
| [007](007-plan-limits/spec.md) | Planos e limites por quantidade de veículos | Implementado (valores de exemplo; pagamento na 008) |
| [008](008-asaas-billing/spec.md) | Cobrança das assinaturas com Asaas | Não iniciado (último passo) |
| [009](009-audit-log/spec.md) | Log de auditoria (empresa + JSON de mudanças) | Implementado (sem tela de consulta) |

## Como os dados fluem

1. A empresa importa pedidos (002). Cada cliente precisa de coordenada.
2. A coordenada vem, nesta ordem: posição confirmada do cliente → base de endereços (006) → provedor
   da plataforma (005), dentro da cota do plano (007).
3. O administrador cria rotas com as paradas ordenadas (002).
4. O motorista executa a rota pelo app (004): baixas, motivos e posições vão para o banco.
5. A tela de Rotas (003) lê o banco e se atualiza sozinha.
6. Custos de combustível e despesas vêm do Diário de Bordo (001).
7. O plano da empresa (007) define limites, e a cobrança (008) mantém o plano ativo.
