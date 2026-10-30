/**
 * Quem acabou de favoritar cai na tela do fundo com o foco em "Quando me avisar".
 * O backend só redireciona, então o aviso passa de uma página para a outra aqui.
 */
let pending = false;

export function requestWhenToAlertFocus(): void {
    pending = true;
}

export function cancelWhenToAlertFocus(): void {
    pending = false;
}

export function consumeWhenToAlertFocus(): boolean {
    const requested = pending;
    pending = false;

    return requested;
}
