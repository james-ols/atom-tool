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