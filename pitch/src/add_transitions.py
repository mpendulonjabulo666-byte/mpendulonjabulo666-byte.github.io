"""Add a slide transition to every slide of a .pptx.

pptxgenjs cannot emit <p:transition>, so patch it in afterwards. The element
belongs between <p:clrMapOvr> and <p:timing> in CT_Slide, and is wrapped in
mc:AlternateContent so PowerPoint 2010+ gets the timed variant and everything
else falls back to the plain one.
"""
import re
import shutil
import sys
import zipfile

TRANSITION = (
    '<mc:AlternateContent xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006">'
    '<mc:Choice xmlns:p14="http://schemas.microsoft.com/office/powerpoint/2010/main" Requires="p14">'
    '<p:transition spd="slow" p14:dur="800" advClick="1"><p:fade/></p:transition>'
    "</mc:Choice>"
    "<mc:Fallback>"
    '<p:transition spd="slow" advClick="1"><p:fade/></p:transition>'
    "</mc:Fallback>"
    "</mc:AlternateContent>"
)


def patch(xml: str) -> str:
    if "<p:transition" in xml or "mc:AlternateContent" in xml:
        return xml
    if "</p:clrMapOvr>" in xml:
        return xml.replace("</p:clrMapOvr>", "</p:clrMapOvr>" + TRANSITION, 1)
    if "<p:clrMapOvr/>" in xml:
        return xml.replace("<p:clrMapOvr/>", "<p:clrMapOvr/>" + TRANSITION, 1)
    # no colour-map override: sit straight after </p:cSld>
    return xml.replace("</p:cSld>", "</p:cSld>" + TRANSITION, 1)


def main(path):
    tmp = path + ".tmp"
    shutil.move(path, tmp)
    n = 0
    with zipfile.ZipFile(tmp) as src, zipfile.ZipFile(
        path, "w", zipfile.ZIP_DEFLATED
    ) as dst:
        for item in src.infolist():
            data = src.read(item.filename)
            if re.fullmatch(r"ppt/slides/slide\d+\.xml", item.filename):
                out = patch(data.decode("utf-8"))
                if out != data.decode("utf-8"):
                    n += 1
                data = out.encode("utf-8")
            dst.writestr(item, data)
    print(f"added fade transition to {n} slides")


if __name__ == "__main__":
    main(sys.argv[1])
