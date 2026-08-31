<?php

App\Models\FarmType::with('category')
    ->orderBy('category_id')
    ->get()
    ->each(fn($type) => print($type->category?->name . ' | ' . $type->name . PHP_EOL));
