<?php

namespace Tests\Unit;

use App\Models\SiatSetting;
use App\Services\Siat\SiatToken;
use Tests\TestCase;

/**
 * El Token Delegado del SIAT es un JWT cuyo payload dice a qué NIT y a qué
 * Código de Sistema fue emitido. El SIN responde siempre "API KEY NO VALIDO" sin
 * decir cuál de los dos falla, así que esta lectura es el diagnóstico.
 */
class SiatTokenTest extends TestCase
{
    private const NIT_EMISOR   = '1234567890';
    private const NIT_DELEGADO = '9876543210';
    private const CODIGO_SISTEMA = 'AAAA1111BBBB2222CCCC3333DDDD44';

    /** Arma un JWT con el mismo formato que emite el Portal SIAT. */
    private function token(array $payload = []): string
    {
        $payload = array_merge([
            // El claim `nit` viaja comprimido en gzip + base64; los demás en claro.
            'nit'           => base64_encode(gzencode(self::NIT_EMISOR)),
            'nitDelegado'   => (int) self::NIT_DELEGADO,
            'codigoSistema' => self::CODIGO_SISTEMA,
            'subsistema'    => 'SFE',
            'exp'           => now()->addYear()->timestamp,
        ], $payload);

        $b64 = fn(array $d) => rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '=');

        return $b64(['typ' => 'JWT', 'alg' => 'HS512']) . '.' . $b64($payload) . '.firma';
    }

    private function setting(array $atributos = []): SiatSetting
    {
        return new SiatSetting(array_merge([
            'nit'            => self::NIT_EMISOR,
            'codigo_sistema' => self::CODIGO_SISTEMA,
        ], $atributos));
    }

    public function test_it_decodes_the_gzip_compressed_nit_claim(): void
    {
        $token = SiatToken::parse($this->token());

        $this->assertSame(self::NIT_EMISOR, $token->nit);
        $this->assertSame(self::NIT_DELEGADO, $token->nitDelegado);
        $this->assertSame(self::CODIGO_SISTEMA, $token->codigoSistema);
        $this->assertSame('SFE', $token->subsistema);
    }

    public function test_it_reports_no_problems_when_token_and_setting_agree(): void
    {
        $token = SiatToken::parse($this->token());

        $this->assertSame([], $token->incoherenciasCon($this->setting()));
    }

    public function test_it_names_the_delegado_nit_when_it_was_configured_by_mistake(): void
    {
        $token = SiatToken::parse($this->token());

        $problemas = $token->incoherenciasCon($this->setting(['nit' => self::NIT_DELEGADO]));

        $this->assertCount(1, $problemas);
        $this->assertStringContainsString('es el NIT delegado del token, no el emisor', $problemas[0]);
        $this->assertStringContainsString(self::NIT_EMISOR, $problemas[0]);
    }

    public function test_it_detects_an_unrelated_nit(): void
    {
        $token = SiatToken::parse($this->token());

        $problemas = $token->incoherenciasCon($this->setting(['nit' => '999999999']));

        $this->assertCount(1, $problemas);
        $this->assertStringContainsString('no coincide con el del token', $problemas[0]);
    }

    public function test_it_detects_a_codigo_sistema_mismatch(): void
    {
        $token = SiatToken::parse($this->token());

        $problemas = $token->incoherenciasCon($this->setting(['codigo_sistema' => 'OTRO']));

        $this->assertCount(1, $problemas);
        $this->assertStringContainsString('Código de Sistema', $problemas[0]);
    }

    public function test_it_detects_an_expired_token(): void
    {
        $token = SiatToken::parse($this->token(['exp' => now()->subDay()->timestamp]));

        $this->assertTrue($token->estaVencido());
        $this->assertStringContainsString('venció', $token->incoherenciasCon($this->setting())[0]);
    }

    public function test_it_accepts_an_uncompressed_nit_claim(): void
    {
        $token = SiatToken::parse($this->token(['nit' => self::NIT_EMISOR]));

        $this->assertSame(self::NIT_EMISOR, $token->nit);
    }

    public function test_it_returns_null_for_values_that_are_not_jwt(): void
    {
        $this->assertNull(SiatToken::parse(null));
        $this->assertNull(SiatToken::parse(''));
        $this->assertNull(SiatToken::parse('un-token-cualquiera'));
    }
}
