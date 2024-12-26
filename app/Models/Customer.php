<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasFactory, Notifiable, HasApiTokens ;

    protected $table = 'customers';
    protected $primaryKey = 'Cust_ID';
    protected $fillable = [
        'Cust_fname',
        'Cust_lname',
        'Cust_mname',
        'Cust_phoneno',
        'Cust_email',
        'Cust_address',
        'Cust_password',
    ];

}
