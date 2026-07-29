"""Render the 3-minute pitch (full script + cue card) to a printable PDF."""
import base64
import html

from weasyprint import HTML

from script_data import BEATS, CUE_CARD, mmss, timed_beats

BEATS_T, TOTAL = timed_beats()
TOTAL_WORDS = sum(b["words"] for b in BEATS_T)

logo_b64 = base64.b64encode(open("assets/logo.png", "rb").read()).decode()


def esc(t):
    return html.escape(t)


beat_rows = "\n".join(
    f"""
    <section class="beat">
      <div class="cue">
        <div class="time">{mmss(b['start'])}</div>
        <div class="slide">Slide {b['slide']} — {esc(b['name'])}</div>
        <div class="stage">{esc(b['cue'])}</div>
      </div>
      <div class="say">{''.join(f'<p>{esc(p)}</p>' for p in b['paras'])}</div>
    </section>"""
    for b in BEATS_T
)

cue_blocks = "\n".join(
    f"""
    <div class="card {'warm' if i % 2 else 'cool'}">
      <div class="card-time">{mmss(BEATS_T[[0, 1, 3, 5, 6, 8, 9][i]]['start'])}</div>
      <div class="card-body">
        <h3>{esc(title)} <span>{esc(where)}</span></h3>
        <ul>{''.join(f'<li>{esc(p)}</li>' for p in pts)}</ul>
      </div>
    </div>"""
    for i, (title, where, pts) in enumerate(CUE_CARD)
)

DOC = f"""
<style>
  @page {{ size: A4; margin: 17mm 16mm 15mm 16mm; }}
  @page {{ @bottom-center {{
      content: "Cortex Synapse · Inspire InterCollege · Majuba TVET internal round · page " counter(page);
      font-family: "Liberation Sans", sans-serif; font-size: 7.5pt; color: #9C8570;
  }} }}
  * {{ box-sizing: border-box; }}
  body {{ font-family: "Liberation Sans", sans-serif; color: #2A1310; font-size: 10.5pt; line-height: 1.55; }}

  h1 {{ font-family: "Bitstream Charter", "Liberation Serif", serif; font-size: 26pt;
       line-height: 1.06; margin: 0 0 6px; color: #2A1310; letter-spacing: -0.4px; }}
  .sub {{ color: #A0522D; font-size: 10.5pt; margin: 0 0 4px; }}
  .meta {{ color: #6E5347; font-size: 9.5pt; margin: 0 0 22px; }}

  .masthead {{ display: flex; gap: 16px; align-items: center; margin-bottom: 4px; }}
  .masthead img {{ width: 58px; height: 58px; border-radius: 11px; flex: none; }}
  .masthead > div {{ flex: 1; }}

  .beat {{ display: flex; gap: 16px; page-break-inside: avoid; margin-bottom: 15px; }}
  .cue {{ width: 33%; padding-top: 1px; }}
  .time {{ font-family: "Bitstream Charter", serif; font-weight: bold; font-size: 12.5pt;
           color: #A0522D; letter-spacing: 0.3px; }}
  .slide {{ font-size: 9.5pt; color: #4A2C22; margin-top: 2px; }}
  .stage {{ font-size: 9pt; color: #8A7160; font-style: italic; margin-top: 3px; line-height: 1.4; }}
  .say {{ width: 67%; }}
  .say p {{ margin: 0 0 8px; }}
  .say p:last-child {{ margin-bottom: 0; }}

  h2 {{ font-family: "Bitstream Charter", serif; font-size: 21pt; margin: 0 0 4px; }}
  .lede {{ color: #A0522D; font-size: 10pt; margin: 0 0 13px; }}

  .card {{ display: flex; gap: 14px; border-radius: 11px; padding: 9px 15px 10px;
           margin-bottom: 7px; page-break-inside: avoid; }}
  .card.warm {{ background: #FBEADA; }}
  .card.cool {{ background: #EFF1E3; }}
  .card-time {{ font-family: "Bitstream Charter", serif; font-weight: bold; font-size: 12pt;
                color: #A0522D; width: 46px; flex: none; padding-top: 2px; }}
  .card-body {{ flex: 1; }}
  .card h3 {{ font-family: "Bitstream Charter", serif; font-size: 12pt; margin: 0 0 2px; }}
  .card h3 span {{ font-family: "Liberation Sans", sans-serif; font-weight: normal;
                   font-size: 8.5pt; color: #8A7160; margin-left: 6px; }}
  .card ul {{ margin: 0; padding-left: 15px; }}
  .card li {{ font-size: 9.4pt; line-height: 1.42; }}
  .card li::marker {{ color: #C05621; }}

  .note {{ border-radius: 11px; background: #FAF2E2; padding: 13px 17px; margin-top: 11px;
           page-break-inside: avoid; }}
  .note h4 {{ font-family: "Bitstream Charter", serif; font-size: 11.5pt; margin: 0 0 5px; }}
  .note p, .note li {{ font-size: 9.6pt; color: #4A2C22; margin: 0 0 5px; line-height: 1.5; }}
  .note ul {{ margin: 0 0 2px; padding-left: 16px; }}
  .breaker {{ page-break-before: always; }}
</style>

<div class="masthead">
  <img src="data:image/png;base64,{logo_b64}" alt="">
  <div>
    <h1>Cortex Synapse<br>— 3-minute pitch</h1>
  </div>
</div>
<p class="sub">Inspire InterCollege competition · Majuba TVET internal round</p>
<p class="meta">10 slides · {TOTAL_WORDS} words · {mmss(TOTAL)} at a calm 150 words a minute</p>

{beat_rows}

<div class="breaker"></div>
<h2>Cue card</h2>
<p class="lede">Print this page alone. Seven beats, ten slides, three minutes.</p>

{cue_blocks}

<div class="note">
  <h4>Timing check</h4>
  <p>Read it aloud once with a stopwatch. At a calm 150 words a minute this runs
     {mmss(TOTAL)}. Most people speed up under pressure, so expect nearer 2:50 on the day.</p>
  <p><strong>If you run long, cut in this order:</strong></p>
  <ul>
    <li>Slide 4, second paragraph — the quizzes-and-flashcards sentence.</li>
    <li>Slide 2 — the tutor-cost sentence (the number is on the slide anyway).</li>
    <li>Slide 7 — drop the thirteen-million line, keep 900k and 500k.</li>
  </ul>
  <p><strong>Never cut the last two lines.</strong> They are the reason you are in the room.</p>
</div>

<div class="note">
  <h4>Before you present</h4>
  <ul>
    <li>Once the app is deployed, add your live learner number to slide 5 — one line
        under the timeline, e.g. “First cohort: 40 learners, Majuba TVET”. A real number
        beats a projection with judges.</li>
    <li>Confirm the founder name on slides 1 and 10 reads exactly as you want it.</li>
    <li>Open the deck in Presenter View — every slide carries these words as speaker notes.</li>
    <li>Have the app open in a browser tab in case a judge asks to see it live.</li>
  </ul>
</div>
"""

HTML(string=DOC, base_url=".").write_pdf("Cortex_Synapse_3min_Pitch_Script.pdf")
print("wrote Cortex_Synapse_3min_Pitch_Script.pdf")
