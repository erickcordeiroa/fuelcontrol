<?php

namespace App\Support;

use App\Models\User;

/**
 * Menu do sistema agrupado por categoria. Fonte única do menu lateral, dos atalhos no celular e da busca rápida.
 *
 * @phpstan-type MenuItem array{label: string, description: string, route: string, active: string, icon: string, keywords: string}
 * @phpstan-type MenuSection array{label: string, items: list<MenuItem>}
 */
class FleetMenu
{
    /**
     * @return list<MenuSection>
     */
    public static function for(User $user): array
    {
        $isAdmin = $user->isAdmin();

        $sections = [
            [
                'label' => __('Acompanhamento'),
                'items' => [
                    self::item(__('Relatórios'), __('Combustível, gastos e entregas da frota'), 'reports', 'reports*', 'chart-bar', 'painel inicio dashboard indicadores combustivel gastos entregas custo por entrega'),
                    $isAdmin ? self::item(__('Rotas'), __('Acompanhar entregadores e entregas'), 'deliveries.monitor', 'deliveries.monitor', 'map', 'mapa motorista gps') : null,
                ],
            ],
            [
                'label' => __('Lançamentos'),
                'items' => [
                    self::item(__('Diário de Bordo'), __('Viagens, abastecimentos e despesas'), 'logbook', 'logbook', 'clipboard-document', 'viagem combustivel abastecimento despesa km'),
                    $isAdmin ? self::item(__('Entregas'), __('Importar pedidos e criar rotas'), 'deliveries.planner', 'deliveries.planner', 'map-pin', 'pedidos planilha roteirizacao') : null,
                    $isAdmin ? self::item(__('Trocas de óleo'), __('Registro e alertas de troca de óleo'), 'oil-changes.index', 'oil-changes.*', 'wrench', 'manutencao oleo') : null,
                ],
            ],
            [
                'label' => __('Cadastros'),
                'items' => [
                    $isAdmin ? self::item(__('Veículos'), __('Frota, placas e capacidade'), 'vehicles.index', 'vehicles.*', 'truck', 'caminhao placa frota') : null,
                    $isAdmin ? self::item(__('Motoristas'), __('Cadastro e acesso dos motoristas'), 'drivers.index', 'drivers.*', 'users', 'condutor cnh') : null,
                    $isAdmin ? self::item(__('Postos'), __('Postos e preços de combustível'), 'gas-stations.index', 'gas-stations.*', 'building-storefront', 'combustivel preco diesel') : null,
                ],
            ],
            [
                'label' => __('Configurações'),
                'items' => [
                    $isAdmin ? self::item(__('Assinatura'), __('Plano, uso e troca de plano'), 'subscription.index', 'subscription.*', 'credit-card', 'plano limite pagamento mensalidade') : null,
                    $isAdmin ? self::item(__('Integrações'), __('Busca de endereços e consumo'), 'integrations.index', 'integrations.*', 'key', 'endereços geolocalização cota consumo') : null,
                    self::item(__('Perfil'), __('Dados da conta e senha'), 'profile', 'profile', 'user-circle', 'senha conta usuario'),
                ],
            ],
        ];

        return collect($sections)
            ->map(fn (array $section): array => [...$section, 'items' => array_values(array_filter($section['items']))])
            ->filter(fn (array $section): bool => $section['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * Itens para a busca rápida, com a categoria junto para filtrar e exibir.
     *
     * @return list<array{label: string, description: string, category: string, keywords: string, url: string}>
     */
    public static function searchablePages(User $user): array
    {
        return collect(self::for($user))
            ->flatMap(fn (array $section): array => array_map(fn (array $item): array => [
                'label' => $item['label'],
                'description' => $item['description'],
                'category' => $section['label'],
                'keywords' => $item['keywords'],
                'url' => route($item['route']),
            ], $section['items']))
            ->values()
            ->all();
    }

    /**
     * @return MenuItem
     */
    private static function item(string $label, string $description, string $route, string $active, string $icon, string $keywords): array
    {
        return compact('label', 'description', 'route', 'active', 'icon', 'keywords');
    }
}
