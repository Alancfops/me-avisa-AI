import { createFileRoute } from "@tanstack/react-router";
import { Button } from "@/components/ui/button";
import { SinalBadge } from "@/components/sinal-badge";

export const Route = createFileRoute("/estilo")({
  head: () => ({
    meta: [
      { title: "Guia de estilo — Me Avisa Aí" },
      { name: "description", content: "Cores, fontes e componentes do Me Avisa Aí." },
      { property: "og:title", content: "Guia de estilo — Me Avisa Aí" },
      { property: "og:description", content: "Cores, fontes e componentes do Me Avisa Aí." },
    ],
  }),
  component: Estilo,
});

const cores = [
  { nome: "Fundo", uso: "Preto (escuro) / branco (claro)", cls: "bg-background border" },
  { nome: "Cartão", uso: "Superfícies e painéis", cls: "bg-card border" },
  { nome: "Azul", uso: "Ações, links, gráficos", cls: "bg-primary" },
  { nome: "Verde", uso: "Preço bom / compra", cls: "bg-signal" },
  { nome: "Vermelho", uso: "Quedas fortes", cls: "bg-destructive" },
  { nome: "Cinza", uso: "Textos de apoio", cls: "bg-muted-foreground" },
];

function Estilo() {
  return (
    <div className="mx-auto max-w-4xl space-y-10">
      <div>
        <h1 className="text-3xl font-semibold md:text-4xl">Guia de estilo</h1>
        <p className="text-muted-foreground">Use o botão de sol/lua no topo para ver as cores nos dois modos.</p>
      </div>

      <section className="space-y-4">
        <h2 className="text-xl font-semibold">Cores</h2>
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
          {cores.map((c) => (
            <div key={c.nome} className="surface overflow-hidden">
              <div className={`h-20 ${c.cls}`} />
              <div className="p-3">
                <div className="font-medium">{c.nome}</div>
                <div className="text-xs text-muted-foreground">{c.uso}</div>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-semibold">Fontes</h2>
        <div className="surface space-y-3 p-6">
          <div className="font-display text-4xl font-semibold">Sora — títulos e números</div>
          <div className="num text-2xl">R$ 9,42 · +1,2% · P/VP 0,98</div>
          <p className="text-muted-foreground">Manrope — textos do dia a dia, legível em tamanhos pequenos.</p>
        </div>
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-semibold">Componentes</h2>
        <div className="surface flex flex-wrap items-center gap-3 p-6">
          <Button>Principal</Button>
          <Button variant="secondary">Secundário</Button>
          <Button variant="outline">Contorno</Button>
          <SinalBadge sinal="comprar" />
          <SinalBadge sinal="queda" />
          <SinalBadge sinal="observando" />
        </div>
      </section>
    </div>
  );
}
