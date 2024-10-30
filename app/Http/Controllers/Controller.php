<?php

namespace App\Http\Controllers;

class Controller
{
    public function index()
    {
        return view('index');
    }

    public function show(string $stopId)
    {
        return view('stop', ['stopId' => $stopId]);
    }
}
