<?php

declare(strict_types=1);

namespace App\Services\AI;

class VertexConfig
{
    public static function isEnabled(array $config): bool
    {
        $vertex = $config['vertex'] ?? [];

        return !empty($vertex['project_id'])
            && !empty($vertex['key_path'])
            && file_exists($vertex['key_path']);
    }
}
