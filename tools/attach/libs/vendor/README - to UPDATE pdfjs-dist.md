# README to update pdfjs-dist

## Context

`pdfjs-dist` is available as javascript library via `yarn` but as pre-build package.
To use it as viewer in `<iframe>`, we have to download the official viewer package not availaable via `yarn`.

## Procedure

1. Go to official download page of pdfjs : https://mozilla.github.io/pdf.js/getting_started/#download
2. Download Prebuilt stable revision : (example : https://github.com/mozilla/pdf.js/releases/download/v2.12.313/pdfjs-2.12.313-dist.zip)
3. unzip to folder `tools/attach/libs/vendor/pdfjs-dist` replacing older folder content.