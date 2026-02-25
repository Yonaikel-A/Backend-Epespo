<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'codigo_anterior',
        'nombre',
        'descripcion',
        'categoria',
        'marca',
        'modelo',
        'numero_serie',
        'dimensiones',
        'color',
        'fecha_ingreso',
        'ubicacion_id',
        'estado',
        'motivo_baja',
        'fecha_baja',
        'es_donado', 
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_baja'    => 'datetime',
        'es_donado'     => 'boolean', 
    ];

    public function ubicacion()
    {
        return $this->belongsTo(Departamento::class, 'ubicacion_id');
    }
}
