<?php
/**
 * Custom Exception class for MySQLiLib operations.
 *
 * Provides a namespaced exception to distinguish database-layer errors.
 *
 * @package MySQLiLib
 * @author jonathanbak
 * @since 2017-02-01
 */

namespace MySQLiLib;

/**
 * Class Exception
 *
 * Used for throwing and catching MySQLiLib-specific database exceptions.
 */
class Exception extends \Exception
{
    // You can extend with custom properties or methods if needed in the future.
}