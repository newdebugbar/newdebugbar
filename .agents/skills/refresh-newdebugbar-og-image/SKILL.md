---
name: refresh-newdebugbar-og-image
description: Refresh the New Debug Bar website's Open Graph (OG) social-sharing image after product UI changes. Capture the current inspector and export the existing approved composition without redesigning its copy, layout, or lighting.
---

# Refresh the New Debug Bar OG Image

A refresh updates the real product evidence, not the design. Use the current OG template and image as the approved baseline. Preserve the copy, text and screenshot positions, perspective, strong description glow, and soft screenshot halo unless the user asks to change them. Do not restore the bright outline around the screenshot.

## Local inputs

Inspect current Git state and project instructions in each repository touched. These are the expected locations; verify them rather than assuming the checkout or running assets are current.

| Purpose | Location |
| --- | --- |
| Package | `/Users/benjamin/Sites/new-debug-bar` |
| Benchmark | `/Users/benjamin/newdebugbar/benchmark` |
| Populated request | `http://newdebugbar-benchmark.test/trips/kyoto-autumn` |
| Website | `/Users/benjamin/newdebugbar/website` |
| Local-only OG preview | `http://newdebugbar.test/__newdebugbar/social-preview` |

Within the website:

- Composition: `resources/views/social/og-image.blade.php`.
- Final image: `resources/images/social/newdebugbar-og.png`.
- Current product source: `resources/images/screenshots/request-inspector-desktop-dark.png`. Resolve the actual source from `[data-request-inspector-image]` and the template each run.
- Canvas: `[data-social-preview-canvas]`; its data attributes specify the intended **1200 × 630** CSS-pixel output.
- Social metadata: `resources/views/components/layouts/site.blade.php`. Both Open Graph and Twitter use the final image through Vite; `vite.config.js` includes that asset. Normally neither file needs changing.

Within the package, `README.md` displays `.github/readme/newdebugbar-og.png`, an identical copy of the website's final OG image. Keep this consumer in sync during refreshes.

## Get current product evidence

When the bar has changed, take a fresh capture; re-exporting an old screenshot is not a refresh. Confirm the benchmark's `vendor/newdebugbar/newdebugbar` resolves to the intended package checkout and that the rendered frontend reflects the changes. Do not alter Composer linkage or unrelated dirty files.

Use the built-in browser first. Inspect the existing source image, then capture the real populated **Requests inspector in dark mode**, retaining its story and useful Favorites. Do not switch to Queries, another tool, another theme, or invented data merely to improve the picture.

Capture the complete product surface without the host page or scrim. Temporarily hide host siblings and make only the host backgrounds transparent; preserve product styling. A 1600 × 1000 desktop viewport at DPR 1 has been used for the current 1536 × 780 source. Confirm the actual surface bounds each time. Keep transparent outer corners, sharp UI text, and the real aspect ratio; never stretch the product to fit. Save and inspect the capture in a temporary location first.

**The current source is shared by the homepage and documentation.** If it is already current, reuse it. If it needs replacement and only the OG image was requested, resolve that scope before overwriting it: get approval to refresh the shared source, or isolate the new capture as an OG-only source under `resources/images/screenshots` and update only the OG template reference. Confirm that Vite can resolve any new source path; adding a file does not guarantee a manifest entry. Do not silently refresh all desktop/mobile or light/dark website assets. Preserve intrinsic dimensions in any consuming markup you do change.

## Render and export

Use browser rendering, not image generation or raster retouching. The approved Blade composition remains the source of truth; do not duplicate it as a separately maintained design.

1. Open the local OG preview. Wait for its fonts and source image to finish loading. Make sure it displays the fresh capture, not an older fingerprinted Vite asset.
2. For an image-only refresh, temporary browser substitution of the captured image's data URL can avoid a build. If a preview-only style override is needed, copy the exact values from the template. Keep persisted source changes consistent with the exported render, and reload afterward to remove temporary overrides. Never describe this as verification of compiled production assets.
3. Capture **only the canvas bounds** as a lossless PNG. Prefer an exact browser clip. With the browser's documented CDP capability, `Page.captureScreenshot` supports `format: "png"` and `clip: {x, y, width, height, scale: 1}`; derive the bounds from the canvas. The exported pixel dimensions may be multiplied by DPR.
4. Keep the master outside the repository. Downsample once to exactly **1200 × 630 RGB PNG**, without stretching or upscaling. For a master already clipped to the canvas's aspect ratio:

   ```sh
   ffmpeg -hide_banner -loglevel error -i /absolute/path/master.png \
     -vf 'scale=1200:630:flags=lanczos,format=rgb24' \
     -frames:v 1 /absolute/path/newdebugbar-og.png
   ```

5. If the capture includes the surrounding viewport, crop using the measured canvas origin and DPR before resizing. Do not center-crop: a prior centered export introduced black bars on the right and bottom and shifted the text. Exact canvas clipping avoids this failure.

## Finish within scope

- Visually inspect the final PNG at 1200 × 630 and a 300-pixel-wide feed preview. Confirm the original positioning, crisp glowing description, softly separated 3D screenshot, and absence of host remnants, black bands, or accidental edge crops.
- Replace the tracked final PNG only after inspection, then copy it unchanged to the package's `.github/readme/newdebugbar-og.png`. Keep the README's single OG image; do not restore the old standalone inspector screenshot. Keep only the needed image and source/template changes; do not add temporary captures or progress notes to either repository.
- For routine OG refreshes, **do not run application tests, formatters, or builds**. If current package assets, a new source path, or stale preview CSS makes a build necessary, explain the concrete dependency and ask before running it; do not substitute stale evidence.
- Commit the refresh locally with its matching source changes. Do not push or deploy until the user approves that refresh.
- Show the final image and briefly state what was refreshed, any shared consumers affected, and whether builds were skipped. Distinguish the local result from anything published.
