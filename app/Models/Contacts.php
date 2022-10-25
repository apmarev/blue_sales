<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contacts extends Model {
    use HasFactory;

    protected $table = 'contacts';

    protected int $updated;
    protected string $name;
    protected string $phones;
    protected string $emails;
    protected int $vk_id;
    protected string $leads;

}
