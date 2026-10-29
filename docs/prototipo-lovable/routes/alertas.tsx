import { createFileRoute, Link } from "@tanstack/react-router";
import { Send } from "lucide-react";
import { SinalBadge } from "@/components/sinal-badge";
import { alertas } from "@/lib/fiis";

export const Route = createFileRoute("/alertas")({
  head: () => ({
    meta: [
      { title: "Avisos — Me Avisa Aí" },
      { name: "description", content: "Histórico dos avisos enviados no Telegram." },
      { property: "og:title", content: "Avisos — Me Avisa Aí" },
      { property: "og:description", content: "Histórico dos avisos enviados no Telegram." },
    ],
  }),
  component: Alertas,
});

function Alertas() {
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-3xl font-semibold md:text-4xl">Avisos</h1>
        <p className="text-muted-foreground">O que já foi mandado pro seu Telegram.</p>
      </div>
      <div className="surface divide-y">
        {alertas.map((a) => (
          <Link key={a.id} to="/fundo/$ticker" params={{ ticker: a.ticker }} className="flex items-center gap-4 p-4 hover:bg-accent/50">
            <div className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary">
              <Send className="size-4" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <span className="font-display font-semibold">{a.ticker}</span>
                <span className="text-xs text-muted-foreground">· {a.tipo}</span>
              </div>
              <div className="truncate text-sm text-muted-foreground">{a.texto}</div>
            </div>
            <div className="flex flex-col items-end gap-1">
              <SinalBadge sinal={a.sinal} />
              <span className="text-xs text-muted-foreground">{a.quando}</span>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
