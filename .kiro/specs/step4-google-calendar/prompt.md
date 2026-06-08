/kiro-spec-requirements
docs/planningにプロジェクトの開発計画の資料が置いてあります。
まずその資料を読み込み、
次に、開発計画ステップ4の開発を進めていきます。

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
ステップ4の要件定義書を作成してください。

あと、Google Calendar APIのAPIキーをどう渡せば良いかの指示が欲しい。
go-heijituでは環境変数にキーを設定してた。


feature/step4-google-calendar
step4-google-calendar

Google Calendar APIのAPIキーは必要ない？
開発中の動作確認で使うでしょう？

>  追加した基準（5番）: integration テストは環境変数 GOOGLE_API_KEY または GOOGLE_CREDENTIALS_FILE
>  から資格情報を読み取り、GoogleCalendar\Provider
>  に渡す。開発者はこの環境変数を設定することで実API検証テストを実行できる。

GOOGLE_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
export GOOGLE_API_KEY
した。これでGOOGLE_API_KEYが開発で利用できるかな？

方法Aでいきましょう。
>  方法A（推奨）: .env ファイル + compose.yaml 更新
>
>  # .env ファイルを作成（gitignore に入れる）
>  echo 'GOOGLE_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' >
>  /Users/taku-o/Documents/workspaces/php-heijitu/.env

!echo GOOGLE_API_KEY=$GOOGLE_API_KEY > /Users/taku-o/Documents/workspaces/php-heijitu/.env

開発用の.envファイルのセットアップは、ドキュメントに記載したい。
docs/planningに資料がある。
後のステップ5のタスクの作業で、作業が漏れないように、記載を追加して。

/kiro-validate-gap step4-google-calendar

~2.16.0 に固定する。
>  2. ^2.16 を ~2.16.0 に固定する必要あり（必須修正） — ^2.16 だと Composer が v2.17+
>  を解決する場合があり、v2.17 から PHP 要件が ^8.1 に変わるため PHP 7.4 で composer install が壊れる。

警告が避けられないなら仕方ない。動かないより良い。
> PHP 8.1 の deprecation 警告

クエリ都度取得
>  4. データ取得タイミングが設計決定事項 — CaoCsv はコンストラクタで全件ロードするが、GoogleCalendar
>  はコンストラクタ時に日付範囲が不明。「コンストラクタ即時全件取得」「年単位遅延取得＋キャッシュ」「クエ
>  リ都度取得」のいずれかを設計フェーズで確定する。

ここでいう、各メソッドを教えて。
>  - timeMin/timeMax の具体的な設計（クエリ都度取得と確定したので、各メソッドの API
>  クエリ範囲をどう構成するか）

> 単日クエリ（シンプル・APIコール増）
呼び出し回数の問題は、呼び出し側がキャッシュするなり、
holidaysBetweenを代わりに使うなりして、回避するでしょう。

/kiro-approve-req  step4-google-calendar
jj new

-------------

/kiro-spec-design step4-google-calendar
/kiro-validate-design step4-google-calendar

これで
  Suggestion: fetchEvents() 内部で buildService() を呼ぶ（Option A）と明示する。これにより try/catch は
  fetchEvents() 1箇所に集約でき、public メソッドはシンプルに $this->fetchEvents($from, $to)
  を呼ぶだけになる。実装ノートに1行追加すればよい。

/kiro-approve-design  step4-google-calendar
jj new

-------------

/kiro-spec-tasks step4-google-calendar
/kiro-approve-tasks  step4-google-calendar
jj new

/kiro-review-spec step4-google-calendar


credentialsFile認証テストは削除。
>  1. [要確認] credentialsFile 認証テストの扱い（design.md Testing Strategy #7 と tasks.md の不整合）
>    - Task 5.2 に credentialsFile 認証テストを追加するか、design.md から削除するか決定してください

許可。
>  2. [削除推奨] 曖昧コミットメント表現
>    - requirements.md Boundary Context の「可能なら実施」「（可能なら）」を削除してください

分割してください。
>  3. [任意] Task 3.1 の粒度分割 — 実装中に 3.1/3.2 に分割することを検討

軽微な問題（推奨改善）であげられた項目は修正してください。

対応お願いします
  1. Task 6.1 では php81 Docker での全テスト通過確認が 必須タスクObservable
  付き）として定義されており、実態と矛盾する。この1行は削除が推奨。

jj new

!jj-merge feature/step4-google-calendar
/commit-commands:commit-push-pr
/review 4

修正推奨事項に挙げられた項目を修正してください。



