<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/run-link', function () {
    \Illuminate\Support\Facades\Artisan::call('storage:link');
    return 'Storage link created successfully!';
});
