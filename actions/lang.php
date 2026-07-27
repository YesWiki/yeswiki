<?php

// {{lang="xx"}} markers split a page body into per-language sections; the
// show/iframe handlers and {{include}} strip them before rendering (see
// src/lang.functions.php, formerly tools/lang). This deliberately-empty action
// keeps a marker that still reaches the formatter (revisions, exports, a page
// with no matching section) from rendering an "unknown action" error.
