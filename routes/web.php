<?php

use App\Http\Controllers\DocsController;
use App\Http\Middleware\NoIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// One route for the whole documentation directory: any .html file dropped into docs/
// is served straight away. The literal .html suffix keeps it clear of Scramble's
// /docs/api, and the slug pattern rules out path traversal.
Route::get('/docs/{page}.html', [DocsController::class, 'show'])
    ->where('page', '[A-Za-z0-9_-]+')
    ->middleware(NoIndex::class);
