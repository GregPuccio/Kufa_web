<?php

namespace App\Utility;

class ApiResponse
{
    private $code;
    private $data;

    /**
     * ApiResponse constructor.
     * @param int $status
     * @param mixed $data Data to return to client as payload
     */
    public function __construct($status, $data = null)
    {
        $this->code = $status;
        $this->data = $data;
    }

    /**
     * Convert this object to JSON representation
     * @return string
     */
    public function toJSON()
    {
        return \json_encode($this->data);
    }

    /**
     * Emit this object to client.
     * Also set HTTP header status code
     */
    public function emit()
    {
        \Base::instance()->status($this->code);
        if ($this->data !== false) {
            header("Content-type: application/json");
            echo $this->toJSON();
        }
    }
}
