import { Field, TextArea } from '@/components/field';
import { Panel } from '@/components/platform/ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PlatformLayout from '@/layouts/platform-layout';
import { useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

type Settings = {
    brand_name: string | null;
    support_email: string | null;
    support_whatsapp: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_holder: string | null;
    bank_document: string | null;
    payment_instructions: string | null;
};

type Form = { [K in keyof Settings]: string } & { qr: File | null; remove_qr: boolean };

export default function PlatformSettings({ settings, hasQr, gateway, graceDays }: { settings: Settings; hasQr: boolean; gateway: string | null; graceDays: number }) {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm<Form>({
        brand_name: settings.brand_name ?? '',
        support_email: settings.support_email ?? '',
        support_whatsapp: settings.support_whatsapp ?? '',
        bank_name: settings.bank_name ?? '',
        bank_account: settings.bank_account ?? '',
        bank_holder: settings.bank_holder ?? '',
        bank_document: settings.bank_document ?? '',
        payment_instructions: settings.payment_instructions ?? '',
        qr: null,
        remove_qr: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // Con archivo adjunto el envío es multipart.
        post('/plataforma/ajustes', { preserveScroll: true, forceFormData: true });
    };

    const campo = (clave: keyof Settings) => ({
        value: data[clave],
        onChange: (e: React.ChangeEvent<HTMLInputElement>) => setData(clave, e.target.value),
    });

    return (
        <PlatformLayout title="Ajustes" description="Los datos con los que las empresas te pagan y te contactan.">
            <form onSubmit={submit} noValidate className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <div className="grid content-start gap-6">
                    <Panel title="Cobro por QR o transferencia" description="Se muestran a cada empresa en su página «Plan y pagos».">
                        <div className="grid gap-5 p-5 sm:grid-cols-2">
                            <Field label="Banco" optional error={errors.bank_name}>
                                <Input {...campo('bank_name')} />
                            </Field>
                            <Field label="Número de cuenta" optional error={errors.bank_account}>
                                <Input {...campo('bank_account')} />
                            </Field>
                            <Field label="Titular" optional error={errors.bank_holder}>
                                <Input {...campo('bank_holder')} />
                            </Field>
                            <Field label="NIT o CI del titular" optional error={errors.bank_document}>
                                <Input {...campo('bank_document')} />
                            </Field>
                            <Field
                                label="Indicaciones para quien paga"
                                optional
                                error={errors.payment_instructions}
                                className="sm:col-span-2"
                                hint="Por ejemplo: qué poner en la glosa o en cuánto tiempo confirmas el pago."
                            >
                                <TextArea value={data.payment_instructions} onChange={(e) => setData('payment_instructions', e.target.value)} />
                            </Field>
                        </div>

                        <div className="grid gap-5 border-t p-5 sm:grid-cols-[auto_1fr] sm:items-start">
                            {hasQr && !data.remove_qr ? (
                                <img src="/plataforma/ajustes/qr" alt="QR de cobro actual" className="size-36 rounded-lg border bg-white object-contain p-2" />
                            ) : (
                                <div className="text-muted-foreground flex size-36 items-center justify-center rounded-lg border border-dashed text-center text-xs">Sin QR cargado</div>
                            )}
                            <div className="grid gap-3">
                                <Field label="Imagen del QR de cobro" optional error={errors.qr} hint="PNG o JPG, hasta 2 MB. Reemplaza al actual.">
                                    <Input type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => setData('qr', e.target.files?.[0] ?? null)} />
                                </Field>
                                {hasQr && (
                                    <label className="flex cursor-pointer items-center gap-2.5 text-sm">
                                        <input type="checkbox" className="size-4" checked={data.remove_qr} onChange={(e) => setData('remove_qr', e.target.checked)} />
                                        Quitar el QR actual
                                    </label>
                                )}
                            </div>
                        </div>
                    </Panel>
                </div>

                <div className="grid content-start gap-6">
                    <Panel title="Contacto" description="Aparece en el pie del sitio y en la página de pagos.">
                        <div className="grid gap-5 p-5">
                            <Field label="Correo de soporte" optional error={errors.support_email}>
                                <Input type="email" {...campo('support_email')} />
                            </Field>
                            <Field label="WhatsApp" optional error={errors.support_whatsapp} hint="Con código de país: 59170012345.">
                                <Input type="tel" {...campo('support_whatsapp')} />
                            </Field>
                        </div>
                    </Panel>

                    <Panel title="Reglas de cobro">
                        <dl className="grid gap-4 p-5 text-sm">
                            <div>
                                <dt className="font-medium">Tolerancia tras el vencimiento</dt>
                                <dd className="text-muted-foreground mt-0.5">
                                    {graceDays} {graceDays === 1 ? 'día' : 'días'}. Se cambia con <code className="font-mono text-xs">BILLING_GRACE_DAYS</code> en el servidor.
                                </dd>
                            </div>
                            <div>
                                <dt className="font-medium">Pago en línea</dt>
                                <dd className="text-muted-foreground mt-0.5">
                                    {gateway ? (
                                        <>
                                            Pasarela activa: <strong className="text-foreground">{gateway}</strong>.
                                        </>
                                    ) : (
                                        'Sin pasarela conectada. Las empresas pagan por QR o transferencia y tú apruebas el comprobante.'
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </Panel>

                    <div className="flex items-center gap-4">
                        <Button type="submit" size="lg" disabled={processing}>
                            Guardar ajustes
                        </Button>
                        <p role="status" className="text-sm font-medium text-green-700">
                            {recentlySuccessful ? 'Guardado' : ''}
                        </p>
                    </div>
                </div>
            </form>
        </PlatformLayout>
    );
}
