/**
 * Cortex Synapse — Inspire InterCollege pitch deck (Majuba TVET internal round)
 * 10 slides, "Organic" warm design system taken from the Cortex Synapse logo.
 */
const pptxgen = require("pptxgenjs");
// speaker notes come from script_data.py via notes.json, so Presenter View and the
// printed 3-minute script can never drift apart
const NOTES = require("./notes.json");

// ---------------------------------------------------------------- palette ---
const INK = "2A1310"; // espresso — body text on cream
const INK_SOFT = "6E5347"; // muted brown
const MAROON = "5C231C";
const AMBER = "E08A2E";
const TERRA = "C05621";
const SAGE = "5F7050";
const CREAM = "FAF2E2";
const PAPER = "FFFCF5"; // card face on cream slides
const LIGHT = "F6EBD8"; // text on dark
const LIGHT_SOFT = "D5BCA6"; // muted text on dark

const HEAD = "Bookman Old Style";
const BODY = "Calibri";

const A = (f) => `assets/${f}`;

// motif: every card is a soft rounded tile
const card = (extra = {}) => ({
  shape: "roundRect",
  rectRadius: 0.14,
  fill: { color: PAPER },
  line: { color: "E6D6BC", width: 0.75 },
  shadow: { type: "outer", color: "8A6A4A", opacity: 0.16, blur: 10, offset: 2, angle: 90 },
  ...extra,
});

const darkCard = (extra = {}) => ({
  shape: "roundRect",
  rectRadius: 0.14,
  fill: { color: "FFFFFF", transparency: 91 },
  line: { color: "FFFFFF", width: 0.75, transparency: 78 },
  ...extra,
});

const pres = new pptxgen();
pres.layout = "LAYOUT_WIDE"; // 13.333 x 7.5in
pres.author = "Cortex Synapse";
pres.title = "Cortex Synapse — Inspire InterCollege pitch";

const bg = (s, file) => s.background = { path: A(file) };

// small amber badge circle holding a number or short glyph
function badge(s, x, y, d, text, fill = AMBER, color = "FFFFFF") {
  s.addShape("ellipse", { x, y, w: d, h: d, fill: { color: fill } });
  s.addText(text, {
    x, y, w: d, h: d, align: "center", valign: "middle", margin: 0,
    fontFace: HEAD, fontSize: 15, bold: true, color,
  });
}

function title(s, text, opts = {}) {
  s.addText(text, {
    x: 0.72, y: 0.42, w: 11.9, h: 0.78, margin: 0, valign: "middle",
    fontFace: HEAD, fontSize: 33, bold: true, color: opts.dark ? LIGHT : INK,
  });
}

/* ========================================================== 1. COVER ===== */
{
  const s = pres.addSlide();
  bg(s, "bg-dark.jpg");

  // logo tile — the logo art already sits on cream, so the tile matches it
  s.addShape("roundRect", {
    x: 8.28, y: 1.62, w: 4.28, h: 4.28, rectRadius: 0.2,
    fill: { color: "FDF1D8" },
    shadow: { type: "outer", color: "000000", opacity: 0.45, blur: 26, offset: 5, angle: 90 },
  });
  s.addImage({ path: A("logo.png"), x: 8.42, y: 1.76, w: 4.0, h: 4.0 });

  s.addText("INSPIRE INTERCOLLEGE  ·  MAJUBA TVET INTERNAL ROUND", {
    x: 0.85, y: 1.5, w: 7.0, h: 0.34, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 11.5, bold: true, color: AMBER, charSpacing: 2.4,
  });

  s.addText("Cortex Synapse", {
    x: 0.8, y: 1.94, w: 7.3, h: 1.05, margin: 0, valign: "middle",
    fontFace: HEAD, fontSize: 50, bold: true, color: LIGHT,
  });

  s.addText("A South African AI study companion — so that every learner, from every background, walks into an exam room understanding the work instead of fearing it.", {
    x: 0.85, y: 3.12, w: 6.9, h: 1.5, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 16.5, color: LIGHT_SOFT, lineSpacing: 26,
  });

  s.addShape("roundRect", {
    x: 0.85, y: 4.86, w: 3.05, h: 0.56, rectRadius: 0.28,
    fill: { color: AMBER },
  });
  s.addText("LEARN DEEPER", {
    x: 0.85, y: 4.86, w: 3.05, h: 0.56, margin: 0, align: "center", valign: "middle",
    fontFace: BODY, fontSize: 12, bold: true, color: "3A1808", charSpacing: 2.2,
  });

  s.addText("Mpendulo Njabulo — Founder   ·   Tony — Infrastructure", {
    x: 0.85, y: 5.72, w: 7.0, h: 0.4, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 13.5, color: LIGHT_SOFT,
  });

  s.addNotes(NOTES["1"]);
}

/* ================================================ 2. SIZE OF THE PROBLEM = */
{
  const s = pres.addSlide();
  bg(s, "bg-problem.jpg");
  title(s, "The size of the problem", { dark: true });

  s.addText("88%", {
    x: 0.72, y: 1.5, w: 4.5, h: 1.85, margin: 0, valign: "middle",
    fontFace: HEAD, fontSize: 96, bold: true, color: AMBER,
  });
  s.addText("The 2025 matric pass rate — the highest in our history.", {
    x: 0.75, y: 3.42, w: 4.35, h: 0.8, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 15.5, bold: true, color: LIGHT, lineSpacing: 22,
  });
  s.addText("It is also the number that hides the problem I am here to solve.", {
    x: 0.75, y: 4.24, w: 4.35, h: 0.9, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 14, color: LIGHT_SOFT, lineSpacing: 21,
  });
  s.addText("Sources: DBE NSC announcement, Jan 2026; published cohort analyses.", {
    x: 0.75, y: 6.18, w: 4.4, h: 0.6, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 9.5, italic: true, color: "A98A72", lineSpacing: 13,
  });

  const stats = [
    ["54.7%", "of the learners who started Grade 1 together actually finish with a matric. The headline rate counts only those who reach the exam desk."],
    ["34%", "of candidates even wrote Mathematics in 2025 — and the maths pass rate fell from 69% to 64% in a single year."],
    ["83%", "of South African schools have no laboratory, and 74% have no library. Township and rural learners study without what the exam assumes."],
    ["R150–450", "per hour for private tutoring — the one proven fix, priced out of reach of the learners who need it most."],
  ];
  const cw = 3.36, ch = 2.42, gx = 0.28, gy = 0.32, x0 = 5.62, y0 = 1.44;
  stats.forEach(([n, t], i) => {
    const x = x0 + (i % 2) * (cw + gx);
    const y = y0 + Math.floor(i / 2) * (ch + gy);
    s.addShape("roundRect", darkCard({ x, y, w: cw, h: ch }));
    s.addText(n, {
      x: x + 0.28, y: y + 0.18, w: cw - 0.5, h: 0.68, margin: 0, valign: "middle",
      fontFace: HEAD, fontSize: n.length > 5 ? 26 : 33, bold: true, color: AMBER,
    });
    s.addText(t, {
      x: x + 0.28, y: y + 0.9, w: cw - 0.56, h: 1.36, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 11.5, color: LIGHT_SOFT, lineSpacing: 17,
    });
  });

  s.addNotes(NOTES["2"]);
}

/* ============================================ 3. PASSING ≠ UNDERSTANDING = */
{
  const s = pres.addSlide();
  bg(s, "bg-cream.jpg");
  title(s, "Passing is not understanding");

  s.addChart(
    pres.ChartType.bar,
    [{
      name: "Class of 2025",
      labels: ["Headline pass rate", "Mathematics pass rate", "Reached matric from Grade 1", "Even wrote Mathematics"],
      values: [88, 64, 54.7, 34],
    }],
    {
      x: 0.5, y: 1.3, w: 8.15, h: 4.92,
      barDir: "bar",
      chartColors: [AMBER, "A8552A", "8A3E22", MAROON],
      barGapWidthPct: 42,
      showLegend: false,
      showValue: true,
      dataLabelPosition: "outEnd",
      dataLabelColor: INK,
      dataLabelFontFace: HEAD,
      dataLabelFontSize: 14,
      dataLabelFontBold: true,
      dataLabelFormatCode: '0.0"%"',
      catAxisLabelColor: INK,
      catAxisLabelFontFace: BODY,
      catAxisLabelFontSize: 12.5,
      catAxisLineShow: false,
      catGridLine: { style: "none" },
      valAxisHidden: true,
      valAxisMaxVal: 100,
      valGridLine: { style: "none" },
      plotArea: { fill: { color: "FFFFFF", transparency: 100 } },
      chartArea: { fill: { color: "FFFFFF", transparency: 100 } },
    }
  );

  s.addShape("roundRect", card({ x: 8.86, y: 1.3, w: 3.78, h: 2.92 }));
  s.addText("THE GAP IN ONE LINE", {
    x: 9.14, y: 1.56, w: 3.24, h: 0.34, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 10.5, bold: true, color: TERRA, charSpacing: 1.8,
  });
  s.addText("We are getting better at producing passes. We are not getting better at producing understanding.", {
    x: 9.14, y: 1.98, w: 3.24, h: 2.0, margin: 0, valign: "top",
    fontFace: HEAD, fontSize: 15.5, bold: true, color: INK, lineSpacing: 23,
  });

  s.addShape("roundRect", card({ x: 8.86, y: 4.44, w: 3.78, h: 1.78, fill: { color: "F5E3C6" } }));
  s.addText([
    { text: "A 30% is a pass. ", options: { bold: true, color: INK } },
    { text: "It is not a mark that carries a learner into a degree, a bursary or a job.", options: { color: "5F4534" } },
  ], {
    x: 9.14, y: 4.7, w: 3.24, h: 1.3, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 13, lineSpacing: 20,
  });

  s.addText("Per cent, Class of 2025. DBE NSC announcement, January 2026; cohort completion per published cohort analyses.", {
    x: 0.72, y: 6.48, w: 8.0, h: 0.5, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 9.5, italic: true, color: INK_SOFT,
  });

  s.addNotes(NOTES["3"]);
}

/* ==================================================== 4. OUR SOLUTION ==== */
{
  const s = pres.addSlide();
  bg(s, "bg-cream.jpg");
  title(s, "Our solution");
  s.addText("A home-grown AI study companion — built on the South African syllabus, not adapted to it.", {
    x: 0.75, y: 1.24, w: 9.6, h: 0.44, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 15, color: INK_SOFT,
  });

  const feats = [
    ["1", "Notes that match the syllabus", "Topic-aligned notes with worked examples and the exam tips that show up in CAPS and NATED papers — from a topic, pasted lecture text, or a YouTube link."],
    ["2", "Proper maths, properly rendered", "Fractions, integrals, ∑, √, π — the full South African exam notation set, not plain-text approximations a learner has to decode."],
    ["3", "Active recall, built in", "Quizzes and flashcards generated from any note in one click. Mastery is tracked, and the topics a learner gets wrong come back."],
    ["4", "Paced to the learner", "Explains one concept five different ways without ever judging, at two in the morning, on cheap data and an entry-level phone."],
  ];
  const cw = 5.86, ch = 2.28, gx = 0.34, gy = 0.32, x0 = 0.72, y0 = 1.9;
  feats.forEach(([n, h, t], i) => {
    const x = x0 + (i % 2) * (cw + gx);
    const y = y0 + Math.floor(i / 2) * (ch + gy);
    s.addShape("roundRect", card({ x, y, w: cw, h: ch }));
    badge(s, x + 0.34, y + 0.36, 0.52, n);
    s.addText(h, {
      x: x + 1.02, y: y + 0.32, w: cw - 1.34, h: 0.46, margin: 0, valign: "middle",
      fontFace: HEAD, fontSize: 16, bold: true, color: INK,
    });
    s.addText(t, {
      x: x + 1.02, y: y + 0.88, w: cw - 1.36, h: 1.2, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 12.5, color: "5F4534", lineSpacing: 18,
    });
  });

  s.addNotes(NOTES["4"]);
}

/* ============================================ 5. LIVE ON THE SYLLABUS ==== */
{
  const s = pres.addSlide();
  bg(s, "bg-dark.jpg");
  title(s, "Built, and on the real syllabus", { dark: true });
  s.addText("Not a mock-up. This is the product today — every CAPS subject a Grade 10 to 12 learner writes, and the NATED subjects a TVET student writes, side by side in one app.", {
    x: 0.75, y: 1.22, w: 11.7, h: 0.5, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 14.5, color: LIGHT_SOFT,
  });

  const iw = 5.86, ih = iw / 1.8633, iy = 2.0;
  [["caps.png", 0.72, "CAPS — Grade 10 to 12"], ["nated-pad.png", 6.75, "NATED — N1 to N6"]].forEach(([f, x, cap]) => {
    s.addShape("roundRect", {
      x: x - 0.08, y: iy - 0.08, w: iw + 0.16, h: ih + 0.16, rectRadius: 0.1,
      fill: { color: "FFFFFF", transparency: 88 },
      shadow: { type: "outer", color: "000000", opacity: 0.5, blur: 18, offset: 4, angle: 90 },
    });
    s.addImage({ path: A(f), x, y: iy, w: iw, h: ih });
    s.addText(cap, {
      x, y: iy + ih + 0.14, w: iw, h: 0.34, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 11.5, bold: true, color: AMBER, charSpacing: 1.6,
    });
  });

  // traction timeline — the last stop is right-aligned so it stays on the slide
  const ty = 6.32, tx0 = 0.95, tx1 = 12.5;
  s.addShape("line", { x: tx0, y: ty, w: tx1 - tx0, h: 0, line: { color: "8A6A55", width: 1 } });
  const stops = [
    ["NOW", "Built & deploying", AMBER],
    ["TERM 3", "Campus pilot", "B9977B"],
    ["TERM 4", "Measured results", "B9977B"],
    ["2027", "Provincial rollout", "B9977B"],
  ];
  stops.forEach(([k, v, c], i) => {
    const last = i === stops.length - 1;
    const x = tx0 + (i * (tx1 - tx0)) / (stops.length - 1);
    const lx = last ? x - 2.7 : x - 0.1;
    const align = last ? "right" : "left";
    s.addShape("ellipse", { x: x - 0.075, y: ty - 0.075, w: 0.15, h: 0.15, fill: { color: c } });
    s.addText(k, {
      x: lx, y: ty - 0.52, w: 2.8, h: 0.3, margin: 0, valign: "top", align,
      fontFace: BODY, fontSize: 10, bold: true, color: c, charSpacing: 1.4,
    });
    s.addText(v, {
      x: lx, y: ty + 0.12, w: 2.8, h: 0.3, margin: 0, valign: "top", align,
      fontFace: BODY, fontSize: 12, color: LIGHT,
    });
  });

  s.addNotes(NOTES["5"]);
}

/* ==================================================== 6. WHY US ========== */
{
  const s = pres.addSlide();
  bg(s, "bg-cream.jpg");
  title(s, "Why us, not them");

  const cols = [
    { x: 3.28, w: 3.16, name: "CORTEX SYNAPSE", hot: true },
    { x: 6.62, w: 3.16, name: "GLOBAL PLATFORMS", hot: false },
    { x: 9.96, w: 2.66, name: "PRIVATE TUTOR", hot: false },
  ];
  const rows = [
    ["Curriculum", "CAPS & NATED, home-grown", "US-centric, adapted", "Local, tutor-dependent"],
    ["Cost to a family", "Low monthly subscription", "Free, but off-syllabus", "R150–450 per hour"],
    ["Available when", "Any hour, adapts to the learner", "Any hour, fixed video pace", "Booked sessions only"],
    ["Exam practice", "Generated from the learner's own notes", "Generic exercises", "The tutor's own material"],
  ];
  const rh = 1.0, rg = 0.16, ry0 = 2.06;
  const tableBottom = ry0 + rows.length * (rh + rg) - rg;

  // column backings: the Cortex column warm, the other two a quiet neutral
  s.addShape("roundRect", {
    x: cols[0].x - 0.1, y: 1.34, w: cols[0].w + 0.2, h: tableBottom - 1.34 + 0.12,
    rectRadius: 0.12, fill: { color: "F7E4C4" },
  });
  cols.slice(1).forEach((c) => {
    s.addShape("roundRect", {
      x: c.x - 0.1, y: 1.34, w: c.w + 0.2, h: tableBottom - 1.34 + 0.12,
      rectRadius: 0.12, fill: { color: "FFFFFF", transparency: 62 },
    });
  });

  cols.forEach((c) => {
    s.addText(c.name, {
      x: c.x, y: 1.44, w: c.w, h: 0.56, margin: 0, align: "center", valign: "middle",
      fontFace: BODY, fontSize: 11, bold: true, charSpacing: 1.4,
      color: c.hot ? "8A3E12" : INK_SOFT,
    });
  });

  rows.forEach((r, i) => {
    const y = ry0 + i * (rh + rg);
    s.addText(r[0], {
      x: 0.72, y, w: 2.4, h: rh, margin: 0, valign: "middle",
      fontFace: HEAD, fontSize: 13.5, bold: true, color: INK,
    });
    cols.forEach((c, j) => {
      if (c.hot) {
        s.addShape("roundRect", {
          x: c.x, y, w: c.w, h: rh, rectRadius: 0.12,
          fill: { color: PAPER },
          line: { color: AMBER, width: 1 },
          shadow: { type: "outer", color: "8A6A4A", opacity: 0.18, blur: 8, offset: 2, angle: 90 },
        });
      }
      s.addText(r[j + 1], {
        x: c.x + 0.16, y, w: c.w - 0.32, h: rh, margin: 0, align: "center", valign: "middle",
        fontFace: BODY, fontSize: 12, lineSpacing: 16,
        bold: c.hot, color: c.hot ? INK : "6E5347",
      });
    });
  });

  s.addText("Only one of the three is South African, affordable, and awake whenever the learner is.", {
    x: 0.72, y: tableBottom + 0.26, w: 11.9, h: 0.4, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 13, italic: true, color: INK_SOFT,
  });

  s.addNotes(NOTES["6"]);
}

/* ================================================== 7. TARGET MARKET ===== */
{
  const s = pres.addSlide();
  bg(s, "bg-cream-warm.jpg");
  title(s, "Target market");
  s.addText("Where the need is largest, and where we can prove it fastest.", {
    x: 0.75, y: 1.24, w: 9.0, h: 0.44, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 15, color: INK_SOFT,
  });

  const mk = [
    ["900k+", "matric candidates", "write the NSC every year — the largest cohort in the country's history, and the sharpest moment of need."],
    ["13m", "learners in basic education", "with Grade 8 to 12 the immediate addressable band for a study companion like ours."],
    ["~500k", "TVET college students", "under-served by every existing study platform, and the beachhead we know best."],
  ];
  const cw = 3.86, gx = 0.34, y = 1.92, ch = 2.74;
  mk.forEach(([n, l, t], i) => {
    const x = 0.72 + i * (cw + gx);
    s.addShape("roundRect", card({ x, y, w: cw, h: ch }));
    s.addText(n, {
      x: x + 0.3, y: y + 0.3, w: cw - 0.6, h: 0.92, margin: 0, valign: "middle",
      fontFace: HEAD, fontSize: 40, bold: true, color: TERRA,
    });
    s.addText(l, {
      x: x + 0.3, y: y + 1.28, w: cw - 0.6, h: 0.36, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 13, bold: true, color: INK,
    });
    s.addText(t, {
      x: x + 0.3, y: y + 1.7, w: cw - 0.62, h: 0.86, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 12, color: "6E5347", lineSpacing: 17,
    });
  });

  s.addShape("roundRect", {
    x: 0.72, y: 5.0, w: 11.9, h: 1.62, rectRadius: 0.14,
    fill: { color: MAROON },
    shadow: { type: "outer", color: "8A6A4A", opacity: 0.22, blur: 12, offset: 3, angle: 90 },
  });
  badge(s, 1.1, 5.53, 0.56, "▶");
  s.addText("Start here", {
    x: 1.86, y: 5.24, w: 3.0, h: 0.44, margin: 0, valign: "middle",
    fontFace: HEAD, fontSize: 19, bold: true, color: AMBER,
  });
  s.addText("Majuba TVET and the Newcastle high schools — one campus, real learners, and results we can measure before we go wide.", {
    x: 1.86, y: 5.74, w: 10.3, h: 0.66, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 14, color: LIGHT_SOFT, lineSpacing: 20,
  });

  s.addNotes(NOTES["7"]);
}

/* ================================================ 8. REVENUE ============= */
{
  const s = pres.addSlide();
  bg(s, "bg-cream-warm.jpg");
  title(s, "Revenue generation");
  s.addText("Affordable by design. The learner who cannot pay is never the learner who is locked out.", {
    x: 0.75, y: 1.24, w: 9.6, h: 0.44, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 15, color: INK_SOFT,
  });

  const streams = [
    ["Free forever tier", "Core notes, quizzes and flashcards at no cost. This is the promise, not a trial."],
    ["Family plan", "A low monthly subscription — about the price of one tutoring hour, for a whole year of study."],
    ["Institution seats", "Colleges and schools licence seats per learner, with a lecturer view of weak topics. CSI and telco partners sponsor bundles for no-fee schools."],
  ];
  const rh = 1.5, rg = 0.28, y0 = 1.9;
  streams.forEach(([h, t], i) => {
    const y = y0 + i * (rh + rg);
    s.addShape("roundRect", card({ x: 0.72, y, w: 7.5, h: rh }));
    badge(s, 1.06, y + 0.48, 0.54, String(i + 1));
    s.addText(h, {
      x: 1.78, y: y + 0.24, w: 5.5, h: 0.42, margin: 0, valign: "middle",
      fontFace: HEAD, fontSize: 16, bold: true, color: INK,
    });
    s.addText(t, {
      x: 1.78, y: y + 0.68, w: 6.16, h: 0.72, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 12.5, color: "5F4534", lineSpacing: 17,
    });
  });

  const px = 8.62, pw = 4.0, py = y0, ph = 3 * rh + 2 * rg;
  s.addShape("roundRect", {
    x: px, y: py, w: pw, h: ph, rectRadius: 0.14,
    fill: { color: MAROON },
    shadow: { type: "outer", color: "8A6A4A", opacity: 0.22, blur: 12, offset: 3, angle: 90 },
  });
  s.addText("BREAK-EVEN", {
    x: px + 0.34, y: py + 0.5, w: pw - 0.68, h: 0.32, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 11, bold: true, color: AMBER, charSpacing: 2.2,
  });
  s.addText("2 000", {
    x: px + 0.32, y: py + 0.94, w: pw - 0.64, h: 1.2, margin: 0, valign: "middle",
    fontFace: HEAD, fontSize: 52, bold: true, color: LIGHT,
  });
  s.addText("paying learners covers hosting, AI inference and support in full.", {
    x: px + 0.34, y: py + 2.24, w: pw - 0.7, h: 0.8, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 13, color: LIGHT_SOFT, lineSpacing: 19,
  });
  s.addText("Surplus funds sponsored seats and more subjects — not dividends.", {
    x: px + 0.34, y: py + 3.6, w: pw - 0.7, h: 0.9, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 12.5, bold: true, italic: true, color: AMBER, lineSpacing: 18,
  });

  s.addNotes(NOTES["8"]);
}

/* ================================================== 9. IMPACT ============ */
{
  const s = pres.addSlide();
  bg(s, "bg-cream-sage.jpg");
  title(s, "Impact on society and the environment");

  const panels = [
    {
      x: 0.72, tone: TERRA, tint: "F7E4C4", kicker: "SOCIETY",
      lead: "The same quality of explanation reaches every learner.",
      pts: [
        "A learner in a school with no laboratory gets the explanation a private-school learner gets.",
        "Practice replaces panic — by exam day the question has already been answered fifty times.",
        "Every extra bachelor pass changes a household's trajectory, not just a learner's marks.",
      ],
    },
    {
      x: 6.74, tone: SAGE, tint: "E4EAD8", kicker: "ENVIRONMENT",
      lead: "Study material with no paper and no travel.",
      pts: [
        "No printed study packs and no photocopied past papers — material is generated on demand.",
        "No weekly travel to a tutoring centre, so no fuel burned to reach an explanation.",
        "Built light, so it runs on cheap data and the older phone a learner already owns.",
      ],
    },
  ];

  panels.forEach((p) => {
    const w = 5.86, y = 1.36, h = 5.32;
    s.addShape("roundRect", card({ x: p.x, y, w, h }));
    s.addShape("roundRect", {
      x: p.x + 0.36, y: y + 0.4, w: 1.72, h: 0.46, rectRadius: 0.23,
      fill: { color: p.tint },
    });
    s.addText(p.kicker, {
      x: p.x + 0.36, y: y + 0.4, w: 1.72, h: 0.46, margin: 0, align: "center", valign: "middle",
      fontFace: BODY, fontSize: 10.5, bold: true, color: p.tone, charSpacing: 1.8,
    });
    s.addText(p.lead, {
      x: p.x + 0.36, y: y + 1.06, w: w - 0.72, h: 0.98, margin: 0, valign: "top",
      fontFace: HEAD, fontSize: 18, bold: true, color: INK, lineSpacing: 26,
    });
    p.pts.forEach((t, i) => {
      const ly = y + 2.24 + i * 1.0;
      s.addShape("ellipse", { x: p.x + 0.4, y: ly + 0.12, w: 0.15, h: 0.15, fill: { color: p.tone } });
      s.addText(t, {
        x: p.x + 0.74, y: ly, w: w - 1.12, h: 0.86, margin: 0, valign: "top",
        fontFace: BODY, fontSize: 12.5, color: "5F4534", lineSpacing: 18,
      });
    });
  });

  s.addNotes(NOTES["9"]);
}

/* ========================================= 10. TEAM · ASK · CLOSE ======== */
{
  const s = pres.addSlide();
  bg(s, "bg-dark.jpg");
  title(s, "The team, and what we are asking for", { dark: true });

  // --- team, left
  s.addText("THE TEAM", {
    x: 0.75, y: 1.34, w: 3.0, h: 0.3, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 10.5, bold: true, color: AMBER, charSpacing: 2.2,
  });

  s.addImage({ path: A("founder-sq.jpg"), x: 0.72, y: 1.82, w: 1.5, h: 1.5, rounding: true });
  s.addText("Mpendulo Njabulo — Founder", {
    x: 2.4, y: 1.86, w: 3.9, h: 0.36, margin: 0, valign: "top",
    fontFace: HEAD, fontSize: 15, bold: true, color: LIGHT,
  });
  s.addText("Builds the product and carries the mission. A Majuba TVET student who came through the same classrooms Cortex Synapse is built for.", {
    x: 2.4, y: 2.26, w: 3.86, h: 1.0, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 12, color: LIGHT_SOFT, lineSpacing: 17,
  });

  s.addShape("ellipse", { x: 0.72, y: 3.62, w: 1.5, h: 1.5, fill: { color: "6E3A22" } });
  s.addText("T", {
    x: 0.72, y: 3.62, w: 1.5, h: 1.5, margin: 0, align: "center", valign: "middle",
    fontFace: HEAD, fontSize: 40, bold: true, color: AMBER,
  });
  s.addText("Tony — Infrastructure", {
    x: 2.4, y: 3.72, w: 3.9, h: 0.36, margin: 0, valign: "top",
    fontFace: HEAD, fontSize: 15, bold: true, color: LIGHT,
  });
  s.addText("Deployment, data security, and keeping the platform up and cheap to run as learner numbers grow.", {
    x: 2.4, y: 4.1, w: 3.86, h: 0.9, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 12, color: LIGHT_SOFT, lineSpacing: 17,
  });

  // --- ask, right
  s.addText("THE ASK", {
    x: 6.78, y: 1.34, w: 3.0, h: 0.3, margin: 0, valign: "top",
    fontFace: BODY, fontSize: 10.5, bold: true, color: AMBER, charSpacing: 2.2,
  });
  const asks = [
    ["Funding", "Seed funding to cover hosting, AI inference and support for the first year — so the free tier stays free while we prove the model."],
    ["School pilot access", "Introductions to run a term-long pilot at Majuba TVET and two local high schools, with pass rates and topic mastery measured before and after."],
  ];
  asks.forEach(([h, t], i) => {
    const y = 1.82 + i * 1.74;
    s.addShape("roundRect", darkCard({ x: 6.74, y, w: 5.88, h: 1.5 }));
    badge(s, 7.06, y + 0.48, 0.54, String(i + 1));
    s.addText(h, {
      x: 7.78, y: y + 0.24, w: 4.6, h: 0.4, margin: 0, valign: "middle",
      fontFace: HEAD, fontSize: 16, bold: true, color: LIGHT,
    });
    s.addText(t, {
      x: 7.78, y: y + 0.66, w: 4.6, h: 0.72, margin: 0, valign: "top",
      fontFace: BODY, fontSize: 12, color: LIGHT_SOFT, lineSpacing: 17,
    });
  });

  // --- close
  s.addText("“The goal is not the pass mark. The goal is a learner who understands, and is not afraid to write.”", {
    x: 0.9, y: 5.5, w: 11.6, h: 1.1, margin: 0, align: "center", valign: "middle",
    fontFace: HEAD, fontSize: 21, bold: true, italic: true, color: AMBER, lineSpacing: 30,
  });
  s.addText("Give us one campus and one term, and we will bring you the marks.", {
    x: 0.9, y: 6.62, w: 11.6, h: 0.4, margin: 0, valign: "top", align: "center",
    fontFace: BODY, fontSize: 13.5, color: LIGHT_SOFT,
  });

  s.addNotes(NOTES["10"]);
}

pres.writeFile({ fileName: "Cortex_Synapse_Pitch.pptx" }).then(() => console.log("wrote Cortex_Synapse_Pitch.pptx"));
