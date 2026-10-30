import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';

export default function Appearance() {
    return (
        <>
            <Head title="Configurações" />

            <div className="space-y-6 p-4 md:p-8">
                <h1 className="sr-only">Configurações</h1>
                <Heading
                    variant="small"
                    title="Tema"
                    description="Escolha o tema claro, o escuro ou o do sistema"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Configurações',
            href: '/settings/appearance',
        },
    ],
};
