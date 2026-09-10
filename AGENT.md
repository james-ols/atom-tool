# Agent Instructions and Structured Application Specification
## About you, the AI agent
You are Claude, operating within the PHPStorm IDE. Your primary task is to assist developers in creating and managing their applications. You will provide guidance, suggestions, and solutions to help them achieve their goals. Your responses should be concise, clear, and focused on the specific needs of the developer.
You are a PHP 8.5 specialist and can write clear, concise and well-structured code. Your responses should be well-documented and follow the best practices for PHP development.
Your code should not be overdocumented, keep comments to one or two lines maximum.
You are also a specialist in XML and understand PHP Stream Parsing.

You have skills and knowledge of the Access To Memory AtoM software system for archivists.

## Project overview and goals.
This project shall be a tool to assist the technical developer and client archivist in mapping the fields from the Axiell CALM archives system into the AtoM system.
Each import shall transform the CALM DScribe format XML into AtoM CSV.  There are six pipelines: Description , Accession , Authority Record, Authroity Record Relationships , Events and Archival Institutions.
The tool will be presented as a web application, and shall use PHP 8 with a minimum of dependencies.
The aim is that the tool will run locally at http://localhost:8000 , but it will also be deployed to AWS Lightsail using Docker.

The overall project structure is organized that this repository, atom-tool shall contain all the main functionality, the User Interface, Stream parsing and screens.
This project, atom-tool will then be a submodule (subproject) of each customer repository.  For example, there will be a repository atom-tool-gb166 for customer GB166.
This will repo will contain the customer specific mapping.php as well as the configured CALM DTDs and the baseline 'ground truth' AtoM CSV templates.
The DTD and CSV templates will very rarely change, but they might, if AtoM is extended in import functionality.

So, for purposes of local development there will be atom-tool-gb166 containing atom-tool as a submodule, to combine the two.

There will then be a deployment script, that would deply to AWS per customer into a container.  So "deploy gb166" would get the customer repo, with mapping.php and the latest build of atom-tool.

## Technical architecture
This project, will have a reasonable and not unmanageable number of php and css/js files, do not create an explosion of dozens of files.
The project is principally a single page application.  Sketches are provided in the chat.  The will be a basic authentication, the application will always be locked behind a firewall, but
good practice to have one basic username/password login.  A login box will be first presented, the login state can be set in a cookie.
For local runs, the file upload can be stored in customer folders like /gb166

When deployed, the file upload and resultant CSV and preflight report json txt will be stored in AWS s3 bukets.  We ill have one bucket called atom-toom , then in there will be customer buckets like gb166
The files have to survive repeated container deploys.

# Application Flow : Transforming
1. The user shall login
2. The user shall be presented with the 'dashboard' style screen, as in the sketches, and very similar to our CollectionsBase imported, also provided in the chat.  A materialstyle look could be used - use modern font and icons
3. There will be a nice static Archives Graphic at the top.
4. on the left hand side, a thin fixed column with icons for Description , Accession , Authority Record, Authroity Record Relationships , Events and Archival Institutions.
5. the central panel will be scrollable, this will contain the CALM file upload button, the Run transformation button, and the clear files button.
6. there will then be a scrollable tabulated style list, with a selector for each upload file, (name, size) , Transformation run date, and the selectable ATOM pipeline.
7. The selector will be a drop down list, with a default value of 'Select a pipeline', and then have Description , Accession , Authority Record, Authroity Record Relationships , Events and Archival Institutions
8. Upon selecting the row, the user will the select the Pipeline to run, and then click Run.
9. The PHP streams parser will read in the uploaded CALM file, and then run through the customer field mapping CALM to Atom CSV.
10. Most fields will just be one to one direct straight mapping. Some fields may have basic cleaning function, such as strip whitespace, and this can be a function thats shared generically across customers.
11. Some fields will have special customer specific PHP functions that are called, like fn_concat(fieldX,fieldY) that will be in the per customer mapping (so other customers might not run that)
12. The streams parser will write the CSV, there will be a progress bar that shoots below the row of the table as it is parsing, and then ends up with the File Icon appearing, ready for the customer to click and download the CSV.
13. The parsing function will hold a report, in json, of : rows processed, and fields in rows that were found but not mapped (for the preflight checker)

# Application Flow: Pre-flight
The application will have a pre-flight panel on the right, with the contents appearing on hovering over the end of each row.  Pre-flight shall show the coverage: how many fields were mapped, how many were not.
The exercise is to have all the fields in CALM mapped to corresponding fields in Atom CSV.
There will also be a validation function: this will use the code from AtoM itself to validate the CSV.

# Application : the "big view"
The USP of this tool will be found by hovering over the left hand side icons in the small fixed lhs column.  Each icon will be for the  Description , Accession , Authority Record, Authroity Record Relationships , Events and Archival Institutions.
Hovering over the icons will create a full panel overlay, over everything except the top header and lef hand menu icon bar.
This panel will contain an SVG live drawn image of the field mappings from CALM to ATOM.  For one to one, these are just arrows.  Where a special function has been called, this then shows the field to that function, then out to the ATOM.
See sketch page three.
It is slightly less important to get that working, that can be phase two. 

# Mapping Notes

Handover — Step 6 mapping design, unresolved
Where we stopped: deciding the exact shape of mapping.php. Everything up to and including Step 5c is complete, committed (or ready to commit), and the app works end-to-end for upload + delete. POST /run is a stub. Storage lands at atom-tool-gb166/storage/{uploads,outputs,reports}/.
The design premise you've established:
You author mappings, not customers.
mapping.php is a PHP file per pipeline (six per customer).
Layout: atom-tool-gb166/mapping/description.php, mapping/accession.php, etc.
Contents: PHP file returns an array; also declares customer-specific transform functions at the top.
The static map is keyed by CALM field.
Values are one of two shapes:
'atomColumnName' — direct 1:1 copy.
'gb166_functionName:atomColumnName' — through a named function.
Multiple CALM fields can share functionName:atomColumnName — engine groups them and invokes the function once with all their values.
Working example for GB166 Description sits in the previous message — copy it into a scratch file when you sit back down.
Open questions for tomorrow-you
Four things to decide before the engine can be written. In priority order:
Function signature: Design A (positional args in map order) or Design B (whole record array)? My lean: A. Explicit contract, no hidden coupling.
Multi-CALM-to-one-function grouping: engine collects entries with matching functionName:atomColumn value, invokes function once with concatenated arg list. Confirm this is what you want.
One CALM field to multiple AtoM columns (rare but possible, e.g. Date → both eventDates and eventStartDates):
Ignore for now, or
Value can be array: 'Date' => ['eventDates', 'eventStartDates'].
Directory layout: mapping/description.php etc., or six files at root (mapping-description.php)? My lean: subdirectory.
What Step 6 & 7 look like once the shape is locked
6a: Config::mappingPath(string $pipeline); delete the empty top-level mapping.php; create mapping/ subdirectory in the customer repo with description.php (the real GB166 one).
6b: Mapping\Mapping class — loads a mapping file, exposes apply(array $record): array returning [atomColumn => value, …], and unmappedInputs(array $record): array for preflight.
6c: Parser\CalmStreamParser — XMLReader walking DScribeRecord elements, yielding one record array at a time. Constant memory.
6d: CSV writer — reads header row from atom_{pipeline}.csv template, uses mapping output to build each row, fputcsv to outputs/<basename>.csv.
6e: Preflight accumulator — during the parse, tallies coverage and unmapped-fields; writes reports/<basename>.json at the end.
7a: Wire POST /run to the real pipeline.
7b: GET /download/{name} — download link, ⬇ icon activates when output exists.
7c: Preflight hover (the 5d we deferred) — panel loads the JSON on hover.
7d: Run-date column populates from output file mtime.
Also lingering — smaller, from earlier
Parent/child records: your sample XML has MI9861 (Collection) and MI9861/1 (Component/File). AtoM CSV import uses parentId (I think — you'll know) to build the hierarchy. Do we emit parentId derived from RefNo prefix matching? Worth explicit thought at Step 6c.
Date normalisation: <Created>20/08/2026</Created> is DD/MM/YYYY, AtoM wants ISO. Handled as a gb166_date_iso function in the customer's mapping.php, or engine-provided? Given "no engine primitives" is the whole point of your design, probably the former.

## build chat transcript
Good design instinct — the floating dialog with dead space around it looks tentative; a panel that *snaps to fill the centre + preflight area* (everything right of the rail, below the banner) will feel intentional and give the diagram far more room. It also reinforces the mental model: the rail stays, and the diagram takes over the working area.

The change is CSS-only. Right now `.diagram-overlay` centres a floating card with padding. I'll make it (a) start below the top bar + banner rather than at `top:0`, and (b) drop the card/padding so the white panel fills the region edge-to-edge.

There's one number to get right: the overlay's `top`. Your shell rows are `56px` (topbar) + `120px` (banner) = **176px** before the content row. So the panel should start at `top: 176px` to sit exactly where the centre/preflight area begins, and `left: 80px` (past the rail) as before. That makes it fill precisely the `centre + preflight` cells.

Here's the replacement for the `/* ---- Mapping diagram overlay ---- */` block in `app.css` — swap the existing overlay rules for these:

```css
/* ---- Mapping diagram overlay ---- */

/* Rail must float above the overlay so its icons stay visible and clickable
   (switch pipelines with the overlay open). */
.app-rail {
    position: relative;
    z-index: 60;
}

/* The overlay snaps to fill the working area: everything right of the 80px
   rail and below the 56px topbar + 120px banner (= 176px). No floating card,
   no wasted margin — it reads as the diagram taking over the workspace. */
.diagram-overlay {
    position: fixed;
    top: 176px;
    left: 80px;
    right: 0;
    bottom: 0;
    background: #ffffff;
    border-top: 1px solid var(--ols-border);
    border-left: 1px solid var(--ols-border);
    display: flex;
    flex-direction: column;
    z-index: 50;
}
.diagram-overlay[hidden] {
    display: none;
}
.diagram-overlay__card {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    min-height: 0;
    overflow: auto;
}
.diagram-overlay__img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    display: block;
}
.diagram-overlay__close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid var(--ols-border);
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    color: var(--ols-ink);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}
.diagram-overlay__close:hover {
    color: var(--ols-orange-dark);
}
.diagram-overlay__close .material-symbols-rounded {
    font-size: 20px;
}
```


What changed vs. the previous version:
- **`top: 176px`** (was `0`) — starts exactly at the content row, so the top bar and banner stay visible above it. The app still looks like itself; the diagram just fills the workspace.
- **Solid white background** (was a dim `rgba` backdrop) — it's now a *panel*, not a modal scrim. No dead space, no darkening.
- **No floating card** — the white fills the whole region; the SVG centres within it and scales to fit (`max-width/height: 100%`).
- **Close button moved inside** the panel's top-right (was floating at `-14px`), now that there's no card to hang off.
- Thin top/left borders to visually seat it against the topbar/banner and rail.

Two small notes:
- Because the background is now opaque white and it doesn't cover the banner/topbar, **clicking the backdrop to close** effectively means clicking empty panel space — which now *is* the diagram area, so I'd rely on the ✕ and Esc to close rather than backdrop-click (the JS backdrop-click still works, but there's less "backdrop" now). No JS change needed; ✕ and Esc already work.
- The `176px` is coupled to your shell's `56px + 120px` rows. If you ever change those row heights, bump this `top` to match. (A CSS variable could formalise that later, but for now the literal is clear.)

Reload after pasting, click Description, and the diagram should now fill the centre-plus-preflight area cleanly — no dialog framing, much more room for the fan-in arrows. If the SVG still looks small within the now-larger panel, that's just because it's scaling to fit a wider area while keeping its 1280×800 aspect — we can widen the diagram's internal usage (bigger boxes/font) to make better use of the space if you'd like, once you see it at full size.

