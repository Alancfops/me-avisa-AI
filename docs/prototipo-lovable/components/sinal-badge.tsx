import type { Sinal } from "@/lib/fiis";
import { cn } from "@/lib/utils";

const estilos: Record<Sinal, { label: string; cls: string }> = {
  comprar: { label: "Preço bom", cls: "bg-signal-soft text-signal" },
  queda: { label: "Queda forte", cls: "bg-drop-soft text-destructive" },
  observando: { label: "Observando", cls: "bg-muted text-muted-foreground" },
};

export function SinalBadge({ sinal, className }: { sinal: Sinal; className?: string }) {
  const e = estilos[sinal];
  return (
    <span className={cn("inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold", e.cls, className)}>
      <span className="size-1.5 rounded-full bg-current" />
      {e.label}
    </span>
  );
}
