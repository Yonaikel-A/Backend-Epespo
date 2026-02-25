<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recepcion extends Model
{
    use HasFactory;

    protected $table = 'recepciones';

    protected $fillable = [
        'responsable_id',
        'area_id',
        'fecha_devolucion',
        'categoria',
        'acta_id',
        'activo',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_devolucion' => 'date',
        'activo'           => 'boolean',
    ];

    public function responsable()
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }

    public function area()
    {
        return $this->belongsTo(Departamento::class, 'area_id');
    }

 public function productos()
{
    return $this->belongsToMany(Producto::class, 'recepcion_producto')->withTimestamps();
}


    public function acta()
    {
        return $this->belongsTo(Acta::class, 'acta_id');
    }
}
