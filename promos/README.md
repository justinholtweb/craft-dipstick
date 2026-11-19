# Plugin Store promo images

Marketing images for the Dipstick listing on the Craft Plugin Store.

```bash
./build.sh          # all slides
./build.sh "2 4"    # just slides 2 and 4
```

Output: `out/dipstick-promo-N.jpg`, 1920×1080 (rendered at 2× in headless Chrome, downsampled).
**JPEG, never PNG.** `fonts.css` is generated and gitignored. The cover and closing slide carry the Craft 6
beta caution, and every footer says "Craft CMS 6 (beta)". The icon and watermark come from `resources/icon.svg` /
`resources/icon-mask.svg` (traced from `src/icon.jpg`); rebuild if the icon changes.

| # | Slide |
| --- | --- |
| 1 | Cover — free, Craft 6 beta caution |
| 2 | Static scan |
| 3 | Runtime probe |
| 4 | Report and CI |
| 5 | Closing — summary, beta caution |
