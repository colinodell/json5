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

class MaximumDepthTest extends TestCase
{
    public function testMaximumDepth()
    {
        $this->setExpectedException('ColinODell\\Json5\\SyntaxError', 'Maximum stack depth exceeded');
        Json5Decoder::decode('[[1]]', false, 2);
    }

    public function testMaximumDepthNotHit()
    {
        $result = Json5Decoder::decode('[[1]]', false, 3);

        $this->assertSame(array(array(1)), $result);
    }
}
