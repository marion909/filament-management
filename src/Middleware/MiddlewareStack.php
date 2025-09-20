<?php

declare(strict_types=1);

namespace Filament\Middleware;

/**
 * Middleware Stack
 * 
 * Manages and executes a collection of middleware in sequence.
 */
class MiddlewareStack
{
    private array $middleware = [];
    
    /**
     * Add middleware to the stack
     */
    public function add($middleware): void
    {
        $this->middleware[] = $middleware;
    }
    
    /**
     * Execute all middleware in the stack
     */
    public function execute(array $request): void
    {
        foreach ($this->middleware as $middleware) {
            if (method_exists($middleware, 'handle')) {
                $middleware->handle($request);
            }
        }
    }
}