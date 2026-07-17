<?php

namespace App\Foundation\Modules\Exceptions;

use RuntimeException;

class InvalidManifestException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  validation messages keyed by manifest field
     */
    public function __construct(
        public readonly string $modulePath,
        protected array $errors,
    ) {
        parent::__construct(sprintf(
            'Invalid module manifest at [%s]: %s',
            $modulePath,
            collect($errors)->flatten()->implode(' '),
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
