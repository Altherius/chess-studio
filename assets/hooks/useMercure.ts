import { useState, useEffect } from 'react';

const MERCURE_URL = 'http://localhost:3000/.well-known/mercure';

export function useMercure(topic: string | null) {
    const [data, setData] = useState<any>(null);

    useEffect(() => {
        if (!topic) return;

        let eventSource: EventSource;
        try {
            const mercureUrl = new URL(MERCURE_URL);
            mercureUrl.searchParams.append('topic', topic);
            eventSource = new EventSource(mercureUrl.toString());
        } catch {
            console.warn('Failed to connect to Mercure hub');
            return;
        }

        eventSource.onmessage = (event) => {
            try {
                setData(JSON.parse(event.data));
            } catch {
                setData(event.data);
            }
        };

        eventSource.onerror = () => {
            console.warn(`Mercure connection error for topic: ${topic}`);
        };

        return () => {
            eventSource.close();
        };
    }, [topic]);

    return data;
}
