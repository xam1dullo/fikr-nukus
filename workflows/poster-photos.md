# Poster photos — regenerate

The printed QR banners (`admin.html` → Xodimlar → QR plakatlar → "3 ta banner") show one photo per staff member, picked by `posterPhoto()`: the survey template, or for doctors the department/specialty. The text, QR, name and department are live HTML; the photo is the **only** generated part.

Run from the repo root, e.g. `codex exec "$(cat workflows/poster-photos.md)"`, or paste this file into any image tool and save the results under the listed names.

## Rules for every image

- Square, **1536 × 1536 px**, saved as JPEG (quality ≈ 82, under 300 KB) to the exact path given. Overwrite the old file.
- Photorealistic, modern bright clinic, blue-and-white palette (navy #082B72, medical blue #1479F8, light blue, white), soft daylight, shallow depth of field.
- People: Central Asian (Uzbek / Karakalpak) appearance, natural anatomy, friendly and professional. White coat or blue scrubs as written.
- Composition: main person **centre-right**, face in the **upper third**, nothing important in the left 20 % (a white curve covers it) or the bottom 10 % (the navy strip covers it). Faces unobstructed.
- **No text anywhere**: no signs, labels, logos, name badges with letters, screens with writing, watermarks.
- Do not depict real or named people.

## Images

22 files. Which staff get which photo (81 seeded staff): endo 12, head 7, icu 6, physio 5, generic doctor 21 (“all specialities except surgeons”, no department), the rest 1–3 each. The mapping is `PHOTO_BY` in `app/public/admin.html`.

| File | Prompt |
|---|---|
| `app/public/p/doctor.jpg` | Young female doctor, late 20s, white medical coat over blue scrubs, stethoscope around neck, warm smile, standing in a modern consultation room. |
| `app/public/p/d/endo.jpg` | Female adult endocrinologist, 35–45, white coat, stethoscope, sitting at a consultation desk explaining test results on paper (no readable text) to a middle-aged patient, kind expression. *(Currently a copy of diabetology.jpg — replace.)* |
| `app/public/p/d/head.jpg` | Senior male doctor, 50–60, head of department, white coat over shirt and tie, stethoscope, standing confidently with arms relaxed in a bright hospital corridor, trustworthy smile. *(Currently a copy of doctor.jpg — replace.)* |
| `app/public/p/d/gp.jpg` | Male general practitioner, 30–40, white coat, measuring an adult patient's blood pressure with a cuff, friendly. *(Currently a copy of doctor.jpg — replace.)* |
| `app/public/p/d/diabetology.jpg` | Male endocrinologist, 35–45, white coat, stethoscope, showing a glucose meter to an adult patient across a desk, approachable expression. |
| `app/public/p/d/thyroid.jpg` | Female endocrinologist, 40–50, white coat, gently examining the front of a patient's neck, calm reassuring look. |
| `app/public/p/d/cardio.jpg` | Male cardiologist, 40–50, white coat, stethoscope on an older patient's chest, heart-monitor glow in soft focus behind (no readable numbers). |
| `app/public/p/d/nephro.jpg` | Female doctor, 40–50, white coat, explaining with a simple kidney anatomy model to a seated patient, speaking kindly. |
| `app/public/p/d/neuro.jpg` | Male neurologist, 35–45, white coat, testing a patient's reflexes with a reflex hammer, attentive. |
| `app/public/p/d/eye.jpg` | Female ophthalmologist, 30–40, white coat, at a slit-lamp examining an older patient's eye. |
| `app/public/p/d/pediatric.jpg` | Young female paediatrician, blue scrubs and white coat, smiling while listening to a child's chest with a stethoscope, parent's hand on the child's shoulder. |
| `app/public/p/d/surgery.jpg` | Male surgeon, 35–45, blue scrubs and surgical cap, mask lowered to the chin, confident friendly face, bright corridor of an operating unit behind. |
| `app/public/p/d/icu.jpg` | Female intensive-care doctor, 35–45, blue scrubs, checking an IV line beside a hospital bed, monitors softly blurred (no readable screens). |
| `app/public/p/d/physio.jpg` | Male physiotherapist, 30–40, blue polo-style uniform, guiding a patient's knee exercise on a treatment table. |
| `app/public/p/d/diet.jpg` | Female dietitian, 30–40, white coat, holding a bowl of fresh vegetables and fruit while talking to a patient. |
| `app/public/p/d/lab.jpg` | Female lab specialist, 30–40, white coat and gloves, holding up a test tube in a clean laboratory with a microscope. |
| `app/public/p/podiatrist.jpg` | Male podiatrist, 35–45, white coat and gloves, carefully examining a seated patient's foot. |
| `app/public/p/imaging.jpg` | Young female radiologist, white coat, beside an ultrasound machine with a patient on the couch, screen turned away or blurred. |
| `app/public/p/nurse.jpg` | Young female nurse, blue scrubs, gloves, preparing to take a blood sample from a smiling patient's arm, tourniquet visible. |
| `app/public/p/clinic.jpg` | Modern outpatient clinic hallway, bright white and blue, a few blurred patients waiting on blue chairs, plants, **no signage**. |
| `app/public/p/cashier.jpg` | Young female cashier in a navy uniform at a clinic payment desk, handing a receipt to a patient, glass counter, card terminal. |
| `app/public/p/registry.jpg` | Female receptionist, 30–40, navy uniform, smiling at a clinic front desk while helping a patient, monitor turned away. |

## After generating

1. Check each file opens, is 1536 × 1536 and has no text in it.
2. Open Xodimlar → pick a few staff → QR plakatlar → "3 ta banner" and look at the photos in place.
3. Commit the new JPEGs.
