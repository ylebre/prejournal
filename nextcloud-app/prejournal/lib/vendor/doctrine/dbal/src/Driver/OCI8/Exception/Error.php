<?php

declare(strict_types=1);

namespace Doctrine\DBAM\Driver\OCI8\Exception;

use Doctrine\DBAM\Driver\AbstractException;

use function assert;
use function oci_error;

/**
 * @internal
 *
 * @psalm-immutable
 */
final class Error extends AbstractException
{
    /**
     * @param resource $resource
     */
    public static function new($resource): self
    {
        $error = oci_error($resource);
        assert($error !== false);

        return new self($error['message'], null, $error['code']);
    }
}