<?php

use App\Http\Controllers\OfficeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//Tags
Route::get('/tags',  \App\Http\Controllers\TagController::class);


//Offices
Route::get('/offices',  [OfficeController::class,'index']);
Route::get('/office/{office}',  [OfficeController::class,'show']);
Route::post('/offices',  [OfficeController::class,'create'])->middleware(['auth:sanctum','verified']);
