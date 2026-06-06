<?php

declare(strict_types=1);

namespace Heijitu\Tests;

use Heijitu\Config;
use Heijitu\Exception\ConfigException;
use Heijitu\MonthDay;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private function yamlConfigPath(): string
    {
        return __DIR__ . '/testdata/config.yaml';
    }

    private function jsonConfigPath(): string
    {
        return __DIR__ . '/testdata/config.json';
    }

    /** @return array<string, array<string>> */
    public function configFileProvider(): array
    {
        return [
            'yaml' => [$this->yamlConfigPath()],
            'json' => [$this->jsonConfigPath()],
        ];
    }

    /** @dataProvider configFileProvider */
    public function testLoadExcludedDates(string $path): void
    {
        $result = Config::loadExcludedDates($path);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(MonthDay::class, $result);
    }

    /** @dataProvider configFileProvider */
    public function testLoadExcludedDatesReturnsCorrectValues(string $path): void
    {
        $result = Config::loadExcludedDates($path);
        $this->assertSame(8, $result[0]->getMonth());
        $this->assertSame(15, $result[0]->getDay());
        $this->assertSame(12, $result[1]->getMonth());
        $this->assertSame(29, $result[1]->getDay());
    }

    public function testLoadExcludedDatesFromYmlExtension(): void
    {
        $ymlPath = __DIR__ . '/testdata/config_tmp.yml';
        copy($this->yamlConfigPath(), $ymlPath);

        try {
            $result = Config::loadExcludedDates($ymlPath);
            $this->assertCount(2, $result);
            $this->assertContainsOnlyInstancesOf(MonthDay::class, $result);
        } finally {
            unlink($ymlPath);
        }
    }

    public function testThrowsConfigExceptionForUnsupportedExtension(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/config.txt');
    }

    public function testThrowsConfigExceptionForXmlExtension(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/config.xml');
    }

    public function testThrowsConfigExceptionForNonExistentYamlFile(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/non_existent.yaml');
    }

    public function testThrowsConfigExceptionForNonExistentJsonFile(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/non_existent.json');
    }

    public function testThrowsConfigExceptionForInvalidJsonContent(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/config_invalid.json');
    }

    public function testThrowsConfigExceptionForInvalidYamlContent(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/config_invalid.yaml');
    }

    public function testThrowsConfigExceptionWhenExcludedDatesIsScalar(): void
    {
        $this->expectException(ConfigException::class);
        Config::loadExcludedDates(__DIR__ . '/testdata/config_scalar_dates.yaml');
    }

    public function testReturnsEmptyArrayWhenExcludedDatesKeyMissingInYaml(): void
    {
        $result = Config::loadExcludedDates(__DIR__ . '/testdata/config_empty.yaml');
        $this->assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenExcludedDatesKeyMissingInJson(): void
    {
        $result = Config::loadExcludedDates(__DIR__ . '/testdata/config_empty.json');
        $this->assertSame([], $result);
    }

    public function testConfigExceptionIsCaughtAsHeijituException(): void
    {
        $caught = false;
        try {
            Config::loadExcludedDates(__DIR__ . '/testdata/non_existent.yaml');
        } catch (\Heijitu\Exception\HeijituException $e) {
            $caught = true;
        }
        $this->assertTrue($caught);
    }
}
