<?php

namespace App\Utility;
/**
 * Flash - A Flash Messages Plugin for the PHP Fat-Free Framework
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * Copyright (c) 2015 ~ ikkez
 * Christian Knuth <ikkez0n3@gmail.com>
 *
 * @version 0.2.0
 * @date: 21.01.2015
 *
 * Modified by Rich Goldstein, MD for Cancer Expert Now Inc.
 **/
class Flash extends \Prefab
{
    /** @var \Base */
    protected $f3;
    /** @var array */
    protected $msg;
    /** @var array */
    protected $key;

    /**
     * Flash constructor.
     *
     * @param string $key The key into $_SESSION for flash messages.
     */
    public function __construct($key = 'flash')
    {
        $this->f3 = \Base::instance();
        $this->msg = &$this->f3->ref('SESSION.' . $key . '.msg');
        $this->key = &$this->f3->ref('SESSION.' . $key . '.key');
    }

    /**
     * Add a message to the list of flash messages.
     * Note that when debugging is enabled, this adds a stack trace
     *
     * @param string $text   The text to display
     * @param string $status Status - a class added to the message upon display
     * @param bool $toast
     */
    public function addMessage($text, $status = 'is-success', $toast = false)
    {
        $this->msg[] = array('text' => $text, 'status' => $status, 'toast' => $toast);
    }

    /**
     * Add the exception's message as a flash message.
     *
     * @param Exception $u
     * @param bool $toast
     */
    public function addException(Exception $u, $toast = false)
    {
        $code = 'is-danger';

        $this->addMessage($u->getMessage(), $code, $toast);
    }

    /**
     * Return the array of messages and clear the message list
     *
     * @return array
     */
    public function getMessages()
    {
        $out = $this->msg;
        $this->clearMessages();
        return $out;
    }

    /**
     * Clear the message list
     */
    public function clearMessages()
    {
        $this->msg = array();
    }

    /**
     * @return bool Are there outstanding flash messages?
     */
    public function hasMessages()
    {
        return !empty($this->msg);
    }

    /**
     * Store a value in the sessions flash storage
     *
     * @param string $key The key under which to store the value
     * @param mixed $val The value to store
     */
    public function setKey($key, $val = true)
    {
        $this->key[$key] = $val;
    }

    /**
     * Get a key's from flash storage and clear the stored value
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getKey($key)
    {
        $out = null;
        if ($this->key && array_key_exists($key, $this->key)) {
            $out = $this->key[$key];
            unset($this->key[$key]);
        }
        return $out;
    }

    /**
     * Does the existing key exost in Flash Storage?
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasKey($key)
    {
        return ($this->key && array_key_exists($key, $this->key));
    }
}
