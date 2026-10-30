import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { DialogOverlay, DialogPortal } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Composto por fora do DialogContent do shadcn, que traz o botão de fechar em inglês.
 * Fica no topo, e não no centro, para o teclado do celular não cobrir o campo.
 */
export function ModalContent({
    className,
    children,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Content>) {
    return (
        <DialogPortal>
            <DialogOverlay className="bg-black/60" />
            <DialogPrimitive.Content
                className={cn(
                    'fixed top-[8svh] left-1/2 z-50 flex max-h-[84svh] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 flex-col gap-4 overflow-y-auto rounded-xl border bg-popover p-5 text-popover-foreground shadow-lg md:p-6',
                    className,
                )}
                {...props}
            >
                {children}
                <DialogPrimitive.Close
                    aria-label="Fechar"
                    className="absolute top-3 right-3 grid size-9 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                >
                    <X className="size-4" aria-hidden="true" />
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPortal>
    );
}
