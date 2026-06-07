# Implementation Plan

- [ ] 1. フィクスチャ・設定ファイルの準備

- [ ] 1.1 Shift_JIS テストフィクスチャと `.gitattributes` の作成
  - `tests/Providers/CaoCsv/testdata/` ディレクトリを作成する
  - `syukujitsu_test.csv` を Shift_JIS エンコードで作成する（ヘッダー行 + 既知祝日5〜10件の最小データ）
  - `.gitattributes` を新規作成または更新し、`tests/Providers/CaoCsv/testdata/syukujitsu_test.csv binary` を追記する
  - Observable: フィクスチャが Shift_JIS バイト列を保持しており、`.gitattributes` の `binary` 属性設定で git による文字コード変換が抑制されている
  - _Requirements: 4.1_

- [ ] 1.2 `phpunit.xml` への integration グループ除外設定
  - `phpunit.xml` に `<groups><exclude><group>integration</group></exclude></groups>` を追加する
  - Observable: `vendor/bin/phpunit` を引数なしで実行しても `@group integration` テストがスキップされ、`--group integration` を付けた場合のみ実行される
  - _Requirements: 4.3_

- [ ] 2. CaoCsv\Provider — コンストラクタ・CSV 読み込みの実装
  - `src/Providers/CaoCsv/Provider.php` を新規作成し `final class Provider implements HolidayProvider` を宣言する
  - コンストラクタ先頭で `extension_loaded('mbstring')` を確認し、未導入時は `ext-mbstring` のインストール案内メッセージ付き `ProviderException` を throw する
  - `$csvPath` 非空時は `file_get_contents($csvPath)`、空時は内閣府固定URL（`CABINET_OFFICE_CSV_URL` 定数）から取得する（取得失敗時は `ProviderException`）
  - `mb_convert_encoding` で Shift_JIS→UTF-8 変換後、行分割・ヘッダースキップ・`str_getcsv` パース・`DateTimeImmutable::createFromFormat('Y/m/d', ...)` で `$this->holidays` 配列に格納する（不正行はスキップ）
  - Observable: ローカルフィクスチャを指定して `new Provider($path)` が例外なく完了し、フィクスチャ内日付で `isHoliday` が `true` を返す
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 3.1_
  - _Depends: 1.1_

- [ ] 3. CaoCsv\Provider — 祝日判定メソッドの実装
  - `isHoliday` を `isset($this->holidays[$t->format('Y-m-d')])` で実装する
  - `holidayName` を `$this->holidays[$t->format('Y-m-d')] ?? ''` で実装する
  - `holidaysBetween` を `$from`〜`$to` の文字列範囲フィルタ + `usort` 昇順ソートで `Holiday[]` を返す実装にする（`$from > $to` のとき空配列）
  - Observable: フィクスチャ内の既知祝日で `isHoliday=true`・`holidayName` が祝日名・`holidaysBetween` が昇順 `Holiday[]` を返し、非祝日で `isHoliday=false`・`holidayName=''` が返る
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_
  - _Depends: 2_

- [ ] 4. テストの実装

- [ ] 4.1 ユニットテストの実装（ローカルフィクスチャ使用）
  - `tests/Providers/CaoCsv/ProviderTest.php` を新規作成し `Heijitu\Tests\Providers\CaoCsv` 名前空間で定義する
  - インターフェース確認・`isHoliday`（true/false）・`holidayName`（名前返却/空文字）・`holidaysBetween`（昇順/from>to空配列/両端含む/範囲外空配列）・ローカルCSVモード確認・`Holiday::getDate()` 型確認 の 11 件のテストを実装する
  - Observable: `vendor/bin/phpunit --exclude-group integration` で 11 件すべて PASS し、0 failures・0 errors・0 warnings が出力される
  - _Requirements: 4.1, 4.2_
  - _Depends: 3_

- [ ] 4.2 オンライン取得インテグレーションテストの実装
  - `@group integration` アノテーション付きでオンライン取得テストを `ProviderTest.php` に追加する
  - `csvPath` 未指定で `new Provider()` が成功し、内閣府URLから取得したデータで `isHoliday` が動作することを確認する
  - Observable: `vendor/bin/phpunit --group integration` でオンライン取得テストが PASS する
  - _Requirements: 1.3, 4.3_

- [ ] 5. PHP 7.4・8.1 両環境での検証
  - Docker `php74` サービスで `vendor/bin/phpunit --exclude-group integration` を実行し全テスト PASS を確認する
  - Docker `php81` サービスで `vendor/bin/phpunit --exclude-group integration` を実行し全テスト PASS を確認する
  - PHP 8.1 での deprecation 警告がゼロであることを確認する
  - Observable: `php74`・`php81` 両環境でエラー・失敗・警告ゼロで全テストが PASS する
  - _Requirements: 4.4_
  - _Depends: 4.1_

## Implementation Notes
