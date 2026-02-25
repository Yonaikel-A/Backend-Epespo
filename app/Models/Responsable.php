<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Responsable extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'nombre',
        'apellido',
        'correo',
        'cedula',
        'cargo',
        'activo',
        'fecha_inactivacion',
        'motivo_inactivacion',
    ];
}
