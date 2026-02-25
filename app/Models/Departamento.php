<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    use HasFactory;

    protected $table = 'departamentos';

    protected $fillable = [
        'nombre',
       'ubicacion',
        'responsable_id',
       
    ];
    public function responsable()
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }
}
