<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\Central\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes de la plataforma: datos de cobro y contacto que se muestran a las
 * empresas. Pares clave-valor para poder sumar ajustes sin migrar.
 */
final class PlatformSetting extends Model
{
    use UsesCentralConnection;

    public const KEYS = [
        'brand_name', 'support_email', 'support_whatsapp',
        'bank_name', 'bank_account', 'bank_holder', 'bank_document',
        'payment_instructions', 'payment_qr_path',
    ];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /** @return array<string, string|null> */
    public static function values(): array
    {
        $stored = self::query()->pluck('value', 'key')->all();

        return array_merge(array_fill_keys(self::KEYS, null), $stored);
    }

    /** @param  array<string, string|null>  $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
