import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ModalContent } from '@/components/modal';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function RemoveFavorite({ ticker }: { ticker: string }) {
    const [processing, setProcessing] = useState(false);
    const cancel = useRef<HTMLButtonElement>(null);

    function remove() {
        const unavailable = () => {
            toast.error(
                'Não conseguimos remover agora. Tente de novo em instantes.',
            );

            return false;
        };

        setProcessing(true);
        router.delete(`/favoritos/${ticker}`, {
            onHttpException: unavailable,
            onNetworkError: unavailable,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 aria-hidden="true" />
                    Remover dos favoritos
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
                        Remover {ticker} dos favoritos?
                    </DialogTitle>
                    <DialogDescription>
                        O fundo sai do painel e deixa de gerar avisos.
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
                        disabled={processing}
                        onClick={remove}
                    >
                        Remover dos favoritos
                    </Button>
                </div>
            </ModalContent>
        </Dialog>
    );
}
