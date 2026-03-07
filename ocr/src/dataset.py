"""PyTorch Dataset for chess move cell images."""

import csv
import random
from pathlib import Path

import cv2
import numpy as np
import torch
from PIL import Image, ImageEnhance
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

    def __init__(self, data_dir: str | Path, train: bool = False):
        self.data_dir = Path(data_dir)
        self.train = train
        self.samples: list[tuple[str, str]] = []

        labels_file = self.data_dir / "labels.csv"
        with open(labels_file, newline="") as f:
            reader = csv.reader(f)
            for row in reader:
                if len(row) >= 1:
                    label = row[1] if len(row) >= 2 else ""
                    self.samples.append((row[0], label))

    def __len__(self) -> int:
        return len(self.samples)

    def _augment(self, img: Image.Image) -> Image.Image:
        bg = 255

        if random.random() < 0.5:
            angle = random.uniform(-3, 3)
            img = img.rotate(angle, resample=Image.BILINEAR, fillcolor=bg)

        if random.random() < 0.5:
            img = ImageEnhance.Brightness(img).enhance(random.uniform(0.7, 1.3))

        if random.random() < 0.5:
            img = ImageEnhance.Contrast(img).enhance(random.uniform(0.7, 1.3))

        if random.random() < 0.5:
            w, h = img.size
            dx = int(w * random.uniform(-0.08, 0.08))
            img = img.transform((w, h), Image.AFFINE, (1, 0, -dx, 0, 1, 0), fillcolor=bg)

        arr = np.array(img, dtype=np.float32)

        if random.random() < 0.3:
            sigma = random.uniform(5, 15)
            noise = np.random.normal(0, sigma, arr.shape).astype(np.float32)
            arr = np.clip(arr + noise, 0, 255)

        if random.random() < 0.3:
            kernel = np.ones((2, 2), np.uint8)
            if random.random() < 0.5:
                arr = cv2.erode(arr.astype(np.uint8), kernel, iterations=1).astype(np.float32)
            else:
                arr = cv2.dilate(arr.astype(np.uint8), kernel, iterations=1).astype(np.float32)

        return Image.fromarray(arr.astype(np.uint8), mode="L")

    def __getitem__(self, idx: int) -> tuple[torch.Tensor, torch.Tensor, int]:
        filename, label = self.samples[idx]
        img_path = self.data_dir / filename

        img = Image.open(img_path).convert("L")

        if self.train:
            img = self._augment(img)

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
    targets = torch.cat(targets, dim=0) if any(t.numel() > 0 for t in targets) else torch.tensor([], dtype=torch.long)
    target_lengths = torch.tensor(target_lengths, dtype=torch.long)

    return images, targets, target_lengths
