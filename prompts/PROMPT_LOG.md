# Prompt log

AI-assisted development for the Mallow Billing take-home (Cursor).

This folder exists because the brief asks for the actual prompts used, not a reconstructed summary. Screenshots of the IDE chat should be dropped here as `01-*.png`, `02-*.png`, … before submission.

## Prompts from this session

### 1. Project kickoff

> I wil attach the two docs, prepare this project
> Technology: Laravel Framework
> Database: Mysql

### 2. Locate the briefs

> two pdf Files available in this Folder

The two PDFs in the project root:

- `ATL_Mini_Task 1.pdf` (Associate Team Lead — includes code review + rollout note)
- `Laravel_Senior_Developer_Mini_Task_261001_155040.pdf`

Implementation followed the ATL brief (superset of the Senior brief).

### 3. What the agent was asked to do, in practice

There was no further prompt after the PDFs were found. The agent:

- Extracted requirements from both PDFs
- Created a Laravel 12 app in this folder (PHP 8.2 cannot install Laravel 13)
- Designed the schema, services, jobs, dashboard UI, tests, and README
- Did **not** merge the sample `store()` endpoint; replaced it and wrote `docs/CODE_REVIEW.md`

## How to capture screenshots for submission

1. Open this chat in Cursor.
2. Screenshot the two user messages above plus the implementation turn.
3. Save PNGs into this `prompts/` folder.
4. Optional: keep this file as a text companion so reviewers can search the wording.
