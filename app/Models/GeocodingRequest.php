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
 * Uma consulta enviada ao Google Geocoding, usada para acompanhar o consumo e estimar a fatura.
 */
class GeocodingRequest extends Model
{
    /** @use HasFactory<GeocodingRequestFactory> */
    use BelongsToTenant, HasFactory;

    public const LOOKUP_ADDRESS = 'address';

    public const LOOKUP_PLACE_ID = 'place_id';

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
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
     * Consultas do mês calendário da data informada (o Google zera a faixa grátis todo dia 1º).
     *
     * @param  Builder<GeocodingRequest>  $query
     */
    public function scopeInMonth(Builder $query, Carbon $month): void
    {
        $query->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }
}
