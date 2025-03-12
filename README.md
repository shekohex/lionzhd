# Lionz IPTV Downloader

Lionz IPTV Downloader is a simple, yet powerful tool designed to interact with the [Lionz](https://lionz.tv) IPTV service. It leverages the Xtream Codes API to fetch Series and VODs, downloads them using aria2c, and utilizes Meilisearch for efficient content indexing and searching.

## Features

- Search and download any VODs or Series (including specific episodes or full seasons)
- automatically syncs and updates the media library and MeiliSearch indexes for faster searching
- A Clean and fast web interface
- Integration with aria2 for efficient downloading
- Favorite a series to automatically download new eposides when they are available.

## Prerequisites

Before you begin, ensure you have met the following requirements:

- PHP 8.4+
- [Meilisearch](https://www.meilisearch.com/) installed and running on port 7700
- [aria2c](https://aria2.github.io/) instance running on port 16800
    - We recommend using [Motrix](https://motrix.app/) as a GUI for aria2c
- [composer](https://getcomposer.org/) for managing project dependencies

## Installation

1. Clone the repository:

    ```
    git clone https://github.com/shekohex/lionzhd.git
    cd lionzhd
    ```

2. Install dependencies using composer:
    ```
    composer install
    ```

## Configuration

1. Copy the `.env.example` file to `.env`:

    ```
    cp .env.example .env
    ```

2. Edit the `.env` file and fill in your Lionz IPTV service credentials:
    ```
    XTREAM_CODES_API_HOST=
    XTREAM_CODES_API_PORT=
    XTREAM_CODES_API_USER=
    XTREAM_CODES_API_PASS=
    MEILI_HTTP_URL=
    MEILI_MASTER_KEY=
    ARIA2_RPC_HOST=
    ARIA2_RPC_PORT=
    ARIA2_RPC_SECRET=
    ```

## Usage

To run the Lionz IPTV Downloader:

```
composer dev
```

Follow the interactive prompts to search for content, select items to download, and manage MeiliSearch indexes.

## Contributing

Contributions to the Lionz IPTV Downloader are welcome. Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](./LICENSE) file for details.
