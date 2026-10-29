<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RouteTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rota base de uma rota que se repete: ordem dos clientes guardada pelo nome da rota.
 */
class RouteTemplate extends Model
{
    /** @use HasFactory<RouteTemplateFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'name_key',
        'customer_ids',
        'optimized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_ids' => 'array',
            'optimized_at' => 'datetime',
        ];
    }

    /**
     * "Setor Sul", "setor sul " e "SETOR SUL" são a mesma rota base.
     */
    public static function keyFor(string $name): string
    {
        return Str::of($name)->squish()->lower()->ascii()->toString();
    }

    /**
     * @param  Builder<RouteTemplate>  $query
     */
    public function scopeNamed(Builder $query, string $name): void
    {
        $query->where('name_key', self::keyFor($name));
    }
}
