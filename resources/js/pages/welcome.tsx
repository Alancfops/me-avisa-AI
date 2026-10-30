import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Início" />
            <main className="flex min-h-screen items-center justify-center p-6">
                <h1 className="text-2xl font-semibold">Me Avisa Aí</h1>
            </main>
        </>
    );
}
