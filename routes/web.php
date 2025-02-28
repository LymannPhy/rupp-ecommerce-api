<?php

use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/', [SupplierController::class, 'showQRModal']);


// Route::get('/', function () {
//     return view('suppliers.qr_modal'); 
// });
Route::get('/', function () {
    return view('login'); 
});
