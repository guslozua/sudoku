<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('sudoku.index');
});

Route::get('', function () {
    return view('sudoku.index');
});
