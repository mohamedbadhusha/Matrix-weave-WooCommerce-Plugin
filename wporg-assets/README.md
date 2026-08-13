# WordPress.org directory assets

Drop images here with these **exact** filenames, then run `..\bin\svn-assets.ps1`.
The script checks real pixel dimensions before staging, because a banner that is
773px wide instead of 772px is silently ignored by the directory with no error.

These files are **not** part of the plugin. They are excluded from the release zip
and from `trunk/`, and are committed to `assets/` at the SVN repository root.

## Icon — highest priority

| File | Size | Notes |
|---|---|---|
| `icon-256x256.png` | 256x256 | The one that matters. Shown in search results and in the WP admin plugin browser. |
| `icon-128x128.png` | 128x128 | Fallback for non-retina. |

`icon.svg` is also accepted in place of the PNGs. A square mark reads better than
a wordmark — it is rendered small and often circular-cropped.

## Banner

| File | Size | Notes |
|---|---|---|
| `banner-772x250.png` | 772x250 | Header on the plugin page. |
| `banner-1544x500.png` | 1544x500 | Retina version of the same artwork. |

Keep text in the left third: the right side is overlaid by the plugin title on
some viewports. JPG is fine if the artwork is photographic.

## Screenshots

Name them `screenshot-1.png`, `screenshot-2.png`, ... in the order they should
appear. Any dimensions; roughly 16:9 and at least 1280px wide looks best.

**Captions come from `readme.txt`, not from the filenames.** The numbered list
under `== Screenshots ==` maps to the files by position, and the directory reads
that readme from the **stable tag**, not from trunk. So new screenshot captions
only appear after the next version release — the images alone can be committed
any time.
