// Sem import ou export, o `declare module` abaixo substituiria os tipos do pacote
// em vez de ampliá-los.
export {};

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
