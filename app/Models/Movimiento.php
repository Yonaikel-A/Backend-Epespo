<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $table = 'movimientos';

  protected $fillable = [
    'accion',
    'descripcion',
    'usuario_id',
    'producto_id',
    'asignacion_id',
    'acta_id',
    'fecha',
];


    protected $casts = [
        'fecha' => 'datetime',
    ];

    public $timestamps = false;

    public function usuarioRel()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
