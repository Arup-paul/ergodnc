<?php

use App\Http\Controllers\HostReservationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImageController;
use App\Http\Controllers\UserReservationController;
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
Route::delete('/offices/{office}/images/{image:id}',  [OfficeImageController::class,'delete'])->middleware(['auth:sanctum','verified']);


//user reservation
Route::get('/reservations',[UserReservationController::class,'index'])->middleware(['auth:sanctum','verified']);


//Host reservation
//Route::get('/host/reservations',[HostReservationController::class,'index']);
