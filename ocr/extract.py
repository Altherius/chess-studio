import cv2, os
from src.preprocess import extract_cells

os.makedirs('data/cells', exist_ok=True)
sheets_dir = 'data/sheets'

for sheet_file in sorted(os.listdir(sheets_dir)):
  path = os.path.join(sheets_dir, sheet_file)
  cells = extract_cells(path)
  prefix = os.path.splitext(sheet_file)[0]
  for i, cell in enumerate(cells):
      out = f'data/cells/{prefix}_{i:03d}.png'
      cv2.imwrite(out, cell)
  print(f'{sheet_file}: {len(cells)} cells extracted')