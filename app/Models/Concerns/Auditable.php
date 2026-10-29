<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Grava no log de auditoria (spec 009) toda criação, alteração e exclusão do modelo,
 * com os valores antigos e novos de cada campo, quem fez e de onde.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAuditLog('created'));
        static::updated(fn (Model $model) => $model->writeAuditLog('updated'));
        static::deleted(fn (Model $model) => $model->writeAuditLog('deleted'));
    }

    /**
     * Empresa dona do registro. Modelos sem `user_id` próprio sobrescrevem.
     */
    public function auditTenantId(): ?int
    {
        $tenantId = $this->getAttribute('user_id');

        return $tenantId === null ? null : (int) $tenantId;
    }

    protected function writeAuditLog(string $event): void
    {
        $changes = $this->auditChanges($event);

        if ($changes === []) {
            return;
        }

        $user = Auth::user();
        $isWebRequest = ! app()->runningInConsole();

        AuditLog::query()->withoutGlobalScopes()->create([
            'user_id' => $this->auditTenantId(),
            'metadata' => [
                'event' => $event,
                'model' => class_basename($this),
                'model_id' => $this->getKey(),
                'changes' => $changes,
                'actor' => $user === null ? null : [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'role' => $user->role instanceof BackedEnum ? $user->role->value : $user->role,
                ],
                'source' => $isWebRequest ? 'web' : 'console',
                'ip' => $isWebRequest ? request()->ip() : null,
            ],
        ]);
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function auditChanges(string $event): array
    {
        $ignored = [...$this->getHidden(), 'created_at', 'updated_at'];

        $fields = match ($event) {
            'updated' => array_keys($this->getChanges()),
            default => array_keys($this->getAttributes()),
        };

        $changes = [];

        foreach (array_diff($fields, $ignored) as $field) {
            $changes[$field] = match ($event) {
                'created' => ['old' => null, 'new' => $this->getAttributes()[$field] ?? null],
                'deleted' => ['old' => $this->getRawOriginal($field), 'new' => null],
                default => ['old' => $this->getRawOriginal($field), 'new' => $this->getAttributes()[$field] ?? null],
            };
        }

        return $changes;
    }
}
