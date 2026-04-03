# What Will You Build? — Workflow

A 5-step user research survey bundled with XPressUI WordPress Bridge PRO.

## Purpose

This workflow collects structured feedback from your visitors about their use cases and challenges — while demonstrating the plugin's multi-step, file upload, and choice field capabilities in a real context.

## Steps

| Step | Purpose | Fields shown |
|------|---------|--------------|
| 1 — Profile | Segment the respondent | Radio buttons |
| 2 — Challenges | Understand pain points | Checkboxes, radio buttons |
| 3 — Use case | Collect free-form descriptions + attachments | Textarea, file upload |
| 4 — Priorities | Rank what matters most | Checkboxes (max 3), radio buttons |
| 5 — Contact | Capture email and opt-ins | Email, text, checkboxes |

## Usage

Embed the form on any page or post with the shortcode:

```
[xpressui id="what-will-you-build"]
```

Or use the helper function from `shortcode-example.php`:

```php
echo xpressui_render_what_will_you_build();
```

## Data collected

Submissions are stored in **XPressUI > Submissions** in wp-admin. Each entry includes:

- Respondent profile and site count
- Selected challenges and current tooling
- Free-text use case description
- Optional reference file attachment
- Top-3 priorities and purchase timeline
- Email, name, and opt-in preferences

## Requirements

- XPressUI Bridge (free) — v1.0.26+
- XPressUI Bridge PRO — v1.0.29+
