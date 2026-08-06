<?php

namespace App\Services\Siat;

use App\Models\SiatSetting;
use Carbon\CarbonImmutable;

/**
 * Lectura del Token Delegado del SIAT, que es un JWT.
 *
 * Su payload lleva a qué NIT y a qué Código de Sistema fue emitido. El SIN no
 * dice cuál de los dos no cuadra cuando rechaza una petición —responde siempre
 * "API KEY NO VALIDO"—, así que contrastarlo aquí es la única forma barata de
 * saberlo sin ir probando contra el servicio.
 */
class SiatToken
{
    private function __construct(
        public readonly ?string $nit,
        public readonly ?string $nitDelegado,
        public readonly ?string $codigoSistema,
        public readonly ?string $subsistema,
        public readonly ?CarbonImmutable $expiraEn,
    ) {}

    /** Devuelve null si el valor no tiene forma de JWT legible. */
    public static function parse(?string $token): ?self
    {
        if (blank($token) || substr_count($token, '.') !== 2) {
            return null;
        }

        $payload = json_decode(self::base64Url(explode('.', $token)[1]), true);

        if (! is_array($payload)) {
            return null;
        }

        return new self(
            nit:           self::descomprimir($payload['nit'] ?? null),
            nitDelegado:   isset($payload['nitDelegado']) ? (string) $payload['nitDelegado'] : null,
            codigoSistema: $payload['codigoSistema'] ?? null,
            subsistema:    $payload['subsistema'] ?? null,
            expiraEn:      isset($payload['exp']) ? CarbonImmutable::createFromTimestamp((int) $payload['exp']) : null,
        );
    }

    public function estaVencido(): bool
    {
        return $this->expiraEn !== null && $this->expiraEn->isPast();
    }

    /**
     * Motivos por los que esta configuración no podrá autenticarse, en lenguaje
     * llano. Vacío significa que el token y la configuración son coherentes.
     *
     * @return list<string>
     */
    public function incoherenciasCon(SiatSetting $setting): array
    {
        $problemas = [];

        if ($this->estaVencido()) {
            $problemas[] = sprintf(
                'El Token Delegado venció el %s. Genere uno nuevo en el Portal SIAT.',
                $this->expiraEn->format('d/m/Y H:i')
            );
        }

        if (filled($this->codigoSistema) && $this->codigoSistema !== $setting->codigo_sistema) {
            $problemas[] = sprintf(
                'El Código de Sistema configurado (%s) no es el del token (%s).',
                $setting->codigo_sistema ?: '—',
                $this->codigoSistema
            );
        }

        $problemas = array_merge($problemas, $this->problemasDeNit((string) $setting->nit));

        return $problemas;
    }

    /** @return list<string> */
    private function problemasDeNit(string $nitConfigurado): array
    {
        if (blank($this->nit) || $nitConfigurado === $this->nit) {
            return [];
        }

        // Confundir ambos NIT es el error habitual: el token se emite al NIT
        // emisor y delega en otro, y solo el emisor sirve en el cuerpo del SOAP.
        if ($nitConfigurado === $this->nitDelegado) {
            return [sprintf(
                'El NIT configurado (%s) es el NIT delegado del token, no el emisor. El SIN espera %s.',
                $nitConfigurado,
                $this->nit
            )];
        }

        return [sprintf(
            'El NIT configurado (%s) no coincide con el del token (%s).',
            $nitConfigurado ?: '—',
            $this->nit
        )];
    }

    /**
     * El claim `nit` viaja comprimido en gzip y codificado en base64; los demás
     * llegan en claro.
     */
    private static function descomprimir(mixed $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $valor = (string) $valor;

        if (ctype_digit($valor)) {
            return $valor;
        }

        $binario = base64_decode($valor, true);

        if ($binario === false) {
            return $valor;
        }

        return @gzdecode($binario) ?: $valor;
    }

    private static function base64Url(string $valor): string
    {
        return (string) base64_decode(strtr($valor, '-_', '+/'), true);
    }
}
