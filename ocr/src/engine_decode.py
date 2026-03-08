"""Game-level beam search using chess legality to filter OCR candidates."""

import chess
import torch

from .decode import NEG_INF, ctc_forced_log_prob

_FRENCH_TO_EN = str.maketrans("CFTDR", "NBRQK")
_EN_TO_FRENCH = str.maketrans("NBRQK", "CFTDR")
_RESULT_TOKENS = {"1-0", "0-1", "1/2"}

_LOOKAHEAD = 6


def french_to_san(move: str) -> str:
    """Translate French piece letters to English SAN. Castling passes through."""
    if move.startswith("O-O"):
        return move
    return move.translate(_FRENCH_TO_EN)


def san_to_french(move: str) -> str:
    """Translate English SAN piece letters to French notation."""
    if move.startswith("O-O"):
        return move
    return move.translate(_EN_TO_FRENCH)


def _try_parse_french(board: chess.Board, french_move: str) -> chess.Move | None:
    if not french_move or french_move in _RESULT_TOKENS:
        return None
    san = french_to_san(french_move)
    try:
        return board.parse_san(san)
    except (chess.IllegalMoveError, chess.InvalidMoveError, chess.AmbiguousMoveError):
        return None


def _count_downstream_legal(
    board: chess.Board,
    candidate: chess.Move,
    start_idx: int,
    moves: list[str],
    all_candidates: list[list[tuple[str, float]]],
    lookahead: int,
) -> int:
    """
    Simulate pushing candidate, then check how many of the next cells
    have at least one legal beam candidate.
    """
    sim_board = board.copy()
    sim_board.push(candidate)
    count = 0

    for i in range(lookahead):
        idx = start_idx + i
        if idx >= len(moves):
            break

        move_text = moves[idx]

        if move_text != "?":
            move_obj = _try_parse_french(sim_board, move_text)
            if move_obj is not None:
                sim_board.push(move_obj)
                count += 1
                continue

        found = False
        if idx < len(all_candidates):
            for fr_move, _ in all_candidates[idx]:
                move_obj = _try_parse_french(sim_board, fr_move)
                if move_obj is not None:
                    sim_board.push(move_obj)
                    found = True
                    count += 1
                    break

        if not found:
            break

    return count


def _resolve_placeholders(
    moves: list[str],
    log_probs: torch.Tensor,
    all_candidates: list[list[tuple[str, float]]],
    lookahead: int = _LOOKAHEAD,
) -> list[str]:
    """
    Post-processing: replay the move list and replace "?" placeholders
    with the best legal move via forced alignment against raw CTC output.
    Uses look-ahead validation to pick candidates that preserve downstream legality.
    """
    board = chess.Board()
    resolved = []

    for cell_idx, move in enumerate(moves):
        if move != "?":
            san = french_to_san(move)
            try:
                move_obj = board.parse_san(san)
                board.push(move_obj)
            except (chess.IllegalMoveError, chess.InvalidMoveError, chess.AmbiguousMoveError):
                board.push(chess.Move.null())
            resolved.append(move)
            continue

        cell_lp = log_probs[:, cell_idx, :]
        scored: list[tuple[str, chess.Move, float, int]] = []

        for legal_move in board.legal_moves:
            san = board.san(legal_move)
            french = san_to_french(san)
            ctc_score = ctc_forced_log_prob(cell_lp, french)
            downstream = _count_downstream_legal(
                board, legal_move, cell_idx + 1, moves, all_candidates, lookahead,
            )
            scored.append((french, legal_move, ctc_score, downstream))

        if scored:
            scored.sort(key=lambda x: (x[3], x[2]), reverse=True)
            fr_move, move_obj, _, _ = scored[0]
            resolved.append(fr_move)
            board.push(move_obj)
        else:
            resolved.append("?")
            board.push(chess.Move.null())

    return resolved


def decode_game(
    all_candidates: list[list[tuple[str, float]]],
    log_probs: torch.Tensor | None = None,
    beam_width: int = 10,
) -> list[str]:
    """
    Game-level beam search: thread a chess.Board through OCR candidates,
    using move legality as a hard filter and OCR log-prob as ranking signal.

    When log_probs is provided, a post-processing pass replaces any remaining
    "?" placeholders using CTC forced alignment with look-ahead validation.

    Args:
        all_candidates: Per-cell list of (french_move_string, log_prob) tuples,
                        as returned by beam_search_decode().
        log_probs: Optional (T, batch, num_classes) raw model output.
                   When provided, used for forced alignment on "?" placeholders.
        beam_width: Max number of game-level beams to keep.

    Returns:
        List of French-notation move strings for the best legal path found.
    """
    beams: list[tuple[chess.Board, list[str], float]] = [
        (chess.Board(), [], 0.0)
    ]

    for cell_candidates in all_candidates:
        if not cell_candidates:
            break

        top_move = cell_candidates[0][0]
        if not top_move or top_move in _RESULT_TOKENS:
            break

        new_beams: list[tuple[chess.Board, list[str], float]] = []

        for board, moves, cum_score in beams:
            found_legal = False
            for french_move, log_prob in cell_candidates:
                if not french_move or french_move in _RESULT_TOKENS:
                    continue
                san = french_to_san(french_move)
                try:
                    move_obj = board.parse_san(san)
                except (chess.IllegalMoveError, chess.InvalidMoveError, chess.AmbiguousMoveError):
                    continue
                new_board = board.copy()
                new_board.push(move_obj)
                new_beams.append((
                    new_board,
                    moves + [french_move],
                    cum_score + log_prob,
                ))
                found_legal = True

            if not found_legal:
                skipped_board = board.copy()
                skipped_board.push(chess.Move.null())
                new_beams.append((
                    skipped_board,
                    moves + ["?"],
                    cum_score + (-10.0),
                ))

        new_beams.sort(key=lambda x: x[2], reverse=True)
        beams = new_beams[:beam_width]

        if not beams:
            break

    if not beams:
        return []

    moves = beams[0][1]

    if log_probs is not None and "?" in moves:
        moves = _resolve_placeholders(moves, log_probs, all_candidates)

    return moves
