<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResponsableController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\ActaController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\ProductoAsignacionActualController;

use App\Http\Controllers\Auth\PasswordResetController;
 Route::post('/register', [AuthController::class, 'register']); 
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');


Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/reset-password',  [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');


Route::group(['middleware' => ['jwt.verify']], function () {


    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);


    Route::middleware('role:admin,lector')->group(function () {
        

        Route::get('/productos/inactivos', [ProductoController::class, 'inactivos']);
        Route::get('/productos',           [ProductoController::class, 'index']);
        Route::get('/productos/{id}',      [ProductoController::class, 'show']);
        Route::get('/responsables',      [ResponsableController::class, 'index']);
        Route::get('/responsables/{id}', [ResponsableController::class, 'show']);
        Route::get('/departamentos',      [DepartamentoController::class, 'index']);
        Route::get('/departamentos/{id}', [DepartamentoController::class, 'show']);
        Route::get('/movimientos', [MovimientoController::class, 'index']);
        Route::get('/producto-asignaciones-actuales',    [ProductoAsignacionActualController::class, 'index']);
        Route::get('/responsables/{id}/bienes-actuales', [ProductoAsignacionActualController::class, 'bienesDeResponsable']);
        Route::get('/recepciones', [RecepcionController::class, 'index']);
        Route::get('/actas',                     [ActaController::class, 'index']);
        Route::get('/actas/{id}/descargar',      [ActaController::class, 'descargar']);
        Route::get('/actas/{id}/descargar-word', [ActaController::class, 'descargarWord']);
        Route::get('/actas/{id}/descargar-pdf',  [ActaController::class, 'descargarPdf']);
        Route::get('/asignaciones', [AsignacionController::class, 'index']);
         Route::get('/usuarios',                    [UsuarioController::class, 'index']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::post('/productos',        [ProductoController::class, 'store']);
        Route::put('/productos/{id}',    [ProductoController::class, 'update']);
        Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
        Route::post('/productos/exportar-word', [ProductoController::class, 'exportarWordSeleccionados']);
        Route::post('/responsables',     [ResponsableController::class, 'store']);
        Route::put('/responsables/{id}', [ResponsableController::class, 'update']);
        // Route::delete('/responsables/{id}', [ResponsableController::class, 'destroy']); // si lo vuelves a activar
        Route::post('/responsables/exportar-word', [ResponsableController::class, 'exportarWordSeleccionados']);
        Route::post('/departamentos',        [DepartamentoController::class, 'store']);
        Route::put('/departamentos/{id}',    [DepartamentoController::class, 'update']);
        Route::delete('/departamentos/{id}', [DepartamentoController::class, 'destroy']);
       
        Route::post('/usuarios',                   [UsuarioController::class, 'store']);
        Route::put('/usuarios/{id}',               [UsuarioController::class, 'update']);
        Route::delete('/usuarios/{id}',            [UsuarioController::class, 'destroy']);
        Route::patch('/usuarios/{id}/toggle-activo', [UsuarioController::class, 'toggleActivo']);
        Route::post('/asignaciones',        [AsignacionController::class, 'store']);
        Route::put('/asignaciones/{id}',    [AsignacionController::class, 'update']);
        Route::delete('/asignaciones/{id}', [AsignacionController::class, 'destroy']);
        Route::post('/recepciones',        [RecepcionController::class, 'store']);
        Route::put('/recepciones/{id}',    [RecepcionController::class, 'update']);
        Route::delete('/recepciones/{id}', [RecepcionController::class, 'destroy']);
        Route::post('/actas/generar',           [ActaController::class, 'generarDesdeAsignacion']);
        Route::post('/actas/generar-recepcion', [ActaController::class, 'generarDesdeRecepcion']);
        Route::post('/actas/{acta}/subir-pdf',  [ActaController::class, 'subirPdfRecepcion']);
    });
});
