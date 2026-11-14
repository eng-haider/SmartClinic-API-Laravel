# Smart Clinic Orthodontics module

An optional, per-clinic module with orthodontic analysis tools: cephalometric tracing, 3D cast analysis, CBCT viewing and reconstruction, facial and smile analysis, treatment planning, and reports. It lives inside the existing case details screen. It is not a separate patient system.

- **Backend:** `app/Modules/Orthodontics` (this repo)
- **Frontend:** `src/modules/orthodontics` (SmartClinic-Front)

> **Clinical status.** Every measurement is a deterministic geometric calculation from landmarks that a doctor placed or confirmed. The reference values are published means and ranges for specific populations. They are shown for comparison only and are not a diagnosis. The module has not been clinically validated or certified as a medical device. Before diagnostic use, validate it with orthodontic specialists against a reference tool (see [Validation plan](#validation-plan)).

---

## 1. Activation and visibility

| Rule | Where it is enforced |
|---|---|
| The `enable_orthodontics` clinic setting is off by default, per clinic | Tenant migration `2026_11_14_000001` (never overwrites an existing value); settings seeders |
| Only users with `edit-clinic-settings` can switch it | Existing `ClinicSettingController` permission middleware |
| The module shows only when the setting is on **and** the case category has `is_orthodontic = true` | API: `OrthodonticsAccess::case()`. UI: `useOrthodontics().showForCategory()` |
| Category ids and names are never used at runtime | Eligibility reads only the `case_categories.is_orthodontic` flag, which clinics set on the Case Categories page. That page *suggests* categories named تقويم / Orthodontics, but nothing is flagged automatically. |
| Disabling the module never deletes data | The API answers `403 orthodontics_disabled`; switching it back on shows the same records |
| Moving a case to a non-orthodontic category hides the module but keeps its records | The API answers `403 orthodontics_not_eligible`. The case form asks for confirmation when the case has records. |

Where it appears:

- **Case details page** (`/cases/:id`): an «تحليل التقويم» tab, deep-linkable with `?tab=orthodontics&ortho_tab=ceph`.
- **Patient page:** the orthodontic case panel has a «تحليل التقويم» button.
- **Case form:** shows an orthodontics notice for orthodontic categories, and asks for confirmation before moving a case with records to another category.
- **Settings › Clinic › Display:** the «وحدة تقويم الأسنان» switch.

## 2. Security model

Every endpoint is registered in `app/Modules/Orthodontics/routes.php` inside the tenant API group, and is checked as follows:

1. **Authenticated:** `jwt` middleware.
2. **Token issued for this clinic:** `EnsureTokenMatchesTenant`, so a token from clinic B cannot be pointed at clinic A's database.
3. **Module enabled:** `EnsureOrthodonticsEnabled`.
4. **Permission:** `view-orthodontics` for reads, `manage-orthodontics` for writes (`OrthodonticsAccess::authorize`, using `checkPermissionTo`, so a permission row that doesn't exist yet counts as "no").
5. **Case visible to the user:** the same rule as `CaseController`. Doctors without `view-clinic-cases` only reach their own cases.
6. **Orthodontic category.**
7. **Child records are always looked up through the case:** a file, analysis or study id from another case, or another clinic's database, is simply "not found". `file_id`, `cbct_study_id` and `data.model_file_ids` must belong to the same case.

Files:

- Uploads are stored on the tenant's **private** `local` disk (`storage/tenant{id}/app/orthodontics/...`). That is outside the `public/` tree served by `/file/tenant/...`.
- Files are only streamed through authenticated endpoints, with `Cache-Control: private, no-store`. There are no public or signed URLs.
- The PWA service worker uses `NetworkOnly` for `/orthodontics/`, so medical data is never kept in the device cache.

Audit: `orthodontic_audit_logs` records uploads, views, saves (with the previous plan snapshot), reviews, deletions, CBCT opens and report generation.

Permissions:

- Tenant migration `2026_11_14_000002` creates `view-orthodontics` and `manage-orthodontics` and grants them to `super_admin`, `clinic_super_doctor` and `doctor`.
- Secretaries don't get them by role. A clinic can grant them per secretary from the permissions dialog, because they are listed under `clinic_super_doctor` in `config/rolesAndPermissions.php`.
- Users see new permissions after their permission list refreshes, or at their next login.

## 3. Data model (tenant database)

| Table | Purpose |
|---|---|
| `orthodontic_files` | Original uploads: `ceph_xray`, `face_frontal`, `face_smile`, `face_profile`, `intraoral_photo`, `model_upper`, `model_lower`, `model_combined`. Stores sha256, size, image size, and model meta (format, triangle count, **units confirmation**). Soft deletes. |
| `orthodontic_analyses` | One analysis (`ceph`, `cast`, `face`, `cbct`). `data` = what the doctor entered; `results` = what the **server computed**. `status` is `draft` or `reviewed`, with `revision`. Soft deletes. |
| `orthodontic_analysis_revisions` | An immutable snapshot of every save and every review change. |
| `orthodontic_treatment_plans` | One per case: assessment, plan, stages and status. Earlier versions are kept in the audit log. |
| `orthodontic_cbct_studies` / `_instances` | DICOM study and instance index (UIDs, slice order, storage location). There is no pixel data in MySQL. |
| `orthodontic_audit_logs` | The audit trail. |

- The patient is always read through the case, so patient merges (which move cases) need no extra handling.
- Foreign keys to `cases` use `RESTRICT`. Cases are soft-deleted in the app, and a hard delete must never silently drop medical records.
- Rolling back migration `000003` drops the module tables. Don't do that in production.

Saves never overwrite silently:

- An `expected_revision` mismatch returns `409 revision_conflict`.
- Editing a reviewed analysis returns it to `draft`, and it then needs a fresh review.
- A *final* report (`?final=1`) includes only reviewed analyses.

## 4. API

Base path: `/api/tenant/cases/{case}/orthodontics`

| Method | Path | Ability |
|---|---|---|
| GET | `/` | view: summary, `can_manage`, capabilities, landmark definitions |
| GET | `/report?final=1&analysis_id=&types=` | view: everything a combined or single report needs |
| GET, POST | `/files` (`kind`, `file`, `label`, `captured_at`) | view / manage |
| GET | `/files/{file}/content` | view: original bytes |
| PATCH | `/files/{file}` (`units`, `scale_to_mm`, `label`, `captured_at`, `bounding_box`) | manage |
| DELETE | `/files/{file}` | manage (soft) |
| POST | `/files/{file}/detect-landmarks` | manage (`501` unless a provider is configured) |
| GET, POST | `/analyses` (`type`, `title`, `file_id`, `cbct_study_id`, `analysis_date`, `data`, `notes`) | view / manage |
| GET, PUT, DELETE | `/analyses/{id}` (PUT accepts `expected_revision`) | view / manage |
| POST | `/analyses/{id}/review` (`reviewed`) | manage |
| GET | `/analyses/{id}/revisions`, `/analyses/{id}/revisions/{rev}` | view |
| POST | `/analyses/compute` (`type`, `data`) | view: compute without saving |
| GET, POST | `/cbct` | view / manage |
| GET | `/cbct/{study}` | view: study plus instances in viewing order |
| POST | `/cbct/{study}/instances` (`files[]`, one batch) | manage |
| POST | `/cbct/{study}/complete` | manage |
| GET | `/cbct/{study}/instances/{instance}` | view: `application/dicom` |
| PUT | `/cbct/{study}/viewer-state` | manage |
| DELETE | `/cbct/{study}` | manage (an unfinished upload's partial files are removed) |
| GET, PUT | `/treatment-plan` (PUT accepts `expected_revision`) | view / manage |
| GET | `/treatment-plan/history` | view |

Errors use the app's usual `{ success: false, message, message_ar, code }` shape. The codes are:

- `orthodontics_disabled`
- `orthodontics_not_eligible`
- `orthodontics_forbidden`
- `revision_conflict`
- `invalid_file`
- `unsupported_file`
- `invalid_dicom`
- `study_closed`
- `detection_unavailable`

## 5. Calculations

The server calculators (`app/Modules/Orthodontics/Analysis`) are authoritative: `results` are always recomputed from `data` on save. The browser has twins with the same formulas (`src/modules/orthodontics/analysis/*.js`) for live preview. Both are tested against the **same fixtures** (`tests/Fixtures/orthodontics`), whose landmarks are placed by geometric construction, so the expected values are known exactly rather than produced by either engine.

### 5.1 Conventions

- Image coordinates are pixels with **y pointing down**.
- **Facing:** taken from S→N (or set explicitly). "Anterior" is the facing direction, and every signed distance is positive anteriorly. A mirrored radiograph gives identical values (this is tested).
- `fromDown(v)` / `fromUp(v)` give the inclination of a downward or upward vector, measured from vertical towards anterior, in an anterior-up frame. They are used for signed incisor angles.
- **Calibration**, either:
  - a ruler (two points plus its known mm): `mm_per_px = known_mm / |p1 − p2|`; or
  - a detector pixel spacing: `mm_per_px = spacing / magnification`.
- Without calibration, distances are in **px**, flagged `calibrated: false`, and never compared with mm norms. Angles and ratios don't depend on scale.
- The **occlusal plane** (Downs) is the line from the molar overlap `mid(U6, L6)` to the incisal overlap `mid(U1T, L1T)`.
- The incisor tip stands in for the "most labial point" in the incisor distance measurements.

### 5.2 Cephalometric measurements

All angles are in degrees and distances in mm (or px when uncalibrated).

| Key | Formula | Reference |
|---|---|---|
| SNA | ∠(S, N, A) at N | 82 ± 2 (Steiner 1953) |
| SNB | ∠(S, N, B) at N | 80 ± 2 |
| ANB | SNA − SNB | 2 ± 2 |
| SN_GoGn | angle between S→N and Go→Gn | 32 ± 5 |
| SN_OP | angle between S→N and the occlusal plane | 14 |
| U1_NA_deg | fromDown(U1A→U1T) − fromDown(N→A) | 22 |
| U1_NA_mm | signed distance from U1T to line NA | 4 |
| L1_NB_deg | fromUp(L1A→L1T) − fromUp(B→N) | 25 |
| L1_NB_mm | signed distance from L1T to line NB | 4 |
| interincisal | angle between U1T→U1A and L1T→L1A | 131 (Steiner) / 135.4 ± 5.8 (Downs) |
| facial_angle | angle between Or→Po and N→Pog | 87.8 ± 3.6 (Downs); 87 ± 3 at age 9 (Ricketts, facial depth) |
| convexity | sign(A anterior to N-Pog) × (180 − ∠N-A-Pog) | 0 ± 5.1 (Downs) |
| AB_plane | fromDown(A→B) − fromDown(N→Pog) | −4.6 ± 3.7 (Downs) |
| FMA | angle between Po→Or and Go→Me | 25 ± 5 (Tweed); 21.9 ± 3.2 (Downs); 26 ± 4.5 (Ricketts); 22 ± 4 (McNamara) |
| Y_axis | angle between Po→Or and S→Gn | 59.4 ± 3.8 (Downs) |
| OP_cant | signed FH → occlusal-plane angle, positive when the occlusal plane descends anteriorly | 9.3 ± 3.8 (Downs) |
| L1_OP | angle(L1A→L1T, occlusal plane posterior) − 90 | 14.5 ± 3.5 (Downs) |
| IMPA | angle between L1A→L1T and Me→Go | 90 ± 5 (Tweed); 91.4 ± 3.8 (Downs) |
| FMIA | 180 − FMA − IMPA (Tweed triangle) | 65 (Tweed) |
| U1_APog_mm | signed distance from U1T to line A-Pog | 2.7 ± 1.8 (Downs) |
| facial_axis | angle between N→Ba and Pt→Gn | 90 ± 3.5 (Ricketts) |
| convexity_mm | signed distance from A to line N-Pog | 2 ± 2 at age 9 (Ricketts) |
| L1_APog_mm | signed distance from L1T to line A-Pog | 1 ± 2 (Ricketts) |
| L1_APog_deg | angle between L1A→L1T and Pog→A | 22 ± 4 (Ricketts) |
| lower_lip_E / upper_lip_E | signed distance from Li / Ls to line Prn-Pog′ | lower −2 ± 2 (Ricketts, adult) |
| A_Nperp | signed distance from A to the line through N perpendicular to FH | 0–1 mm (McNamara, adult) |
| Pog_Nperp, Co_A, Co_Gn, mm_differential, LAFH (ANS-Me), N_Me, S_Go | distances | no single norm (they depend on size, age and sex) |
| U1_Avert_mm | signed distance from U1T to the line through A parallel to N-perpendicular | 4–6 (McNamara) |
| wits | (proj(A) − proj(B)) on the occlusal plane, positive anteriorly | about 0 (female) / about −1 (male) (Jacobson 1975) |
| saddle / articular / gonial | ∠N-S-Ar, ∠S-Ar-Go, ∠Ar-Go-Me | 123 ± 5 / 143 ± 6 / 130 ± 7 (Björk) |
| bjork_sum | sum of the three | 396 ± 6 (Björk) |
| jarabak_ratio | S-Go / N-Me × 100 | 62–65 % (Jarabak) |
| U1_SN, FH_SN | angles | 103; 7 |

- **Comparison:** `(value − mean) / sd`, giving `within` when |z| ≤ 1, otherwise `above` or `below`. Min–max norms are a simple range check. This is a numeric comparison only: no clinical interpretation is generated anywhere, including reports.
- Wits uses the Downs occlusal plane, which can differ from Jacobson's functional occlusal plane.

### 5.3 Cast analysis

- **Units:** STL has no units. A model's measurements stay in *model units* until the doctor confirms the file's units (`PATCH files/{id}` with `units`).
- The server suggests nothing on its own. The UI shows a size-based hint only.
- Caliper widths (`manual_mm`) are never summed with unconfirmed model units (`mixed_units`).

| Analysis | Formula |
|---|---|
| Tooth width | \|mesial − distal\| (3D) × scale, or a caliper reading |
| Bolton overall | Σ mandibular 6→6 / Σ maxillary 6→6 × 100. Norm 91.3 ± 1.91 (Bolton 1958). The excess is the tooth material to remove to reach the mean: if ratio > mean, the mandibular excess = Σmand − Σmax × 0.913; otherwise the maxillary excess = Σmax − Σmand / 0.913. |
| Bolton anterior | The same for 3→3, with norm 77.2 ± 1.65 |
| Space analysis | Available = length of the arch-perimeter polyline (contact points, mesial of 6 to mesial of 6). Required = Σ widths 5→5. Discrepancy = available − required (negative means crowding). |
| Carey (1949) | The mandibular space analysis |
| Intercanine / intermolar width | Distance between cusp tips |
| Arch depth | Distance from the incisal point to the line joining the mesial contacts of the first molars |
| Pont (1909) | SI = Σ widths 12, 11, 21, 22. Premolar width = SI × 100/80; molar width = SI × 100/64. Compared with the measured distal-pit / mesial-pit widths. Based on a French population sample, so it is population-specific. |
| Tanaka & Johnston (1974) | SI = Σ widths 42, 41, 31, 32. Upper C+P1+P2 = SI/2 + 11.0; lower = SI/2 + 10.5 (per quadrant). Compared with the measured segment space when it is picked. |
| Moyers | Table interpolation, **disabled until configured** (see below) |
| Curve of Spee | Reference plane through the incisal point and the distobuccal cusp tips of the last molars on both sides. Depth per side = the largest distance from the picked buccal cusp tips to that plane, plus the mean of both sides. |

**Moyers tables are not shipped.** The 75 % values could not be verified against the original source while building this, so `config('orthodontics.moyers_table')` is empty, and the UI says "table not configured". To enable it, enter the rows from a verified printed source. The format is documented in `config/orthodontics.php`. Interpolation happens only inside the table, never beyond its rows.

### 5.4 Facial analysis

Photos are perspective images, so ratios and angles are the primary output. Lengths are in mm only after a ruler calibration in the facial plane, and they stay approximate (`approximate: true`).

| Measure | Formula |
|---|---|
| Facial midline | Through the midpoint of the pupils (or of the inner or outer canthi), perpendicular to the interpupillary line |
| Thirds | Distances projected on the midline direction: Tr–G, G–Sn, Sn–Me′, as % of the total, plus middle/lower and (Sn–Sto)/(Sto–Me′) |
| Symmetry | Per pair (pupils, canthi, alae, cheilia, zygia, gonia): distance of each side to the midline, asymmetry = \|dR − dL\| / (dR + dL) × 100, and the tilt of the pair line against the interpupillary line |
| Midline deviation | Signed distance of Sn, Ls, Li, Pog′, Me′ and the dental midline to the facial midline (+ means towards the patient's left) |
| Proportions | Facial index (N′–Me′ / Zy–Zy × 100), intercanthal/alar, mouth/interpupillary |
| Smile | Width (Ch–Ch), width/interpupillary, buccal corridor = (smile width − visible dentition width) / smile width × 100, incisor display (Stms→UIE) and gingival display |
| Profile | Nasolabial angle ∠Cm-Sn-Ls (102 ± 8, Legan & Burstone 1980); facial convexity = sign × (180 − ∠G-Sn-Pog′) (12 ± 4, Legan & Burstone); lips to E-line |

MediaPipe Face Landmarker:

- It runs **in the browser only**. The photo is never sent anywhere; only the runtime (served by the app) and the model file are downloaded.
- Its 478 mesh vertices are mapped to the named points (`MEDIAPIPE_INDEX` in `face.js`). They are mesh vertices, not anatomical landmarks, so each one is shown dashed until the doctor confirms or moves it, and saving is blocked until then.
- The hairline, columella and dental points are always placed manually.
- Detection isn't offered for profile photos.
- No confidence scores are shown.

### 5.5 CBCT

- **Measurements:** world (DICOM patient) coordinates in mm, from `ImagePositionPatient`, `PixelSpacing` and the slice positions. Accuracy is bounded by the voxel size and the scanner calibration.
- **Panoramic / curved planar reconstruction** (`panoramic.js`):
  1. The arch curve is drawn on an axial slice.
  2. It is turned into a Catmull-Rom spline in mm and resampled every `step` mm.
  3. Each panoramic column samples the voxels along the in-plane normal across the slab (MIP), for every slice, with bilinear interpolation in plane.
  4. A cross-section is the plane spanned by the normal and the slice axis at one curve point.
  5. Pixel sizes are exact: `step` × slice spacing.
- **Bone height / width:** two points on the cross-section, √((Δcol·sx)² + (Δrow·sz)²).
- **Implant plan:** the platform centre (world), the axis (unit world vector, built from the exact image-to-world mapping), the diameter and length. The server computes the apex and the tilt from vertical.
- The plan is a visualisation and measuring aid, **not a surgical guide**.
- The reconstruction is unit-tested on synthetic volumes (`tests/orthodontics-cbct.test.mjs` in the frontend).

## 6. CBCT storage

The driver is set with `ORTHODONTICS_CBCT_DRIVER`:

- `local` (default):
  - DICOM instances go to the tenant's private disk.
  - It works on shared hosting.
- `orthanc`:
  - Instances are forwarded to an **isolated Orthanc** server. It must be on a private network, never exposed to the internet, and use HTTP basic auth.
  - Laravel still authenticates and authorises every read, and only asks Orthanc for instance ids recorded in the requesting clinic's own database.
  - Studies are labelled `tenant-{id}` for operators (Orthanc 1.12 or later).
  - Orthanc is GPLv3. Running it as a separate, unmodified network service doesn't impose its licence on Smart Clinic's code. Don't install AGPL plugins (for example DICOMweb) unless their obligations are reviewed.

Upload flow:

1. The browser accepts DICOM files, a folder, or a ZIP. ZIPs are unzipped in the browser with fflate.
2. The browser checks the `DICM` magic, so non-DICOM files are dropped.
3. It uploads batches of 60 files or 24 MB at most (`files[]`), with real progress. That keeps every request under the usual PHP limits; raise `upload_max_filesize` / `post_max_size` above 24 MB per request if needed.
4. The server parses each header (`DicomHeaderParser`: implicit/explicit VR little endian; big endian and deflated are refused).
5. The server rejects files from a different StudyInstanceUID, duplicates, and non-image instances.
6. `complete` marks the study ready.
7. `php artisan orthodontics:cleanup-cbct` (schedule it daily) marks uploads left unfinished for 24 h as failed and deletes their partial files. Completed studies are never removed automatically.

Viewer:

- Cornerstone3D 5.14 shows axial, coronal and sagittal MPR and 3D volume rendering (CT-Bone preset), with window/level presets, pan, zoom, slice scrolling, length, angle and crosshairs.
- Instances are downloaded through the API (JWT) and given to the loader from memory.
- Enhanced multi-frame DICOM isn't supported yet. The viewer explains this and asks for a single-frame series export.

## 7. Configuration (`config/orthodontics.php`)

| Env | Default | Purpose |
|---|---|---|
| `ORTHODONTICS_DISK` | `local` | Tenant disk for originals (keep it private) |
| `ORTHODONTICS_MAX_IMAGE_KB` / `_MAX_MODEL_KB` / `_MAX_DICOM_BATCH_KB` | 30 MB / 200 MB / 100 MB | Upload limits |
| `ORTHODONTICS_CBCT_DRIVER` | `local` | `local` or `orthanc` |
| `ORTHODONTICS_CBCT_MAX_INSTANCES` | 5000 | Per study |
| `ORTHODONTICS_CBCT_STALE_HOURS` | 24 | Cleanup age |
| `ORTHANC_URL`, `ORTHANC_USERNAME`, `ORTHANC_PASSWORD`, `ORTHANC_TIMEOUT` | — | Orthanc driver |
| `ORTHODONTICS_LANDMARK_PROVIDER` | empty | `http` enables automatic ceph landmark suggestions |
| `ORTHODONTICS_LANDMARK_URL`, `_TOKEN`, `_TIMEOUT` | — | The provider endpoint |

Landmark provider contract:

- The request is `POST multipart`: `image` (the radiograph), plus `width` and `height`, with an optional bearer token.
- The expected response is `{"landmarks": {"S": {"x": 412.5, "y": 300.1}, ...}}` in pixel coordinates.
- Unknown codes and points outside the image are dropped.
- Suggestions are never saved by themselves, and they don't overwrite points the doctor already placed.
- No model ships with Smart Clinic: a provider must be licensed and validated separately.

Frontend env: `VITE_FACE_LANDMARKER_MODEL_URL` (optional) self-hosts the MediaPipe `face_landmarker.task` model, which is Apache-2.0. The default is Google's model storage.

## 8. Deployment

1. Run the backend migrations for every clinic: `php artisan tenants:migrate`. This adds the setting (off), the permissions and the tables.
2. Schedule `php artisan orthodontics:cleanup-cbct` daily.
3. Deploy the frontend build. It emits `cornerstone-wasm/` and `mediapipe/wasm/` next to `assets/`.
4. Clinic admin: Settings › Display › «وحدة تقويم الأسنان», then flag the orthodontic category under Case Categories.

## 9. External components

**Frontend** (browser bundle, loaded lazily only inside the workspace):

| Package | Version | Licence |
|---|---|---|
| three (OrbitControls, STL/PLY/OBJ loaders) | 0.187.0 | MIT |
| @cornerstonejs/core, tools, dicom-image-loader, metadata, utils | 5.14.0 | MIT |
| @kitware/vtk.js (via Cornerstone) | 36.4.1 | BSD-3-Clause |
| Cornerstone codecs: openjpeg, openjph, charls, libjpeg-turbo-8bit, libjxl, jpeg-lossless | 1.3.6 / 2.4.11 / 1.2.7 / 1.2.7 / 1.1.1 / 2.2.1 | MIT / MIT / MIT / ISC / BSD-3 / MIT |
| dicom-parser, pako, comlink, gl-matrix, xmlbuilder2, clipper2-ts | 1.8.21 / 1.0.11 / 4.4.2 / 3.4.3 / 4.0.3 / 2.0.1 | MIT / MIT+Zlib / Apache-2.0 / MIT / MIT / BSL-1.0 |
| @mediapipe/tasks-vision | 1.2.0 | Apache-2.0 |
| face_landmarker.task model (downloaded at runtime) | float16 v1 | Apache-2.0 |
| fflate (ZIP extraction) | 0.8.3 | MIT |
| events (browser `events`, needed by xmlbuilder2) | 3.3.0 | MIT |

**Server (optional):** Orthanc, GPLv3, run as a separate service.

All bundled licences are permissive. Keep their notices: the licence texts are in each package's `LICENSE` under `node_modules`.

**Not used:**

- OHIF Viewer (MIT). It is a full application that expects a DICOMweb server and its own routing and auth. Cornerstone3D, OHIF's own imaging core, is embedded directly instead, so the viewer works with Laravel's authentication and with the `local` driver on shared hosting. Orthanc's OHIF plugin can be added later for the `orthanc` driver.
- Apexo and Bolton 3D. These were not used and no code was copied. Every formula above was written from the published methods.

## 10. Tests

**Backend** (`php artisan test tests/Unit/Orthodontics tests/Feature/Orthodontics`), 40 tests:

- **Calculators:** the constructed ceph fixture (SNA 82°, SNB 78°, ANB 4°, FMA 25°, IMPA 95°, FMIA 60°, interincisal 130°, facial angle 88°, Wits 1.452 mm); invariance to mirroring; uncalibrated px handling; missing landmarks; norm comparison.
- **Cast:** the hand-computed Bolton, space, arch, Pont, Tanaka–Johnston and Curve of Spee values, unit mixing, and Moyers interpolation and no-extrapolation.
- **Face / CBCT:** thirds, symmetry, midline, profile angles, distances, angles, cross-sections and implants.
- **DICOM header parser:** explicit and implicit VR, with sequences.
- **Acceptance:**
  1. Disabled module: everything is rejected and data is kept.
  2. Non-orthodontic case: refused.
  3. Orthodontic case: the workspace works.
  4. Two clinic databases: settings and data stay independent.
  5. Category change: the module is hidden, data is kept, and changing back restores it.
  6. Cross-clinic token refused; files and studies are reachable only through their own case; doctor scope applies; permissions are enforced.
  7. Measurements match the constructed geometry.
  8. Existing app: the suite shows no new failures.
- **Also:** revision conflicts, review reset, unit confirmation, CBCT upload validation, ordering and streaming, treatment plan history, and idempotent migrations.

**Frontend** (`node --test tests/*.test.mjs`): the JS twins on the same fixtures, plus the panoramic and cross-section reconstruction on synthetic volumes.

## 11. Validation plan

Before production diagnostic use:

1. Trace 30 or more lateral cephalograms both in this module and in a validated reference program (same landmarks, same calibration). Compare each measurement (Bland–Altman; agreement within about 1° / 1 mm), and check intra- and inter-examiner repeatability.
2. Measure plaster casts with a digital caliper, and compare them with 3D scans of the same casts after unit confirmation.
3. Check CBCT distances on a phantom of known dimensions, for each scanner model in use.
4. Review the reference values with the clinic's orthodontists for their population.
5. Keep the model's limitations visible in the UI (they are), and record the validation in the clinic's quality documentation.

## 12. Known limitations / further work

- Automatic ceph landmark detection needs an external, licensed and validated provider. The integration point exists, but no model is included.
- CBCT:
  - Enhanced multi-frame DICOM, segmentation and registration aren't implemented.
  - The implant plan has no implant library and no nerve canal tracing.
  - Saved CBCT length/angle annotations are re-created on reopen as a best effort.
- 3D models: there is no automatic registration (ICP) or superimposition between time points. Models can be shown together, with independent visibility and opacity.
- The facial before/after comparison shows the photos side by side with metric deltas; the images aren't aligned to each other.
- The Moyers table must be entered from a verified source (see 5.3).
- Clinical photographs in follow-up sessions still use the patient page's existing note-image flow. The API accepts an `intraoral_photo` file kind, but the workspace has no upload screen for it yet.
