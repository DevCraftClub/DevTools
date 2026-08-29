<?php

declare(strict_types=1);

namespace Devcraft\Exceptions;

use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * PSR-6 invalid cache key / argument.
 */
final class InvalidArgument extends \InvalidArgumentException implements PsrInvalidArgumentException {}
