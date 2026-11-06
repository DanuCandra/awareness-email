<?php

namespace App\Models;

use Database\Factories\TemplateCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_key'])]
class TemplateCategory extends Model
{
    /** @use HasFactory<TemplateCategoryFactory> */
    use HasFactory;

    public function templates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }
}
