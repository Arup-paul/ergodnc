<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//Tags
Route::get('/tags',  \App\Http\Controllers\TagController::class);


//Offices
Route::get('/offices',  [OfficeController::class,'index']);
Route::get('/office/{office}',  [OfficeController::class,'show']);
Route::post('/offices',  [OfficeController::class,'create'])->middleware(['auth:sanctum','verified']);
Route::put('/offices/{office}',  [OfficeController::class,'update'])->middleware(['auth:sanctum','verified']);
Route::delete('/offices/{office}',  [OfficeController::class,'delete'])->middleware(['auth:sanctum','verified']);


//office photo
Route::post('/offices/{office}/images',  [OfficeImageController::class,'store'])->middleware(['auth:sanctum','verified']);
Route::delete('/offices/{office}/images/{image}',  [OfficeImageController::class,'delete'])->middleware(['auth:sanctum','verified']);

