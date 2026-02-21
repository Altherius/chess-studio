"""CTC greedy decoder."""

import torch

from .alphabet import BLANK, idx_to_char


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
