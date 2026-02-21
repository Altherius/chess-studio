"""CRNN model for chess move OCR (CNN + BiLSTM + CTC)."""

import torch
import torch.nn as nn


class CRNN(nn.Module):
    """
    Input: grayscale image (batch, 1, 32, 128)
    Output: (T, batch, num_classes) log-probabilities for CTC
    """

    def __init__(self, num_classes: int, rnn_hidden: int = 128):
        super().__init__()

        # CNN feature extractor: 3 conv blocks
        self.cnn = nn.Sequential(
            # Block 1: 1 -> 32 channels, pool 2x2
            nn.Conv2d(1, 32, 3, padding=1),
            nn.BatchNorm2d(32),
            nn.ReLU(inplace=True),
            nn.MaxPool2d(2, 2),
            # Block 2: 32 -> 64 channels, pool 2x2
            nn.Conv2d(32, 64, 3, padding=1),
            nn.BatchNorm2d(64),
            nn.ReLU(inplace=True),
            nn.MaxPool2d(2, 2),
            # Block 3: 64 -> 128 channels, pool (2,1) to keep width
            nn.Conv2d(64, 128, 3, padding=1),
            nn.BatchNorm2d(128),
            nn.ReLU(inplace=True),
            nn.MaxPool2d((2, 1), (2, 1)),
        )
        # After CNN: (batch, 128, 4, 32) for input 32x128
        # Collapse height: 128 * 4 = 512 features per width step

        self.rnn = nn.LSTM(
            input_size=128 * 4,
            hidden_size=rnn_hidden,
            num_layers=2,
            bidirectional=True,
            batch_first=False,
        )

        self.fc = nn.Linear(rnn_hidden * 2, num_classes)

    def forward(self, x: torch.Tensor) -> torch.Tensor:
        # x: (batch, 1, 32, 128)
        conv = self.cnn(x)  # (batch, 128, 4, W)

        batch, channels, height, width = conv.size()
        # Reshape to (width, batch, channels * height) — one feature vector per column
        conv = conv.permute(3, 0, 1, 2)  # (W, batch, C, H)
        conv = conv.reshape(width, batch, channels * height)

        rnn_out, _ = self.rnn(conv)  # (W, batch, rnn_hidden*2)
        output = self.fc(rnn_out)  # (W, batch, num_classes)

        return output.log_softmax(dim=2)
