<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Central\Plan;
use App\Models\Central\PlatformSetting;
use App\Models\Central\Tenant;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\PlanUsage;
use App\Services\Billing\SubscriptionService;
use App\Tenancy\Tenancy;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La renta vista desde la empresa: su plan, cuánto consume, cuándo vence y
 * cómo pagar.
 */
final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly SubscriptionService $subscriptions,
        private readonly GatewayManager $gateways,
    ) {}

    public function show(Request $request, PlanUsage $usage): Response
    {
        $tenant = $this->tenant();
        $subscription = $tenant->subscription?->load('plan');
        $settings = PlatformSetting::values();

        return Inertia::render('subscription/show', [
            'company' => [
                'name' => $tenant->name,
                'suspended' => $tenant->status === TenantStatus::Suspended,
                'suspension_reason' => $tenant->suspension_reason,
            ],
            'subscription' => $subscription ? SubscriptionResource::make($subscription)->resolve() : null,
            'usage' => $subscription ? $usage->summary($subscription->plan) : [],
            'plans' => PlanResource::collection(Plan::offered()->get())->resolve(),
            'modules' => config('plans.modules'),
            'payments' => PaymentResource::collection(
                $tenant->payments()->with('plan')->latest()->limit(12)->get(),
            )->resolve(),
            'paymentInfo' => array_merge(
                Arr::only($settings, [
                    'bank_name', 'bank_account', 'bank_holder', 'bank_document',
                    'payment_instructions', 'support_whatsapp', 'support_email',
                ]),
                ['has_qr' => filled($settings['payment_qr_path'])],
            ),
            'onlineGateway' => $this->gateways->active()?->label(),
            'canManage' => $request->user()->hasRole('admin'),
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $data = $request->validate([
            'plan' => ['required', Rule::exists(Plan::class, 'slug')->where('is_active', true)->where('is_public', true)],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
            'method' => ['required', Rule::in([PaymentMethod::Qr->value, PaymentMethod::Transfer->value])],
            'reference' => ['nullable', 'string', 'max:120'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'proof.required' => 'Adjunta el comprobante del pago.',
            'proof.max' => 'El comprobante no puede pesar más de 4 MB.',
            'proof.mimes' => 'El comprobante debe ser una imagen o un PDF.',
        ]);

        if ($tenant->payments()->where('status', PaymentStatus::Pending)->exists()) {
            return back()->with('error', 'Ya tienes un pago por revisar. Espera a que lo confirmemos antes de enviar otro.');
        }

        try {
            $this->subscriptions->submitPayment(
                $tenant,
                Plan::where('slug', $data['plan'])->firstOrFail(),
                BillingCycle::from($data['cycle']),
                PaymentMethod::from($data['method']),
                $data['reference'] ?? null,
                $request->file('proof'),
                $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Recibimos tu comprobante. Te avisaremos cuando el pago esté confirmado.');
    }

    public function payOnline(Request $request): HttpResponse
    {
        $tenant = $this->tenant();
        $gateway = $this->gateways->active();
        abort_if($gateway === null, 404);

        $data = $request->validate([
            'plan' => ['required', Rule::exists(Plan::class, 'slug')->where('is_active', true)->where('is_public', true)],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
        ]);

        $payment = $this->subscriptions->submitPayment(
            $tenant,
            Plan::where('slug', $data['plan'])->firstOrFail(),
            BillingCycle::from($data['cycle']),
            PaymentMethod::Gateway,
        );
        $payment->update(['gateway' => $this->gateways->activeName()]);

        return Inertia::location($gateway->checkoutUrl($payment, route('subscription.show')));
    }

    public function switchToFree(): RedirectResponse
    {
        try {
            $this->subscriptions->switchToFree($this->tenant());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ahora estás en el plan gratuito.');
    }

    /** El QR de cobro, que vive en el disco de la plataforma. */
    public function qr(): StreamedResponse
    {
        $this->tenant();
        $path = PlatformSetting::values()['payment_qr_path'];

        abort_unless($path && Storage::disk(SubscriptionService::PROOF_DISK)->exists($path), 404);

        return Storage::disk(SubscriptionService::PROOF_DISK)->response($path);
    }

    private function tenant(): Tenant
    {
        // Sin arrendamiento no hay renta que mostrar.
        return $this->tenancy->tenant() ?? abort(404);
    }
}
