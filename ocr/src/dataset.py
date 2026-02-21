"""PyTorch Dataset for chess move cell images."""

import csv
from pathlib import Path

import numpy as np
import torch
from PIL import Image
from torch.utils.data import Dataset

from .alphabet import char_to_idx

IMG_HEIGHT = 32
IMG_WIDTH = 128


def encode_label(text: str) -> list[int]:
    """Encode a move string to a list of alphabet indices (no blank)."""
    return [char_to_idx[c] for c in text]


class ChessMoveDataset(Dataset):
    """
    Expects:
      - data_dir/ containing cell images
      - data_dir/labels.csv with rows: filename,label
    """

    def __init__(self, data_dir: str | Path):
        self.data_dir = Path(data_dir)
        self.samples: list[tuple[str, str]] = []

        labels_file = self.data_dir / "labels.csv"
        with open(labels_file, newline="") as f:
            reader = csv.reader(f)
            for row in reader:
                if len(row) >= 2:
                    self.samples.append((row[0], row[1]))

    def __len__(self) -> int:
        return len(self.samples)

    def __getitem__(self, idx: int) -> tuple[torch.Tensor, torch.Tensor, int]:
        filename, label = self.samples[idx]
        img_path = self.data_dir / filename

        img = Image.open(img_path).convert("L")
        img = img.resize((IMG_WIDTH, IMG_HEIGHT), Image.BILINEAR)

        arr = np.array(img, dtype=np.float32) / 255.0
        tensor = torch.from_numpy(arr).unsqueeze(0)  # (1, H, W)

        target = torch.tensor(encode_label(label), dtype=torch.long)
        target_length = len(label)

        return tensor, target, target_length


def collate_fn(
    batch: list[tuple[torch.Tensor, torch.Tensor, int]],
) -> tuple[torch.Tensor, torch.Tensor, torch.Tensor, torch.Tensor]:
    """Collate variable-length targets for CTC loss."""
    images, targets, target_lengths = zip(*batch)

    images = torch.stack(images, dim=0)
    targets = torch.cat(targets, dim=0)
    target_lengths = torch.tensor(target_lengths, dtype=torch.long)

    return images, targets, target_lengths
