<?php

/*
 * This file is part of the colinodell/json5 package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * Based on the official JSON5 implementation for JavaScript (https://github.com/json5/json5)
 *  - (c) 2012-2016 Aseem Kishore and others (https://github.com/json5/json5/contributors)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ColinODell\Json5;

final class SyntaxError extends \JsonException
{
    public function __construct(
        string          $message,
        private int     $lineNumber,
        private int     $column,
        \Throwable|null $previous = null
    ) {
        $message = \sprintf('%s at line %d column %d of the JSON5 data', $message, $lineNumber, $column);

        parent::__construct($message, 0, $previous);
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getColumn(): int
    {
        return $this->column;
    }
}
