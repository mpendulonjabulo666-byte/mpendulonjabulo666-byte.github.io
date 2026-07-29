"""The 3-minute pitch: spoken script, stage cues, and the cue card.

Timings are derived from the word counts at a calm 150 words per minute,
so editing a line automatically re-times everything after it.
"""

WPM = 150

# (slide no, slide name, stage direction, [spoken paragraphs])
BEATS = [
    (1, "Cover", "Stand still. Look up before you speak.", [
        "Good afternoon. My name is Mpendulo Njabulo, and this is Cortex Synapse. "
        "Let me start with a number this country celebrated in January.",
    ]),
    (2, "The size of the problem", "Let 88% sit for a second. Point at each card as you say it.", [
        "Eighty-eight percent. A record matric pass rate. But it counts only the learners "
        "who reached the exam desk.",
        "Measure the same class from the day they started Grade One and it is closer to "
        "fifty-five percent. Only one in three even wrote mathematics. And the fix that "
        "works — a tutor at four hundred and fifty rand an hour — cannot be bought by the "
        "learners who need it most.",
    ]),
    (3, "Passing is not understanding", "One breath. Then move.", [
        "So we are getting better at producing passes. We are not getting better at producing "
        "understanding. A thirty percent is a pass — it is not a mark that carries a learner "
        "into a degree or a job.",
    ]),
    (4, "Our solution", "Slow down. This is your product.", [
        "Cortex Synapse is a home-grown AI study companion. A learner drops in a topic, their "
        "notes, or a YouTube link, and gets notes aligned to CAPS and NATED, with maths in the "
        "notation the exam uses.",
        "It then builds quizzes and flashcards from those notes, so they practise recall "
        "instead of re-reading, and the topics they get wrong come back. It is patient at two "
        "in the morning, and never makes a learner feel slow.",
    ]),
    (5, "Built, and on the real syllabus", "Gesture at the screen. Say it plainly.", [
        "This is not a concept. This is the product — every CAPS subject a Grade 10 to 12 "
        "learner writes, and the NATED subjects for TVET students.",
    ]),
    (6, "Why us, not them", "Fast. Three clauses.", [
        "Khan Academy is excellent — and it is American. A private tutor is ours, and costs a "
        "family thousands a month. We are South African, affordable, and awake whenever the "
        "learner is.",
    ]),
    (7, "Target market", "Numbers only. Do not elaborate.", [
        "Nine hundred thousand matric candidates a year. Thirteen million learners in basic "
        "education. Half a million TVET students with no digital support. We start at "
        "Majuba, because that is where I can prove it fastest.",
    ]),
    (8, "Revenue generation", "Be firm about the free tier.", [
        "The core stays free, permanently. Families who can pay take a low monthly "
        "subscription — one tutoring hour, for a whole year. Colleges licence seats per "
        "learner, and CSI partners sponsor no-fee schools. Two thousand paying learners covers "
        "our costs.",
    ]),
    (9, "Impact on society and the environment", "Warmer tone here.", [
        "A learner in a school with no laboratory gets the same explanation as one in a private "
        "school. Fear drops, because by exam day they have already answered the question fifty "
        "times. Nothing is printed. Nothing is driven to.",
    ]),
    (10, "The team, the ask, the close", "Look at the judges, not the slide. Pause before the last two lines.", [
        "The team is me — building this out of the same classrooms it is built for — and Tony "
        "on infrastructure. We are asking for funding for our first year, and access to pilot "
        "in schools. One campus, one term, and we bring you the marks.",
        "Because this was never about money. It is about a learner who understands, and is not "
        "afraid to write. The better the education, the better the South Africa that comes "
        "after us. Thank you.",
    ]),
]

CUE_CARD = [
    ("Hook", "Slide 1", ["Name, Cortex Synapse", "“A number we all celebrated”"]),
    ("Problem", "Slides 2–3", [
        "88% → really 55%",
        "Only 34% wrote maths",
        "83% no lab · tutor R450/hr",
        "“Passes, not understanding”",
    ]),
    ("Solution", "Slides 4–5", [
        "Topic / notes / YouTube in",
        "CAPS & NATED, real maths notation",
        "Quizzes + flashcards, weak topics return",
        "Patient, 2 a.m., never judges",
    ]),
    ("Why us", "Slide 6", [
        "Khan = American curriculum",
        "Tutor = thousands a month",
        "Us = SA, affordable, always awake",
    ]),
    ("Market + money", "Slides 7–8", [
        "900k matrics · 13m learners · 500k TVET",
        "Start: Majuba + Newcastle schools",
        "Free forever · family plan · seats · CSI",
        "Break-even 2 000 learners",
    ]),
    ("Impact + team", "Slides 9–10", [
        "Same explanation, any school",
        "Practice beats panic",
        "No paper, no travel, low data",
        "Me + Tony · already built",
    ]),
    ("Ask + close", "Slide 10", [
        "Funding · school pilot access",
        "“One campus, one term, we bring the marks”",
        "“Understands, and is not afraid to write”",
    ]),
]


def words(text):
    return len(text.split())


def timed_beats():
    """Attach a cumulative start time (seconds) to each beat."""
    out, elapsed = [], 0.0
    for slide, name, cue, paras in BEATS:
        wc = sum(words(p) for p in paras)
        out.append({
            "slide": slide, "name": name, "cue": cue, "paras": paras,
            "start": elapsed, "words": wc,
        })
        elapsed += wc / WPM * 60
    return out, elapsed


def mmss(seconds):
    return f"{int(seconds // 60)}:{int(round(seconds % 60)):02d}"


if __name__ == "__main__":
    beats, total = timed_beats()
    for b in beats:
        print(f"{mmss(b['start']):>5}  slide {b['slide']:>2}  {b['words']:>3}w  {b['name']}")
    print(f"\nTOTAL {sum(b['words'] for b in beats)} words -> {mmss(total)} at {WPM} wpm")


def export_notes(path="notes.json"):
    """Speaker notes for the deck, keyed by slide number, straight from BEATS."""
    import json
    notes = {str(s): "\n\n".join(paras) for s, _name, _cue, paras in BEATS}
    with open(path, "w") as fh:
        json.dump(notes, fh, indent=2, ensure_ascii=False)
    return notes
