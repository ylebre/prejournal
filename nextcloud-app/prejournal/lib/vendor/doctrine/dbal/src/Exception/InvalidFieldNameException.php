<?php

namespace Doctrine\DBAM\Exception;

/**
 * Exception for an invalid specified field name in a statement detected in the driver.
 *
 * @psalm-immutable
 */
class InvalidFieldNameException extends ServerException
{
}