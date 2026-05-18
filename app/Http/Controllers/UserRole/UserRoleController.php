<?php

namespace App\Http\Controllers\UserRole;

use App\Http\Controllers\Controller;

class UserRoleController extends Controller
{
    public function index()
    {
        return inertia('vue/dashboard/user-role/UserRole');
    }
}
