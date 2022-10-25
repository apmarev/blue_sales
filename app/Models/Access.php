<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model {
    use HasFactory;

    protected $table = 'access';

    protected string $name;
    protected string $access_token;
    protected string $refresh_token;
    protected int $expires_in;

}
