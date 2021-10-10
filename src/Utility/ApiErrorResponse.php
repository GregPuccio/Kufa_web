<?php
/**
 *
 * User: richardgoldstein
 * Date: 11/20/18
 * Time: 5:41 AM
 */

namespace App\Utility;


class ApiErrorResponse extends ApiResponse
{
    public function __construct($status, $error = '', $error_code = 0)
    {
        parent::__construct(
            $status,
            [
                'error' => $error,
                'error_code' => $error_code
            ]
        );
    }

}
