<?php

return [
    'enabled' => (bool) env('GROM_LEGACY_ANALISE_SYNC_ENABLED', true),
    'analise_db_path' => env(
        'GROM_LEGACY_ANALISE_DB_PATH',
        dirname(base_path(), 2).DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'grom_database.sqlite3'
    ),
];
