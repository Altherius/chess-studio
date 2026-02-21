"""Training entrypoint for chess move OCR model."""

import argparse
from pathlib import Path

import torch
import torch.nn as nn
from torch.utils.data import DataLoader, random_split

from src.alphabet import num_classes
from src.dataset import ChessMoveDataset, collate_fn
from src.decode import greedy_decode
from src.model import CRNN


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Train chess move OCR model")
    parser.add_argument("--data", type=str, default="data", help="Path to data directory")
    parser.add_argument("--epochs", type=int, default=50, help="Number of training epochs")
    parser.add_argument("--batch-size", type=int, default=32, help="Batch size")
    parser.add_argument("--lr", type=float, default=1e-3, help="Learning rate")
    parser.add_argument("--val-split", type=float, default=0.1, help="Validation split ratio")
    parser.add_argument("--output", type=str, default="models/best.pt", help="Output model path")
    return parser.parse_args()


def train(args: argparse.Namespace) -> None:
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    print(f"Using device: {device}")

    dataset = ChessMoveDataset(args.data)
    val_size = max(1, int(len(dataset) * args.val_split))
    train_size = len(dataset) - val_size
    train_set, val_set = random_split(dataset, [train_size, val_size])

    train_loader = DataLoader(
        train_set, batch_size=args.batch_size, shuffle=True, collate_fn=collate_fn
    )
    val_loader = DataLoader(
        val_set, batch_size=args.batch_size, shuffle=False, collate_fn=collate_fn
    )

    model = CRNN(num_classes).to(device)
    optimizer = torch.optim.Adam(model.parameters(), lr=args.lr)
    ctc_loss = nn.CTCLoss(blank=0, zero_infinity=True)

    best_accuracy = 0.0
    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    for epoch in range(1, args.epochs + 1):
        # Training
        model.train()
        total_loss = 0.0
        for images, targets, target_lengths in train_loader:
            images = images.to(device)
            targets = targets.to(device)
            target_lengths = target_lengths.to(device)

            log_probs = model(images)  # (T, batch, num_classes)
            T, batch, _ = log_probs.size()
            input_lengths = torch.full((batch,), T, dtype=torch.long, device=device)

            loss = ctc_loss(log_probs, targets, input_lengths, target_lengths)

            optimizer.zero_grad()
            loss.backward()
            torch.nn.utils.clip_grad_norm_(model.parameters(), 5.0)
            optimizer.step()

            total_loss += loss.item()

        avg_loss = total_loss / len(train_loader)

        # Validation
        model.eval()
        correct = 0
        total = 0
        with torch.no_grad():
            for images, targets, target_lengths in val_loader:
                images = images.to(device)
                log_probs = model(images)
                decoded = greedy_decode(log_probs)

                # Reconstruct ground truth strings from flat targets
                offset = 0
                for i, length in enumerate(target_lengths):
                    gt_indices = targets[offset : offset + length].tolist()
                    from src.alphabet import idx_to_char
                    gt = "".join(idx_to_char[idx] for idx in gt_indices)
                    if decoded[i] == gt:
                        correct += 1
                    total += 1
                    offset += length

        accuracy = correct / total if total > 0 else 0.0
        print(f"Epoch {epoch}/{args.epochs} — loss: {avg_loss:.4f} — val accuracy: {accuracy:.2%}")

        if accuracy > best_accuracy:
            best_accuracy = accuracy
            torch.save(model.state_dict(), output_path)
            print(f"  Saved best model ({accuracy:.2%})")

    print(f"Training complete. Best accuracy: {best_accuracy:.2%}")

    # Diagnostic: load best model and show predictions vs ground truth on val set
    print("\n--- Validation diagnostic (best model) ---")
    model.load_state_dict(torch.load(output_path, map_location=device, weights_only=True))
    model.eval()

    from src.alphabet import idx_to_char

    errors = []
    with torch.no_grad():
        for images, targets, target_lengths in val_loader:
            images = images.to(device)
            log_probs = model(images)
            decoded = greedy_decode(log_probs)

            offset = 0
            for i, length in enumerate(target_lengths):
                gt_indices = targets[offset : offset + length].tolist()
                gt = "".join(idx_to_char[idx] for idx in gt_indices)
                pred = decoded[i]
                label = "OK" if pred == gt else "WRONG"
                if pred != gt:
                    errors.append((gt, pred))
                gt_display = gt if gt else "(empty)"
                pred_display = pred if pred else "(empty)"
                print(f"  {label:5s}  expected: {gt_display:8s}  got: {pred_display}")
                offset += length

    if errors:
        print(f"\n{len(errors)} error(s):")
        for gt, pred in errors:
            gt_display = gt if gt else "(empty)"
            pred_display = pred if pred else "(empty)"
            print(f"  {gt_display:8s} → {pred_display}")
    else:
        print("\nAll validation samples correct!")


if __name__ == "__main__":
    args = parse_args()
    train(args)
