<?php

/*
 * This file is part of the colinodell/json5 package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ColinODell\Json5\Test\Functional;

use ColinODell\Json5\Json5Decoder;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testAssocFalseWithNoOptionsSet()
    {
        $result = Json5Decoder::decode('{"foo": true}', false);
        $this->assertInstanceOf('\stdClass', $result);
    }

    public function testAssocFalseWithAssocOption()
    {
        $result = Json5Decoder::decode('{"foo": true}', false, 512, JSON_OBJECT_AS_ARRAY);
        $this->assertInternalType('array', $result);
    }

    public function testBigIntWithNoOptionsSet()
    {
        $result = Json5Decoder::decode('12345678901234567890');
        $this->assertInternalType('float', $result);
    }

    public function testBigIntWithOptionSet()
    {
        $result = Json5Decoder::decode('12345678901234567890', false, 512, JSON_BIGINT_AS_STRING);
        $this->assertInternalType('string', $result);
    }
}
