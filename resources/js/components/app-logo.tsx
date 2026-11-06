import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            {/* A mesma logo da aba; decorativa, porque o nome vem logo ao lado. */}
            <img
                src="/favicon.svg?v=2"
                alt=""
                className="aspect-square size-8 shrink-0 rounded-lg"
            />
            <span className="ml-1 flex-1 text-left font-display text-base leading-tight font-semibold">
                {name}
            </span>
        </>
    );
}
