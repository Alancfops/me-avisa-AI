import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl } from '@/lib/format';
import { ceilingPrice } from '@/lib/fund-list';

const STORAGE_KEY = 'meavisaai:ceiling-rate';
const DEFAULT_RATE = '10';

function storedRate(): string {
    try {
        return window.localStorage.getItem(STORAGE_KEY) ?? DEFAULT_RATE;
    } catch {
        return DEFAULT_RATE;
    }
}

function storeRate(value: string): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Navegador sem armazenamento: a taxa só vale até fechar a página.
    }
}

/** Aceita "10", "10,5" e "10.5"; devolve null para o que não é percentual útil. */
function parseRate(text: string): number | null {
    const rate = Number(text.trim().replace(',', '.'));

    return Number.isFinite(rate) && rate > 0 && rate <= 100 ? rate : null;
}

/** "133.5" vira "133,50", o formato do campo de alvo. */
function toTarget(value: number): string {
    return value.toFixed(2).replace('.', ',');
}

export function CeilingPrice({
    dividends,
    price,
    priceNote,
    onUseAsTarget,
}: {
    dividends: { month: string; perShare: string }[];
    price: string | null;
    /** De onde vem o preço atual, ex.: "fechamento de 30/10 (B3)". */
    priceNote: string;
    /** Só favoritos: preenche o alvo em "Quando me avisar" sem salvar. */
    onUseAsTarget?: (target: string) => void;
}) {
    const [rateText, setRateText] = useState(storedRate);
    const rate = parseRate(rateText);
    const result = rate === null ? null : ceilingPrice(dividends, rate);

    function change(value: string) {
        setRateText(value);

        if (parseRate(value) !== null) {
            storeRate(value.trim());
        }
    }

    return (
        <section aria-labelledby="preco-teto" className="surface p-5">
            <h2 id="preco-teto" className="text-lg font-semibold">
                Preço teto pelo rendimento
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Rendimento estimado dos últimos 12 meses dividido pelo que você
                quer render ao ano.
            </p>

            <div className="mt-4 space-y-1.5">
                <Label htmlFor="taxa-anual">Quero render (% ao ano)</Label>
                <div className="flex items-center gap-2">
                    <Input
                        id="taxa-anual"
                        inputMode="decimal"
                        autoComplete="off"
                        value={rateText}
                        onChange={(event) => change(event.target.value)}
                        aria-invalid={rate === null}
                        aria-describedby={
                            rate === null ? 'taxa-anual-erro' : undefined
                        }
                        className="max-w-28"
                    />
                    <span aria-hidden="true" className="text-muted-foreground">
                        %
                    </span>
                </div>
                {rate === null && (
                    <p
                        id="taxa-anual-erro"
                        className="text-sm text-destructive"
                    >
                        Informe um percentual maior que zero, como 10 ou 10,5.
                    </p>
                )}
            </div>

            <div aria-live="polite" className="mt-4 space-y-1 text-sm">
                {result !== null && 'missingMonths' in result && (
                    <p className="text-muted-foreground">
                        Faltam {result.missingMonths}{' '}
                        {result.missingMonths === 1 ? 'mês' : 'meses'} de
                        rendimento para calcular.
                    </p>
                )}

                {result !== null && 'value' in result && (
                    <>
                        <p className="num text-2xl font-semibold">
                            Preço teto: {brl(String(result.value))}
                        </p>
                        {price !== null && (
                            <Comparison
                                ceiling={result.value}
                                price={Number(price)}
                                note={priceNote}
                            />
                        )}
                    </>
                )}
            </div>

            {onUseAsTarget && result !== null && 'value' in result && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="mt-4"
                    onClick={() => onUseAsTarget(toTarget(result.value))}
                >
                    Usar como alvo
                </Button>
            )}
        </section>
    );
}

function Comparison({
    ceiling,
    price,
    note,
}: {
    ceiling: number;
    price: number;
    note: string;
}) {
    const diff = Math.round((ceiling - price) * 100) / 100;

    return (
        <p className="num text-muted-foreground">
            {diff === 0
                ? 'Igual ao preço atual'
                : `${brl(String(Math.abs(diff)))} ${diff > 0 ? 'acima' : 'abaixo'} do preço atual`}{' '}
            ({brl(String(price))}, {note})
        </p>
    );
}
