<?php

declare(strict_types=1);

namespace Heijitu\Exception;

/**
 * php-heijitu が投げる例外の共通マーカーインターフェース。
 * `catch (HeijituException $e)` で全例外を一括捕捉できる。
 */
interface HeijituException
{
}
