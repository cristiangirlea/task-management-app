<?php

namespace App\Http\Controllers;

class Dashboard extends WebBaseController
{
    public function dashboard()
    {
        $data = ['title' => 'Dashboard'];

        return $this->respondView('dashboard', $data, 'Welcome back!', 'success');
    }
}
