<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Login
Route::post('user/login', 'Auth\LoginController@login');
Route::post('user/logout', 'Auth\LoginController@logout');

// Forgot Password
Route::post('/recover_password', 'UserController@sendPasswordMail');
Route::post('/create_password', 'UserController@createPassword');
Route::group(['middleware' => 'active'], function() {
    // Change Password, Update Profile, Logout and Change Password
Route::middleware('auth:api')->post('user/logout', 'Auth\LoginController@logout');
Route::middleware('auth:api')->post('user/change_password', 'Auth\ResetPasswordController@change_password');
Route::middleware('auth:api')->get('user/profile', 'UserController@getProfileDetail');
Route::middleware('auth:api')->post('user/update', 'UserController@updateProfile');




