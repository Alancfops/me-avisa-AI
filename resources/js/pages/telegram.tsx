import { Head, router, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Copy, RefreshCw, Send } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useRef, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { quoteMoment } from '@/lib/market';

type State = 'no_token' | 'invalid_token' | 'offline' | 'ready' | 'connected';

type TokenSource = 'env' | 'app' | null;

const BOT_NAME = 'Me Avisa Aí';

/** Quanto a aba espera entre as perguntas "já conectou?". */
const CONNECTION_CHECK_MS = 3000;

type Command = { command: string; example: string; description: string };

const UNAVAILABLE = 'Não conseguimos falar com o app agora. Tente de novo.';

function copy(text: string, done: string) {
    navigator.clipboard
        ?.writeText(text)
        .then(() => toast.success(done))
        .catch(() => toast.error('Não deu para copiar.'));
}

function CopyValue({ value, label }: { value: string; label: string }) {
    return (
        <span className="mt-1 flex flex-wrap items-center gap-2">
            <code className="rounded-md bg-muted px-2 py-1">{value}</code>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => copy(value, `${label} copiado.`)}
            >
                <Copy aria-hidden="true" />
                Copiar {label.toLowerCase()}
            </Button>
        </span>
    );
}

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

function TokenForm() {
    const form = useForm({ token: '' });

    return (
        <form
            className="mt-2 space-y-2"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/telegram/token', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        toast.success('Bot salvo. Falta só conectar.');
                    },
                });
            }}
        >
            <Label htmlFor="telegram-token">Token do bot</Label>
            <Input
                id="telegram-token"
                value={form.data.token}
                onChange={(event) => form.setData('token', event.target.value)}
                autoComplete="off"
                spellCheck={false}
                placeholder="123456789:AAH..."
                aria-invalid={form.errors.token !== undefined}
                aria-describedby={
                    form.errors.token ? 'telegram-token-erro' : undefined
                }
                className="num"
            />
            {form.errors.token && (
                <p
                    id="telegram-token-erro"
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {form.errors.token}
                </p>
            )}
            <Button
                type="submit"
                disabled={form.processing || form.data.token.trim() === ''}
            >
                {form.processing ? 'Conferindo…' : 'Salvar token'}
            </Button>
        </form>
    );
}

/** Texto que o @BotFather manda, para a pessoa reconhecer o passo. */
function BotSays({ children }: { children: React.ReactNode }) {
    return (
        <q className="text-muted-foreground italic" lang="en">
            {children}
        </q>
    );
}

function CreateBot({ fromEnv }: { fromEnv: boolean }) {
    return (
        <section aria-labelledby="criar-bot" className="space-y-4 surface p-5">
            <h2 id="criar-bot" className="text-lg font-semibold">
                Crie o seu bot
            </h2>
            <p className="text-sm text-muted-foreground">
                Cada instalação tem o próprio bot, porque quem responde é o app
                deste computador. Leva uns 2 minutos, e as respostas do
                @BotFather vêm em inglês.
            </p>
            <p className="rounded-lg bg-muted p-3 text-sm">
                <strong>Dica:</strong> faça pelo Telegram no computador (o
                aplicativo ou o{' '}
                <a
                    href="https://web.telegram.org"
                    target="_blank"
                    rel="noreferrer"
                    className="text-primary underline"
                >
                    web.telegram.org
                </a>
                ). Assim o token já está aqui para copiar e colar. No celular,
                você teria que mandar o token para o computador de algum jeito.
            </p>
            <ol className="space-y-5 text-sm">
                <Step n={1}>
                    Abra o{' '}
                    <a
                        href="https://t.me/BotFather"
                        target="_blank"
                        rel="noreferrer"
                        className="text-primary underline"
                    >
                        @BotFather
                    </a>
                    , o bot oficial do Telegram que cria bots (tem o selo azul
                    de verificado). Toque em <strong>Start</strong> e mande{' '}
                    <code>/newbot</code>.
                </Step>
                <Step n={2}>
                    Ele pergunta o nome (
                    <BotSays>
                        Alright, a new bot. How are we going to call it?
                    </BotSays>
                    ). Mande:
                    <CopyValue value={BOT_NAME} label="Nome" />
                </Step>
                <Step n={3}>
                    Ele pede o nome de usuário, que precisa terminar em{' '}
                    <code>bot</code> (
                    <BotSays>
                        Now let&apos;s choose a username for your bot.
                    </BotSays>
                    ). Ele é único no Telegram inteiro, então ponha algo seu no
                    meio, como <code>meavisaai_maria_bot</code>. Se ele
                    responder <BotSays>this username is already taken</BotSays>,
                    tente outro.
                </Step>
                <Step n={4}>
                    Ele responde{' '}
                    <BotSays>Done! Congratulations on your new bot.</BotSays>{' '}
                    Logo abaixo de{' '}
                    <BotSays>Use this token to access the HTTP API:</BotSays>{' '}
                    vem o token, uma linha como <code>123456789:AAH...</code>.
                    Toque nele para copiar.
                </Step>
                <Step n={5}>
                    {fromEnv ? (
                        <>
                            Ponha o token no arquivo <code>.env</code>, na linha{' '}
                            <code>TELEGRAM_BOT_TOKEN=</code>, e rode{' '}
                            <code>make start</code>.
                        </>
                    ) : (
                        <>
                            Cole o token aqui e salve. Não precisa mexer em
                            nenhum arquivo nem reiniciar o app:
                            <TokenForm />
                        </>
                    )}
                </Step>
            </ol>
            <p className="text-sm text-muted-foreground">
                Depois de salvar, o app dá ao bot a descrição e o menu de
                comandos, e aparece o QR code para conectar. Não compartilhe o
                token: ele dá controle do bot. Se vazar, mande{' '}
                <code>/revoke</code> ao @BotFather e cole o novo aqui.
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
    useEffect(() => {
        const timer = window.setInterval(() => {
            fetch('/telegram/estado', {
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((body: { connected?: boolean }) => {
                    if (body.connected) {
                        window.clearInterval(timer);
                        toast.success('Bot conectado.');
                        router.reload();
                    }
                })
                .catch(() => undefined);
        }, CONNECTION_CHECK_MS);

        return () => window.clearInterval(timer);
    }, []);

    return (
        <section aria-labelledby="conectar" className="space-y-4 surface p-5">
            <h2 id="conectar" className="text-lg font-semibold">
                Conecte o bot ao app
            </h2>
            <div className="flex flex-wrap items-start gap-5">
                <div className="rounded-lg bg-white p-3">
                    <QRCodeSVG
                        value={link}
                        size={152}
                        role="img"
                        aria-label="QR code que abre o bot no Telegram já com o código"
                    />
                </div>
                <div className="min-w-0 flex-1 basis-56 space-y-2 text-sm">
                    <p>
                        <strong>No celular:</strong> aponte a câmera para o QR
                        code e toque em Começar no Telegram.
                    </p>
                    <p>
                        <strong>Neste computador:</strong> toque em “Abrir no
                        Telegram” e depois em Começar.
                    </p>
                    <p className="text-muted-foreground" role="status">
                        Esta página muda sozinha quando o bot conectar.
                    </p>
                </div>
            </div>
            <p className="text-sm text-muted-foreground">
                Se preferir, mande ao bot <code>/start {code}</code>.
            </p>
            <div className="flex flex-wrap items-center gap-3">
                <p className="rounded-lg border px-4 py-2 num font-display text-2xl font-semibold tracking-[0.3em]">
                    <span aria-hidden="true">{code}</span>
                    <span className="sr-only">
                        Código {code.split('').join(' ')}
                    </span>
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => copy(`/start ${code}`, 'Comando copiado.')}
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

function ChangeBot() {
    return (
        <Button
            type="button"
            variant="ghost"
            onClick={() => router.delete('/telegram/token')}
        >
            Usar outro bot
        </Button>
    );
}

function Connected({
    since,
    canChangeBot,
}: {
    since: string | null;
    canChangeBot: boolean;
}) {
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
                {canChangeBot && <ChangeBot />}
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
    tokenSource,
    commands,
}: {
    state: State;
    tokenSource: TokenSource;
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
                        {tokenSource === 'env' ? (
                            <span>
                                O Telegram recusou o token. Confira a linha{' '}
                                <code>TELEGRAM_BOT_TOKEN</code> do{' '}
                                <code>.env</code> e rode <code>make start</code>
                                .
                            </span>
                        ) : (
                            <span>
                                O Telegram recusou o token salvo. Talvez ele
                                tenha sido trocado no @BotFather: cole o novo
                                abaixo.
                            </span>
                        )}
                    </p>
                )}

                {state === 'offline' && (
                    <div
                        role="alert"
                        className="flex flex-wrap items-center justify-between gap-3 surface p-4 text-sm"
                    >
                        <span className="flex items-start gap-2">
                            <CircleAlert
                                className="mt-0.5 size-4 shrink-0 text-destructive"
                                aria-hidden="true"
                            />
                            O Telegram não respondeu agora. Confira a internet;
                            o bot continua configurado.
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => router.reload()}
                        >
                            <RefreshCw aria-hidden="true" />
                            Tentar de novo
                        </Button>
                    </div>
                )}

                {(state === 'no_token' || state === 'invalid_token') && (
                    <CreateBot fromEnv={tokenSource === 'env'} />
                )}
                {state === 'ready' && code && link && (
                    <Connect
                        code={code}
                        link={link}
                        expiresAt={codeExpiresAt}
                    />
                )}
                {state === 'ready' && tokenSource === 'app' && (
                    <div className="-mt-3 flex justify-end">
                        <ChangeBot />
                    </div>
                )}
                {state === 'connected' && (
                    <Connected
                        since={connectedAt}
                        canChangeBot={tokenSource === 'app'}
                    />
                )}

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
