<?php

namespace App\Models;

use Database\Factories\WorkspaceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['section', 'values', 'secret', 'revision', 'connection_checked_at', 'connection_successful'])]
class WorkspaceSetting extends Model
{
    /** @use HasFactory<WorkspaceSettingFactory> */
    use HasFactory;

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'next_send_at' => 'datetime',
            'secret' => 'encrypted',
            'connection_checked_at' => 'datetime',
            'connection_successful' => 'boolean',
        ];
    }
}
