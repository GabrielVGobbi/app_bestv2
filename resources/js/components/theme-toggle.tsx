import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    // suppressHydrationWarning because the label depends on localStorage
                    // (resolved before React hydrates), which may differ from the SSR default.
                    suppressHydrationWarning
                    aria-label={
                        isDark ? 'Ativar modo claro' : 'Ativar modo escuro'
                    }
                    onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
                    className="relative border border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground"
                >
                    <Sun className="size-4 scale-100 rotate-0 transition-all dark:scale-0 dark:-rotate-90" />
                    <Moon className="absolute size-4 scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0" />
                    <span className="sr-only" suppressHydrationWarning>
                        {isDark ? 'Ativar modo claro' : 'Ativar modo escuro'}
                    </span>
                </Button>
            </TooltipTrigger>
            <TooltipContent side="bottom" suppressHydrationWarning>
                {isDark ? 'Modo claro' : 'Modo escuro'}
            </TooltipContent>
        </Tooltip>
    );
}
