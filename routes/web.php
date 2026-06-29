<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('main');
});
Route::get('/cEvent', function () {
    return view('createEvent');
});
Route::get('/players', function () {
    return view('players');
});
 