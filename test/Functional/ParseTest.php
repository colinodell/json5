<?php

namespace ColinODell\Json5\Test\Functional;

use ColinODell\Json5\Json5Decoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class ParseTest extends TestCase
{
    /**
     * @param string $json
     *
     * @dataProvider dataForTestJsonParsing
     */
    public function testJsonParsing($json)
    {
        $this->assertEquals(json_decode($json), Json5Decoder::decode($json));
        $this->assertSame(json_decode($json, true), Json5Decoder::decode($json, true));
    }

    /**
     * @return array
     */
    public function dataForTestJsonParsing()
    {
        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../../vendor/json5/tests/*')->name('*.json');

        $tests = array();
        foreach ($finder as $file) {
            $tests[] = array(file_get_contents($file));
        }

        return $tests;
    }
}
