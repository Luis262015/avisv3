<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Central\Plan;
use App\Models\Central\SubscriptionPayment;
use App\Models\Central\Tenant;
use App\Services\Billing\SubscriptionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): Response
    {
        $status = PaymentStatus::tryFrom((string) $request->query('status'));

        $payments = SubscriptionPayment::query()
            ->with(['tenant', 'plan', 'reviewer'])
            ->when($status, fn ($q) => $q->where('status', $status))
            // Lo que espera revisión va primero, y de eso lo más antiguo.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PaymentStatus::Pending->value])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('platform/payments/index', [
            'payments' => PaymentResource::collection($payments),
            'status' => $status?->value,
            'statuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
            'pendingCount' => SubscriptionPayment::pending()->count(),
        ]);
    }

    /** Pago que la plataforma registra a mano: efectivo, depósito ya verificado. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', Rule::exists(Tenant::class, 'id')],
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')],
            'cycle' => ['required', Rule::enum(BillingCycle::class)],
            'method' => ['required', Rule::in([PaymentMethod::Qr->value, PaymentMethod::Transfer->value, PaymentMethod::Cash->value])],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $this->subscriptions->recordPayment(
            Tenant::findOrFail($data['tenant_id']),
            Plan::findOrFail($data['plan_id']),
            BillingCycle::from($data['cycle']),
            PaymentMethod::from($data['method']),
            (float) $data['amount'],
            $data['reference'] ?? null,
            $request->user('platform'),
        );

        return back()->with('success', 'Pago registrado y suscripción al día.');
    }

    public function approve(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        try {
            $this->subscriptions->approve($payment, $request->user('platform'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pago aprobado. {$payment->tenant->name} está al día.");
    }

    public function reject(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:191']], [
            'reason.required' => 'Indica el motivo: la empresa lo verá.',
        ]);

        try {
            $this->subscriptions->reject($payment, $data['reason'], $request->user('platform'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pago rechazado.');
    }

    public function proof(SubscriptionPayment $payment): StreamedResponse
    {
        $disk = Storage::disk(SubscriptionService::PROOF_DISK);

        abort_unless($payment->proof_path && $disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path);
    }
}
