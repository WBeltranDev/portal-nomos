<?php

use Illuminate\Support\Facades\Route;

// Ruta raíz: Carga el formulario de Login
Route::get('/', function () {
    return view('login');
});

// Ruta interna: Carga el Dashboard unificado que ya aprobaste
Route::get('/dashboard', function () {
    return view('dashboard');
});