<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', function () {
    return view('Auth\Login');
})->middleware('auth');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/', 'HomeController@index')->name('index');
    Route::get('/home', 'HomeController@index')->name('home');

    Route::get('/buildings', 'BuildingController@index');
    Route::post('/buildings', 'BuildingController@store');
    Route::get('/buildings/create', 'BuildingController@create');
    Route::get('/buildings/{building}', 'BuildingController@show');
});
