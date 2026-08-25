# Scroll Hero Sequence — Data Model & Scrollsequence Parity

Version: 0.3.0
Status: living document — updated whenever the config schema changes.

This document records the canonical data model of the plugin and maps each
field to its Scrollsequence equivalent, so the two stay conceptually aligned.
It is the source of truth referenced by the StoryBoard Live architecture
contract ("one canonical Story Definition; the runtime manifest is a
serialized representation, not a competing source of truth").

## 1. Where the data lives

- Persisted per hero as post meta `_shs_config` (see `Domain\HeroConfig`).
- Frame sets live in `_shs_frames_desktop` / `_shs_frames_mobile`.
- The REST/preview serializer (`Admin\REST\HeroController::serialize_hero`)
  turns the stored config into the runtime payload consumed by
  `assets/public/scroll-engine.js`.

## 2. Canonical tree

```text
HeroConfig (_shs_config)
├─ template_slug
├─ master_frame_index
├─ total_frames_desktop / total_frames_mobile
├─ master_image_id
├─ prompt_start
├─ lock_zone { start_frame, end_frame, source_attachment_id }
├─ scroll_config { pin_duration, easing, mobile_disable, reduced_motion }
├─ overlay_steps[]        ← legacy semantic overlay layer (server-rendered)
└─ scenes[]
    ├─ index, frame_start, frame_end, label
    ├─ generation_mode        (ai | manual | locked)   ← storyboard heritage
    ├─ beats[]                (keyframe storyboard)     ← storyboard heritage
    ├─ fixed_content          ← HTML (WYSIWYG)          [Scrollsequence parity]
    ├─ image_sequence[]       ← attachment IDs, frame order (0-indexed/scene)
    ├─ content_animations[]                              [Scrollsequence parity]
    │   ├─ selector           (#id | .class)
    │   ├─ start / end        (image-frame numbers)
    │   ├─ from[]  { type, value, duration }
    │   └─ to[]    { type, value, duration }
    └─ settings                                          [Scrollsequence parity]
        ├─ position           (sticky | absolute | static)
        ├─ start_trigger      (sooner | default)
        ├─ end_trigger        (default | later)
        ├─ scroll_delay       (0.0 – 3.5 s)
        ├─ image_width        (content | full)
        ├─ image_opacity      (0.0 – 1.0)
        ├─ custom_css         (scoped, sanitized)
        ├─ portrait  { scale (fit|fill), h_align 0–100, v_align 0–100 }
        └─ landscape { scale (fit|fill), h_align 0–100, v_align 0–100 }
```

## 3. Scrollsequence → plugin field mapping

| Scrollsequence concept        | Plugin field                                   |
|-------------------------------|------------------------------------------------|
| Scene                         | `scenes[]`                                     |
| Fixed Content (WYSIWYG)       | `scene.fixed_content`                          |
| Image Sequence                | `scene.image_sequence[]`                       |
| Content Animation → Selector  | `content_animations[].selector`                |
| Content Animation → Start/End | `content_animations[].start` / `.end`          |
| From Animation list           | `content_animations[].from[]`                  |
| To Animation list             | `content_animations[].to[]`                    |
| Animation type                | tween `type`: fade / move_vertical / move_horizontal / scale |
| Duration (in frames)          | tween `duration`                               |
| Position (Sticky/Abs/Static)  | `settings.position`                            |
| Start/End Trigger             | `settings.start_trigger` / `.end_trigger`      |
| Scroll Delay                  | `settings.scroll_delay`                        |
| Image Width                   | `settings.image_width`                         |
| Image Opacity                 | `settings.image_opacity`                       |
| Custom CSS                    | `settings.custom_css`                          |
| Image Scale & Alignment       | `settings.portrait` / `settings.landscape`     |
| Shortcode `[scrollsequence]`  | Gutenberg block `scroll-hero-sequence/hero-sequence` (heroId attr) |

### Frame-based animation semantics (parity note)

Scrollsequence animates by **image-frame number**, not time. As the user
scrolls, a frame number rises and falls; an animation is active while the
frame is within `[start, end]`. `from` tweens are anchored to the start frame
(they resolve toward the neutral state as the frame advances past `start` by
`duration` frames); `to` tweens are anchored to the end frame (they resolve as
the frame approaches `end`). Outside `[start, end]` the target is hidden
(opacity 0). This is implemented in `updateContentAnimations()` in
`scroll-engine.js`.

Supported tween types and their runtime effect:

- `fade` — `value` is the starting opacity (0–1), interpolated toward 1.
- `move_vertical` — percent-based `translateY` (`value` in %).
- `move_horizontal` — percent-based `translateX` (`value` in %).
- `scale` — `value` is the starting scale, interpolated toward 1.

## 4. Template presets

Presets live in `templates/presets/*.json` and are loaded by
`Templates\TemplateRegistry`. Current presets:

| Slug              | Archetype (real Scrollsequence site)          |
|-------------------|-----------------------------------------------|
| `shahre-honar`    | Keyframe storyboard hero + semantic overlays  |
| `product-showcase`| QuarkBaby / SeaDronePro — assemble on scroll  |
| `hero-zoomout`    | Roxanalytics — zoom-out to brand logo         |
| `model-rotate-3d` | WildVikings / DJI — pre-rendered turntable    |

Each new preset uses the Scrollsequence-parity fields (`fixed_content`,
`content_animations`, `settings`) rather than the legacy `overlay_steps`
layer, and declares `red_lines` consistent with the StoryBoard Live contract
(frame is visual, HTML overlays only, scroll-driven not time, no scroll
hijack, pre-rendered not real-time 3D where applicable).

## 5. Security boundary

All content-animation and settings input is untrusted until validated
(`Admin\HeroAdmin::sanitize_content_animations`, `sanitize_scene_settings`,
`sanitize_scoped_css`):

- Selectors must match `^[#.][A-Za-z0-9_-]+$`; anything else is dropped.
- Tween `type` is whitelisted; `value` is cast to float; `duration` is a
  non-negative int.
- `custom_css` is stripped of tags, `@import`, `expression()`, `javascript:`
  and `behavior:`.
- Enums (position, triggers, width, scale) fall back to safe defaults.
- `fixed_content` passes through `wp_kses_post`.

## 6. Progressive enhancement / accessibility

- No-JS and `.shs-hero--static` (reduced motion / low-power / mobile-disabled)
  restore full opacity and remove transforms so content stays readable.
- Native page scrolling remains authoritative — the engine is scroll-progress
  driven and never calls `preventDefault()` (no scroll hijacking).

## 7. Open items for the next milestone

- Admin editor UI for `content_animations` and `settings` (currently the
  storage, sanitization, serializer and runtime exist; the meta-box editor
  surfaces frame sets + overlays but not yet the per-animation editor).
- Multi-scene frame-range mapping in the runtime (current engine treats the
  hero as a single progressive sequence; multi-scene `frame_start/frame_end`
  handoff is modeled in data but not yet segmented at runtime).
- These are tracked as enhancements, not defects, and must pass the relevant
  StoryBoard Live release gates before being marked done.
