# SVG sanitizer

Package: `enshrined/svg-sanitize` 1.0.0 (GPL-2.0-or-later).
Source: https://github.com/darylldoyle/svg-sanitizer/tree/f3300fcd1bbf67d205b52217c75d0f7d6a8c47ff

The upstream PHP source is included with its namespace scoped from `enshrined\svgSanitize` to `CorsivoToolkit\Vendor\SvgSanitize`. This prevents another plugin's older version from being loaded instead. No other source modifications.

Downloaded source archive SHA-256: `a8e1ce1772e797c6fb0d5adfa7d5ff46c973654389369a9ed5ec3a5b231ca5f3`.

The integration rejects DTD/entity declarations, limits both compressed input and expanded XML to 2 MiB, removes remote references, and writes the sanitized result before WordPress stores the upload. Tests cover unsafe URLs, namespaces, CSS, XML entities and compressed-size limits.
