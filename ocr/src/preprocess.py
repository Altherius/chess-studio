"""Split a score sheet image into individual cell crops using OpenCV."""

import cv2
import numpy as np


def _detect_cells(
    image_path: str, min_area: int = 500
) -> list[tuple[int, int, int, int, np.ndarray]]:
    """
    Detect cell-like rectangular contours and return them sorted by row then column.

    Returns list of (x, y, w, h, grayscale_crop) tuples.
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

        if 1.5 < aspect < 8.0:
            cell = gray[y : y + h, x : x + w]
            cells.append((x, y, w, h, cell))

    if not cells:
        return []

    cells.sort(key=lambda c: (c[1], c[0]))

    row_tolerance = cells[0][3] * 0.5 if cells else 30
    sorted_cells: list[tuple[int, int, int, int, np.ndarray]] = []
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

    return sorted_cells


def _apply_top_inset(
    cell: np.ndarray, top_inset_ratio: float
) -> np.ndarray:
    """Clip the top portion of a cell image to remove descender remnants from above."""
    if top_inset_ratio <= 0:
        return cell
    inset_px = int(cell.shape[0] * top_inset_ratio)
    return cell[inset_px:]


def extract_cells_with_positions(
    image_path: str, min_area: int = 500, top_inset_ratio: float = 0.08
) -> list[tuple[np.ndarray, int, int, int, int]]:
    """
    Extract cell crops with their position metadata.

    Returns list of (image, x, y, w, h) tuples, sorted top-to-bottom then left-to-right.
    """
    raw = _detect_cells(image_path, min_area)
    return [
        (_apply_top_inset(img, top_inset_ratio), x, y, w, h)
        for x, y, w, h, img in raw
    ]


def extract_rows(
    image_path: str,
    min_area: int = 500,
    top_inset_ratio: float = 0.08,
    skip_header_rows: int = 1,
) -> list[list[tuple[np.ndarray, int, int, int, int]]]:
    """
    Extract cells grouped by row, skipping header rows.

    Returns list of rows, each row is a list of (image, x, y, w, h) tuples sorted left-to-right.
    The first `skip_header_rows` rows (typically containing "BLANCS"/"NOIRS" headers) are excluded.
    """
    cells = extract_cells_with_positions(image_path, min_area, top_inset_ratio)
    if not cells:
        return []

    rows: list[list[tuple[np.ndarray, int, int, int, int]]] = []
    current_row = [cells[0]]
    row_tolerance = cells[0][4] * 0.5  # h * 0.5

    for cell in cells[1:]:
        if abs(cell[2] - current_row[0][2]) < row_tolerance:  # compare y
            current_row.append(cell)
        else:
            current_row.sort(key=lambda c: c[1])  # sort by x
            rows.append(current_row)
            current_row = [cell]
    current_row.sort(key=lambda c: c[1])
    rows.append(current_row)

    return rows[skip_header_rows:]


def extract_cells(
    image_path: str,
    min_area: int = 500,
    top_inset_ratio: float = 0.08,
    skip_header_rows: int = 1,
) -> list[np.ndarray]:
    """
    Extract cell crops from a score sheet image (backward-compatible).

    Returns list of cropped grayscale cell images sorted top-to-bottom, left-to-right.
    Header rows (e.g. "BLANCS"/"NOIRS") are skipped.
    """
    rows = extract_rows(image_path, min_area, top_inset_ratio, skip_header_rows)
    return [img for row in rows for img, *_ in row]
