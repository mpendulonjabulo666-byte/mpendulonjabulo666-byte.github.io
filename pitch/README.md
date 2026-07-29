# Cortex Synapse — Inspire InterCollege pitch

Majuba TVET internal round. Ten slides, a three-minute spoken pitch.

| File | What it is |
|---|---|
| `Cortex_Synapse_Pitch.pptx` | The deck. 16:9, 10 slides, fade transition on every slide, speaker notes on every slide. |
| `Cortex_Synapse_3min_Pitch_Script.pdf` | The spoken pitch: full script with timecodes and stage directions (pages 1–2), a one-page cue card to print alone (page 3), timing and pre-flight notes (page 4). |

## The deck

Runs in the order the competition asks for:

1. Cover
2. **The size of the problem** — 88% headline vs. 54.7% cohort completion, 34% wrote maths, 83% no lab, R150–450/hr tutoring
3. Passing is not understanding — the same numbers as a chart
4. **Our solution** — CAPS/NATED notes, real maths notation, active recall, paced to the learner
5. Built, and on the real syllabus — the live product, plus the pilot timeline
6. Why us, not them — Cortex Synapse vs. global platforms vs. a private tutor
7. **Target market** — 900k matrics, 13m learners, ~500k TVET; start at Majuba
8. **Revenue generation** — free forever, family plan, institution seats, break-even at 2 000 learners
9. **Impact on society and the environment**
10. **The team**, the ask, and the close

Every slide's speaker notes are the exact words on the printed script, so
Presenter View and the page in your hand never disagree.

## Rebuilding

Everything is generated, so text edits are cheap. `script_data.py` is the single
source of truth for the spoken words — edit it and both the PDF and the deck's
speaker notes re-time themselves.

```bash
cd src
python make_backgrounds.py                       # organic background washes
python -c "import script_data; script_data.export_notes()"
node make_deck.js                                # needs pptxgenjs
python add_transitions.py Cortex_Synapse_Pitch.pptx
python make_script_pdf.py                        # needs weasyprint
```

## Before presenting

- Add the real learner number to slide 5 once the app is deployed — a live
  figure beats a projection.
- Check the founder name on slides 1 and 10 reads exactly as you want it.
- Fonts used are Bookman Old Style and Calibri, both of which ship with Office.

## Sources for the numbers

DBE NSC announcement, January 2026 (88% pass rate; mathematics pass rate
69% → 64%; 34% of candidates wrote mathematics). Cohort completion (54.7%) per
published cohort analyses measuring the Grade 1 intake through to matric.
School infrastructure (83% without a laboratory, 74% without a library) per
national school infrastructure reporting. Private tutoring rates are the
prevailing R150–450 per hour market range. Verify each figure against its
current source before presenting.
