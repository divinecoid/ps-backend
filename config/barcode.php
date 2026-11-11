<?php

return [
    // How long a pre-generated barcode PDF is kept (and trusted) before it is
    // deleted by `barcode:prune` and regenerated on the next print.
    'retention_days' => (int) env('BARCODE_PDF_RETENTION_DAYS', 30),
];
