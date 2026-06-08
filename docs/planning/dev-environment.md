# 開発環境設計: php-heijitu

php-heijitu の開発・テストに用いる環境の設計。**Docker を利用**し、**PHP 7.4 と 8.1 の両バージョン**でテストできる構成とする。

> 本資料は設計の記録であり、実際の環境ファイル（`docker/Dockerfile`・`docker/compose.yaml`）は **Step 1 着手時に用意**する（`workplan.md` 参照）。ここに記す Dockerfile / compose は実装時の参考実装である。

---

## 1. 方針

- ローカルマシンに `php`・`composer` を導入せず、**Docker 上で開発・テストを完結**する（ローカルに PHP 実行系が無いため）。
- 要件「**PHP 7.4 を基準に開発し、8.1 でもエラーにならない状態**」（decisions.md A-3）を満たすため、**7.4 と 8.1 の2サービス**を用意し、両方でテストを通す。
- 確定方針（decisions.md）に沿い、**追加の Composer 依存を最小化**する。caoCsv は PHP 標準関数のみで動くため、必要拡張も最小限。

---

## 2. 構成（案A: docker compose で 7.4 / 8.1 の2サービス）

- 公式イメージ `php:7.4-cli` / `php:8.1-cli` をベースにする。
- `ARG PHP_VERSION` で 1つの Dockerfile から両バージョンをビルドする。
- プロジェクトルートをボリュームマウントし、`vendor/` を両サービスで共有する。
- `composer install` は **7.4 サービスで実行**して依存を 7.4 基準で解決・固定する。固定された依存は 8.1 でもそのまま動く（採用ライブラリはいずれも `^7.4 || ^8.0` を満たす系列。`^8.0` は 8.1 を含む）。

### ディレクトリ構成

```
php-heijitu/
├── docker/
│   ├── Dockerfile            # ARG PHP_VERSION で 7.4/8.1 を切替
│   └── compose.yaml          # php74 / php81 の2サービス
├── src/
├── tests/
├── examples/
├── composer.json
└── docs/
```

---

## 3. Dockerfile（参考実装）

```dockerfile
# docker/Dockerfile
ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-cli

# mbstring（Shift_JIS デコード用）に必要な oniguruma を導入し、拡張をビルド
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev unzip git \
 && docker-php-ext-install mbstring \
 && rm -rf /var/lib/apt/lists/*

# Composer を公式イメージからコピー
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

---

## 4. compose.yaml（参考実装）

```yaml
# docker/compose.yaml
services:
  php74:
    build:
      context: .
      args:
        PHP_VERSION: "7.4"
    volumes:
      - "../:/app"
    working_dir: /app

  php81:
    build:
      context: .
      args:
        PHP_VERSION: "8.1"
    volumes:
      - "../:/app"
    working_dir: /app
```

---

## 5. 必要な PHP 拡張

| 用途 | 拡張 | 公式 `php:*-cli` での扱い |
|------|------|------------------------|
| 内閣府CSV の Shift_JIS デコード（caoCsv） | `mbstring` | 標準では未同梱 → `docker-php-ext-install mbstring`（`libonig-dev` が必要） |
| CSV / Google Calendar の HTTPS 取得 | `curl` / `openssl` | 公式イメージに同梱・有効 |
| JSON 設定・google/apiclient | `json` | PHP 7.4 で標準有効 |

- caoCsv の HTTP 取得は PHP 標準関数（`file_get_contents` / cURL）で行うため、追加の Composer パッケージは不要（decisions.md D-1）。
- `mbstring` のみイメージへの追加導入が必要。その他は公式イメージで充足する。

---

## 6. 基本操作（コマンド例）

```bash
# 初回ビルド
docker compose -f docker/compose.yaml build

# 依存導入（7.4 基準で解決・固定。vendor/ は両サービスで共有）
docker compose -f docker/compose.yaml run --rm php74 composer install

# テスト（両バージョンで実行）
docker compose -f docker/compose.yaml run --rm php74 vendor/bin/phpunit
docker compose -f docker/compose.yaml run --rm php81 vendor/bin/phpunit

# サンプル実行
docker compose -f docker/compose.yaml run --rm php74 php examples/main.php
```

---

## 7. 7.4 基準・8.1 互換の運用

- 実装は **PHP 7.4 構文**で書く（union 型・enum・コンストラクタプロモーション・名前付き引数・match を使わない。decisions.md A-3）。
- **8.1 サービスでも deprecation 警告を含めてエラーが出ないこと**を確認する（「暗黙的に null 許容な型宣言」など 8.x で非推奨化された書き方を避ける）。
- 各ステップの動作確認・最終確認（workplan.md Step 5）で、`php74` と `php81` の**両サービスで PHPUnit を実行**して通過することをゲートとする。

---

## 8. integration テスト用の資格情報（`.env` ファイル）

Google Calendar プロバイダー（Step 4）の integration テストを実行するには、APIキーまたはサービスアカウントの資格情報が必要。

### セットアップ手順

```bash
# プロジェクトルートに .env を作成（.gitignore 済み・コミット禁止）
echo 'GOOGLE_API_KEY=your_actual_api_key_here' > .env

# サービスアカウントを使う場合は以下を追記
echo 'GOOGLE_CREDENTIALS_FILE=/path/to/credentials.json' >> .env
```

### 仕組み

- Docker Compose はプロジェクトルートの `.env` を自動読み込みする。
- `docker/compose.yaml` の `environment: - GOOGLE_API_KEY` は「ホストまたは `.env` に値があればコンテナに渡す」という設定（Step 4 で追加済み）。
- ライブラリ本体は環境変数を読まない。テストコードが `getenv('GOOGLE_API_KEY')` で値を取得し、コンストラクタに渡す。

### integration テストの実行

```bash
docker compose -f docker/compose.yaml run --rm php81 vendor/bin/phpunit --group integration
```

> ⚠️ `.env` ファイルは `.gitignore` に含まれているためコミットされない。チームメンバーへの共有は別途行うこと（1Password、AWS Secrets Manager 等）。

---

## 9. 作成タイミングと workplan との関係

- 本構成の実ファイル（`docker/Dockerfile`・`docker/compose.yaml`）は **Step 1（プロジェクト初期化）着手時に用意**する。
- `composer.json` 作成・`composer install`・PHPUnit 実行は、いずれも本 Docker 環境上で行う。
- 以降の全ステップ（Step 2〜5）の動作確認も本環境を用いる。

---

_確定事項は `decisions.md`、開発手順は `workplan.md`、設計詳細は `design.md`、go-heijitu 解析と PHP ライブラリ調査は `investigation.md` を参照。_
