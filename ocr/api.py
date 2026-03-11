"""Minimal Flask API exposing OCR prediction for score sheet images."""

import os
import tempfile

import torch
from flask import Flask, jsonify, request

from src.alphabet import num_classes
from src.decode import beam_search_decode
from src.engine_decode import decode_game
from src.model import CRNN
from src.preprocess import extract_cells
from predict import load_model, preprocess_image

app = Flask(__name__)

MODEL_PATH = os.environ.get("OCR_MODEL_PATH", "models/best.pt")
device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
model: CRNN | None = None


def get_model() -> CRNN:
    global model
    if model is None:
        model = load_model(MODEL_PATH, device)
    return model


@app.route("/predict", methods=["POST"])
def predict():
    if "image" not in request.files:
        return jsonify({"error": "No image file provided"}), 400

    file = request.files["image"]
    suffix = os.path.splitext(file.filename or "img.jpg")[1]

    with tempfile.NamedTemporaryFile(suffix=suffix, delete=True) as tmp:
        file.save(tmp.name)

        cells = extract_cells(tmp.name)
        if not cells:
            return jsonify({"error": "Aucune case détectée dans l'image."}), 422

        m = get_model()
        batch = torch.cat([preprocess_image(c) for c in cells]).to(device)
        with torch.no_grad():
            log_probs = m(batch)

        all_candidates = beam_search_decode(log_probs)
        moves = decode_game(all_candidates, log_probs=log_probs)

    parts = []
    move_num = 1
    for i in range(0, len(moves), 2):
        white = moves[i]
        black = moves[i + 1] if i + 1 < len(moves) else ""
        if not white and not black:
            break
        entry = f"{move_num}. {white}"
        if black:
            entry += f" {black}"
        parts.append(entry)
        move_num += 1

    return jsonify({"moves": " ".join(parts)})


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
