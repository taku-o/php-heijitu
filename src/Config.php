<?php

declare(strict_types=1);

namespace Heijitu;

use Heijitu\Exception\ConfigException;

final class Config
{
    /**
     * 設定ファイルを読み込み MonthDay[] を返す
     *
     * @return MonthDay[]
     * @throws ConfigException 読み込み・パース失敗時
     */
    public static function loadExcludedDates(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'yaml' || $extension === 'yml') {
            $data = self::parseYaml($path);
        } elseif ($extension === 'json') {
            $data = self::parseJson($path);
        } else {
            throw new ConfigException(
                sprintf('Unsupported config file extension: .%s', $extension)
            );
        }

        if (!isset($data['excluded_dates'])) {
            return [];
        }

        if (!is_array($data['excluded_dates'])) {
            throw new ConfigException(
                sprintf('excluded_dates must be an array in: %s', $path)
            );
        }

        $result = [];
        foreach ($data['excluded_dates'] as $entry) {
            $result[] = new MonthDay((int) $entry['month'], (int) $entry['day']);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     * @throws ConfigException
     */
    private static function parseYaml(string $path): array
    {
        if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            throw new ConfigException(
                'symfony/yaml is required to parse YAML files. Run: composer require symfony/yaml'
            );
        }

        try {
            /** @var mixed $data */
            $data = \Symfony\Component\Yaml\Yaml::parseFile($path);
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            throw new ConfigException(
                sprintf('Failed to parse YAML file: %s', $path),
                0,
                $e
            );
        }

        if (!is_array($data)) {
            throw new ConfigException(
                sprintf('Failed to parse YAML file: %s', $path)
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     * @throws ConfigException
     */
    private static function parseJson(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new ConfigException(
                sprintf('Failed to read file: %s', $path)
            );
        }

        /** @var mixed $data */
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new ConfigException(
                sprintf('Failed to parse JSON file: %s', $path)
            );
        }

        return $data;
    }
}
