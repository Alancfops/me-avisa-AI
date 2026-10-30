/**
 * Sem o primitivo Switch instalado: checkbox nativo com role="switch", que já
 * traz nome, estado e teclado. A posição da bolinha mostra o estado sem depender da cor.
 */
export function SwitchField({
    id,
    label,
    description,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    const descriptionId = `${id}-descricao`;

    return (
        <label
            htmlFor={id}
            className="relative flex cursor-pointer items-start justify-between gap-3"
        >
            <span className="min-w-0">
                <span className="block text-sm font-medium">{label}</span>
                {description && (
                    <span
                        id={descriptionId}
                        className="block text-xs text-muted-foreground"
                    >
                        {description}
                    </span>
                )}
            </span>
            <input
                id={id}
                type="checkbox"
                role="switch"
                checked={checked}
                aria-describedby={description ? descriptionId : undefined}
                onChange={(event) => onChange(event.target.checked)}
                className="peer sr-only"
            />
            <span
                aria-hidden="true"
                className="relative mt-0.5 h-6 w-11 shrink-0 rounded-full border-2 border-input peer-checked:border-primary peer-checked:bg-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-muted-foreground peer-checked:after:translate-x-5 peer-checked:after:bg-primary-foreground motion-safe:after:transition-transform"
            />
        </label>
    );
}
