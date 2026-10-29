import { createFileRoute, Link } from "@tanstack/react-router";
import { Area, AreaChart, ResponsiveContainer } from "recharts";
import { Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { SinalBadge } from "@/components/sinal-badge";
import { brl, fiis, pct, pvp, sinal } from "@/lib/fiis";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "Painel — Me Avisa Aí" },
      { name: "description", content: "Seus FIIs favoritos, alvos de preço e sinais de compra num só lugar." },
      { property: "og:title", content: "Painel — Me Avisa Aí" },
      { property: "og:description", content: "Seus FIIs favoritos, alvos de preço e sinais de compra." },
    ],
  }),
  component: Painel,
});

function Painel() {
  const bons = fiis.filter((f) => sinal(f) === "comprar");
  const quedas = fiis.filter((f) => sinal(f) === "queda");

  return (
    <div className="mx-auto max-w-6xl space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm text-muted-foreground">Quinta, 29 de outubro</p>
          <h1 className="text-3xl font-semibold md:text-4xl">Seus fundos</h1>
        </div>
        <Button><Plus /> Favoritar FII</Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Stat label="Favoritos" valor={String(fiis.length)} />
        <Stat label="Com preço bom agora" valor={String(bons.length)} destaque="signal" />
        <Stat label="Quedas fortes hoje" valor={String(quedas.length)} destaque="drop" />
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {fiis.map((f) => {
          const s = sinal(f);
          return (
            <Link
              key={f.ticker}
              to="/fundo/$ticker"
              params={{ ticker: f.ticker }}
              className={cn("surface group block p-5 transition-transform hover:-translate-y-0.5", s === "comprar" && "surface-signal")}
            >
              <div className="flex items-start justify-between">
                <div>
                  <div className="font-display text-lg font-semibold">{f.ticker}</div>
                  <div className="text-xs text-muted-foreground">{f.nome} · {f.segmento}</div>
                </div>
                <SinalBadge sinal={s} />
              </div>
              <div className="mt-4 flex items-end justify-between">
                <div>
                  <div className="num text-2xl font-semibold">{brl(f.preco)}</div>
                  <div className={cn("num text-sm", f.variacaoDia < 0 ? "text-destructive" : "text-signal")}>
                    {pct(f.variacaoDia)} hoje
                  </div>
                </div>
                <div className="h-12 w-28">
                  <ResponsiveContainer>
                    <AreaChart data={f.historico.slice(-30)}>
                      <Area
                        dataKey="preco"
                        type="monotone"
                        stroke={s === "comprar" ? "var(--signal)" : "var(--primary)"}
                        fill={s === "comprar" ? "var(--signal-soft)" : "var(--primary-soft)"}
                        strokeWidth={2}
                        isAnimationActive={false}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              </div>
              <div className="mt-4 grid grid-cols-3 gap-2 border-t pt-3 text-xs">
                <Mini label="Alvo" v={f.alvo ? brl(f.alvo) : f.faixa ? `${f.faixa[0]}–${f.faixa[1]}` : "Auto"} />
                <Mini label="P/VP" v={pvp(f).toFixed(2).replace(".", ",")} />
                <Mini label="DY 12m" v={`${f.dy12m.toFixed(1).replace(".", ",")}%`} />
              </div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}

function Stat({ label, valor, destaque }: { label: string; valor: string; destaque?: "signal" | "drop" }) {
  return (
    <div className="surface p-5">
      <div className="text-sm text-muted-foreground">{label}</div>
      <div className={cn("num mt-1 text-3xl font-semibold", destaque === "signal" && "text-signal", destaque === "drop" && "text-destructive")}>
        {valor}
      </div>
    </div>
  );
}

function Mini({ label, v }: { label: string; v: string }) {
  return (
    <div>
      <div className="text-muted-foreground">{label}</div>
      <div className="num font-medium">{v}</div>
    </div>
  );
}
