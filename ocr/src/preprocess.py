"""Split a score sheet image into individual cell crops using OpenCV."""

import cv2
import numpy as np


def _deskew(img: np.ndarray) -> np.ndarray:
    """
    Detect and correct skew by measuring the angle of near-horizontal lines
    using the Hough transform, then rotating to straighten.
    """
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY) if len(img.shape) == 3 else img
    binary = cv2.adaptiveThreshold(
        gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 15, 10
    )
    h, w = binary.shape

    lines = cv2.HoughLinesP(
        binary, 1, np.pi / 3600, threshold=200,
        minLineLength=int(w * 0.1), maxLineGap=5,
    )
    if lines is None:
        return img

    angles = []
    for line in lines:
        x1, y1, x2, y2 = line[0]
        angle = np.degrees(np.arctan2(y2 - y1, x2 - x1))
        if abs(angle) < 10:
            angles.append(angle)

    if not angles:
        return img

    skew = float(np.median(angles))
    if abs(skew) < 0.05:
        return img

    center = (w / 2, h / 2)
    matrix = cv2.getRotationMatrix2D(center, skew, 1.0)
    rotated = cv2.warpAffine(
        img, matrix, (w, h),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_REPLICATE,
    )
    return rotated


def _detect_lines(binary: np.ndarray, axis: str, min_length_ratio: float = 0.3) -> list[int]:
    """
    Detect grid lines along a given axis using morphological operations.

    Returns sorted list of line positions (y-coords for horizontal, x-coords for vertical).
    """
    h, w = binary.shape

    if axis == "horizontal":
        kernel_len = int(w * min_length_ratio)
        kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (kernel_len, 1))
        morph = cv2.morphologyEx(binary, cv2.MORPH_OPEN, kernel)
        projection = np.sum(morph, axis=1)
    else:
        kernel_len = int(h * min_length_ratio)
        kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (1, kernel_len))
        morph = cv2.morphologyEx(binary, cv2.MORPH_OPEN, kernel)
        projection = np.sum(morph, axis=0)

    threshold = projection.max() * 0.3
    positions = np.where(projection > threshold)[0]

    if len(positions) == 0:
        return []

    groups: list[list[int]] = [[positions[0]]]
    for pos in positions[1:]:
        if pos - groups[-1][-1] <= 5:
            groups[-1].append(pos)
        else:
            groups.append([pos])

    return [int(np.mean(g)) for g in groups]


def _find_move_columns(v_lines: list[int], expected_width: int = 280) -> list[int]:
    """
    Find the 5 vertical lines that form 4 move columns of roughly equal width.

    Tries all combinations of 5 lines from the candidates and picks the one
    where all 4 gaps are closest to expected_width.
    """
    from itertools import combinations

    if len(v_lines) < 5:
        return []

    best_score = float("inf")
    best_combo = None
    for combo in combinations(v_lines, 5):
        gaps = [combo[i + 1] - combo[i] for i in range(4)]
        if any(g < expected_width * 0.6 or g > expected_width * 1.4 for g in gaps):
            continue
        score = sum((g - expected_width) ** 2 for g in gaps)
        if score < best_score:
            best_score = score
            best_combo = combo

    return list(best_combo) if best_combo else []


def _find_row_lines(h_lines: list[int], expected_height: int = 70) -> list[int]:
    """
    From raw horizontal lines, find the consistent grid rows.

    Deduplicates nearby lines, builds the longest chain of lines
    with spacing close to expected_height (allowing small misses),
    then interpolates any gaps where a line was missed.
    """
    if len(h_lines) < 2:
        return []

    deduped = [h_lines[0]]
    for hl in h_lines[1:]:
        if hl - deduped[-1] > expected_height * 0.4:
            deduped.append(hl)

    best_run: list[int] = []
    for start in range(len(deduped)):
        run = [deduped[start]]
        for j in range(start + 1, len(deduped)):
            gap = deduped[j] - run[-1]
            if expected_height * 0.8 <= gap <= expected_height * 1.2:
                run.append(deduped[j])
            elif gap > expected_height * 1.2:
                break
        if len(run) > len(best_run):
            best_run = run

    if len(best_run) < 5:
        return best_run

    # Compute median row height from the best run
    gaps = [best_run[i + 1] - best_run[i] for i in range(len(best_run) - 1)]
    median_h = int(np.median(gaps))

    # Extrapolate: find all lines in deduped that fall on the grid
    y_start = best_run[0]
    y_end = deduped[-1]
    grid: list[int] = []
    y = y_start
    while y <= y_end + median_h // 2:
        closest = min(deduped, key=lambda l: abs(l - y))
        if abs(closest - y) < median_h * 0.3:
            grid.append(closest)
            y = closest + median_h
        else:
            grid.append(y)
            y += median_h

    # Check for a shorter first row above the grid start (e.g. right below a header)
    candidates_above = [l for l in deduped if grid[0] - median_h < l < grid[0] and grid[0] - l >= median_h * 0.4]
    if candidates_above:
        grid.insert(0, max(candidates_above))

    return grid


def extract_cells(image_path: str) -> list[np.ndarray]:
    """
    Extract cell crops from a score sheet image.

    Uses morphological line detection to find the grid, then crops
    cells at grid intersections. This avoids splitting cells when
    handwriting touches cell borders.
    """
    img = cv2.imread(image_path)
    if img is None:
        raise FileNotFoundError(f"Cannot read image: {image_path}")

    img = _deskew(img)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    binary = cv2.adaptiveThreshold(
        gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 15, 10
    )

    expected_row_height = 70
    expected_col_width = 280

    # Try progressively shorter kernels for horizontal lines
    data_h_lines: list[int] = []
    for ratio in [0.1, 0.07, 0.05, 0.03]:
        raw = _detect_lines(binary, "horizontal", min_length_ratio=ratio)
        data_h_lines = _find_row_lines(raw, expected_row_height)
        if len(data_h_lines) >= 30:
            break

    data_v_lines: list[int] = []
    for ratio in [0.03, 0.02, 0.01]:
        v_lines = _detect_lines(binary, "vertical", min_length_ratio=ratio)
        data_v_lines = _find_move_columns(v_lines, expected_col_width)
        if len(data_v_lines) == 5:
            break

    if len(data_v_lines) < 2 or len(data_h_lines) < 2:
        return []

    grid: dict[tuple[int, int], np.ndarray] = {}
    margin = 2
    valid_rows: list[int] = []
    for r in range(len(data_h_lines) - 1):
        y1, y2 = data_h_lines[r], data_h_lines[r + 1]
        row_h = y2 - y1
        min_ratio = 0.5 if r == 0 else 0.85
        if row_h < expected_row_height * min_ratio or row_h > expected_row_height * 1.15:
            continue
        valid_rows.append(r)
        for c in range(len(data_v_lines) - 1):
            x1, x2 = data_v_lines[c], data_v_lines[c + 1]
            if x2 - x1 < expected_col_width * 0.5:
                continue
            shift = int((y2 - y1) * 0.1)
            cell = gray[y1 + shift : y2 + shift, x1 + margin : x2 - margin]
            if c % 2 == 0:
                cell = cell[:, 60:]
            grid[(r, c)] = cell

    n_cols = len(data_v_lines) - 1
    half = n_cols // 2

    cells = []
    for col_start in range(0, n_cols, half):
        for r in valid_rows:
            for c in range(col_start, min(col_start + half, n_cols)):
                if (r, c) in grid:
                    cells.append(grid[(r, c)])

    return cells
