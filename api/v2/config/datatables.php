<?php

// Server-side pagination toggle per DataTable instance.
// Key format: '{portal}.{module}.{table_id}' => bool
// true  = server-side (Controller must call BaseModel::paginate() and return pagination key)
// false = client-side (default — Controller returns full result set)
return [
    // 'admin.inventory.products' => true,
];
