<?php

declare(strict_types=1);

namespace Heijitu\Exception;

/**
 * 設定ファイルの読み込み・パース失敗時の例外
 */
class ConfigException extends \RuntimeException implements HeijituException
{
}
