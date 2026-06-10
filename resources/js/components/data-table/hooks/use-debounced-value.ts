import { useEffect, useState } from 'react';

export function useDebouncedValue<TValue>(value: TValue, delayMs = 350): TValue {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timeout = window.setTimeout(() => setDebouncedValue(value), delayMs);

        return () => window.clearTimeout(timeout);
    }, [delayMs, value]);

    return debouncedValue;
}
