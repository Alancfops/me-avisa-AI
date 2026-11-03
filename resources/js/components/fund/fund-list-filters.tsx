import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { BaseFilter, KindFilter, SortKey } from '@/lib/fund-list';

const KINDS: { value: KindFilter; label: string }[] = [
    { value: 'all', label: 'Todos' },
    { value: 'tijolo', label: 'Tijolo' },
    { value: 'papel', label: 'Papel' },
    { value: 'fundo_de_fundos', label: 'Fundo de fundos' },
    { value: 'hibrido', label: 'Híbrido' },
];

const BASES: { value: BaseFilter; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'base10', label: 'Base 10' },
    { value: 'base100', label: 'Base 100' },
];

const SORTS: { value: SortKey; label: string }[] = [
    { value: 'priceToBook', label: 'Menor P/VP' },
    { value: 'dividendYield', label: 'Maior DY 12m' },
    { value: 'ticker', label: 'Ticker (A a Z)' },
    { value: 'netAssets', label: 'Maior patrimônio' },
];

const SEGMENT_ITEM =
    'rounded-md px-3 first:rounded-md last:rounded-md data-[state=on]:bg-card data-[state=on]:font-semibold data-[state=on]:text-foreground data-[state=on]:shadow-xs';

function Segmented<T extends string>({
    labelId,
    label,
    options,
    value,
    onChange,
}: {
    labelId: string;
    label: string;
    options: { value: T; label: string }[];
    value: T;
    onChange: (value: T) => void;
}) {
    return (
        <div className="space-y-1.5">
            <span
                id={labelId}
                className="block text-sm font-medium text-muted-foreground"
            >
                {label}
            </span>
            <ToggleGroup
                type="single"
                aria-labelledby={labelId}
                value={value}
                // Clicar no item ativo manda '': a escolha nunca fica vazia.
                onValueChange={(next) => next && onChange(next as T)}
                className="flex-wrap gap-0.5 rounded-lg bg-muted p-0.5"
            >
                {options.map((option) => (
                    <ToggleGroupItem
                        key={option.value}
                        value={option.value}
                        className={SEGMENT_ITEM}
                    >
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
        </div>
    );
}

export function FundListFilters({
    query,
    kind,
    base,
    sort,
    onQuery,
    onKind,
    onBase,
    onSort,
}: {
    query: string;
    kind: KindFilter;
    base: BaseFilter;
    sort: SortKey;
    onQuery: (value: string) => void;
    onKind: (value: KindFilter) => void;
    onBase: (value: BaseFilter) => void;
    onSort: (value: SortKey) => void;
}) {
    return (
        <section
            aria-label="Filtros da lista"
            className="flex flex-wrap items-end gap-x-6 gap-y-4 surface p-4"
        >
            <div className="w-full space-y-1.5 sm:w-56">
                <Label htmlFor="busca-fundos">Buscar</Label>
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="busca-fundos"
                        type="search"
                        value={query}
                        onChange={(event) => onQuery(event.target.value)}
                        placeholder="Ticker ou nome"
                        autoComplete="off"
                        spellCheck={false}
                        className="pl-9"
                    />
                </div>
            </div>

            <Segmented
                labelId="filtro-tipo"
                label="Tipo"
                options={KINDS}
                value={kind}
                onChange={onKind}
            />

            <Segmented
                labelId="filtro-base"
                label="Preço da cota"
                options={BASES}
                value={base}
                onChange={onBase}
            />

            <div className="space-y-1.5">
                <Label htmlFor="ordenar-fundos">Ordenar por</Label>
                <Select
                    value={sort}
                    onValueChange={(next) => onSort(next as SortKey)}
                >
                    <SelectTrigger id="ordenar-fundos" className="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {SORTS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </section>
    );
}
