import { Button } from '@/components/ui/button';
import { Camera, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    onScan: (barcode: string) => boolean;
    onClose: () => void;
}

function playBeep(success: boolean) {
    try {
        const AC = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        const ctx = new AC();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = success ? 1800 : 400;
        const duration = success ? 0.15 : 0.3;
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration);
        osc.onended = () => ctx.close();
    } catch {
        // audio not available
    }
}

type ScanStatus = 'scanning' | 'found' | 'not_found';

export default function BarcodeScanner({ onScan, onClose }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const controlsRef = useRef<{ stop: () => void } | null>(null);
    const cooldownRef = useRef(false);
    const mountedRef = useRef(true);

    const [status, setStatus] = useState<ScanStatus>('scanning');
    const [lastBarcode, setLastBarcode] = useState('');
    const [cameraError, setCameraError] = useState('');

    useEffect(() => {
        mountedRef.current = true;

        async function startScanner() {
            try {
                const { BrowserMultiFormatReader } = await import('@zxing/browser');
                const reader = new BrowserMultiFormatReader();
                const devices = await BrowserMultiFormatReader.listVideoInputDevices();

                if (!devices.length) {
                    if (mountedRef.current) setCameraError('No se encontró ninguna cámara.');
                    return;
                }

                // Prefer rear/back camera on mobile
                const device =
                    devices.find((d) => /back|rear|environment/i.test(d.label)) ?? devices[0];

                const controls = await reader.decodeFromVideoDevice(
                    device.deviceId,
                    videoRef.current!,
                    (result) => {
                        if (!result || cooldownRef.current || !mountedRef.current) return;

                        cooldownRef.current = true;
                        const barcode = result.getText();
                        setLastBarcode(barcode);

                        const found = onScan(barcode);
                        setStatus(found ? 'found' : 'not_found');
                        playBeep(found);

                        setTimeout(
                            () => {
                                if (mountedRef.current) setStatus('scanning');
                                cooldownRef.current = false;
                            },
                            found ? 1200 : 2000,
                        );
                    },
                );

                if (mountedRef.current) controlsRef.current = controls;
            } catch {
                if (mountedRef.current)
                    setCameraError('No se pudo acceder a la cámara. Verifica los permisos del navegador.');
            }
        }

        startScanner();

        return () => {
            mountedRef.current = false;
            controlsRef.current?.stop();
        };
    }, []);

    return (
        <>
            <style>{`
                @keyframes barcode-scan {
                    0%   { top: 8%;  opacity: 1; }
                    48%  { top: 84%; opacity: 1; }
                    50%  { top: 84%; opacity: 0; }
                    52%  { top: 8%;  opacity: 0; }
                    54%  { top: 8%;  opacity: 1; }
                    100% { top: 8%;  opacity: 1; }
                }
                .barcode-scan-line {
                    animation: barcode-scan 2s ease-in-out infinite;
                }
            `}</style>

            <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/75 p-0 sm:items-center sm:p-4">
                <div className="w-full overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-w-sm sm:rounded-2xl">

                    {/* Header */}
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Camera className="h-5 w-5 text-blue-600" />
                            <span className="font-semibold text-gray-800">Escáner de código de barras</span>
                        </div>
                        <Button type="button" variant="ghost" size="icon" onClick={onClose}>
                            <X className="h-4 w-4" />
                        </Button>
                    </div>

                    {/* Camera viewport */}
                    <div className="relative bg-black" style={{ aspectRatio: '4/3' }}>
                        <video
                            ref={videoRef}
                            className="h-full w-full object-cover"
                            playsInline
                            muted
                        />

                        {!cameraError && (
                            <div className="pointer-events-none absolute inset-0">
                                {/* Dimmed overlay with cutout effect via box-shadow */}
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div
                                        className="relative"
                                        style={{ width: '78%', height: '44%' }}
                                    >
                                        {/* Semi-transparent surrounding */}
                                        <div
                                            className="absolute inset-0"
                                            style={{ boxShadow: '0 0 0 9999px rgba(0,0,0,0.5)' }}
                                        />

                                        {/* Corner brackets */}
                                        <span className="absolute left-0 top-0 h-7 w-7 border-l-[3px] border-t-[3px] border-green-400" />
                                        <span className="absolute right-0 top-0 h-7 w-7 border-r-[3px] border-t-[3px] border-green-400" />
                                        <span className="absolute bottom-0 left-0 h-7 w-7 border-b-[3px] border-l-[3px] border-green-400" />
                                        <span className="absolute bottom-0 right-0 h-7 w-7 border-b-[3px] border-r-[3px] border-green-400" />

                                        {/* Animated scan line */}
                                        {status === 'scanning' && (
                                            <div
                                                className="barcode-scan-line absolute left-0 right-0 h-0.5"
                                                style={{
                                                    background:
                                                        'linear-gradient(to right, transparent 0%, #4ade80 30%, #4ade80 70%, transparent 100%)',
                                                }}
                                            />
                                        )}
                                    </div>
                                </div>

                                {/* Success flash */}
                                {status === 'found' && (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <div className="flex flex-col items-center gap-2 rounded-xl bg-green-500/90 px-6 py-4 text-white shadow-lg">
                                            <svg className="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm font-semibold">Producto agregado</span>
                                        </div>
                                    </div>
                                )}

                                {/* Not found flash */}
                                {status === 'not_found' && (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <div className="flex flex-col items-center gap-1 rounded-xl bg-red-500/90 px-5 py-4 text-white shadow-lg">
                                            <svg className="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span className="text-sm font-semibold">No encontrado</span>
                                            <span className="font-mono text-xs opacity-80">{lastBarcode}</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Status bar */}
                    <div className="px-4 py-3 text-center">
                        {cameraError ? (
                            <p className="text-sm text-red-500">{cameraError}</p>
                        ) : status === 'found' ? (
                            <p className="text-sm font-medium text-green-600">✓ Producto agregado al carrito</p>
                        ) : status === 'not_found' ? (
                            <p className="text-sm font-medium text-red-500">
                                Código <span className="font-mono">{lastBarcode}</span> no está en el catálogo
                            </p>
                        ) : (
                            <p className="text-sm text-gray-500">
                                Apunta la cámara al código de barras del producto
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
