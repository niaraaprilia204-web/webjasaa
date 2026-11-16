<?php

/**
 * Base Service Class
 * Decouples business logic, data processing, and validation from Controllers.
 */
abstract class BaseService
{
    protected function response($success = true, $message = '', $data = [])
    {
        return [
            'success' => $success,
            'message' => $message,
            'data'    => $data
        ];
    }
}
