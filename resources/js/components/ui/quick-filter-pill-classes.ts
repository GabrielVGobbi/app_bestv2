export type QuickFilterTone =
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info';

const tones: Record<QuickFilterTone, { idle: string; active: string }> = {
    neutral: {
        idle: 'border-border bg-card text-foreground hover:bg-secondary',
        active: 'border-foreground bg-foreground text-background ring-2 ring-foreground/15',
    },
    success: {
        idle: 'border-success/50 bg-card text-success-soft-foreground hover:bg-success-soft',
        active: 'border-success bg-success text-success-foreground ring-2 ring-success/20',
    },
    warning: {
        idle: 'border-warning/60 bg-card text-warning-soft-foreground hover:bg-warning-soft',
        active: 'border-warning bg-warning text-warning-foreground ring-2 ring-warning/20',
    },
    danger: {
        idle: 'border-destructive/50 bg-card text-destructive-soft-foreground hover:bg-destructive-soft',
        active: 'border-destructive bg-destructive text-destructive-foreground ring-2 ring-destructive/20',
    },
    info: {
        idle: 'border-info/50 bg-card text-info-soft-foreground hover:bg-info-soft',
        active: 'border-info bg-info text-info-foreground ring-2 ring-info/20',
    },
};

export function quickFilterPillClassName(
    isActive: boolean,
    tone: QuickFilterTone = 'neutral',
): string {
    return tones[tone][isActive ? 'active' : 'idle'];
}
