import { Head, router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Copy, Send } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ModalContent } from '@/components/modal';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { quoteMoment } from '@/lib/market';

type State = 'no_token' | 'invalid_token' | 'ready' | 'connected';

type Command = { command: string; example: string; description: string };

const UNAVAILABLE = 'Não conseguimos falar com o app agora. Tente de novo.';

function Step({ n, children }: { n: number; children: React.ReactNode }) {
    return (
        <li className="flex gap-3">
            <span
                aria-hidden="true"
                className="grid size-7 shrink-0 place-items-center rounded-full bg-primary-soft text-sm font-semibold text-primary"
            >
                {n}
            </span>
            <div className="min-w-0 pt-0.5">{children}</div>
        </li>
    );
}

function CreateBot() {
    return (
        <section aria-labelledby="criar-bot" className="space-y-4 surface p-5">
            <h2 id="criar-bot" className="text-lg font-semibold">
                Crie o seu bot
            </h2>
            <ol className="space-y-4 text-sm">
                <Step n={1}>
                    No Telegram, abra o{' '}
                    <a
                        href="https://t.me/BotFather"
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary underline"
                    >
                        @BotFather
                    </a>
                    , mande <code>/newbot</code> e escolha um nome.
                </Step>
                <Step n={2}>
                    Copie o token que ele devolver e coloque no arquivo{' '}
                    <code>.env</code> do app:
                    <pre className="mt-2 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                        TELEGRAM_BOT_TOKEN=seu_token
                    </pre>
                </Step>
                <Step n={3}>
                    Suba o app de novo com <code>make start</code> e volte a
                    esta página.
                </Step>
            </ol>
            <p className="text-sm text-muted-foreground">
                Não compartilhe o token: ele dá controle do bot. Se vazar, mande{' '}
                <code>/revoke</code> ao @BotFather e troque no <code>.env</code>
                .
            </p>
        </section>
    );
}

function Connect({
    code,
    link,
    expiresAt,
}: {
    code: string;
    link: string;
    expiresAt: string | null;
}) {
    return (
        <section aria-labelledby="conectar" className="space-y-4 surface p-5">
            <h2 id="conectar" className="text-lg font-semibold">
                Conecte o bot ao app
            </h2>
            <p className="text-sm text-muted-foreground">
                Toque em “Abrir no Telegram” e depois em Começar. Se preferir,
                mande ao bot <code>/start {code}</code>.
            </p>
            <div className="flex flex-wrap items-center gap-3">
                <p
                    className="rounded-lg border px-4 py-2 num font-display text-2xl font-semibold tracking-[0.3em]"
                    aria-label={`Código ${code.split('').join(' ')}`}
                >
                    {code}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        navigator.clipboard
                            ?.writeText(`/start ${code}`)
                            .then(() => toast.success('Comando copiado.'))
                            .catch(() => toast.error('Não deu para copiar.'));
                    }}
                >
                    <Copy aria-hidden="true" />
                    Copiar comando
                </Button>
            </div>
            {expiresAt && (
                <p className="text-sm text-muted-foreground">
                    Vale até {quoteMoment(expiresAt)}.
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                <Button asChild>
                    <a href={link} target="_blank" rel="noreferrer">
                        <Send aria-hidden="true" />
                        Abrir no Telegram
                    </a>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            '/telegram/codigo',
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Gerar outro código
                </Button>
            </div>
        </section>
    );
}

function Connected({ since }: { since: string | null }) {
    const [sending, setSending] = useState(false);
    const cancel = useRef<HTMLButtonElement>(null);

    function sendTest() {
        setSending(true);
        router.post(
            '/telegram/teste',
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Mensagem de teste enviada.'),
                onError: (errors) =>
                    toast.error(errors.telegram ?? UNAVAILABLE),
                onHttpException: () => {
                    toast.error(UNAVAILABLE);

                    return false;
                },
                onFinish: () => setSending(false),
            },
        );
    }

    return (
        <section aria-labelledby="conectado" className="space-y-4 surface p-5">
            <h2
                id="conectado"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <CircleCheck
                    className="size-5 text-signal"
                    aria-hidden="true"
                />
                Conectado
            </h2>
            {since && (
                <p className="text-sm text-muted-foreground">
                    Desde {quoteMoment(since)}. Todo aviso do site também chega
                    no Telegram.
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                <Button type="button" disabled={sending} onClick={sendTest}>
                    <Send aria-hidden="true" />
                    {sending ? 'Enviando…' : 'Enviar mensagem de teste'}
                </Button>
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                        >
                            Desconectar
                        </Button>
                    </DialogTrigger>
                    <ModalContent
                        role="alertdialog"
                        onOpenAutoFocus={(event) => {
                            event.preventDefault();
                            cancel.current?.focus();
                        }}
                    >
                        <div className="space-y-1 pr-8">
                            <DialogTitle className="font-display text-xl">
                                Desconectar o Telegram?
                            </DialogTitle>
                            <DialogDescription>
                                Os avisos param de chegar no Telegram e
                                continuam no site. Para voltar, gere um código
                                novo nesta página.
                            </DialogDescription>
                        </div>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <DialogClose asChild>
                                <Button ref={cancel} variant="outline">
                                    Cancelar
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                className="text-destructive-foreground"
                                onClick={() => router.delete('/telegram')}
                            >
                                Desconectar
                            </Button>
                        </div>
                    </ModalContent>
                </Dialog>
            </div>
        </section>
    );
}

export default function Telegram({
    state,
    bot,
    code,
    codeExpiresAt,
    connectedAt,
    link,
    commands,
}: {
    state: State;
    bot: { username: string; name: string } | null;
    code: string | null;
    codeExpiresAt: string | null;
    connectedAt: string | null;
    link: string | null;
    commands: Command[];
}) {
    return (
        <>
            <Head title="Telegram" />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <PageHeader
                    title="Telegram"
                    description={
                        bot
                            ? `Bot @${bot.username} (${bot.name}).`
                            : 'Receba os avisos e consulte seus fundos pelo Telegram.'
                    }
                />

                {state === 'invalid_token' && (
                    <p
                        role="alert"
                        className="flex items-start gap-2 surface p-4 text-sm"
                    >
                        <CircleAlert
                            className="mt-0.5 size-4 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                        O Telegram recusou o token, ou não respondeu. Confira o{' '}
                        <code>TELEGRAM_BOT_TOKEN</code> no <code>.env</code> e
                        reinicie o app.
                    </p>
                )}

                {(state === 'no_token' || state === 'invalid_token') && (
                    <CreateBot />
                )}
                {state === 'ready' && code && link && (
                    <Connect
                        code={code}
                        link={link}
                        expiresAt={codeExpiresAt}
                    />
                )}
                {state === 'connected' && <Connected since={connectedAt} />}

                <section
                    aria-labelledby="comandos"
                    className="space-y-3 surface p-5"
                >
                    <h2 id="comandos" className="text-lg font-semibold">
                        Comandos do bot
                    </h2>
                    <dl className="divide-y text-sm">
                        {commands.map((item) => (
                            <div
                                key={item.command}
                                className="flex flex-wrap gap-x-4 gap-y-1 py-2"
                            >
                                <dt className="w-32 shrink-0">
                                    <code>{item.example}</code>
                                </dt>
                                <dd className="text-muted-foreground">
                                    {item.description}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        Os links das mensagens só abrem neste computador, onde o
                        app roda. No celular, use os comandos.
                    </p>
                </section>
            </div>
        </>
    );
}
