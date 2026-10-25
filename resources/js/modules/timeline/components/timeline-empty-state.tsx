import { MessageSquare } from 'lucide-react';

export function TimelineEmptyState() {
    return (
        <div className="rounded-lg border border-dashed border-border/70 bg-muted/20 px-4 py-8 text-center">
            <div className="mx-auto mb-3 flex size-9 items-center justify-center rounded-full border border-border/80 bg-background">
                <MessageSquare className="size-4 text-muted-foreground" />
            </div>
            <p className="text-sm font-medium text-foreground">
                Nenhuma atividade registrada.
            </p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Os comentários e eventos do registro aparecem aqui.
            </p>
        </div>
    );
}
