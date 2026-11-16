<?php

class NotFound extends Controller
{
    public function index()
    {
        $data = [
            'title' => '404 Halaman Tidak Ditemukan - TKJ Service Hub'
        ];

        http_response_code(404);
        $this->view('layouts/header', $data);
        $this->view('errors/404', $data);
        $this->view('layouts/footer', $data);
    }
}
