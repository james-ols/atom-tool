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