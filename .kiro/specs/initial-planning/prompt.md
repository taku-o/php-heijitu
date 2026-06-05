Go言語製営業日取得ライブラリ
~/Documents/workspaces/go-heijitu 
のPHP版を作成します。

まず、go-heijituプロジェクトを解析し、
そのPHP版を作る方法を検討します。
検討資料、分析資料は docs/planning に作成してください。

特に、
~/Documents/workspaces/go-heijitu/.kiro/specs/initial-planning/
の資料はgo-heijituの仕組みを確認する上で参考になると思います。

1. どんなことを決める必要があるか
2. どんな手順で開発することになるか
3. 何段階かに分けて開発を進めるとすれば、どのように作業を区切るべきか

PHPは、
php7.4
php8.1
あたりで動くと嬉しい。

ライブラリの管理はcomposerを使うものとします。

この検討作業は時間をかけて良いです。
think.


想定動作環境の都合上、基本は7.4で動かすもので、8.1に持っていってもエラーにならない状態にしたい。
>  - A-3 PHP バージョン（7.4・8.1 両対応を必須にするか）

推奨に合わせる
>  - B-1 デフォルトプロバイダー（holiday-jp/holiday_jp 推奨）
>  - C-3 除外日付の指定 API 形（コンストラクタ引数＋設定ローダー 推奨）

実はguzzleも動作環境でバージョン縛り問題があるので、標準関数を使用しよう。
>  - D-1 CaoCsv の HTTP 取得手段（標準関数 or guzzle）

en/jaでお願い。
>  - E-3 ドキュメント言語（最低 ja、go-heijitu に揃えるなら en/ja）


今の計画だと、どんな手順で開発を進めることになる？
go-heijituはこんな手順だった。5分割。
>  ┌──────────┬────────────────────────────────────────────────┐
>  │ ステップ │                      内容                      │
>  ├──────────┼────────────────────────────────────────────────┤
>  │ Step 1   │ プロジェクト初期化・コア型・IsBusinessDay まで │
>  ├──────────┼────────────────────────────────────────────────┤
>  │ Step 2   │ holidayjp プロバイダー + 残り全APIの実装       │
>  ├──────────┼────────────────────────────────────────────────┤
>  │ Step 3   │ 内閣府CSVプロバイダー                          │
>  ├──────────┼────────────────────────────────────────────────┤
>  │ Step 4   │ Google Calendar APIプロバイダー                │
>  ├──────────┼────────────────────────────────────────────────┤
>  │ Step 5   │ example・GoDoc・README（ドキュメント整備）     │
>  └──────────┴────────────────────────────────────────────────┘


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


PHPの動作環境はどうやって用意しよう。
Dockerを利用して開発を進められる？


推奨の案Aで進める。
  1. 構成は 案A（2サービス） で進めてよいか

資料に記録する。
  2. この Docker 環境構成を docs/planning/
  に「開発環境」資料として記録するか（残すなら実装前の設計として明文化します）

後の実装の段階で用意する。
  3. Docker 環境ファイル（docker/Dockerfile・compose.yaml）を今用意するか、Step 1 着手時に用意するか


今の計画で進めて、最終的に、composer requireでphp-heijituを利用できるようになる？

これってどんな作り？
  - holiday-jp/holiday_jp / google/apiclient は suggest にし、利用者が使うプロバイダーの依存だけ自分で
  require する


配布方法はGitHubを想定。dev-branchと指定して、取り込ませる。
  1. 配布方法: Packagist 公開（composer require taku-o/php-heijitu を直接成立）／ GitHub の VCS
  配布（利用者が repositories 指定）／ その他

リリース工程は追加無しで良い。
  2. リリース工程の追加: workplan に「Step 6: リリース（公開・タグ付け・Packagist
  登録）」を新設するか、Step 5 を拡張するか

それでお願いします。
  3. 依存方針: 上記の「コア依存は最小、プロバイダー依存は suggest」で確定してよいか


-------------

  - C-5 例外クラスの具体粒度
  - E-2 内部生成する日付のタイムゾーン基準
  - D-4 PHP 8.1 上での google/apiclient 実機検証
  - H ext-mbstring を require/suggest どちらにするか、依存未導入時に class_exists()
  で親切なエラーを出すかの要否





