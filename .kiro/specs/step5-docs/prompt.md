/kiro-spec-requirements
docs/planningにプロジェクトの開発計画の資料が置いてあります。
まずその資料を読み込み、
次に、開発計画ステップ5の開発を進めていきます。

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
ステップ5の要件定義書を作成してください。

feature/step5-docs

/kiro-validate-gap step5-docs

Option B（全成果物作成）を採用。

  設計フェーズへの持ち越し事項

そちらで適当に決めて良い。迷うなら、go-heijituを参考にして。
>  - examples/main.php の出力フォーマット

そちらで適当に決めて良い。
  - docs/ ドキュメントの構成（api-spec と usage の重複整理）
  - CHANGELOG.md の形式選択

まだ設計フェーズを始める宣言をしていない。
要件定義書を承認もしていない。

/kiro-approve-req  step5-docs
jj new

-------------

/kiro-spec-design step5-docs
/kiro-validate-design step5-docs

ネットワーク失敗時はエラーを投げる。
エラーを握りつぶさない。

/kiro-approve-design  step5-docs
jj new

-------------

/kiro-spec-tasks step5-docs
/kiro-approve-tasks  step5-docs
jj new

/kiro-review-spec step5-docs

修正推奨の項目は修正してください。

docs/planningの資料は、.kiro/specs/initial-planning/planning以下に移動する。
これは、tasks.mdにTask 6の作業として追加。

jj new

!jj-merge feature/step5-docs
/commit-commands:commit-push-pr
/review 5

こちら修正してください。
  1. design.md L65 の docs/planning/ パス参照に「※ Task 6 実行後は
  .kiro/specs/initial-planning/planning/ へ移動済み」等の注記を追加する
  2. research.md L953 の examples/config/excluded_dates.yaml
  行に「設計フェーズで不採用」の注記を追記する

/commit-push

-------------

/kiro-impl step5-docs 1
必要ならテストを修正して良い。

/prevent-cc-sdd-auto-progress

Task 1のチェックボックスを外してください。
明確にルール違反です。

/kiro-review-feature step5-docs 1
必要ならテストを修正して良い。

jj new

-------------

takt --task "/kiro-impl step5-docs 2
必要ならテストを修正して良い。"

/kiro-review-feature step5-docs 2
必要ならテストを修正して良い。

jj new

-------------

/kiro-impl step5-docs 3
必要ならテストを修正して良い。

/kiro-review-feature step5-docs 3
必要ならテストを修正して良い。

jj new

-------------

/kiro-impl step5-docs 4
必要ならテストを修正して良い。

/kiro-review-feature step5-docs 4
必要ならテストを修正して良い。

jj new

-------------

/kiro-impl step5-docs 5
必要ならテストを修正して良い。

/kiro-review-feature step5-docs 5
必要ならテストを修正して良い。

jj new

-------------

/kiro-impl step5-docs 6
必要ならテストを修正して良い。

/kiro-review-feature step5-docs 6
必要ならテストを修正して良い。

jj new










