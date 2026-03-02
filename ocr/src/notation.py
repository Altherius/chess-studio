"""Conversion utilities between standard and French chess notation."""

import io

import chess
import chess.pgn

PIECE_TO_FRENCH = {"N": "C", "B": "F", "R": "T", "Q": "D", "K": "R"}


def san_to_french(san: str) -> str:
    """Convert a SAN move to French notation (N→C, B→F, R→T, Q→D, K→R)."""
    if not san:
        return san
    if san[0] in PIECE_TO_FRENCH:
        return PIECE_TO_FRENCH[san[0]] + san[1:]
    return san


def pgn_to_french_moves(pgn_text: str) -> list[str]:
    """Parse a PGN string and return a flat list of moves in French notation."""
    game = chess.pgn.read_game(io.StringIO(pgn_text))
    if game is None:
        return []
    return [san_to_french(move.san()) for move in game.mainline()]
