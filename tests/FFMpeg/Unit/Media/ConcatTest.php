<?php

namespace Tests\FFMpeg\Unit\Media;

use FFMpeg\Media\Concat;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ConcatTest extends AbstractMediaTestCase
{
    public function testGetSources()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $concat = new Concat([__FILE__, __FILE__], $driver, $ffprobe);
        $this->assertSame([__FILE__, __FILE__], $concat->getSources());
    }

    public function testFiltersReturnFilters()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $concat = new Concat([__FILE__, __FILE__], $driver, $ffprobe);
        $this->assertInstanceOf('FFMpeg\Filters\Concat\ConcatFilters', $concat->filters());
    }

    public function testAddFiltersAddsAFilter()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $filters = $this->getMockBuilder('FFMpeg\Filters\FiltersCollection')
            ->disableOriginalConstructor()
            ->getMock();

        $filter = $this->getMockBuilder('FFMpeg\Filters\Concat\ConcatFilterInterface')->getMock();

        $filters->expects($this->once())
            ->method('add')
            ->with($filter);

        $concat = new Concat([__FILE__, __FILE__], $driver, $ffprobe);
        $concat->setFiltersCollection($filters);
        $concat->addFilter($filter);
    }

    /**
     * @dataProvider provideSaveFromSameCodecsOptions
     */
    public function testSaveFromSameCodecs($streamCopy, $commands)
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $pathfile = '/target/destination';

        array_push($commands, $pathfile);

        $driver->expects($this->exactly(1))
            ->method('command')
            ->with($this->isType('array'), false, $this->anything())
            ->will($this->returnCallback(function ($commands, $errors, $listeners) {
            }));

        $concat = new Concat([__FILE__, 'concat-2.mp4'], $driver, $ffprobe);
        $concat->saveFromSameCodecs($pathfile, $streamCopy);

        $this->assertEquals('-f', $commands[0]);
        $this->assertEquals('concat', $commands[1]);
        $this->assertEquals('-safe', $commands[2]);
        $this->assertEquals('0', $commands[3]);
        $this->assertEquals('-i', $commands[4]);
        if (isset($commands[6]) && (0 == strcmp($commands[6], '-c'))) {
            $this->assertEquals('-c', $commands[6]);
            $this->assertEquals('copy', $commands[7]);
        }
    }

    public function provideSaveFromSameCodecsOptions()
    {
        $fs = (new TemporaryDirectory())->create();
        $tmpFile = $fs->path('ffmpeg-concat');
        touch($tmpFile);

        return [
            [
                true,
                [
                    '-f', 'concat',
                    '-safe', '0',
                    '-i', $tmpFile,
                    '-c', 'copy',
                ],
            ],
            [
                false,
                [
                    '-f', 'concat',
                    '-safe', '0',
                    '-i', $tmpFile,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideSaveFromDifferentCodecsOptions
     */
    public function testSaveFromDifferentCodecs($commands)
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();
        $format = $this->getFormatInterfaceMock();

        $pathfile = '/target/destination';

        array_push($commands, $pathfile);

        $configuration = $this->getMockBuilder('Alchemy\BinaryDriver\ConfigurationInterface')->getMock();

        $driver->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $driver->expects($this->once())
            ->method('command')
            ->with($commands);

        $concat = new Concat([__FILE__, 'concat-2.mp4'], $driver, $ffprobe);
        $this->assertSame($concat, $concat->saveFromDifferentCodecs($format, $pathfile));
    }

    public function provideSaveFromDifferentCodecsOptions()
    {
        return [
            [
                [
                    '-i', __FILE__,
                    '-i', 'concat-2.mp4',
                    '-filter_complex',
                    '[0:v:0] [0:a:0] [1:v:0] [1:a:0] concat=n=2:v=1:a=1 [v] [a]',
                    '-map', '[v]',
                    '-map', '[a]',
                ],
            ],
        ];
    }
}
