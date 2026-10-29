import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { Area, AreaChart, CartesianGrid, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { SinalBadge } from "@/components/sinal-badge";
import { brl, fiis, media30, pct, pvp, sinal } from "@/lib/fiis";

export const Route = createFileRoute("/fundo/$ticker")({
  loader: ({ params }) => {
    const f = fiis.find((x) => x.ticker === params.ticker);
    if (!f) throw notFound();
    return { ticker: f.ticker };
  },
  head: ({ loaderData }) => ({
    meta: [
      { title: `${loaderData?.ticker ?? "Fundo"} — Me Avisa Aí` },
      { name: "description", content: "Gráfico, alvo de preço e regras de aviso do fundo." },
      { property: "og:title", content: `${loaderData?.ticker ?? "Fundo"} — Me Avisa Aí` },
      { property: "og:description", content: "Gráfico, alvo de preço e regras de aviso do fundo." },
    ],
  }),
  component: Fundo,
});

function Fundo() {
  const { ticker } = Route.useLoaderData();
  const f = fiis.find((x) => x.ticker === ticker)!;
  const m30 = media30(f);

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <Link to="/" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Voltar
      </Link>
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-4xl font-semibold">{f.ticker}</h1>
            <SinalBadge sinal={sinal(f)} />
          </div>
          <p className="text-muted-foreground">{f.nome} · {f.segmento}</p>
        </div>
        <div className="text-right">
          <div className="num text-4xl font-semibold">{brl(f.preco)}</div>
          <div className={f.variacaoDia < 0 ? "num text-destructive" : "num text-signal"}>{pct(f.variacaoDia)} hoje</div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="surface p-5 lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="font-semibold">Últimos 90 dias</h2>
            <span className="text-xs text-muted-foreground">tracejado = média 30d</span>
          </div>
          <div className="h-72">
            <ResponsiveContainer>
              <AreaChart data={f.historico}>
                <defs>
                  <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--primary)" stopOpacity={0.3} />
                    <stop offset="100%" stopColor="var(--primary)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid stroke="var(--border)" vertical={false} />
                <XAxis dataKey="dia" tick={{ fill: "var(--muted-foreground)", fontSize: 11 }} tickLine={false} axisLine={false} minTickGap={30} />
                <YAxis domain={["auto", "auto"]} tick={{ fill: "var(--muted-foreground)", fontSize: 11 }} tickLine={false} axisLine={false} width={50} />
                <Tooltip
                  contentStyle={{ background: "var(--popover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--foreground)" }}
                  formatter={(v: number) => brl(v)}
                />
                <ReferenceLine y={m30} stroke="var(--muted-foreground)" strokeDasharray="4 4" />
                {f.alvo && <ReferenceLine y={f.alvo} stroke="var(--signal)" label={{ value: "alvo", fill: "var(--signal)", fontSize: 11 }} />}
                <Area dataKey="preco" type="monotone" stroke="var(--primary)" strokeWidth={2} fill="url(#g)" isAnimationActive={false} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
          <div className="mt-4 grid grid-cols-3 gap-4 border-t pt-4">
            <Info l="Média 30d" v={brl(m30)} />
            <Info l="P/VP" v={pvp(f).toFixed(2).replace(".", ",")} />
            <Info l="DY 12 meses" v={`${f.dy12m.toFixed(1).replace(".", ",")}%`} />
          </div>
        </div>

        <div className="surface space-y-5 p-5">
          <h2 className="font-semibold">Quando me avisar</h2>
          <div className="space-y-2">
            <Label htmlFor="alvo">Preço alvo (R$)</Label>
            <Input id="alvo" defaultValue={f.alvo?.toFixed(2) ?? ""} placeholder="ex: 9,50" />
          </div>
          <Regra t="Regra automática" d="Abaixo da média de 30 dias e P/VP abaixo de 1" on={!f.alvo} />
          <Regra t="Queda forte no dia" d="Avisa se cair 3% ou mais" on />
          <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
            No máximo 1 aviso por fundo a cada 24h.
          </p>
          <Button className="w-full">Salvar</Button>
        </div>
      </div>
    </div>
  );
}

function Info({ l, v }: { l: string; v: string }) {
  return (
    <div>
      <div className="text-xs text-muted-foreground">{l}</div>
      <div className="num text-lg font-semibold">{v}</div>
    </div>
  );
}

function Regra({ t, d, on }: { t: string; d: string; on?: boolean }) {
  return (
    <div className="flex items-start justify-between gap-3">
      <div>
        <div className="text-sm font-medium">{t}</div>
        <div className="text-xs text-muted-foreground">{d}</div>
      </div>
      <Switch defaultChecked={!!on} />
    </div>
  );
}
