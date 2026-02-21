"""Generate a skeleton labels.csv from cell images in data/cells/."""

import os

CELLS_DIR = "data/cells"
OUTPUT = "data/labels.csv"

files = sorted(f for f in os.listdir(CELLS_DIR) if f.lower().endswith((".png", ".jpg", ".jpeg")))

with open(OUTPUT, "w") as f:
    for name in files:
        f.write(f"cells/{name},\n")

print(f"{len(files)} entries written to {OUTPUT}")
