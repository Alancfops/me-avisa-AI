import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl } from '@/lib/format';
import { parseShares, saveHolding } from '@/lib/portfolio';

/**
 * Quanto as cotas rendem por mês, pelo rendimento estimado (DY × VP da CVM).
 * "Salvar na carteira" grava a quantidade; quem já está na carteira começa com ela.
 */
export function IncomeCalculator({
    ticker,
    holding,
    dividends,
}: {
    ticker: string;
    holding: number | null;
    dividends: { month: string; perShare: string }[];
}) {
    const [text, setText] = useState(holding === null ? '' : String(holding));
    const [saving, setSaving] = useState(false);
    const shares = parseShares(text);
    const invalid = text.trim() !== '' && shares === null;
    const recent = dividends.slice(-12);
    const last = recent.at(-1);
    const average =
        recent.reduce((sum, month) => sum + Number(month.perShare), 0) /
        recent.length;

    return (
        <section aria-labelledby="quanto-rende" className="surface p-5">
            <h2 id="quanto-rende" className="text-lg font-semibold">
                Quanto rende
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Estimativa pelo DY e pelo VP informados à CVM. O fundo pode
                pagar outro valor.
            </p>

            <>
                <div className="mt-4 space-y-1.5">
                    <Label htmlFor="quantas-cotas">
                        Quantas cotas você tem
                    </Label>
                    <Input
                        id="quantas-cotas"
                        inputMode="numeric"
                        autoComplete="off"
                        value={text}
                        onChange={(event) => setText(event.target.value)}
                        aria-invalid={invalid}
                        aria-describedby={
                            invalid ? 'quantas-cotas-erro' : undefined
                        }
                        className="max-w-28"
                    />
                    {invalid && (
                        <p
                            id="quantas-cotas-erro"
                            className="text-sm text-destructive"
                        >
                            Informe um número inteiro de cotas, como 100.
                        </p>
                    )}
                </div>

                <div aria-live="polite" className="mt-4 space-y-1 text-sm">
                    {last === undefined && (
                        <p className="text-muted-foreground">
                            Este fundo ainda não tem rendimento informado à CVM.
                        </p>
                    )}
                    {last !== undefined && shares !== null && (
                        <>
                            <p>
                                Pelo último rendimento:{' '}
                                <span className="num font-semibold">
                                    {brl(
                                        String(Number(last.perShare) * shares),
                                    )}
                                </span>{' '}
                                por mês
                            </p>
                            <p>
                                Pela média{' '}
                                {recent.length === 12
                                    ? 'dos últimos 12 meses'
                                    : `de ${recent.length} ${recent.length === 1 ? 'mês' : 'meses'}`}
                                :{' '}
                                <span className="num font-semibold">
                                    {brl(String(average * shares))}
                                </span>{' '}
                                por mês
                            </p>
                        </>
                    )}
                </div>
            </>

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-4"
                disabled={shares === null || shares === holding || saving}
                onClick={() => {
                    if (shares !== null) {
                        setSaving(true);
                        saveHolding(ticker, shares, {
                            onFinish: () => setSaving(false),
                        });
                    }
                }}
            >
                {holding === null
                    ? 'Salvar na carteira'
                    : 'Atualizar na carteira'}
            </Button>
        </section>
    );
}
