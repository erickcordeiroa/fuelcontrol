<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GeocodingRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma chamada ao provedor (busca de endereço ou rota), usada para a cota da empresa e o teto diário da plataforma.
 */
class GeocodingRequest extends Model
{
    /** @use HasFactory<GeocodingRequestFactory> */
    use BelongsToTenant, HasFactory;

    public const LOOKUP_ADDRESS = 'address';

    public const LOOKUP_PLACE_ID = 'place_id';

    /** Ordem das paradas pelas ruas (Geoapify Route Planner). */
    public const LOOKUP_ROUTE_PLANNER = 'route_planner';

    /** Trajeto e instruções de navegação (Geoapify Routing). */
    public const LOOKUP_ROUTING = 'routing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'customer_id',
        'provider',
        'lookup',
        'provider_status',
        'billable',
        'credits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
            'credits' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Só buscas de endereço: é o que conta na cota mensal da empresa; rotas usam créditos do teto diário.
     *
     * @param  Builder<GeocodingRequest>  $query
     */
    public function scopeAddressLookups(Builder $query): void
    {
        $query->whereIn('lookup', [self::LOOKUP_ADDRESS, self::LOOKUP_PLACE_ID]);
    }

    /**
     * Consultas do mês calendário da data informada (o Google zera a faixa grátis todo dia 1º).
     *
     * @param  Builder<GeocodingRequest>  $query
     */
    public function scopeInMonth(Builder $query, Carbon $month): void
    {
        $query->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }
}
