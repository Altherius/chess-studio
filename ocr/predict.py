"""CLI inference: image → predicted chess move text."""

import argparse

import cv2
import numpy as np
import torch
from PIL import Image

from src.alphabet import num_classes
from src.dataset import IMG_HEIGHT, IMG_WIDTH
from src.decode import greedy_decode
from src.model import CRNN
from src.preprocess import extract_cells


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
        cells = extract_cells(args.sheet)
        if not cells:
            print("No cells detected in the score sheet.")
            return
        print(f"Detected {len(cells)} cells:")
        for i, cell in enumerate(cells):
            move = predict_cell(model, cell, device)
            print(f"  {i + 1:3d}. {move}")


if __name__ == "__main__":
    main()
