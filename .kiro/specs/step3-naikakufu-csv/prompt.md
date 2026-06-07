/kiro-spec-requirements
docs/planningにプロジェクトの開発計画の資料が置いてあります。
まずその資料を読み込み、
次に、開発計画ステップ3の開発を進めていきます。

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
ステップ3の要件定義書を作成してください。

feature/step3-naikakufu-csv

/kiro-validate-gap step3-naikakufu-csv

/kiro-approve-req  step3-naikakufu-csv
jj new

-------------

/kiro-spec-design step3-naikakufu-csv
/kiro-validate-design step3-naikakufu-csv

これでお願い
  - Suggestion: File Structure Plan の "Modified Files" に phpunit.xml を追加し、<groups><exclude>
  の追加を明記する。

/kiro-approve-design  step3-naikakufu-csv
jj new

-------------

/kiro-spec-tasks step3-naikakufu-csv
/kiro-approve-tasks  step3-naikakufu-csv
jj new

/kiro-review-spec step3-naikakufu-csv

修正お願いします
  修正を推奨（ブロッカー）:
  1. tasks.md のタスク 2・3 を自然言語記述に書き換える —
  現在の実装詳細（file_get_contents・mb_convert_encoding・isset($this->holidays[...]) 等）を design.md
  への参照に置き換え、「何を実現するか」の記述に変更する

軽微な問題で直せるものは直して。

jj new
!jj-merge feature/step3-naikakufu-csv
/commit-commands:commit-push-pr
/review 2





