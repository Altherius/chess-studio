import { useState, useRef, useCallback, useEffect } from 'react';
import type { StockfishEvaluation } from '../types/chess';

export function useStockfish(defaultDepth = 18) {
    const [lines, setLines] = useState<StockfishEvaluation[]>([]);
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const [available, setAvailable] = useState(false);
    const workerRef = useRef<Worker | null>(null);

    useEffect(() => {
        try {
            const worker = new Worker('/build/stockfish-18-lite-single.js');

            worker.onerror = () => {
                console.warn('Stockfish WASM not available, client-side analysis disabled');
                setAvailable(false);
                worker.terminate();
                workerRef.current = null;
            };

            worker.onmessage = (e: MessageEvent) => {
                const line: string = e.data;

                if (typeof line !== 'string') return;

                if (line.startsWith('uciok')) {
                    setAvailable(true);
                    worker.postMessage('setoption name MultiPV value 3');
                }

                if (line.startsWith('bestmove')) {
                    setIsAnalyzing(false);
                }

                if (line.includes('score cp') || line.includes('score mate')) {
                    let score: number | string = 0;
                    let bestMove = '';
                    let pv = '';
                    let depth = 0;
                    let multipv = 1;

                    const depthMatch = line.match(/depth (\d+)/);
                    if (depthMatch) depth = parseInt(depthMatch[1]);

                    const multipvMatch = line.match(/multipv (\d+)/);
                    if (multipvMatch) multipv = parseInt(multipvMatch[1]);

                    if (line.includes('score cp')) {
                        const match = line.match(/score cp (-?\d+)/);
                        if (match) score = parseInt(match[1]) / 100;
                    } else if (line.includes('score mate')) {
                        const match = line.match(/score mate (-?\d+)/);
                        if (match) score = `M${match[1]}`;
                    }

                    const pvMatch = line.match(/\bpv\b (.+)$/);
                    if (pvMatch) {
                        pv = pvMatch[1];
                        bestMove = pv.split(' ')[0];
                    }

                    const evalData: StockfishEvaluation = { score, bestMove, pv, depth, multipv };
                    const index = multipv - 1;

                    setLines(prev => {
                        const next = [...prev];
                        next[index] = evalData;
                        return next;
                    });
                }
            };

            workerRef.current = worker;
            worker.postMessage('uci');
        } catch {
            console.warn('Stockfish WASM not available, client-side analysis disabled');
        }

        return () => {
            workerRef.current?.terminate();
        };
    }, []);

    const analyze = useCallback((fen: string, depth = defaultDepth) => {
        if (!workerRef.current) return;

        setIsAnalyzing(true);
        setLines([]);
        workerRef.current.postMessage('stop');
        workerRef.current.postMessage('ucinewgame');
        workerRef.current.postMessage(`position fen ${fen}`);
        workerRef.current.postMessage(`go depth ${depth}`);
    }, [defaultDepth]);

    const stop = useCallback(() => {
        workerRef.current?.postMessage('stop');
        setIsAnalyzing(false);
    }, []);

    return { lines, isAnalyzing, available, analyze, stop };
}
