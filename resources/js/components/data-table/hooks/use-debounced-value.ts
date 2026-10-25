import { useEffect, useState } from 'react';

export function useDebouncedValue<TValue>(
    value: TValue,
    delayMs = 350,
    ready = true,
): TValue {
    const [snapshot, setSnapshot] = useState({ value, ready });

    if (snapshot.ready !== ready) {
        setSnapshot({ value, ready });
    }

    useEffect(() => {
        if (!ready) {
            return;
        }

        const timeout = window.setTimeout(
            () => setSnapshot({ value, ready }),
            delayMs,
        );

        return () => window.clearTimeout(timeout);
    }, [delayMs, ready, value]);

    return snapshot.ready !== ready ? value : snapshot.value;
}
