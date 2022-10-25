<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accounts extends Model {
    use HasFactory;

    protected $table = 'accounts';

    protected string $login;
    protected string $password;
    protected int $client_send;

}
