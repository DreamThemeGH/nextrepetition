# Contributing

Thanks for helping improve Repetio.

## Before you open a PR

- Run `npm run build`.
- Run `npm run test`.
- Run `npm run typecheck`.
- If you touched PHP or app metadata, also verify the app still enables in Nextcloud.

## Style

- Keep changes focused and small.
- Match the existing code style in the file you edit.
- Prefer explicit names for components, services, and helper functions.
- Keep user-facing text in English.

## Release-related changes

- Update `CHANGELOG.md` when you ship a user-visible change.
- Update `appinfo/info.xml` when compatibility, links, or metadata change.
- Do not commit private signing keys or App Store tokens.

## Bug reports

Please include:

- Nextcloud version
- Repetio version
- Steps to reproduce
- Relevant console or server errors