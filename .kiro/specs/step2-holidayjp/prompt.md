/kiro-spec-requirements
docs/planningにプロジェクトの開発計画の資料が置いてあります。
まずその資料を読み込み、
次に、開発計画ステップ2の開発を進めていきます。

  Step: Step 1
  内容: プロジェクト初期化・コア型・isBusinessDay まで
  go-heijitu との対応・PHP 版固有の差分: composer.json（"php": "^7.4 || ^8.0 ||
    ^8.1"）／MonthDay・Holiday／HolidayProvider interface／BusinessCalendar
    骨格＋isBusinessDay／Config（YAML=symfony/yaml:^5.4＋JSON=標準json_decode）。テストは PHPUnit
    9.6＋モックプロバイダー
  ────────────────────────────────────────
  Step: Step 2
  内容: holidayjp プロバイダー ＋ 残り全 API
  go-heijitu との対応・PHP 版固有の差分: holiday-jp/holiday_jp
    を導入。nextBusinessDay／firstBusinessDayOfMonth／firstBusinessDaysOfYear／holidays を実装
  ────────────────────────────────────────
  Step: Step 3
  内容: 内閣府CSVプロバイダー
  go-heijitu との対応・PHP 版固有の差分: 追加依存ゼロ。file_get_contents/cURL で取得 →
    mb_convert_encoding(...,'SJIS-win') → str_getcsv。オンライン取得テストは @group integration で分離
  ────────────────────────────────────────
  Step: Step 4
  内容: Google Calendar APIプロバイダー
  go-heijitu との対応・PHP 版固有の差分: google/apiclient:^2.16。APIキー／サービスアカウント認証。PHP
  8.1
     実機検証が要確認点
  ────────────────────────────────────────
  Step: Step 5
  内容: example・PHPDoc・README（ドキュメント整備）
  go-heijitu との対応・PHP 版固有の差分: examples/main.php／全公開 API に PHPDoc／en/ja
    両方（README.md＋README-ja.md、docs/en＋docs/ja の api-spec・usage・providers）。最終確認で 7.4 と
    8.1  の両方でテストが通ることを検証

作業用のgitブランチを作成後、
ステップ2の要件定義書を作成してください。


step2-holidayjp
/kiro-validate-gap step2-holidayjp

何か問題は見つかった？

/kiro-approve-req  step2-holidayjp
jj new

-------------

/kiro-spec-design step2-holidayjp
/kiro-validate-design step2-holidayjp

対応してください。推奨の方法を選択
  観察 1: BusinessCalendarTest 追記テストの日付が未指定
  - Concern: テスト表に「祝日前日 → 祝日翌日」「1月1日が元日 →
  翌営業日」と書かれているが、具体的な日付が記載されていない。holiday-jp/holiday_jp のデータ範囲は 2020
  年で終わるため、実装者が 2021 年以降の日付を選ぶと期待外れの結果になる可能性がある。
  - Impact: テスト実装の誤りのリスク（軽微。設計の実行可能性に影響なし）
  - Suggestion: テスト表に使用日付の例を追記する（例: testNextBusinessDaySkipsHoliday → 2019-12-31
  を入力、期待値 2020-01-02）。または実装タスクの Notes に「2020 年以前の祝日を使うこと」と明記する。
  - Traceability: 6.1, 6.2
  - Evidence: Testing Strategy — tests/BusinessCalendarTest.php 追記テスト表

  観察 2: シーケンス図が isBusinessDay の全チェックを省略
  - nextBusinessDay のシーケンス図は isHoliday(candidate) のみ描画しており、週末チェック・除外日付チェッ
  クを省略している。図の読者に誤解を与える可能性がある（軽微）。
  - Traceability: 2.1〜2.3 / Evidence: System Flows — nextBusinessDay フロー図

/kiro-approve-design step2-holidayjp
jj new

-------------

/kiro-spec-tasks step2-holidayjp
/kiro-approve-tasks step2-holidayjp
jj new

/kiro-review-spec step2-holidayjp

「ProviderExceptionを投げる機能を実装する
テストが難しければ、テストが甘くなっても仕方ない。

改善提案と軽微な問題の修正を取り込んでください。


途中、見つかったこの問題、最終的にはドキュメントに記載したい。
後々のタスクでドキュメント記載の作業を忘れないように、docs/planningに記載してくれない？
「動作確認には使えるけど、運用では別のプロバイダーを使うべき」、と。
  1. holiday-jp/holiday_jp のデータ範囲（既知の弱点）
  ライブラリの祝日データは
  2020年で更新停止しています。2021年以降の祝日（山の日の日付変更など）はカバーされません。

これを選択
  │ A-1    │ $tz = date_default_timezone_get() ?: 'Asia/Tokyo' を $tz = date_default_timezone_get()  │
  │        │ に変更し、フォールバック仕様を削除する（PHP 標準に委ねる）
  │ B-1    │ nextBusinessDay も同様に実行環境 TZ を取得し、戻り値を実行環境 TZ の DateTimeImmutable  │
  │        │ にする

jj new
!jj-merge feature/step2-holidayjp
/commit-commands:commit-push-pr
/review 2

要修正と注意事項で上げられた項目を修正してください。

/commit-push-pr-update

-------------

takt --task "/kiro-impl step2-holidayjp 1
必要ならテストを修正して良い。"

/kiro-review-feature step2-holidayjp 1
必要ならテストを修正して良い。

jj new

-------------

takt --task "/kiro-impl step2-holidayjp 2
必要ならテストを修正して良い。"

/kiro-review-feature step2-holidayjp 2
必要ならテストを修正して良い。

エラーを投げる。基本発生しないため。
「月内に営業日が存在しない場合」

このループはどんな処理で発生する？
「無限ループの上限」

findBusinessDayFromで発生する無限ループなら、
1ヶ月想定にする。
31ループを上限としよう。

jj new

-------------

takt --task "/kiro-impl step2-holidayjp 3
必要ならテストを修正して良い。"






/kiro-review-feature step2-holidayjp 3
必要ならテストを修正して良い。

jj new




