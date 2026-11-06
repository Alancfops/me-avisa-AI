import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            aria-label={dark ? 'Usar tema claro' : 'Usar tema escuro'}
            onClick={() => updateAppearance(dark ? 'light' : 'dark')}
        >
            {dark ? <Sun aria-hidden="true" /> : <Moon aria-hidden="true" />}
        </Button>
    );
}
