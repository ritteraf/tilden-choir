#!/usr/bin/env python3
# Usage: python3 tools/clean_score.py "scan.pdf" workdir out.pdf
# Needs: poppler-utils (pdfimages, pdfinfo), opencv-python, pillow, img2pdf.
"""Clean a scanned score: render each page at 300 dpi, straighten it, make it pure black & white, rebuild as a small CCITT G4 PDF."""
import sys, os, subprocess, glob, json
import numpy as np, cv2, img2pdf
from PIL import Image

src, outdir, outpdf = sys.argv[1], sys.argv[2], sys.argv[3]
DPI = 300
os.makedirs(outdir, exist_ok=True)

def skew_angle(gray):
    """Measure the tilt from the staff lines themselves: pull out long horizontal strokes, fit lines to them,
    and take the median angle. Too few lines (a narration page) means: leave the page alone."""
    g = cv2.resize(gray, None, fx=0.5, fy=0.5, interpolation=cv2.INTER_AREA)  # half size is plenty to measure a tilt
    h, w = g.shape
    ink = (g < 190).astype(np.uint8) * 255
    horiz = cv2.morphologyEx(ink, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_RECT, (max(30, w // 25), 1)))
    lines = cv2.HoughLinesP(horiz, 1, np.pi / 1800, threshold=120, minLineLength=w // 3, maxLineGap=15)
    if lines is None:
        return 0.0
    angs = [np.degrees(np.arctan2(y2 - y1, x2 - x1)) for x1, y1, x2, y2 in lines[:, 0]]
    angs = [a for a in angs if abs(a) < 3]
    if len(angs) < 5:
        return 0.0
    return float(np.median(angs))

def clear_edges(bw):
    """Scanner shadows at the paper edge become solid black bars; drop anything touching the border."""
    inv = 255 - bw
    num, lab = cv2.connectedComponents(inv, connectivity=8)
    edge = np.unique(np.concatenate([lab[0, :], lab[-1, :], lab[:, 0], lab[:, -1]]))
    edge = edge[edge != 0]
    if len(edge):
        bw[np.isin(lab, edge)] = 255
    return bw

n = int(subprocess.check_output(['pdfinfo', src]).decode().split('Pages:')[1].split()[0])
first = int(sys.argv[4]) if len(sys.argv) > 4 else 1
last = int(sys.argv[5]) if len(sys.argv) > 5 else n
report = []
tiffs = []
for p in range(first, last + 1):
    base = os.path.join(outdir, 'x')
    for old in glob.glob(base + '-*'): os.remove(old)
    # take the page's own scanned image out of the PDF: no re-rendering, no resampling
    subprocess.check_call(['pdfimages', '-f', str(p), '-l', str(p), '-j', src, base])
    f = sorted(glob.glob(base + '-*'), key=os.path.getsize)[-1]
    gray = cv2.imread(f, cv2.IMREAD_GRAYSCALE)
    for old in glob.glob(base + '-*'): os.remove(old)
    a = skew_angle(gray)
    if abs(a) >= 0.05:
        h, w = gray.shape
        M = cv2.getRotationMatrix2D((w / 2, h / 2), a, 1.0)
        gray = cv2.warpAffine(gray, M, (w, h), flags=cv2.INTER_CUBIC, borderValue=255)
    # black & white: Otsu picks the cut between paper and ink per page; a light blur first keeps edges smooth
    g = cv2.GaussianBlur(gray, (3, 3), 0)
    t, bw = cv2.threshold(g, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    t = min(max(t + 14, 195), 215)  # a little above Otsu keeps the grey edges of thin stems, ties and ledger lines
    _, bw = cv2.threshold(g, t, 255, cv2.THRESH_BINARY)
    # drop isolated specks (scanner dust) smaller than ~3 px
    inv = 255 - bw
    num, lab, stats, _ = cv2.connectedComponentsWithStats(inv, connectivity=8)
    small = np.where(stats[:, cv2.CC_STAT_AREA] < 4)[0]
    if len(small) > 1:
        mask = np.isin(lab, small[small != 0])
        bw[mask] = 255
    bw = clear_edges(bw)
    tif = os.path.join(outdir, 'p%03d.tif' % p)
    Image.fromarray(bw).convert('1').save(tif, compression='group4', dpi=(DPI, DPI))
    tiffs.append(tif)
    report.append((p, round(a, 2)))
with open(outpdf, 'wb') as fh:
    fh.write(img2pdf.convert(tiffs, layout_fun=img2pdf.get_fixed_dpi_layout_fun((DPI, DPI))))
print(json.dumps(report))
print('size', os.path.getsize(outpdf))
