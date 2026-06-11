<?php

declare(strict_types=1);

namespace Heijitu\Tests\Examples;

use PHPUnit\Framework\TestCase;

/**
 * examples/main.php の実行テスト
 *
 * Section 5（CaoCsv オンライン）がネットワーク接続を必要とするため
 * integration グループに分類する。
 *
 * @group integration
 */
final class MainPhpTest extends TestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../examples/main.php';

    // -------------------------------------------------------
    // 前提条件: スクリプトファイルが存在する
    // -------------------------------------------------------

    public function testScriptFileExists(): void
    {
        // Given: examples/main.php のパス
        // When: ファイルの存在を確認する
        // Then: ファイルが存在する
        $this->assertFileExists(self::SCRIPT_PATH);
    }

    // -------------------------------------------------------
    // 前提条件: PHP 構文が有効である
    // -------------------------------------------------------

    public function testScriptHasValidPhpSyntax(): void
    {
        // Given: examples/main.php のパス
        $path = realpath(self::SCRIPT_PATH);
        $this->assertNotFalse($path, 'examples/main.php が存在しません');

        // When: php -l で構文チェックする
        $output = [];
        $exitCode = 0;
        exec(sprintf('php -l %s 2>&1', escapeshellarg($path)), $output, $exitCode);

        // Then: exit code 0（構文エラーなし）
        $this->assertSame(0, $exitCode, "PHP 構文エラー:\n" . implode("\n", $output));
    }

    // -------------------------------------------------------
    // 正常系: スクリプトが exit code 0 で完走する（要件 1.7）
    // -------------------------------------------------------

    public function testScriptExitsWithZero(): void
    {
        // executeScript() 内で exit code 0 をアサートする
        $this->executeScript();
    }

    // -------------------------------------------------------
    // 正常系: Section 1（HolidayJp 全 API）のヘッダーが出力される（要件 1.1）
    // -------------------------------------------------------

    public function testOutputContainsHolidayJpSection(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: Section 1 のヘッダーが含まれる
        $this->assertStringContainsString('=== HolidayJp Provider ===', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 1 — isBusinessDay の結果が出力される（要件 1.1）
    // -------------------------------------------------------

    public function testOutputContainsIsBusinessDayResult(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: isBusinessDay の判定結果が含まれる
        $this->assertMatchesRegularExpression('/is business day:/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 1 — nextBusinessDay の結果が出力される（要件 1.1）
    // -------------------------------------------------------

    public function testOutputContainsNextBusinessDayResult(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: nextBusinessDay の結果が含まれる
        $this->assertMatchesRegularExpression('/Next business day after/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 1 — firstBusinessDayOfMonth の結果が出力される（要件 1.1）
    // -------------------------------------------------------

    public function testOutputContainsFirstBusinessDayOfMonthResult(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: firstBusinessDayOfMonth の結果が含まれる
        $this->assertMatchesRegularExpression('/First business day of/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 1 — firstBusinessDaysOfYear の結果が出力される（要件 1.1）
    // -------------------------------------------------------

    public function testOutputContainsFirstBusinessDaysOfYearResult(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: firstBusinessDaysOfYear の結果が含まれる
        $this->assertMatchesRegularExpression('/First business days of/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 1 — holidays の結果が出力される（要件 1.1）
    // -------------------------------------------------------

    public function testOutputContainsHolidaysResult(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: holidays の結果が含まれる
        $this->assertMatchesRegularExpression('/Holidays/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 2（除外日付コンストラクタ）のヘッダーが出力される（要件 1.2）
    // -------------------------------------------------------

    public function testOutputContainsExcludedDatesSection(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: Section 2 のヘッダーが含まれる
        $this->assertStringContainsString('=== Excluded Dates (constructor) ===', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 2 — 除外日付の isBusinessDay 対比出力（要件 1.2）
    // -------------------------------------------------------

    public function testOutputContainsExcludedDatesComparison(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: 除外ありと除外なしの対比出力が含まれる
        $this->assertMatchesRegularExpression('/with .+ excluded/', $stdout);
        $this->assertMatchesRegularExpression('/without exclusion/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 3（設定ファイル + array_merge）のヘッダーが出力される（要件 1.3）
    // -------------------------------------------------------

    public function testOutputContainsConfigFileSection(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: Section 3 のヘッダーが含まれる
        $this->assertStringContainsString('=== Config File ===', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 3 — Config から読み込んだ件数が出力される（要件 1.3）
    // -------------------------------------------------------

    public function testOutputContainsLoadedExcludedDatesCount(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: 読み込み件数の出力が含まれる
        $this->assertMatchesRegularExpression('/Loaded excluded dates from config: \d+ entries/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 4（CaoCsv ローカル）のヘッダーが出力される（要件 1.4）
    // -------------------------------------------------------

    public function testOutputContainsCaoCsvLocalSection(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: Section 4 のヘッダーが含まれる
        $this->assertStringContainsString('=== CaoCsv Provider (local) ===', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 4 — CaoCsv ローカルモードの判定結果（要件 1.4）
    // -------------------------------------------------------

    public function testOutputContainsCaoCsvLocalHolidayResult(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: CaoCsv ローカルの祝日判定結果が含まれる
        $this->assertMatchesRegularExpression('/is holiday \(CaoCsv\): true/', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 5（CaoCsv オンライン）のヘッダーが出力される（要件 1.5）
    // -------------------------------------------------------

    public function testOutputContainsCaoCsvOnlineSection(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: Section 5 のヘッダーが含まれる
        $this->assertStringContainsString('=== CaoCsv Provider (online) ===', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 6（extraExcluded）のヘッダーが出力される（要件 1.6）
    // -------------------------------------------------------

    public function testOutputContainsExtraExcludedSection(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: Section 6 のヘッダーが含まれる
        $this->assertStringContainsString('=== extraExcluded ===', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: Section 6 — extraExcluded の対比出力（要件 1.6）
    // -------------------------------------------------------

    public function testOutputContainsExtraExcludedComparison(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: extraExcluded ありとなしの対比出力が含まれる
        $this->assertMatchesRegularExpression('/no extra.*: true/i', $stdout);
        $this->assertMatchesRegularExpression('/excluded.*: false/i', $stdout);
    }

    // -------------------------------------------------------
    // 正常系: 全6セクションが出力に含まれる（全体構成の検証）
    // -------------------------------------------------------

    public function testOutputContainsAllSixSections(): void
    {
        // Given: スクリプトの実行結果
        $stdout = $this->executeScript();

        // Then: 全セクションのヘッダーが出力される
        $expectedHeaders = [
            '=== HolidayJp Provider ===',
            '=== Excluded Dates (constructor) ===',
            '=== Config File ===',
            '=== CaoCsv Provider (local) ===',
            '=== CaoCsv Provider (online) ===',
            '=== extraExcluded ===',
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $stdout, "セクションヘッダーが見つかりません: {$header}");
        }
    }

    /**
     * examples/main.php を実行して stdout を返す
     */
    private function executeScript(): string
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $path = realpath(self::SCRIPT_PATH);
        $this->assertNotFalse($path, 'examples/main.php が存在しません');

        $output = [];
        $exitCode = 0;
        exec(sprintf('php %s 2>&1', escapeshellarg($path)), $output, $exitCode);

        $this->assertSame(0, $exitCode, "スクリプト実行失敗 (exit code: {$exitCode}):\n" . implode("\n", $output));

        $cache = implode("\n", $output);
        return $cache;
    }
}
