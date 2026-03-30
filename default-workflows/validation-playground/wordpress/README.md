# Validation Playground WordPress pack

This package gives you a ready-to-publish workflow for testing field validation in WordPress.

It is bundled with the Pro extension because it exercises advanced runtime
coverage, including `select-multiple`, alongside upload and validation checks.

## What you get
- A multi-step workflow covering the most useful field types
- Upload fields for file type and file size validation
- Choice fields for min/max selection testing
- A normal WordPress inbox flow after submit

## Recommended use
Use this starter to verify:
- text validation (`min`, `max`, `pattern`)
- number and price validation (`min`, `max`, `step`)
- multi-choice validation (`min choices`, `max choices`)
- file validation (`accept`, `max file size`)
- standard required/optional behavior

## Install
1. Install and activate `XPressUI Bridge`.
2. Install and activate `XPressUI WordPress Bridge PRO`.
3. Open `XPressUI -> Workflows -> Included Pro Tools`.
4. Create a page and embed:

```text
[xpressui id="validation-playground"]
```

Optional redirect:

```text
[xpressui id="validation-playground" redirect="https://yoursite.com/thank-you/"]
```

This workflow is bundled automatically with the Pro plugin and does not need to be uploaded manually.

## Included steps
1. Contact
2. Validation
3. Documents
4. Review

## Included field coverage
- text
- email
- tel
- radio-buttons
- number
- price
- date
- time
- checkboxes
- select-multiple
- url
- upload-image
- file
- textarea
