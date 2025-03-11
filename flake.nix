{
  description = "Lionzhd development environment";
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs =
    {
      self,
      nixpkgs,
      flake-utils,
    }:
    flake-utils.lib.eachDefaultSystem (
      system:
      let
        overlays = [ ];
        pkgs = import nixpkgs {
          inherit system overlays;
        };
        stdenv = pkgs.stdenv;
        myphp = pkgs.php84.override {
          embedSupport = true;
          ztsSupport = true;
          staticSupport = stdenv.hostPlatform.isDarwin;
          zendSignalsSupport = false;
          zendMaxExecutionTimersSupport = stdenv.hostPlatform.isLinux;
        };
        php = myphp.buildEnv {
          extensions = (
            { enabled, all }:
            enabled
            ++ (with all; [
              # Add More PHP Extensions Here
              xdebug
              gd
              redis
              pdo
              pdo_mysql
              gmp
              imagick
              bcmath
              opcache
              intl
              memcached
              sockets
              pcntl
              zip
              zstd
              apcu
            ])
          );
          extraConfig = ''
            date.timezone = "UTC"
            # turn off xdebug by default
            xdebug.mode=off
            #xdebug.mode=debug,develop
            #xdebug.start_with_request=yes
            #xdebug.start_upon_error=yes
            #xdebug.idekey=VSCODE
            # Incresing memory limit
            memory_limit = 1G
          '';
        };
        frankenphp = pkgs.frankenphp.override {
          inherit php;
        };
      in
      {
        devShells.default = pkgs.mkShell {
          name = "lionzhd";
          nativeBuildInputs = [ ];
          buildInputs = [
            # Nodejs
            pkgs.nodePackages.typescript-language-server
            pkgs.nodejs_22
            pkgs.pnpm

            php
            frankenphp
            php.packages.composer
            php.packages.phpstan
            pkgs.blade-formatter
            pkgs.phpactor
            pkgs.meilisearch
          ];
          packages = [ ];

          COMPOSER_HOME = ".composer";
        };
      }
    );
}
