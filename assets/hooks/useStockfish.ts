import { useState, useRef, useCallback, useEffect } from 'react';
import type { StockfishEvaluation } from '../types/chess';

export function useStockfish(defaultDepth = 18) {
    const [lines, setLines] = useState<StockfishEvaluation[]>([]);
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const [available, setAvailable] = useState(false);
    const workerRef = useRef<Worker | null>(null);
    const pendingRef = useRef<{ fen: string; depth: number } | null>(null);
    const searchingRef = useRef(false);

    useEffect(() => {
        try {
            const worker = new Worker('/build/stockfish-18-lite-single.js');
            let initialized = false;

            worker.onerror = (e) => {
                if (!initialized) {
                    console.warn('Stockfish WASM not available, client-side analysis disabled');
                    setAvailable(false);
                    worker.terminate();
                    workerRef.current = null;
                }
                e.preventDefault();
            };

            worker.onmessage = (e: MessageEvent) => {
                const line: string = e.data;

                if (typeof line !== 'string') return;

                if (line.startsWith('uciok')) {
                    worker.postMessage('setoption name MultiPV value 3');
                    worker.postMessage('isready');
                }

                if (line.startsWith('readyok')) {
                    if (!initialized) {
                        initialized = true;
                        setAvailable(true);
                    }

                    if (pendingRef.current) {
                        const { fen, depth } = pendingRef.current;
                        pendingRef.current = null;
                        searchingRef.current = true;
                        worker.postMessage(`position fen ${fen}`);
                        worker.postMessage(`go depth ${depth}`);
                    }
                }

                if (line.startsWith('bestmove')) {
                    searchingRef.current = false;

                    if (pendingRef.current) {
                        worker.postMessage('isready');
                    } else {
                        setIsAnalyzing(false);
                    }
                }

                if (line.includes('score cp') || line.includes('score mate')) {
                    if (pendingRef.current) return;

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
        pendingRef.current = { fen, depth };

        if (searchingRef.current) {
            workerRef.current.postMessage('stop');
        } else {
            workerRef.current.postMessage('isready');
        }
    }, [defaultDepth]);

    const stop = useCallback(() => {
        pendingRef.current = null;
        workerRef.current?.postMessage('stop');
        setIsAnalyzing(false);
    }, []);

    return { lines, isAnalyzing, available, analyze, stop };
}
