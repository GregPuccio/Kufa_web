<?php

// Composer-compatible startup
require_once __DIR__ . '/vendor/autoload.php';

App\Bootstrap\Bootstrap::init();

\Base::instance()->run();
