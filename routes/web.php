<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// This is an API-only app with no web login form. This named route only
// exists so Laravel's default auth middleware can resolve route('login')
// for unauthenticated requests that don't ask for JSON (e.g. a browser);
// real API clients (Accept: application/json) get a 401 JSON response instead.
Route::get('/login', function () {
    return response()->json(['error' => 'Unauthenticated'], 401);
})->name('login');
