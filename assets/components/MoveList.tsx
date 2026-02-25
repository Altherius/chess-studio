import React, { useRef, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface MoveListProps {
    moves: string[];
    currentMoveIndex: number;
    onMoveClick: (index: number) => void;
}

const MoveList: React.FC<MoveListProps> = ({ moves, currentMoveIndex, onMoveClick }) => {
    const activeRef = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        activeRef.current?.scrollIntoView({ inline: 'center', block: 'nearest' });
    }, [currentMoveIndex]);

    const pairs: { number: number; white: string; black?: string }[] = [];

    for (let i = 0; i < moves.length; i += 2) {
        pairs.push({
            number: Math.floor(i / 2) + 1,
            white: moves[i],
            black: moves[i + 1],
        });
    }

    return (
        <div className="flex flex-nowrap overflow-x-scroll font-mono text-sm gap-1 pt-1 pb-2.5 px-1 w-0 min-w-full" data-testid="move-list">
            {pairs.map((pair) => (
                <React.Fragment key={pair.number}>
                    <span className="text-muted-foreground whitespace-nowrap">{pair.number}.</span>
                    <span
                        ref={currentMoveIndex === (pair.number - 1) * 2 ? activeRef : undefined}
                        className={cn(
                            'cursor-pointer px-1 rounded-sm whitespace-nowrap hover:bg-secondary',
                            currentMoveIndex === (pair.number - 1) * 2 && 'bg-primary text-primary-foreground'
                        )}
                        onClick={() => onMoveClick((pair.number - 1) * 2)}
                    >
                        {pair.white}
                    </span>
                    {pair.black && (
                        <span
                            ref={currentMoveIndex === (pair.number - 1) * 2 + 1 ? activeRef : undefined}
                            className={cn(
                                'cursor-pointer px-1 rounded-sm whitespace-nowrap hover:bg-secondary',
                                currentMoveIndex === (pair.number - 1) * 2 + 1 && 'bg-primary text-primary-foreground'
                            )}
                            onClick={() => onMoveClick((pair.number - 1) * 2 + 1)}
                        >
                            {pair.black}
                        </span>
                    )}
                </React.Fragment>
            ))}
            {moves.length === 0 && (
                <p className="text-muted-foreground text-center w-full py-1">
                    Aucun coup à afficher
                </p>
            )}
        </div>
    );
};

export default MoveList;
