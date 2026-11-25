<?php

return [
    'testing' => [
        // Halaman React berada di frontend/src/pages (repo dipisah backend/frontend).
        'ensure_pages_exist' => true,
        'page_paths' => [base_path('../frontend/src/pages')],
        'page_extensions' => ['tsx'],
    ],
];
