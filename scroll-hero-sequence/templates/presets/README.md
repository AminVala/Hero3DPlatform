# Template Presets

JSON files in this directory are auto-discovered by `TemplateRegistry`.

## Schema

Each preset must include:

- `slug` — unique identifier (kebab-case)
- `name` — display name in admin
- `version` — semver
- `master_frame_index` — usually `70`
- `total_frames_desktop` — usually `120`
- `total_frames_mobile` — usually `60`
- `prompt_start` — AI prompt for frame 001 (Pro)
- `lock_zone` — `{ start_frame, end_frame }`
- `scenes` — array of 4 scenes with beats
- `overlay_steps` — HTML overlay timeline (frame_trigger, element_type, content)
- `scroll_config` — pin duration, reduced motion behavior

## Current Presets

| Slug | Name | Version |
|------|------|---------|
| `shahre-honar` | Shahre Honar Homepage Hero | 1.0.0 |

## Reference

See `shahre-honar.json` and `shahre-honar-storyboard.png` for the complete production sheet.
