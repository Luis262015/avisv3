<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformSetting;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Datos de cobro y de contacto que ven las empresas al pagar.
 */
final class SettingController extends Controller
{
    public function edit(): Response
    {
        $settings = PlatformSetting::values();

        return Inertia::render('platform/settings', [
            'settings' => Arr::except($settings, ['payment_qr_path']),
            'hasQr' => filled($settings['payment_qr_path']),
            'gateway' => config('billing.gateway'),
            'graceDays' => (int) config('billing.grace_days'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:80'],
            'support_email' => ['nullable', 'email', 'max:191'],
            'support_whatsapp' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:60'],
            'bank_holder' => ['nullable', 'string', 'max:120'],
            'bank_document' => ['nullable', 'string', 'max:30'],
            'payment_instructions' => ['nullable', 'string', 'max:1000'],
            'qr' => ['nullable', 'image', 'max:2048'],
            'remove_qr' => ['boolean'],
        ]);

        $disk = Storage::disk(SubscriptionService::PROOF_DISK);
        $current = PlatformSetting::values()['payment_qr_path'];

        if ($request->hasFile('qr') || $request->boolean('remove_qr')) {
            if ($current) {
                $disk->delete($current);
            }

            $data['payment_qr_path'] = $request->file('qr')?->store('settings', SubscriptionService::PROOF_DISK);
        }

        PlatformSetting::put(Arr::except($data, ['qr', 'remove_qr']));

        return back()->with('success', 'Ajustes guardados.');
    }

    public function qr(): StreamedResponse
    {
        $disk = Storage::disk(SubscriptionService::PROOF_DISK);
        $path = PlatformSetting::values()['payment_qr_path'];

        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path);
    }
}
