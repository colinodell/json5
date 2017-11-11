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

use mikehaertl\shellcommand\Command;
use PHPUnit\Framework\TestCase;

class BinTest extends TestCase
{
    /**
     * Tests the behavior of not providing any JSON5 input
     */
    public function testNoArgsOrStdin()
    {
        $cmd = $this->createCommand();
        $cmd->execute();

        $this->assertEquals(1, $cmd->getExitCode());
        $this->assertEmpty($cmd->getOutput());

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $this->assertContains('Usage:', $cmd->getError());
        }
    }

    /**
     * Tests the -h flag
     */
    public function testHelpShortFlag()
    {
        $cmd = $this->createCommand();
        $cmd->addArg('-h');
        $cmd->execute();

        $this->assertEquals(0, $cmd->getExitCode());
        $this->assertContains('Usage:', $cmd->getOutput());
    }

    /**
     * Tests the --help option
     */
    public function testHelpOption()
    {
        $cmd = $this->createCommand();
        $cmd->addArg('--help');
        $cmd->execute();

        $this->assertEquals(0, $cmd->getExitCode());
        $this->assertContains('Usage:', $cmd->getOutput());
    }

    /**
     * Tests converting a file by filename
     */
    public function testFileArgument()
    {
        // Create a temporary file
        $filename = tempnam(sys_get_temp_dir(), 'json5');
        file_put_contents($filename, '[0,1,2,/*3*/]');

        $cmd = $this->createCommand();
        $cmd->addArg($filename);
        $cmd->execute();

        $this->assertEquals(0, $cmd->getExitCode());
        $expectedContents = '[0,1,2]';
        $this->assertEquals($expectedContents, $cmd->getOutput());

        unlink($filename);
    }

    /**
     * Tests converting JSON5 from STDIN
     */
    public function testStdin()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Test skipped: STDIN is not supported on Windows');
        }

        // Create a temporary file
        $filename = tempnam(sys_get_temp_dir(), 'json5');
        file_put_contents($filename, '[0,1,2,/*3*/]');

        $cmd = new Command(sprintf('cat %s | %s ', $filename, $this->getPathToJson5()));
        $cmd->execute();

        $this->assertEquals(0, $cmd->getExitCode());
        $expectedContents = '[0,1,2]';
        $this->assertEquals($expectedContents, $cmd->getOutput());

        unlink($filename);
    }

    /**
     * @return string
     */
    private function getPathToJson5()
    {
        return realpath(__DIR__ . '/../../bin/json5');
    }

    /**
     * @return Command
     */
    private function createCommand()
    {
        $path = $this->getPathToJson5();

        $command = new Command();
        if ($command->getIsWindows()) {
            $command->setCommand('php');
            $command->addArg($path);
        } else {
            $command->setCommand($path);
        }

        return $command;
    }
}
