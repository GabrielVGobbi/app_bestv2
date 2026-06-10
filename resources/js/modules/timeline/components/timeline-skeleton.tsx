import { Skeleton } from '@/components/ui/skeleton';

export function TimelineSkeleton() {
    return (
        <div className="space-y-3" aria-label="Carregando timeline">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="flex gap-3">
                    <div className="flex w-7 justify-center">
                        <Skeleton className="mt-1 size-6 rounded-full" />
                    </div>
                    <div className="flex-1 space-y-2 py-1.5">
                        <div className="flex items-center gap-2">
                            <Skeleton className="h-3.5 w-28" />
                            <Skeleton className="h-3 w-20" />
                        </div>
                        <Skeleton className="h-3.5 w-full max-w-lg" />
                        <Skeleton className="h-3.5 w-2/3 max-w-md" />
                    </div>
                </div>
            ))}
        </div>
    );
}
