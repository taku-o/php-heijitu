/kiro-spec-requirements
docs/planningにプロジェクトの開発計画の資料が置いてあります。
まずその資料を読み込み、
次に、開発計画ステップ1の開発を進めていきます。

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
ステップ1の要件定義書を作成してください。

feature/step1-core
/kiro-validate-gap step1-core
/kiro-approve-req  step1-core
jj-init
jj new


-------------

/kiro-spec-design step1-core
/kiro-validate-design step1-core

Issue 1
    foreach ($excludedDates as $item) での instanceof チェック + \InvalidArgumentException を選択する。
Issue 2
    Technology Stack の Docker 行または File Structure Plan の docker/ 説明に「composer
      install は php74 サービスで実行（7.4 基準で composer.lock を生成）」の一文を追記する。

/kiro-approve-design step1-core
jj new

-------------

/kiro-spec-tasks step1-core
/kiro-approve-tasks step1-core
jj new

/kiro-review-spec step1-core

!jj-merge feature/step1-core
/commit-commands:commit-push-pr

/review 1

改善提案と軽微修正を取り込んでください。

/commit-push-pr-update

-------------

takt --task "/kiro-impl step1-core 1
必要ならテストを修正して良い。"

/kiro-review-feature step1-core 1
jj new

-------------

takt --task "/kiro-impl step1-core 2
必要ならテストを修正して良い。"

/kiro-review-feature step1-core 2
必要ならテストを修正して良い。

jj new

-------------

takt --task "/kiro-impl step1-core 3
必要ならテストを修正して良い。"

/kiro-review-feature step1-core 3
必要ならテストを修正して良い。

jj new

-------------

takt --task "/kiro-impl step1-core 4
必要ならテストを修正して良い。"

/kiro-review-feature step1-core 4
必要ならテストを修正して良い。

jj new

-------------

takt --task "/kiro-impl step1-core 5
必要ならテストを修正して良い。"

/kiro-review-feature step1-core 5
必要ならテストを修正して良い。

事前に実行した /kiro-impl step1-core 5 の結果は
    ## 結果: APPROVE

    ## サマリー
    全ソースファイルが200行以内・1ファイル1責務を達成し、依存方向は設計書の Boundary Map 通り上位→下位が厳守されている。循環依存なし、エラーハンドリング一貫、テストカバレッジは設計の Testing Strategy を全網羅しており、ブロッキング問題は検出されなかった。

    ## 確認した観点
    - [x] 構造・設計
    - [x] コード品質
    - [x] 変更スコープ
    - [x] テストカバレッジ
    - [x] デッドコード
    - [x] 呼び出しチェーン検証

jj new

-------------

!jj-merge feature/step1-core
/commit-push-pr-update
/review 1



