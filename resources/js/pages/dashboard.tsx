import { Head } from '@inertiajs/react';

type Favorite = {
    ticker: string;
};

export default function Dashboard({ favorites }: { favorites: Favorite[] }) {
    return (
        <>
            <Head title="Painel" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-8">
                <h1 className="text-2xl font-semibold">Painel</h1>

                {favorites.length === 0 ? (
                    <p className="text-muted-foreground">
                        Nenhum favorito ainda
                    </p>
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {favorites.map((favorite) => (
                            <li
                                key={favorite.ticker}
                                className="rounded-xl border bg-card p-5 text-lg font-semibold text-card-foreground"
                            >
                                {favorite.ticker}
                            </li>
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
            title: 'Painel',
            href: '/',
        },
    ],
};
