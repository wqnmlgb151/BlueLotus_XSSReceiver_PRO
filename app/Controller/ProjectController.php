<?php
namespace App\Controller;

use App\Config\AppConfig;

final class ProjectController
{
    public static function handle(string $cmd)    {
        if ($cmd === 'list') {
            return AppConfig::projects();
        }
        return false;
    }
}
