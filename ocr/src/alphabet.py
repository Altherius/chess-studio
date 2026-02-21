"""Chess move character set for CTC-based OCR."""

# CTC blank is always index 0
BLANK = "⌀"

# Characters that appear in French chess notation
_CHARS = (
    "abcdefgh"   # files
    "12345678"   # ranks
    "CFTDR"      # pieces: Cavalier, Fou, Tour, Dame, Roi
    "x+#O-="     # capture, check, checkmate, castling, promotion
)

ALPHABET = BLANK + _CHARS

char_to_idx: dict[str, int] = {c: i for i, c in enumerate(ALPHABET)}
idx_to_char: dict[int, str] = {i: c for i, c in enumerate(ALPHABET)}

num_classes: int = len(ALPHABET)  # includes blank
