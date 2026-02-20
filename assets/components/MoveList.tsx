import React from 'react';
import { cn } from '@/lib/utils';

interface MoveListProps {
    moves: string[];
    currentMoveIndex: number;
    onMoveClick: (index: number) => void;
}

const MoveList: React.FC<MoveListProps> = ({ moves, currentMoveIndex, onMoveClick }) => {
    const pairs: { number: number; white: string; black?: string }[] = [];

    for (let i = 0; i < moves.length; i += 2) {
        pairs.push({
            number: Math.floor(i / 2) + 1,
            white: moves[i],
            black: moves[i + 1],
        });
    }

    return (
        <div className="max-h-[500px] overflow-y-auto">
            {pairs.map((pair) => (
                <div key={pair.number} className="flex gap-1 px-2 py-1 rounded hover:bg-accent">
                    <span className="text-muted-foreground min-w-[30px]">{pair.number}.</span>
                    <span
                        className={cn(
                            'cursor-pointer px-1.5 py-0.5 rounded-sm min-w-[60px] hover:bg-secondary',
                            currentMoveIndex === (pair.number - 1) * 2 && 'bg-primary text-primary-foreground'
                        )}
                        onClick={() => onMoveClick((pair.number - 1) * 2)}
                    >
                        {pair.white}
                    </span>
                    {pair.black && (
                        <span
                            className={cn(
                                'cursor-pointer px-1.5 py-0.5 rounded-sm min-w-[60px] hover:bg-secondary',
                                currentMoveIndex === (pair.number - 1) * 2 + 1 && 'bg-primary text-primary-foreground'
                            )}
                            onClick={() => onMoveClick((pair.number - 1) * 2 + 1)}
                        >
                            {pair.black}
                        </span>
                    )}
                </div>
            ))}
            {moves.length === 0 && (
                <p className="text-muted-foreground text-center p-5">
                    No moves to display
                </p>
            )}
        </div>
    );
};

export default MoveList;
