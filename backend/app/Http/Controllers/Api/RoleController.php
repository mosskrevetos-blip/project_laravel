<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // Отдаём все роли. Доступ к этому методу будет только у авторизованных.
    public function index()
    {
        return Role::all();
    }
}