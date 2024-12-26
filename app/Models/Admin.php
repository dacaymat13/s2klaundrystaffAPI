<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable ;

    protected $primaryKey = 'Admin_ID';
    public $incrementing = true;
    // protected $table = 'admins';
    // protected $keyType = 'bigint';

    protected $fillable = [
        "Admin_lname",
        "Admin_fname",
        "Admin_mname",
        "Admin_image",
        "Birthdate",
        "Phone_no",
        "Address",
        "Role",
        "Email",
        "Password"
    ];
}
