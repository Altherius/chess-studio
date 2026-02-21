"""Split a score sheet image into individual cell crops using OpenCV."""

import cv2
import numpy as np


def extract_cells(image_path: str, min_area: int = 500) -> list[np.ndarray]:
    """
    Extract cell crops from a score sheet image.

    Steps:
    1. Convert to grayscale
    2. Adaptive threshold to get binary image
    3. Find contours and filter by rectangular shape
    4. Sort cells top-to-bottom, left-to-right
    5. Return list of cropped cell images
    """
    img = cv2.imread(image_path)
    if img is None:
        raise FileNotFoundError(f"Cannot read image: {image_path}")

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    binary = cv2.adaptiveThreshold(
        gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 15, 10
    )

    contours, _ = cv2.findContours(binary, cv2.RETR_TREE, cv2.CHAIN_APPROX_SIMPLE)

    cells = []
    for cnt in contours:
        area = cv2.contourArea(cnt)
        if area < min_area:
            continue

        x, y, w, h = cv2.boundingRect(cnt)
        aspect = w / h if h > 0 else 0

        # Cell-like rectangles: wider than tall, reasonable aspect ratio
        if 1.5 < aspect < 8.0:
            cell = gray[y : y + h, x : x + w]
            cells.append((x, y, w, h, cell))

    # Sort by row (y) then column (x), grouping rows within a tolerance
    if not cells:
        return []

    cells.sort(key=lambda c: (c[1], c[0]))

    # Group into rows using y-coordinate tolerance
    row_tolerance = cells[0][3] * 0.5 if cells else 30
    sorted_cells = []
    current_row: list = [cells[0]]

    for cell in cells[1:]:
        if abs(cell[1] - current_row[0][1]) < row_tolerance:
            current_row.append(cell)
        else:
            current_row.sort(key=lambda c: c[0])
            sorted_cells.extend(current_row)
            current_row = [cell]
    current_row.sort(key=lambda c: c[0])
    sorted_cells.extend(current_row)

    return [cell[4] for cell in sorted_cells]
