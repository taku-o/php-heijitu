<?php

declare(strict_types=1);

namespace Heijitu\Exception;

/**
 * プロバイダーのデータ取得・API 呼び出し失敗時の例外
 */
class ProviderException extends \RuntimeException implements HeijituException
{
}
