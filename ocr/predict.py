"""CLI inference: image → predicted chess move text."""

import argparse
from statistics import median

import cv2
import numpy as np
import torch
from PIL import Image

from src.alphabet import num_classes
from src.dataset import IMG_HEIGHT, IMG_WIDTH
from src.decode import greedy_decode
from src.model import CRNN
from src.preprocess import extract_cells, extract_rows


def load_model(model_path: str, device: torch.device) -> CRNN:
    model = CRNN(num_classes).to(device)
    model.load_state_dict(torch.load(model_path, map_location=device, weights_only=True))
    model.eval()
    return model


def preprocess_image(img: np.ndarray) -> torch.Tensor:
    """Convert a grayscale numpy image to model input tensor."""
    pil = Image.fromarray(img).convert("L")
    pil = pil.resize((IMG_WIDTH, IMG_HEIGHT), Image.BILINEAR)
    arr = np.array(pil, dtype=np.float32) / 255.0
    return torch.from_numpy(arr).unsqueeze(0).unsqueeze(0)  # (1, 1, H, W)


def predict_cell(model: CRNN, img: np.ndarray, device: torch.device) -> str:
    tensor = preprocess_image(img).to(device)
    with torch.no_grad():
        log_probs = model(tensor)
    return greedy_decode(log_probs)[0]


def _classify_columns(
    rows: list[list[tuple[np.ndarray, int, int, int, int]]],
) -> tuple[float, list[list[tuple[np.ndarray, int, int, int, int]]]]:
    """
    Determine the median cell width and filter out narrow "move number" cells.

    Returns (median_width, filtered_rows with only move cells).
    """
    all_widths = [w for row in rows for _, _, _, w, _ in row]
    if not all_widths:
        return 0, rows

    med_w = median(all_widths)
    width_threshold = med_w * 0.6

    filtered = []
    for row in rows:
        move_cells = [(img, x, y, w, h) for img, x, y, w, h in row if w >= width_threshold]
        if move_cells:
            filtered.append(move_cells)

    return med_w, filtered


def _build_game_moves(
    rows: list[list[tuple[np.ndarray, int, int, int, int]]],
    model: CRNN,
    device: torch.device,
) -> list[tuple[int, str, str]]:
    """
    Map the 4-column layout to structured moves.

    Columns 0-1 = white/black moves 1-40, columns 2-3 = white/black moves 41-80.
    Returns list of (move_number, white_move, black_move).
    """
    _, filtered_rows = _classify_columns(rows)

    expected_cols = 4
    data_rows = [row for row in filtered_rows if len(row) >= expected_cols]

    if not data_rows:
        data_rows = [row for row in filtered_rows if len(row) >= 2]
        expected_cols = 2

    moves: list[tuple[int, str, str]] = []

    for row_idx, row in enumerate(data_rows):
        if expected_cols == 4 and len(row) >= 4:
            white_1 = predict_cell(model, row[0][0], device)
            black_1 = predict_cell(model, row[1][0], device)
            move_num_1 = row_idx + 1
            moves.append((move_num_1, white_1, black_1))

            white_2 = predict_cell(model, row[2][0], device)
            black_2 = predict_cell(model, row[3][0], device)
            move_num_2 = row_idx + 1 + len(data_rows)
            moves.append((move_num_2, white_2, black_2))
        elif len(row) >= 2:
            white = predict_cell(model, row[0][0], device)
            black = predict_cell(model, row[1][0], device)
            moves.append((row_idx + 1, white, black))

    moves.sort(key=lambda m: m[0])
    return moves


def _format_game(moves: list[tuple[int, str, str]]) -> str:
    """Format moves into a readable game string, stopping on 2 consecutive empty cells."""
    parts = []
    consecutive_empty = 0

    for move_num, white, black in moves:
        if not white and not black:
            consecutive_empty += 2
        elif not black:
            consecutive_empty = 0
            parts.append(f"{move_num}. {white}")
            consecutive_empty = 1
        elif not white:
            consecutive_empty += 1
            if consecutive_empty >= 2:
                break
            parts.append(f"{move_num}... {black}")
            consecutive_empty = 0
        else:
            consecutive_empty = 0
            parts.append(f"{move_num}. {white} {black}")

        if consecutive_empty >= 2:
            break

    return " ".join(parts)


def main() -> None:
    parser = argparse.ArgumentParser(description="Predict chess moves from images")
    parser.add_argument("--model", type=str, required=True, help="Path to trained model")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--image", type=str, help="Single cell image to predict")
    group.add_argument("--sheet", type=str, help="Full score sheet to split and predict")
    args = parser.parse_args()

    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    model = load_model(args.model, device)

    if args.image:
        img = cv2.imread(args.image, cv2.IMREAD_GRAYSCALE)
        if img is None:
            print(f"Error: cannot read image {args.image}")
            return
        result = predict_cell(model, img, device)
        print(result)

    elif args.sheet:
        rows = extract_rows(args.sheet)
        if not rows:
            print("No cells detected in the score sheet.")
            return

        total_cells = sum(len(row) for row in rows)
        print(f"Detected {total_cells} cells in {len(rows)} rows")

        moves = _build_game_moves(rows, model, device)
        game_str = _format_game(moves)
        print(game_str)


if __name__ == "__main__":
    main()
