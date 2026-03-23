from pathlib import Path
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
import re

ROOT = Path(__file__).resolve().parents[1]
FILES = [
    ROOT / "UserSide" / "backends" / "encryptionService.js",
    ROOT / "AdminSide" / "admin" / "app" / "Services" / "EncryptionService.php",
]
OUTPUT = ROOT / "docs" / "encryption_code_for_presentation.pdf"

KEYWORDS = {
    "const", "let", "var", "function", "return", "if", "else", "try", "catch", "for", "while",
    "class", "public", "private", "static", "namespace", "use", "new", "throw", "true", "false", "null"
}

# VS Code-like dark theme colors (closer to editor screenshot)
PAGE_BG = colors.HexColor("#1e1e1e")
LINE_NO = colors.HexColor("#858585")
TEXT = colors.HexColor("#d4d4d4")
COMMENT = colors.HexColor("#6A9955")
STRING = colors.HexColor("#CE9178")
KEYWORD = colors.HexColor("#569CD6")
NUMBER = colors.HexColor("#B5CEA8")
PANEL_BORDER = colors.HexColor("#252526")
GUTTER_BG = colors.HexColor("#1b1b1b")
GUTTER_DIVIDER = colors.HexColor("#2a2a2a")


def split_tokens(line: str):
    return re.findall(
        r"//.*|\"(?:\\.|[^\"\\])*\"|'(?:\\.|[^'\\])*'|[A-Za-z_][A-Za-z0-9_]*|\d+|\s+|\S",
        line,
    )


def token_color(token: str, in_comment: bool):
    if in_comment:
        return COMMENT
    if token.startswith("//"):
        return COMMENT
    if (token.startswith('"') and token.endswith('"')) or (token.startswith("'") and token.endswith("'")):
        return STRING
    if token in KEYWORDS:
        return KEYWORD
    if token.isdigit():
        return NUMBER
    return TEXT


def draw_color_line(c: canvas.Canvas, line: str, x: float, y: float, max_width: float, font_name: str, font_size: int):
    tokens = split_tokens(line)
    current_x = x
    in_comment = False

    for token in tokens:
        if not token:
            continue

        width = pdfmetrics.stringWidth(token, font_name, font_size)
        if current_x + width > x + max_width:
            break

        if token.startswith("//"):
            in_comment = True

        c.setFillColor(token_color(token, in_comment))

        c.drawString(current_x, y, token)
        current_x += width

    c.setFillColor(TEXT)


def start_vscode_page(c: canvas.Canvas, width: float, height: float, margin: float, rel_path: str, continued: bool = False):
    # Full editor-style dark page
    c.setFillColor(PAGE_BG)
    c.rect(0, 0, width, height, stroke=0, fill=1)

    # Filename label (requested)
    c.setFillColor(colors.HexColor("#c5c5c5"))
    c.setFont("Helvetica", 8)
    label = rel_path + (" (continued)" if continued else "")
    c.drawString(margin, height - (margin - 10), label)

    panel_x = margin
    panel_y = margin
    panel_w = width - (2 * margin)
    panel_h = height - (2 * margin) - 12

    # Editor panel border
    c.setStrokeColor(PANEL_BORDER)
    c.setLineWidth(1)
    c.rect(panel_x, panel_y, panel_w, panel_h, stroke=1, fill=0)

    # Gutter area for line numbers
    gutter_w = 44
    c.setFillColor(GUTTER_BG)
    c.rect(panel_x, panel_y, gutter_w, panel_h, stroke=0, fill=1)
    c.setStrokeColor(GUTTER_DIVIDER)
    c.setLineWidth(1)
    c.line(panel_x + gutter_w, panel_y, panel_x + gutter_w, panel_y + panel_h)

    # Code area
    code_x = panel_x + gutter_w + 6
    code_y = panel_y + panel_h - 10
    code_w = panel_w - gutter_w - 12
    min_y = panel_y + 8
    return panel_x, code_x, code_y, code_w, min_y


def export_pdf():
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)

    # Register monospace font fallback
    font_name = "Courier"
    try:
        consolas = Path("C:/Windows/Fonts/consola.ttf")
        if consolas.exists():
            pdfmetrics.registerFont(TTFont("Consolas", str(consolas)))
            font_name = "Consolas"
    except Exception:
        pass

    c = canvas.Canvas(str(OUTPUT), pagesize=A4)
    width, height = A4

    margin = 36
    line_height = 13
    font_size = 9

    c.setTitle("Encryption Code")

    for idx, file_path in enumerate(FILES, start=1):
        rel = file_path.relative_to(ROOT).as_posix()
        if idx > 1:
            c.showPage()

        panel_x, code_x, y, usable_width, min_y = start_vscode_page(c, width, height, margin, rel)
        c.setFont(font_name, font_size)

        try:
            lines = file_path.read_text(encoding="utf-8").splitlines()
        except Exception as exc:
            c.setFillColor(colors.HexColor("#f87171"))
            c.setFont("Helvetica", 10)
            c.drawString(code_x, y, f"Failed to read file: {exc}")
            continue

        for line_no, line in enumerate(lines, start=1):
            if y < min_y + line_height:
                c.showPage()
                panel_x, code_x, y, usable_width, min_y = start_vscode_page(c, width, height, margin, rel, continued=True)
                c.setFont(font_name, font_size)

            line = line.replace("\t", "    ")

            # Line number
            ln = f"{line_no:4d}: "
            c.setFillColor(LINE_NO)
            c.drawString(panel_x + 4, y, ln)

            # Code text
            draw_color_line(c, line, code_x, y, usable_width, font_name, font_size)
            y -= line_height

    c.save()
    print(f"PDF created: {OUTPUT}")


if __name__ == "__main__":
    export_pdf()
