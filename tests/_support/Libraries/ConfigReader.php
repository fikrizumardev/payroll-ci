<?php

declare(strict_types=1);

namespace Tests\Support\Libraries;

use Config\App;

/**
 * Membaca nilai Config\App langsung dari file, tanpa override dari .env.
 */
class ConfigReader extends App
{
    public function __construct()
    {
    }
}
