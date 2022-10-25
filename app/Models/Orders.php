<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model {
    use HasFactory;

    protected $table = 'orders';

    protected string $contact_name;
    protected string $contact_phone;
    protected string $contact_email;
    protected string $contact_next_date;
    protected int $vk_id;
    protected int $vk_group;
    protected int $status_type;
    protected string $tag;
    protected string $manager_full_name;
    protected string $date;
    protected string $tag_position_marking;
    protected string $tag_position_name;
    protected int $price;
    protected string $tags;
    protected bool $update;

}
