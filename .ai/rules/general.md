---
paths:
  - '**'
---

# General

## House copy style: no em dashes, gender-neutral
No em dashes anywhere a person reads: UI copy, code comments, docs, commit messages. Use a hyphen with spaces instead: " - ".

Keep all copy gender-neutral. Never "dude", "man", "guys" or "bro", including in bot or system replies.

## Formatters run once at the end, never between edits
`npm run format` (prettier --write), `npm run lint` (eslint --fix) and `vendor/bin/pint` rewrite files on disk. Run them once when the file work is finished, not in the middle of a series of edits.

The check variants are read only and safe at any point: `npm run format:check`, `npm run lint:check`, `vendor/bin/pint --test`.

After any write pass, re-read a file before editing it again. Prettier rewraps at printWidth 100 and eslint's @stylistic rules move blank lines around control statements, so remembered text stops matching and replacements silently miss.
