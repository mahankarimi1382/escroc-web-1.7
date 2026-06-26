<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SetupPage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function sections(){
        return $this->hasMany(SetupPageHasSection::class,'setup_page_id')->orderBy('position', 'asc');
    }
}
