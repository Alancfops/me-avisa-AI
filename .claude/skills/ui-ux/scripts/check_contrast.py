#!/usr/bin/env python3
"""Verifica contraste WCAG 2.2 dos tokens de cor do Me Avisa Aí.

Lê as variáveis CSS de `:root` (tema claro) e `.dark` (tema escuro) num
arquivo CSS e mede os pares texto/fundo que a interface realmente usa.

Uso:
    python3 check_contrast.py resources/css/app.css
    python3 check_contrast.py app.css --pair muted-foreground:card:4.5

Sai com código 1 se algum par falhar, então dá pra usar em hook ou CI.
"""
import argparse
import math
import re
import sys

# (texto, fundo, mínimo, onde aparece)
# Fundo "a/b" = token a (translúcido) aplicado por cima do token b.
PARES_PADRAO = [
    ("foreground", "background", 4.5, "texto principal"),
    ("foreground", "card", 4.5, "texto em cartão"),
    ("muted-foreground", "background", 4.5, "texto de apoio"),
    ("muted-foreground", "card", 4.5, "texto de apoio em cartão"),
    ("muted-foreground", "muted", 4.5, "texto em caixa cinza / badge observando"),
    ("primary", "background", 4.5, "link azul"),
    ("primary-foreground", "primary", 4.5, "botão principal"),
    ("signal", "card", 4.5, "variação positiva / número verde"),
    ("signal", "signal-soft/card", 4.5, "badge 'Preço bom'"),
    ("signal-foreground", "signal", 4.5, "botão verde"),
    ("destructive", "card", 4.5, "variação negativa / número vermelho"),
    ("destructive", "drop-soft/card", 4.5, "badge 'Queda forte'"),
    ("destructive-foreground", "destructive", 4.5, "botão destrutivo"),
    ("ring", "background", 3.0, "anel de foco (1.4.11)"),
    ("ring", "card", 3.0, "anel de foco em cartão (1.4.11)"),
    ("input", "background", 3.0, "borda de campo de formulário (1.4.11)"),
]

TEMAS = {"claro": r":root\s*\{", "escuro": r"\.dark\s*\{"}


def ler_bloco(css, abertura):
    m = re.search(abertura, css)
    if not m:
        return {}
    i, prof = m.end(), 1
    j = i
    while prof and j < len(css):
        prof += {"{": 1, "}": -1}.get(css[j], 0)
        j += 1
    corpo = css[i : j - 1]
    return dict(re.findall(r"--([\w-]+)\s*:\s*([^;]+);", corpo))


def parse_cor(valor):
    """Retorna (r, g, b, alpha) em 0..1 para oklch(), #hex ou rgb()."""
    v = valor.strip()
    m = re.match(r"oklch\(\s*([\d.]+%?)\s+([\d.]+)\s+([\d.]+)\s*(?:/\s*([\d.]+%?))?\s*\)", v)
    if m:
        L = float(m[1].rstrip("%")) / (100 if m[1].endswith("%") else 1)
        C, H = float(m[2]), math.radians(float(m[3]))
        a = 1.0
        if m[4]:
            a = float(m[4].rstrip("%")) / (100 if m[4].endswith("%") else 1)
        return (*oklch_para_srgb(L, C, H), a)
    m = re.match(r"#([0-9a-fA-F]{6})", v)
    if m:
        h = m[1]
        return tuple(int(h[k : k + 2], 16) / 255 for k in (0, 2, 4)) + (1.0,)
    m = re.match(r"rgba?\(\s*([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:\s*[,/]\s*([\d.]+))?", v)
    if m:
        return tuple(float(m[k]) / 255 for k in (1, 2, 3)) + (float(m[4] or 1),)
    raise ValueError(f"cor não suportada: {valor}")


def oklch_para_srgb(L, C, H):
    a, b = C * math.cos(H), C * math.sin(H)
    l_ = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3
    m_ = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3
    s_ = (L - 0.0894841775 * a - 1.2914855480 * b) ** 3
    lin = (
        4.0767416621 * l_ - 3.3077115913 * m_ + 0.2309699292 * s_,
        -1.2684380046 * l_ + 2.6097574011 * m_ - 0.3413193965 * s_,
        -0.0041960863 * l_ - 0.7034186147 * m_ + 1.7076147010 * s_,
    )

    def gamma(x):
        x = min(max(x, 0.0), 1.0)
        return 12.92 * x if x <= 0.0031308 else 1.055 * x ** (1 / 2.4) - 0.055

    return tuple(gamma(x) for x in lin)


def compor(cima, baixo):
    a = cima[3]
    return tuple(cima[k] * a + baixo[k] * (1 - a) for k in range(3)) + (1.0,)


def luminancia(c):
    def lin(x):
        return x / 12.92 if x <= 0.04045 else ((x + 0.055) / 1.055) ** 2.4

    r, g, b = (lin(x) for x in c[:3])
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def razao(c1, c2):
    l1, l2 = sorted((luminancia(c1), luminancia(c2)), reverse=True)
    return (l1 + 0.05) / (l2 + 0.05)


def resolver(nome, tokens, base):
    """Resolve 'token' ou 'token/fundo' para uma cor opaca."""
    if "/" in nome:
        cima, baixo = nome.split("/", 1)
        return compor(parse_cor(tokens[cima]), resolver(baixo, tokens, base))
    cor = parse_cor(tokens[nome])
    return compor(cor, base) if cor[3] < 1 else cor


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("css")
    ap.add_argument("--pair", action="append", default=[], help="texto:fundo:minimo (ex.: signal:card:4.5)")
    args = ap.parse_args()

    css = open(args.css, encoding="utf-8").read()
    pares = PARES_PADRAO + [(*p.split(":")[:2], float(p.split(":")[2]), "personalizado") for p in args.pair]
    claro = ler_bloco(css, TEMAS["claro"])
    falhas = 0

    for tema, abertura in TEMAS.items():
        tokens = dict(claro)
        if tema == "escuro":
            tokens.update(ler_bloco(css, abertura))
        if not tokens:
            continue
        base = parse_cor(tokens.get("background", "#ffffff"))
        print(f"\n== Tema {tema}")
        for texto, fundo, minimo, uso in pares:
            try:
                r = razao(resolver(texto, tokens, base), resolver(fundo, tokens, base))
            except KeyError as e:
                print(f"  ??   {texto} sobre {fundo}: token {e} não existe")
                continue
            ok = r >= minimo
            falhas += not ok
            print(f"  {'ok ' if ok else 'FALHA'} {r:5.2f}:1 (mín {minimo}) {texto} sobre {fundo} — {uso}")

    print(f"\n{falhas} par(es) abaixo do mínimo." if falhas else "\nTodos os pares passam.")
    sys.exit(1 if falhas else 0)


if __name__ == "__main__":
    main()
