import { useCallback, useRef } from 'react';

export function useIntersectionLoadMore(args: {
    enabled: boolean;
    hasMore: boolean;
    isFetchingMore: boolean;
    rootMargin?: string;
    onLoadMore: () => void;
}) {
    const observer = useRef<IntersectionObserver | null>(null);

    return useCallback(
        (node: HTMLDivElement | null) => {
            observer.current?.disconnect();

            if (!node || !args.enabled) {
                return;
            }

            observer.current = new IntersectionObserver(
                ([entry]) => {
                    if (entry?.isIntersecting && args.hasMore && !args.isFetchingMore) {
                        args.onLoadMore();
                    }
                },
                {
                    rootMargin: args.rootMargin ?? '200px',
                },
            );

            observer.current.observe(node);
        },
        [args],
    );
}
