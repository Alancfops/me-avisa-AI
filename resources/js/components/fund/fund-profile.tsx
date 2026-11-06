import { compactBrl, compactNumber, monthLabel } from '@/lib/format';
import type { FundProfile as Profile } from '@/types/fiis';

function Row({ label, value }: { label: string; value: string | null }) {
    if (value === null) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-between gap-x-4 gap-y-0.5">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 num font-semibold wrap-break-word">
                {value}
            </dd>
        </div>
    );
}

export function FundProfile({ profile }: { profile: Profile | null }) {
    return (
        <section aria-labelledby="sobre" className="surface p-5">
            <h2 id="sobre" className="text-lg font-semibold">
                Sobre o fundo
            </h2>

            {profile === null ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    Sem informe da CVM para este fundo.
                </p>
            ) : (
                <>
                    <dl className="mt-4 space-y-3 text-sm">
                        <Row label="Segmento" value={profile.segment} />
                        <Row
                            label="Administrador"
                            value={profile.administrator}
                        />
                        <Row
                            label="Patrimônio"
                            value={
                                profile.netAssets === null
                                    ? null
                                    : compactBrl(profile.netAssets)
                            }
                        />
                        <Row
                            label="Cotistas"
                            value={
                                profile.holders === null
                                    ? null
                                    : compactNumber(profile.holders)
                            }
                        />
                        <Row
                            label="Em funcionamento desde"
                            value={
                                profile.startedOn === null
                                    ? null
                                    : monthLabel(profile.startedOn)
                            }
                        />
                    </dl>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Informe da CVM de {monthLabel(profile.month)}.
                    </p>
                </>
            )}
        </section>
    );
}
