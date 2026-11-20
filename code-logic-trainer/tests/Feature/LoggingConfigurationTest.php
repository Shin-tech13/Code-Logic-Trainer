<?php

use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;

test('stderr is configured and can be selected as the default log channel', function () {
    config(['logging.default' => 'stderr']);
    $handler = Log::channel()->getLogger()->getHandlers()[0];

    expect(config('logging.default'))->toBe('stderr')
        ->and(config('logging.channels.stderr.driver'))->toBe('monolog')
        ->and(config('logging.channels.stderr.handler'))->toBe(StreamHandler::class)
        ->and(config('logging.channels.stderr.handler_with.stream'))->toBe('php://stderr')
        ->and($handler)->toBeInstanceOf(StreamHandler::class);
});
