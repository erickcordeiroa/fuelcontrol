<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FleetMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_is_grouped_by_category(): void
    {
        $menu = $this->labelsBySection(User::factory()->admin()->create());

        $this->assertSame([
            'Acompanhamento' => ['Relatórios', 'Rotas'],
            'Lançamentos' => ['Diário de Bordo', 'Entregas', 'Trocas de óleo'],
            'Cadastros' => ['Veículos', 'Motoristas', 'Postos'],
            'Configurações' => ['Integrações', 'Perfil'],
        ], $menu);
    }

    public function test_driver_menu_hides_admin_pages_and_empty_categories(): void
    {
        $menu = $this->labelsBySection(User::factory()->driverRole()->create());

        $this->assertSame([
            'Acompanhamento' => ['Relatórios'],
            'Lançamentos' => ['Diário de Bordo'],
            'Configurações' => ['Perfil'],
        ], $menu);
    }

    public function test_search_pages_carry_category_and_url(): void
    {
        $pages = collect(FleetMenu::searchablePages(User::factory()->admin()->create()));

        $integrations = $pages->firstWhere('label', 'Integrações');
        $this->assertSame('Configurações', $integrations['category']);
        $this->assertSame(route('integrations.index'), $integrations['url']);
    }

    public function test_layout_renders_categories_and_no_dashboard_link(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports'))
            ->assertOk()
            ->assertSeeInOrder(['Acompanhamento', 'Lançamentos', 'Cadastros', 'Configurações'])
            ->assertDontSee('>Painel<', false);
    }

    /**
     * @return array<string, list<string>>
     */
    private function labelsBySection(User $user): array
    {
        return collect(FleetMenu::for($user))
            ->mapWithKeys(fn (array $section): array => [$section['label'] => array_column($section['items'], 'label')])
            ->all();
    }
}
