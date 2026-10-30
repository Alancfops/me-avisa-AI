import { Form, Head, router } from "@inertiajs/react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { brl, clockTime, dateTime, signedPercent } from "@/lib/format";

type Favorite = {
    ticker: string;
    name: string | null;
    price: string | null;
    changePercent: string | null;
    quotedAt: string | null;
};

type Search = {
    query: string;
    catalogEmpty: boolean;
    results: { ticker: string; name: string; favorite: boolean }[];
};

function isToday(iso: string): boolean {
    return (
        new Date(iso).toLocaleDateString("pt-BR", {
            timeZone: "America/Sao_Paulo",
        }) ===
        new Date().toLocaleDateString("pt-BR", {
            timeZone: "America/Sao_Paulo",
        })
    );
}

function FavoriteCard({ favorite }: { favorite: Favorite }) {
    return (
        <li className="space-y-2 rounded-xl border bg-card p-5 text-card-foreground">
            <h2 className="text-lg font-semibold">{favorite.ticker}</h2>
            {favorite.name && (
                <p className="text-sm text-muted-foreground">{favorite.name}</p>
            )}

            {favorite.price === null || favorite.quotedAt === null ? (
                <p>Cotação indisponível por enquanto.</p>
            ) : (
                <>
                    <p className="text-2xl font-semibold tabular-nums">
                        {brl(favorite.price)}
                    </p>
                    <p className="text-sm tabular-nums">
                        {favorite.changePercent === null
                            ? "Variação indisponível"
                            : `${signedPercent(favorite.changePercent)} hoje`}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {isToday(favorite.quotedAt)
                            ? `Cotação de ${clockTime(favorite.quotedAt)}`
                            : `Cotação de ${dateTime(favorite.quotedAt)}`}
                    </p>
                </>
            )}

            <Button
                variant="ghost"
                size="sm"
                onClick={() =>
                    router.delete(`/favoritos/${favorite.ticker}`, {
                        preserveScroll: true,
                    })
                }
            >
                Remover {favorite.ticker} dos favoritos
            </Button>
        </li>
    );
}

function FundSearch({ search }: { search: Search }) {
    return (
        <section aria-labelledby="favoritar" className="space-y-3">
            <h2 id="favoritar" className="text-lg font-semibold">
                Favoritar FII
            </h2>

            {search.catalogEmpty ? (
                <p>
                    O catálogo de fundos ainda não foi carregado. Rode{" "}
                    <code>fiis:sync-catalog</code> ou aguarde a próxima
                    atualização.
                </p>
            ) : (
                <form method="get" action="/" className="flex items-end gap-2">
                    <div className="grow space-y-1">
                        <Label htmlFor="q">Código ou nome do fundo</Label>
                        <Input
                            id="q"
                            name="q"
                            defaultValue={search.query}
                            autoComplete="off"
                        />
                    </div>
                    <Button type="submit">Buscar</Button>
                </form>
            )}

            {search.query !== "" && search.results.length === 0 && (
                <p role="status">
                    Nenhum FII encontrado. Confira o código na B3.
                </p>
            )}

            {search.results.length > 0 && (
                <ul className="divide-y rounded-xl border">
                    {search.results.map((fund) => (
                        <li
                            key={fund.ticker}
                            className="flex flex-wrap items-center justify-between gap-2 p-3"
                        >
                            <span>
                                <strong>{fund.ticker}</strong> {fund.name}
                            </span>
                            {fund.favorite ? (
                                <span className="text-sm text-muted-foreground">
                                    Já está nos favoritos
                                </span>
                            ) : (
                                <Form action="/favoritos" method="post">
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="ticker"
                                                value={fund.ticker}
                                            />
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Favoritar {fund.ticker}
                                            </Button>
                                            <InputError
                                                message={errors.ticker}
                                            />
                                        </>
                                    )}
                                </Form>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function Dashboard({
    favorites,
    search,
}: {
    favorites: Favorite[];
    search: Search;
}) {
    return (
        <>
            <Head title="Painel" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
                <h1 className="text-2xl font-semibold">Seus fundos</h1>

                <FundSearch search={search} />

                {favorites.length === 0 ? (
                    <p className="text-muted-foreground">
                        Nenhum favorito ainda. Busque um fundo acima para
                        começar.
                    </p>
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {favorites.map((favorite) => (
                            <FavoriteCard
                                key={favorite.ticker}
                                favorite={favorite}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: "Painel",
            href: "/",
        },
    ],
};
