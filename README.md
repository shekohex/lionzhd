# Lionz IPTV Downloader

A modern, high-performance IPTV content manager built with Laravel that integrates with [Lionz](https://lionz.tv) IPTV service. This tool leverages the Xtream Codes API to fetch and manage Series and VODs, uses aria2c for efficient downloading, and Meilisearch for lightning-fast content search and indexing.

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Smart Content Management**
    - Search and download VODs or Series (individual episodes or complete seasons)
    - Automatic media library synchronization with MeiliSearch indexing
    - Favorite series tracking with automatic new episode downloads
- **Modern Architecture**
    - Clean and responsive web interface built with React and Tailwind CSS
    - Efficient download management through aria2 integration
    - Real-time updates and notifications
- **Performance Focused**
    - Fast content searching with MeiliSearch integration
    - Efficient background job processing for media syncing
    - Optimized content delivery

## Prerequisites

Ensure you have the following installed:

- **PHP 8.4** or higher
- **[pnpm](https://pnpm.io/)** - Package manager for Node.js dependencies
- **[Composer](https://getcomposer.org/)** - PHP dependency manager
- **[Meilisearch](https://www.meilisearch.com/)** - Search engine (running on port 7700)
- **[aria2](https://aria2.github.io/)** - Download manager (running on port 16800)
    - Recommended: [Motrix](https://motrix.app/) as a GUI for aria2

## Installation

1. Clone the repository:

```bash
git clone https://github.com/shekohex/lionzhd.git
cd lionzhd
```

2. Install PHP dependencies:

```bash
composer install
```

3. Install Node.js dependencies:

```bash
pnpm install
```

4. Set up the SQLite database:

```bash
touch database/database.sqlite
```

5. Initialize the project:

```bash
composer run-script post-create-project-cmd
```

## Configuration

1. Create your environment file:

```bash
cp .env.example .env
```

2. Configure your `.env` file with the following essential settings:

```ini
# Lionz IPTV Service Credentials
XTREAM_CODES_API_HOST=your-host
XTREAM_CODES_API_PORT=your-port
XTREAM_CODES_API_USER=your-username
XTREAM_CODES_API_PASS=your-password

# MeiliSearch Configuration
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key

# Aria2 Configuration
ARIA2_RPC_HOST=http://localhost
ARIA2_RPC_PORT=16800
ARIA2_RPC_SECRET=your-secret-token
```

## Usage

Start the development server and all required services:

```bash
composer dev
```

This command will concurrently run:

- Laravel development server
- Queue worker for background jobs
- Log watcher
- Vite development server for frontend assets

The application will be available at `http://localhost:8000`.

### Development with SSR

For server-side rendering support:

```bash
composer dev:ssr
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the MIT License - see the [LICENSE](./LICENSE) file for details.
