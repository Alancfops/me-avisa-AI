// Dados de exemplo (fictícios) para o protótipo.
export type Fii = {
  ticker: string;
  nome: string;
  segmento: string;
  preco: number;
  variacaoDia: number; // %
  vpCota: number; // valor patrimonial por cota
  dy12m: number; // %
  alvo?: number;
  faixa?: [number, number];
  historico: { dia: string; preco: number }[];
};

function serie(seed: number, base: number, dias = 90) {
  let x = seed;
  let p = base * 1.06;
  const out: { dia: string; preco: number }[] = [];
  const hoje = new Date(2026, 9, 29);
  for (let i = dias - 1; i >= 0; i--) {
    x = (x * 9301 + 49297) % 233280;
    const r = x / 233280 - 0.52;
    p = p * (1 + r * 0.018);
    const d = new Date(hoje);
    d.setDate(d.getDate() - i);
    out.push({ dia: `${d.getDate()}/${d.getMonth() + 1}`, preco: +p.toFixed(2) });
  }
  out[out.length - 1]!.preco = base;
  return out;
}

const raw: Omit<Fii, "historico">[] = [
  { ticker: "MXRF11", nome: "Maxi Renda", segmento: "Papel", preco: 9.42, variacaoDia: -0.8, vpCota: 9.61, dy12m: 12.4, alvo: 9.5 },
  { ticker: "HGLG11", nome: "CSHG Logística", segmento: "Logística", preco: 158.1, variacaoDia: 0.4, vpCota: 162.3, dy12m: 8.9, faixa: [150, 156] },
  { ticker: "KNRI11", nome: "Kinea Renda Imob.", segmento: "Híbrido", preco: 141.7, variacaoDia: -3.6, vpCota: 158.9, dy12m: 7.8 },
  { ticker: "XPML11", nome: "XP Malls", segmento: "Shoppings", preco: 104.2, variacaoDia: 0.1, vpCota: 112.0, dy12m: 9.6, alvo: 100 },
  { ticker: "VISC11", nome: "Vinci Shopping Centers", segmento: "Shoppings", preco: 112.9, variacaoDia: 1.2, vpCota: 121.4, dy12m: 8.7 },
  { ticker: "BTLG11", nome: "BTG Logística", segmento: "Logística", preco: 99.4, variacaoDia: -0.3, vpCota: 101.2, dy12m: 9.1, alvo: 98 },
];

export const fiis: Fii[] = raw.map((f, i) => ({ ...f, historico: serie(i * 71 + 13, f.preco) }));

export const media30 = (f: Fii) => {
  const u = f.historico.slice(-30);
  return u.reduce((s, d) => s + d.preco, 0) / u.length;
};
export const pvp = (f: Fii) => f.preco / f.vpCota;

export type Sinal = "comprar" | "queda" | "observando";
export function sinal(f: Fii): Sinal {
  if (f.variacaoDia <= -3) return "queda";
  if (f.alvo && f.preco <= f.alvo) return "comprar";
  if (f.faixa && f.preco >= f.faixa[0] && f.preco <= f.faixa[1]) return "comprar";
  if (!f.alvo && !f.faixa && f.preco < media30(f) && pvp(f) < 1) return "comprar";
  return "observando";
}

export const brl = (n: number) => n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
export const pct = (n: number) => `${n > 0 ? "+" : ""}${n.toFixed(1).replace(".", ",")}%`;

export const alertas = [
  { id: 1, ticker: "MXRF11", tipo: "Alvo atingido", texto: "Chegou a R$ 9,42 (seu alvo: R$ 9,50)", quando: "hoje, 14:02", sinal: "comprar" as Sinal },
  { id: 2, ticker: "KNRI11", tipo: "Queda forte", texto: "Caiu 3,6% no dia", quando: "hoje, 11:37", sinal: "queda" as Sinal },
  { id: 3, ticker: "BTLG11", tipo: "Regra automática", texto: "Abaixo da média de 30 dias e P/VP 0,98", quando: "ontem, 16:20", sinal: "comprar" as Sinal },
  { id: 4, ticker: "HGLG11", tipo: "Faixa de preço", texto: "Entrou na faixa R$ 150 – R$ 156", quando: "27/10, 10:05", sinal: "comprar" as Sinal },
];
