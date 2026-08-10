# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
follows [Semantic Versioning](https://semver.org/).

## [2.1.2] - 2026-08-10

### Fixed
- Requester ticket history no longer breaks the analysis for users whose entity scope is
  restricted. The entity criteria for `glpi_tickets` were built with the recursive flag on, which
  makes GLPI add a condition on `glpi_tickets.is_recursive` — a column the tickets table does not
  have. Every run under a non-superadmin profile failed with
  `Unknown column 'glpi_tickets.is_recursive' in 'WHERE clause'` and the user only saw
  "Внутренняя ошибка анализа". Superadmins were unaffected because their entity criteria is empty.
- Requester ticket history is now fail-soft: any error while reading it is logged to
  `aiticketanalysis.log` and the analysis continues without that context block instead of aborting.

### Security
- The entity restriction on requester ticket history is unchanged in scope: tickets from other
  entities still never reach the analysis context.

## [2.1.1] - 2026-08-09 (first public release)

### Added
- README, CHANGELOG, LICENSE and `.gitignore` for public distribution.
- `docs/workspace-prompt.example.txt` — a neutral AnythingLLM workspace prompt to start from.

### Changed
- Neutral out-of-the-box defaults: `anythingllm_url = http://localhost:3001`,
  `vision_base_url = http://127.0.0.1:1234/v1`, `default_workspace = your-workspace`,
  `button_label = AI L3`.
- Vision/OCR wording is vendor-neutral (any OpenAI-compatible endpoint) instead of naming a
  single desktop LLM runner.

### Known issues
- UI strings are hardcoded in Russian; `__()` / locale files are not in place yet.

## [2.1.0] - internal

### Added
- Attachment pipeline: DOCX/XLSX/PDF/TXT text extraction, vision OCR for images and scanned PDF
  pages, per-file and total character budgets, quality flags (`good` / `low` / `none`).
- Attachment diagnostics in the result modal (method, characters, reliability, errors).
- `UNDISCLOSED_CONFIG_VALUE` hook so API keys are not exposed through the GLPI API.
- Fresh AnythingLLM `sessionId` per run so ticket data from different runs cannot mix.

## [2.0.0] - internal

### Added
- Entity + Profile → AnythingLLM workspace mapping table with wildcard support.
- `lite_mode` compact context for low-end hardware.
- Optional `@agent` + MCP (read-only GLPI) mode.

## [1.0.0] - internal

### Added
- Initial version: timeline button, ticket context collection, AnythingLLM chat call, Markdown
  rendering of the answer.
