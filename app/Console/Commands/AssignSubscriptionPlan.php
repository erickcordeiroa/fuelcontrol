<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscription:assign {email : E-mail do administrador da empresa} {plan : Chave do plano em config/plans.php}')]
#[Description('Coloca uma empresa num plano, inclusive os que não aparecem na tela (ex.: parceiros).')]
class AssignSubscriptionPlan extends Command
{
    public function handle(PlanLimits $planLimits): int
    {
        $plan = (string) $this->argument('plan');

        if (! $planLimits->plans()->has($plan)) {
            $this->components->error(__('Plano ":plan" não existe. Planos: :list.', ['plan' => $plan, 'list' => $planLimits->plans()->keys()->implode(', ')]));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null || ! $user->isAdmin()) {
            $this->components->error(__('Nenhum administrador com o e-mail :email.', ['email' => $this->argument('email')]));

            return self::FAILURE;
        }

        $planLimits->changePlan($user->tenantOwnerId(), $plan);

        $this->components->info(__('Empresa ":company" agora está no plano ":plan".', [
            'company' => $user->company_name ?? $user->name,
            'plan' => $planLimits->plans()->get($plan)['name'],
        ]));

        return self::SUCCESS;
    }
}
