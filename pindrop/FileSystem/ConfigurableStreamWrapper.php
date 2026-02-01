<?php

declare(strict_types=1);

namespace Simp\Pindrop\FileSystem;

use Simp\StreamWrapper\Stream\StreamWrapper;

/**
 * Pin drop Public Stream Wrapper
 * 
 * Simple wrapper that extends StreamWrapper and sets the stream_name and base_path
 * from environment configuration.
 */
class ConfigurableStreamWrapper extends StreamWrapper
{
    protected string $stream_name;

    protected string $base_path;

    public function __construct()
    {
        // Set properties from environment
        $this->stream_name = str_replace('://', '', $_ENV['PUBLIC_STREAM'] ?? 'public');
        $this->base_path = $_ENV['PUBLIC_STREAM_DIR'] ?? $_ENV['ROOT'] . '/sites/default/files';

        parent::__construct();
    }
}
