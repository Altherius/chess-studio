"""CTC greedy and beam search decoders."""

import math

import torch

from .alphabet import BLANK, char_to_idx, idx_to_char


def greedy_decode(log_probs: torch.Tensor) -> list[str]:
    """
    Greedy CTC decoding.

    Args:
        log_probs: (T, batch, num_classes) log-probabilities

    Returns:
        List of decoded strings, one per batch element.
    """
    # argmax per timestep
    predictions = log_probs.argmax(dim=2)  # (T, batch)
    batch_size = predictions.size(1)

    results = []
    for b in range(batch_size):
        chars = []
        prev = -1
        for t in range(predictions.size(0)):
            idx = predictions[t, b].item()
            if idx != prev and idx != 0:  # collapse repeats, skip blank
                chars.append(idx_to_char[idx])
            prev = idx
        results.append("".join(chars))

    return results


NEG_INF = float("-inf")


def _log_add(a: float, b: float) -> float:
    if a == NEG_INF:
        return b
    if b == NEG_INF:
        return a
    if a > b:
        return a + math.log1p(math.exp(b - a))
    return b + math.log1p(math.exp(a - b))


def beam_search_decode(
    log_probs: torch.Tensor,
    beam_width: int = 25,
    top_k: int = 5,
) -> list[list[tuple[str, float]]]:
    """
    CTC prefix beam search decoding (Graves & Jaitly 2014).

    Args:
        log_probs: (T, batch, num_classes) log-probabilities
        beam_width: Number of prefixes to keep at each timestep
        top_k: Number of candidates to return per batch element

    Returns:
        List (per batch element) of lists of (decoded_string, log_prob) tuples,
        sorted by descending probability.
    """
    T, batch_size, num_classes = log_probs.shape
    log_probs_np = log_probs.cpu().numpy()

    all_results: list[list[tuple[str, float]]] = []

    for b in range(batch_size):
        beams: dict[str, tuple[float, float]] = {"": (0.0, NEG_INF)}

        for t in range(T):
            new_beams: dict[str, tuple[float, float]] = {}

            sorted_prefixes = sorted(
                beams.items(),
                key=lambda x: _log_add(x[1][0], x[1][1]),
                reverse=True,
            )[:beam_width]

            for prefix, (p_blank, p_non_blank) in sorted_prefixes:
                p_total = _log_add(p_blank, p_non_blank)
                log_p_blank_char = float(log_probs_np[t, b, 0])

                new_p_blank = p_total + log_p_blank_char
                if prefix in new_beams:
                    old = new_beams[prefix]
                    new_beams[prefix] = (
                        _log_add(old[0], new_p_blank),
                        old[1],
                    )
                else:
                    new_beams[prefix] = (new_p_blank, NEG_INF)

                for c in range(1, num_classes):
                    log_p_c = float(log_probs_np[t, b, c])
                    char = idx_to_char[c]

                    if prefix and char == prefix[-1]:
                        new_prefix = prefix + char
                        new_p_nb = p_blank + log_p_c
                        if new_prefix in new_beams:
                            old = new_beams[new_prefix]
                            new_beams[new_prefix] = (
                                old[0],
                                _log_add(old[1], new_p_nb),
                            )
                        else:
                            new_beams[new_prefix] = (NEG_INF, new_p_nb)

                        same_p_nb = p_non_blank + log_p_c
                        if prefix in new_beams:
                            old = new_beams[prefix]
                            new_beams[prefix] = (
                                old[0],
                                _log_add(old[1], same_p_nb),
                            )
                        else:
                            new_beams[prefix] = (NEG_INF, same_p_nb)
                    else:
                        new_prefix = prefix + char
                        new_p_nb = p_total + log_p_c
                        if new_prefix in new_beams:
                            old = new_beams[new_prefix]
                            new_beams[new_prefix] = (
                                old[0],
                                _log_add(old[1], new_p_nb),
                            )
                        else:
                            new_beams[new_prefix] = (NEG_INF, new_p_nb)

            beams = new_beams

        candidates = [
            (prefix, _log_add(p_b, p_nb))
            for prefix, (p_b, p_nb) in beams.items()
        ]
        candidates.sort(key=lambda x: x[1], reverse=True)
        all_results.append(candidates[:top_k])

    return all_results


def ctc_forced_log_prob(log_probs: torch.Tensor, label: str) -> float:
    """
    CTC forward algorithm: compute log P(label | log_probs).

    Args:
        log_probs: (T, num_classes) log-probabilities for a single cell
        label: Target character sequence (e.g. "Cf3")

    Returns:
        Log-probability of the label given the CTC output.
    """
    S = len(label)
    if S == 0:
        return NEG_INF

    for ch in label:
        if ch not in char_to_idx:
            return NEG_INF

    T = log_probs.shape[0]
    lp = log_probs.cpu().numpy()

    expanded_len = 2 * S + 1
    expanded: list[int] = [0] * expanded_len
    for i, ch in enumerate(label):
        expanded[2 * i + 1] = char_to_idx[ch]

    alpha = [[NEG_INF] * expanded_len for _ in range(T)]
    alpha[0][0] = float(lp[0, 0])
    alpha[0][1] = float(lp[0, expanded[1]])

    for t in range(1, T):
        for s in range(expanded_len):
            a = alpha[t - 1][s]
            if s >= 1:
                a = _log_add(a, alpha[t - 1][s - 1])
            if s >= 2 and expanded[s] != 0 and expanded[s] != expanded[s - 2]:
                a = _log_add(a, alpha[t - 1][s - 2])
            alpha[t][s] = a + float(lp[t, expanded[s]])

    return _log_add(alpha[T - 1][expanded_len - 1], alpha[T - 1][expanded_len - 2])
